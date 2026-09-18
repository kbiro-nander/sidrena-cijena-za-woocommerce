<?php
/**
 * WP-CLI: wp scwc <export|snapshot|import|sweep|schedule|prune|history>
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

namespace SidrenaCijena\Cli;

use SidrenaCijena\Admin\Tools\SnapshotRequest;
use SidrenaCijena\Admin\Tools\SnapshotResult;
use SidrenaCijena\History\SweepResult;
use SidrenaCijena\PriceList\GenerationResult;
use SidrenaCijena\Settings\Settings;
use WP_CLI;

final class Commands {

	/** @var callable(string):GenerationResult */
	private $generate;
	/** @var callable(SnapshotRequest,int):SnapshotResult */
	private $snapshot;
	/** @var callable(string,string,bool):array<string,mixed> */
	private $import;
	/** @var callable(int):SweepResult */
	private $sweep;
	/** @var callable():void */
	private $prune;
	/** @var callable():array<string,int|null> */
	private $status;
	/** @var callable():void */
	private $reschedule;
	/** @var callable(int,int):array<int,array<string,mixed>> */
	private $history;

	/**
	 * @param callable(string):GenerationResult                  $generate   Generation.
	 * @param callable(SnapshotRequest,int):SnapshotResult       $snapshot   One snapshot page.
	 * @param callable(string,string,bool):array<string,mixed>   $import     CSV import (csv, type, dryRun).
	 * @param callable(int):SweepResult                          $sweep      One sweep page.
	 * @param callable():void                                    $prune      Prune history.
	 * @param callable():array<string,int|null>                  $status     Schedule status.
	 * @param callable():void                                    $reschedule Reschedule.
	 * @param callable(int,int):array<int,array<string,mixed>>   $history    History rows.
	 */
	public function __construct(
		private readonly Settings $settings,
		callable $generate,
		callable $snapshot,
		callable $import,
		callable $sweep,
		callable $prune,
		callable $status,
		callable $reschedule,
		callable $history,
	) {
		$this->generate   = $generate;
		$this->snapshot   = $snapshot;
		$this->import     = $import;
		$this->sweep      = $sweep;
		$this->prune      = $prune;
		$this->status     = $status;
		$this->reschedule = $reschedule;
		$this->history    = $history;
	}

	public static function registerCommand( self $commands ): void {
		WP_CLI::add_command( 'scwc', $commands );
	}

	/**
	 * Generira cjenik (XML/CSV) sada.
	 *
	 * @param string[]             $args  Positional args.
	 * @param array<string,mixed>  $assoc Named args.
	 */
	public function export( array $args, array $assoc ): void {
		$result = ( $this->generate )( (string) ( $assoc['reason'] ?? 'cli' ) );
		if ( ! $result->ok() ) {
			WP_CLI::error( (string) $result->error );
		}
		foreach ( $result->files as $file ) {
			WP_CLI::success( sprintf( 'Generirano [%s]: %s (%d proizvoda, %d usluga)', (string) ( $file['outlet'] ?? '-' ), $file['name'], $file['products'], $file['services'] ) );
		}
	}

	/**
	 * Snimi trenutne redovne cijene kao referentne (sidrene) cijene.
	 *
	 * ## OPTIONS
	 * [--key=<key>] [--date=<Y-m-d>] [--overwrite] [--skip-on-sale] [--dry-run]
	 *
	 * @param string[]            $args  Positional args.
	 * @param array<string,mixed> $assoc Named args.
	 */
	public function snapshot( array $args, array $assoc ): void {
		$request = SnapshotRequest::fromArray(
			[
				'type'         => (string) ( $assoc['key'] ?? 'anchor' ),
				'date'         => (string) ( $assoc['date'] ?? '' ),
				'mode'         => ! empty( $assoc['overwrite'] ) ? 'overwrite' : 'only_missing',
				'skip_on_sale' => ! empty( $assoc['skip-on-sale'] ),
				'dry_run'      => ! empty( $assoc['dry-run'] ),
			],
			$this->settings
		);
		$page    = 1;
		$total   = null;
		do {
			$result = ( $this->snapshot )( $request, $page );
			$total  = $total ? $total->merge( $result ) : $result;
			WP_CLI::log( sprintf( 'Stranica %d: obrađeno %d, zapisano %d', $page, $result->processed, $result->written ) );
			++$page;
		} while ( $result->hasMore );
		WP_CLI::success( sprintf( 'Gotovo: obrađeno %d, zapisano %d, preskočeno (postoji) %d, označeno bez cijene %d, bez redovne cijene %d, na akciji %d%s', $total->processed, $total->written, $total->skippedExisting, $total->markedNa, $total->skippedNoPrice, $total->skippedOnSale, $request->dryRun ? ' [probni rad]' : '' ) );
	}

	/**
	 * Uvezi referentne cijene iz CSV-a (sku;cijena;datum;tip).
	 *
	 * ## OPTIONS
	 * <file> [--key=<key>] [--dry-run]
	 *
	 * @param string[]            $args  Positional args.
	 * @param array<string,mixed> $assoc Named args.
	 */
	public function import( array $args, array $assoc ): void {
		$file = (string) ( $args[0] ?? '' );
		if ( '' === $file || ! is_readable( $file ) ) {
			WP_CLI::error( 'Datoteka nije pronađena: ' . $file );
		}
		$dry    = ! empty( $assoc['dry-run'] );
		$result = ( $this->import )( (string) file_get_contents( $file ), (string) ( $assoc['key'] ?? 'anchor' ), $dry ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		foreach ( (array) ( $result['errors'] ?? [] ) as $error ) {
			WP_CLI::warning( (string) $error );
		}
		if ( $dry ) {
			WP_CLI::success( sprintf( 'Probni rad: %d ispravnih, %d neispravnih redaka.', (int) ( $result['valid'] ?? 0 ), (int) ( $result['invalid'] ?? 0 ) ) );
			return;
		}
		WP_CLI::success( sprintf( 'Uvezeno: %d ažurirano, %d označeno bez cijene.', (int) ( $result['updated'] ?? 0 ), (int) ( $result['markedNa'] ?? 0 ) ) );
	}

	/**
	 * Provjeri i zabilježi trenutne cijene svih proizvoda (povijest cijena).
	 *
	 * @param string[]            $args  Positional args.
	 * @param array<string,mixed> $assoc Named args.
	 */
	public function sweep( array $args, array $assoc ): void {
		$page    = 1;
		$changed = 0;
		do {
			$result   = ( $this->sweep )( $page );
			$changed += $result->changed;
			++$page;
		} while ( $result->hasMore );
		WP_CLI::success( sprintf( 'Provjera gotova, %d promjena zabilježeno.', $changed ) );
	}

	/**
	 * Status zakazanih zadataka ili ponovno zakazivanje.
	 *
	 * ## OPTIONS
	 * <status|reset>
	 *
	 * @param string[]            $args  Positional args.
	 * @param array<string,mixed> $assoc Named args.
	 */
	public function schedule( array $args, array $assoc ): void {
		if ( 'reset' === ( $args[0] ?? '' ) ) {
			( $this->reschedule )();
			WP_CLI::success( 'Zadaci su ponovno zakazani.' );
			return;
		}
		foreach ( ( $this->status )() as $hook => $ts ) {
			WP_CLI::line( sprintf( '%-28s %s', $hook, null === $ts ? '-' : gmdate( 'Y-m-d H:i:s', $ts ) . ' UTC' ) );
		}
	}

	/**
	 * Obriši staru povijest cijena.
	 *
	 * @param string[]            $args  Positional args.
	 * @param array<string,mixed> $assoc Named args.
	 */
	public function prune( array $args, array $assoc ): void {
		( $this->prune )();
		WP_CLI::success( 'Stara povijest cijena je obrisana.' );
	}

	/**
	 * Povijest cijena proizvoda.
	 *
	 * ## OPTIONS
	 * <product_id> [--limit=<n>]
	 *
	 * @param string[]            $args  Positional args.
	 * @param array<string,mixed> $assoc Named args.
	 */
	public function history( array $args, array $assoc ): void {
		$rows = ( $this->history )( (int) ( $args[0] ?? 0 ), (int) ( $assoc['limit'] ?? 60 ) );
		\WP_CLI\Utils\format_items( 'table', $rows, [ 'recorded_at', 'regular', 'sale', 'active', 'on_sale', 'source' ] );
	}
}
