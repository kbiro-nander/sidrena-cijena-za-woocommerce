<?php
/**
 * Parses and applies reference-price CSV imports (sku, amount, date, type, na).
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

namespace SidrenaCijena\Admin\Tools;

use SidrenaCijena\Reference\ReferencePriceRegistry;
use SidrenaCijena\Reference\ReferencePriceRepository;
use SidrenaCijena\Support\DateFormat;
use SidrenaCijena\Support\Money;
use WC_Product;

class CsvImporter {

	public const DELIMITERS = [ ';', ',', "\t" ];

	/** Header aliases per logical field, already normalized (lowercase, ASCII, underscores). */
	private const ALIASES = [
		'sku'    => [ 'sku', 'sifra', 'code' ],
		'amount' => [ 'price', 'cijena', 'sidrena_cijena', 'anchor', 'referentna_cijena', 'iznos' ],
		'date'   => [ 'date', 'datum' ],
		'type'   => [ 'key', 'tip', 'type', 'vrsta' ],
		'na'     => [ 'na', 'nema', 'bez_cijene' ],
	];

	/** @var callable(string):int */
	private $skuResolver;
	/** @var callable(int):?WC_Product */
	private $loader;

	/**
	 * @param callable(string):int|null       $skuResolver SKU → product ID (0 when absent). Default wc_get_product_id_by_sku.
	 * @param callable(int):?WC_Product|null $loader      Product loader. Default wc_get_product.
	 */
	public function __construct(
		private readonly ReferencePriceRegistry $registry,
		private readonly ReferencePriceRepository $repository,
		?callable $skuResolver = null,
		?callable $loader = null,
	) {
		$this->skuResolver = $skuResolver ?? static fn( string $sku ): int => (int) wc_get_product_id_by_sku( $sku );
		$this->loader      = $loader ?? static function ( int $id ): ?WC_Product {
			$product = wc_get_product( $id );
			return $product instanceof WC_Product ? $product : null;
		};
	}

	/**
	 * Parse CSV text into a preview. Never throws; row problems land in CsvImportRow::$error.
	 *
	 * @param string      $csv            Raw file contents (BOM allowed).
	 * @param string      $defaultTypeKey Type used when the file has no type column / empty cell.
	 * @param string|null $delimiter      Force a delimiter; null = auto-detect from the header line.
	 */
	public function parse( string $csv, string $defaultTypeKey = 'anchor', ?string $delimiter = null ): ImportPreview {
		if ( str_starts_with( $csv, "\xEF\xBB\xBF" ) ) {
			$csv = substr( $csv, 3 );
		}
		$csv = str_replace( [ "\r\n", "\r" ], "\n", $csv );
		if ( null === $delimiter || ! in_array( $delimiter, self::DELIMITERS, true ) ) {
			$delimiter = self::detectDelimiter( $csv );
		}

		$records = self::records( $csv, $delimiter );
		if ( [] === $records ) {
			return new ImportPreview( [], $delimiter, [], [ __( 'Datoteka je prazna.', 'sidrena-cijena-za-woocommerce' ) ] );
		}

		$headerMap = self::mapHeader( array_shift( $records )['fields'] );
		$missing   = [];
		foreach ( [ 'sku', 'amount' ] as $required ) {
			if ( ! isset( $headerMap[ $required ] ) ) {
				$missing[] = 'sku' === $required ? 'sku / šifra' : 'cijena / price';
			}
		}
		if ( [] !== $missing ) {
			/* translators: %s: list of column names */
			$message = sprintf( __( 'Nedostaje obavezni stupac: %s', 'sidrena-cijena-za-woocommerce' ), implode( ', ', $missing ) );
			return new ImportPreview( [], $delimiter, $headerMap, [ $message ] );
		}

		$rows = [];
		foreach ( $records as $record ) {
			$rows[] = $this->parseRow( $record['line'], $record['fields'], $headerMap, $defaultTypeKey );
		}
		return new ImportPreview( $rows, $delimiter, $headerMap );
	}

	/** Write every valid row; invalid rows are skipped and reported. */
	public function apply( ImportPreview $preview ): ImportResult {
		$updated  = 0;
		$markedNa = 0;
		$skipped  = 0;
		$errors   = $preview->fatalErrors;
		foreach ( $preview->rows as $row ) {
			$type = $this->registry->get( $row->typeKey );
			if ( ! $row->isValid() || null === $row->productId || null === $type ) {
				++$skipped;
				$errors[] = ImportPreview::prefix( $row );
				continue;
			}
			if ( $row->na ) {
				$this->repository->setNa( $type, $row->productId, ReferencePriceRepository::SOURCE_IMPORT );
				++$markedNa;
				continue;
			}
			$this->repository->set( $type, $row->productId, (string) wc_format_decimal( (string) $row->amount ), $row->date, ReferencePriceRepository::SOURCE_IMPORT );
			++$updated;
		}
		return new ImportResult( $updated, $markedNa, $skipped, $errors );
	}

	/** Sample file offered for download on the Tools page. */
	public static function template( ReferencePriceRegistry $registry ): string {
		$type = $registry->primary();
		$date = $type->defaultDate ?? '';
		return implode(
			"\n",
			[
				'sku;cijena;datum;tip',
				'ABC-001;19,99;' . $date . ';' . $type->key,
				'ABC-002;149,00;;' . $type->key,
			]
		) . "\n";
	}

	public static function detectDelimiter( string $csv ): string {
		$header = strtok( $csv, "\n" );
		$header = false === $header ? '' : $header;
		$best   = ';';
		$max    = -1;
		foreach ( self::DELIMITERS as $candidate ) {
			$count = substr_count( $header, $candidate );
			if ( $count > $max ) {
				$max  = $count;
				$best = $candidate;
			}
		}
		return $best;
	}

	/**
	 * @return array<int,array{line:int,fields:string[]}> Non-empty records with their 1-based line numbers.
	 */
	private static function records( string $csv, string $delimiter ): array {
		$handle = fopen( 'php://temp', 'r+' );
		if ( false === $handle ) {
			return [];
		}
		fwrite( $handle, $csv );
		rewind( $handle );
		$records = [];
		$line    = 0;
		// phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
		while ( false !== ( $fields = fgetcsv( $handle, 0, $delimiter, '"', '\\' ) ) ) {
			++$line;
			if ( [ null ] === $fields || [ '' ] === $fields ) {
				continue;
			}
			$records[] = [
				'line'   => $line,
				'fields' => array_map( static fn( $f ) => trim( (string) $f ), $fields ),
			];
		}
		fclose( $handle );
		return $records;
	}

	/**
	 * @param string[] $header Header cells.
	 * @return array<string,int> Field → column index (first matching column wins).
	 */
	private static function mapHeader( array $header ): array {
		$map = [];
		foreach ( $header as $index => $cell ) {
			$normalized = self::normalizeHeader( $cell );
			foreach ( self::ALIASES as $field => $aliases ) {
				if ( ! isset( $map[ $field ] ) && in_array( $normalized, $aliases, true ) ) {
					$map[ $field ] = (int) $index;
					break;
				}
			}
		}
		return $map;
	}

	/** Lowercase, Croatian diacritics folded to ASCII, whitespace/dashes to underscores. */
	private static function normalizeHeader( string $cell ): string {
		$cell = strtr(
			trim( $cell ),
			[
				'č' => 'c',
				'ć' => 'c',
				'š' => 's',
				'ž' => 'z',
				'đ' => 'd',
				'Č' => 'c',
				'Ć' => 'c',
				'Š' => 's',
				'Ž' => 'z',
				'Đ' => 'd',
			]
		);
		$cell = strtolower( $cell );
		return (string) preg_replace( '/[\s\-]+/', '_', $cell );
	}

	/**
	 * @param string[]          $fields Cells.
	 * @param array<string,int> $map    Header map.
	 */
	private function parseRow( int $line, array $fields, array $map, string $defaultTypeKey ): CsvImportRow {
		$cell = static fn( string $field ): string => isset( $map[ $field ] ) ? (string) ( $fields[ $map[ $field ] ] ?? '' ) : '';

		$errors = [];
		$sku    = $cell( 'sku' );
		if ( '' === $sku ) {
			$errors[] = __( 'Nedostaje šifra (SKU)', 'sidrena-cijena-za-woocommerce' );
		}

		$na     = SnapshotRequest::truthy( $cell( 'na' ) );
		$amount = null;
		if ( ! $na ) {
			$amount = Money::parse( $cell( 'amount' ) );
			if ( null === $amount || $amount <= 0 ) {
				$amount   = null;
				$errors[] = __( 'Neispravan iznos', 'sidrena-cijena-za-woocommerce' );
			}
		}

		$date    = null;
		$rawDate = $cell( 'date' );
		if ( '' !== $rawDate ) {
			if ( null === DateFormat::parseIso( $rawDate ) ) {
				$errors[] = __( 'Neispravan datum', 'sidrena-cijena-za-woocommerce' );
			} else {
				$date = $rawDate;
			}
		}

		$typeKey = strtolower( $cell( 'type' ) );
		if ( '' === $typeKey ) {
			$typeKey = $defaultTypeKey;
		}
		if ( null === $this->registry->get( $typeKey ) ) {
			/* translators: %s: type key */
			$errors[] = sprintf( __( 'Nepoznata vrsta referentne cijene: %s', 'sidrena-cijena-za-woocommerce' ), $typeKey );
		}

		$productId = null;
		if ( '' !== $sku ) {
			$id = (int) ( $this->skuResolver )( $sku );
			if ( $id > 0 && null !== ( $this->loader )( $id ) ) {
				$productId = $id;
			} else {
				$errors[] = __( 'Proizvod s tom šifrom ne postoji', 'sidrena-cijena-za-woocommerce' );
			}
		}

		return new CsvImportRow( $line, $sku, $amount, $date, $typeKey, $na, [] === $errors ? null : implode( '; ', $errors ), $productId );
	}
}
