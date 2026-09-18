<?php
/**
 * AJAX endpoint driving the Tools page: batched snapshot, CSV import preview/apply, CSV export.
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

namespace SidrenaCijena\Admin\Tools;

use SidrenaCijena\Settings\Settings;
use Throwable;

class ToolsAjax {

	public const ACTION         = 'scwc_tool_step';
	public const NONCE          = 'scwc_tools';
	public const CAPABILITY     = 'manage_woocommerce';
	public const PER_PAGE       = 100;
	public const EXPORT_PAGE    = 500;
	public const EXPORT_MAX     = 50000;
	public const PREVIEW_ROWS   = 50;
	public const TRANSIENT_BASE = 'scwc_import_';

	/** @var callable():array<string,mixed> */
	private $input;
	/** @var callable():array<string,mixed> */
	private $files;

	/**
	 * @param callable():array<string,mixed>|null $input Request body provider (default $_POST).
	 * @param callable():array<string,mixed>|null $files Uploaded files provider (default $_FILES).
	 */
	public function __construct(
		private readonly SnapshotService $snapshot,
		private readonly CsvImporter $importer,
		private readonly CsvExporter $exporter,
		private readonly Settings $settings,
		?callable $input = null,
		?callable $files = null,
	) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce verified in handle().
		$this->input = $input ?? static fn(): array => $_POST;
		$this->files = $files ?? static fn(): array => $_FILES;
		// phpcs:enable
	}

	public function register(): void {
		add_action( 'wp_ajax_' . self::ACTION, [ $this, 'handle' ] );
	}

	public function handle(): void {
		check_ajax_referer( self::NONCE, 'nonce' );
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_send_json_error( [ 'message' => __( 'Nemate dopuštenje za ovu radnju.', 'sidrena-cijena-za-woocommerce' ) ], 403 );
		}

		$post = ( $this->input )();
		$tool = sanitize_key( (string) ( $post['tool'] ?? '' ) );
		$page = max( 1, (int) ( $post['page'] ?? 1 ) );
		$args = isset( $post['args'] ) && is_array( $post['args'] ) ? wp_unslash( $post['args'] ) : [];
		/** @var array<string,mixed> $args */

		try {
			$response = $this->dispatch( $tool, $page, $args );
		} catch ( Throwable $e ) {
			wp_send_json_error( [ 'message' => $e->getMessage() ] );
		}
		if ( null === $response ) {
			/* translators: %s: tool identifier */
			wp_send_json_error( [ 'message' => sprintf( __( 'Nepoznat alat: %s', 'sidrena-cijena-za-woocommerce' ), $tool ) ] );
		}
		wp_send_json_success( $response );
	}

	/**
	 * @param array<string,mixed> $args Tool arguments.
	 * @return array<string,mixed>|null Null for an unknown tool.
	 * @throws \RuntimeException On a tool-level failure (message is shown to the user).
	 */
	private function dispatch( string $tool, int $page, array $args ): ?array {
		switch ( $tool ) {
			case 'snapshot':
				return $this->snapshotStep( $page, $args );
			case 'import_preview':
				return $this->importPreview( $args );
			case 'import_apply':
				return $this->importApply( $args );
			case 'export':
				return $this->export( $args );
		}
		return null;
	}

	/**
	 * @param array<string,mixed> $args Tool arguments.
	 * @return array<string,mixed>
	 */
	private function snapshotStep( int $page, array $args ): array {
		$request = SnapshotRequest::fromArray( $args, $this->settings );
		$result  = $this->snapshot->run( $request, $page, self::PER_PAGE );
		$done    = ! $result->hasMore;
		if ( $done && ! $request->dryRun ) {
			do_action( 'scwc_snapshot_completed', $request );
		}
		$log = sprintf(
			/* translators: 1: page, 2: processed, 3: written, 4: skipped existing, 5: skipped on sale, 6: without price, 7: marked na */
			__( 'Stranica %1$d: obrađeno %2$d, zapisano %3$d, preskočeno (postoji) %4$d, preskočeno (na akciji) %5$d, bez cijene %6$d, označeno „bez referentne cijene” %7$d.', 'sidrena-cijena-za-woocommerce' ),
			$page,
			$result->processed,
			$result->written,
			$result->skippedExisting,
			$result->skippedOnSale,
			$result->skippedNoPrice,
			$result->markedNa
		);
		if ( $request->dryRun ) {
			$log .= ' ' . __( '(probno – ništa nije zapisano)', 'sidrena-cijena-za-woocommerce' );
		}
		return [
			'done'      => $done,
			'next_page' => $page + 1,
			'result'    => $result->toArray(),
			'log'       => $log,
		];
	}

	/**
	 * @param array<string,mixed> $args Tool arguments (csv, type, delimiter).
	 * @return array<string,mixed>
	 */
	private function importPreview( array $args ): array {
		$csv = $this->uploadedCsv();
		if ( null === $csv ) {
			$csv = (string) ( $args['csv'] ?? '' );
		}
		if ( '' === trim( $csv ) ) {
			throw new \RuntimeException( __( 'Odaberite CSV datoteku.', 'sidrena-cijena-za-woocommerce' ) );
		}
		$typeKey   = sanitize_key( (string) ( $args['type'] ?? 'anchor' ) );
		$delimiter = self::delimiterArg( (string) ( $args['delimiter'] ?? 'auto' ) );
		$preview   = $this->importer->parse( $csv, '' === $typeKey ? 'anchor' : $typeKey, $delimiter );

		$token = wp_generate_password( 12, false );
		set_transient( self::TRANSIENT_BASE . $token, $preview->toArray(), HOUR_IN_SECONDS );

		return [
			'token'     => $token,
			'delimiter' => $preview->delimiter,
			'total'     => count( $preview->rows ),
			'valid'     => $preview->valid,
			'invalid'   => $preview->invalid,
			'unmatched' => $preview->unmatched,
			'fatal'     => $preview->isFatal(),
			'rows'      => array_map( static fn( CsvImportRow $r ) => $r->toArray(), array_slice( $preview->rows, 0, self::PREVIEW_ROWS ) ),
			'errors'    => array_slice( $preview->errors(), 0, 200 ),
		];
	}

	/**
	 * @param array<string,mixed> $args Tool arguments (token).
	 * @return array<string,mixed>
	 */
	private function importApply( array $args ): array {
		$token = sanitize_key( (string) ( $args['token'] ?? '' ) );
		$data  = '' === $token ? false : get_transient( self::TRANSIENT_BASE . $token );
		if ( ! is_array( $data ) ) {
			throw new \RuntimeException( __( 'Pregled uvoza je istekao. Ponovno učitajte datoteku.', 'sidrena-cijena-za-woocommerce' ) );
		}
		$result = $this->importer->apply( ImportPreview::fromArray( $data ) );
		delete_transient( self::TRANSIENT_BASE . $token );
		do_action( 'scwc_import_completed', $result );
		return $result->toArray();
	}

	/**
	 * @param array<string,mixed> $args Tool arguments (only_missing).
	 * @return array<string,mixed>
	 */
	private function export( array $args ): array {
		$onlyMissing = SnapshotRequest::truthy( $args['only_missing'] ?? false );
		$rows        = [];
		$page        = 1;
		do {
			$generator = $this->exporter->rows( $onlyMissing, $page, self::EXPORT_PAGE );
			foreach ( $generator as $row ) {
				$rows[] = $row;
			}
			$hasMore = (bool) $generator->getReturn();
			++$page;
		} while ( $hasMore && count( $rows ) <= self::EXPORT_MAX );

		return [
			'csv'      => CsvExporter::toCsvString( $rows, (string) $this->settings->get( 'price_list.csv_delimiter', ';' ) ),
			'filename' => 'sidrene-cijene-' . wp_date( 'Ymd' ) . '.csv',
			'rows'     => max( 0, count( $rows ) - 1 ),
		];
	}

	private function uploadedCsv(): ?string {
		$files = ( $this->files )();
		$file  = $files['file'] ?? null;
		if ( ! is_array( $file ) || 0 !== (int) ( $file['error'] ?? UPLOAD_ERR_NO_FILE ) ) {
			return null;
		}
		$path = (string) ( $file['tmp_name'] ?? '' );
		if ( '' === $path || ! is_readable( $path ) ) {
			return null;
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- PHP upload temp file.
		$contents = file_get_contents( $path );
		return false === $contents ? null : $contents;
	}

	private static function delimiterArg( string $value ): ?string {
		return match ( $value ) {
			';'   => ';',
			','   => ',',
			'tab', "\t" => "\t",
			default => null,
		};
	}
}
