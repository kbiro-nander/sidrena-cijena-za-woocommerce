<?php
/**
 * Orchestrates one price-list generation: lock, collect, write per format, publish, manifest, prune.
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

namespace SidrenaCijena\PriceList;

use RuntimeException;
use SidrenaCijena\Reference\ReferencePriceRegistry;
use SidrenaCijena\Settings\Settings;
use SidrenaCijena\Support\Clock;
use Throwable;

class Generator {

	public const LOCK_KEY    = 'scwc_generating';
	public const LOCK_TTL    = 15 * MINUTE_IN_SECONDS;
	public const OPTION_LAST = 'scwc_last_generation';

	/** @var callable():Outlet[] */
	private $outletProvider;

	/**
	 * @param callable():Outlet[]  $outletProvider Supplies all outlets (primary first) at run time.
	 * @param array<string,Writer> $writers        Writers keyed by format (xml, csv, ...).
	 */
	public function __construct(
		private readonly Settings $settings,
		callable $outletProvider,
		private readonly Collector $collector,
		private readonly array $writers,
		private readonly FilenameBuilder $filenames,
		private readonly Storage $storage,
		private readonly Manifest $manifest,
		private readonly Retention $retention,
		private readonly Clock $clock,
		private readonly ReferencePriceRegistry $registry,
	) {
		$this->outletProvider = $outletProvider;
	}

	public function run( string $reason = 'manual' ): GenerationResult {
		$local = $this->clock->nowLocal();
		if ( ! (bool) $this->settings->get( 'price_list.enabled', true ) ) {
			return new GenerationResult( [], $local, $reason, __( 'Cjenik je isključen u postavkama.', 'sidrena-cijena-za-woocommerce' ), 0, 0 );
		}
		if ( get_transient( self::LOCK_KEY ) ) {
			return new GenerationResult( [], $local, $reason, __( 'Generiranje je već u tijeku.', 'sidrena-cijena-za-woocommerce' ), 0, 0 );
		}
		set_transient( self::LOCK_KEY, 1, self::LOCK_TTL );
		if ( function_exists( 'wc_set_time_limit' ) ) {
			wc_set_time_limit( 0 );
		}
		if ( function_exists( 'wp_raise_memory_limit' ) ) {
			wp_raise_memory_limit( 'admin' );
		}
		ignore_user_abort( true );
		try {
			$result = $this->generate( $reason, $local );
		} catch ( Throwable $e ) {
			$result = new GenerationResult( [], $local, $reason, $e->getMessage(), 0, 0 );
		} finally {
			delete_transient( self::LOCK_KEY );
		}

		$this->storeSummary( $result );
		if ( $result->ok() ) {
			do_action( 'scwc_price_list_generated', $result );
		}
		return $result;
	}

	private function generate( string $reason, \DateTimeImmutable $local ): GenerationResult {
		/** @var Outlet[] $outlets */
		$outlets = ( $this->outletProvider )();
		$primary = $outlets[0] ?? null;
		if ( ! $primary instanceof Outlet || ! $primary->isComplete() ) {
			throw new RuntimeException( esc_html__( 'Podaci o prodajnom objektu nisu potpuni.', 'sidrena-cijena-za-woocommerce' ) );
		}
		$this->storage->ensure();

		$items    = iterator_to_array( $this->collector->items(), false );
		$types    = array_values( $this->registry->enabled() );
		$utc      = $this->clock->now()->format( 'Y-m-d H:i:s' );
		$files    = [];
		$products = 0;
		$services = 0;

		foreach ( $outlets as $outlet ) {
			if ( ! $outlet instanceof Outlet || ! $outlet->isComplete() ) {
				continue;
			}
			foreach ( $this->formats() as $format ) {
				$writer = $this->writers[ $format ] ?? null;
				if ( ! $writer instanceof Writer ) {
					continue;
				}
				$name = $this->filenames->build( $outlet, $local, $format );
				$tmp  = $this->storage->tmpPath( $name );
				try {
					$stats = $writer->write( $items, $outlet, $local, $tmp, $types );
				} catch ( Throwable $e ) {
					if ( is_file( $tmp ) ) {
						unlink( $tmp );
					}
					throw $e;
				}
				$this->storage->publish( $name );

				$this->manifest->add(
					[
						'name'             => $name,
						'format'           => $format,
						'outlet'           => $outlet->key,
						'generated_at'     => $local->format( DATE_ATOM ),
						'generated_at_utc' => $utc,
						'reason'           => $reason,
						'products'         => $stats->products,
						'services'         => $stats->services,
						'size'             => $this->storage->size( $name ),
						'sha256'           => (string) hash_file( 'sha256', $this->storage->path( $name ) ),
					]
				);
				$files[]  = [
					'name'     => $name,
					'format'   => $format,
					'outlet'   => $outlet->key,
					'url'      => $this->storage->url( $name ),
					'products' => $stats->products,
					'services' => $stats->services,
				];
				$products = max( $products, $stats->products );
				$services = max( $services, $stats->services );
			}
		}

		$this->retention->prune( (int) $this->settings->get( 'price_list.retention_days', 35 ), $primary->key );

		return new GenerationResult( $files, $local, $reason, null, $products, $services );
	}

	/**
	 * @return string[] Enabled formats in settings order.
	 */
	private function formats(): array {
		$formats = $this->settings->get( 'price_list.formats', [ 'xml', 'csv' ] );
		return array_values( array_unique( array_map( 'strval', is_array( $formats ) ? $formats : [] ) ) );
	}

	private function storeSummary( GenerationResult $result ): void {
		update_option(
			self::OPTION_LAST,
			[
				'at'       => $result->generatedAt->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' ),
				'at_local' => $result->generatedAt->format( DATE_ATOM ),
				'reason'   => $result->reason,
				'files'    => array_column( $result->files, 'name' ),
				'products' => $result->products,
				'services' => $result->services,
				'error'    => (string) $result->error,
			]
		);
	}
}
