<?php
/**
 * Value object: the parsed CSV before it is applied (rows, counters, errors).
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

namespace SidrenaCijena\Admin\Tools;

final class ImportPreview {

	public readonly int $valid;
	public readonly int $invalid;
	public readonly int $unmatched;

	/**
	 * @param CsvImportRow[]    $rows        Parsed rows in file order.
	 * @param string            $delimiter   Delimiter used.
	 * @param array<string,int> $headerMap   Field name → column index.
	 * @param string[]          $fatalErrors File-level errors (no rows were parsed).
	 */
	public function __construct(
		public readonly array $rows,
		public readonly string $delimiter,
		public readonly array $headerMap,
		public readonly array $fatalErrors = [],
	) {
		$valid     = 0;
		$invalid   = 0;
		$unmatched = 0;
		foreach ( $rows as $row ) {
			if ( $row->isValid() ) {
				++$valid;
				continue;
			}
			++$invalid;
			if ( null === $row->productId && '' !== $row->sku ) {
				++$unmatched;
			}
		}
		$this->valid     = $valid;
		$this->invalid   = $invalid;
		$this->unmatched = $unmatched;
	}

	public function isFatal(): bool {
		return [] !== $this->fatalErrors;
	}

	/**
	 * Fatal errors followed by line-prefixed row errors.
	 *
	 * @return string[]
	 */
	public function errors(): array {
		$errors = $this->fatalErrors;
		foreach ( $this->rows as $row ) {
			if ( null !== $row->error ) {
				$errors[] = self::prefix( $row );
			}
		}
		return $errors;
	}

	public static function prefix( CsvImportRow $row ): string {
		/* translators: 1: line number, 2: error message */
		return sprintf( __( 'Redak %1$d: %2$s', 'sidrena-cijena-za-woocommerce' ), $row->line, (string) $row->error );
	}

	/**
	 * @return array<string,mixed>
	 */
	public function toArray(): array {
		return [
			'rows'       => array_map( static fn( CsvImportRow $r ) => $r->toArray(), $this->rows ),
			'delimiter'  => $this->delimiter,
			'header_map' => $this->headerMap,
			'fatal'      => $this->fatalErrors,
		];
	}

	/**
	 * @param array<string,mixed> $a Array from toArray().
	 */
	public static function fromArray( array $a ): self {
		$rows = [];
		foreach ( (array) ( $a['rows'] ?? [] ) as $row ) {
			if ( is_array( $row ) ) {
				$rows[] = CsvImportRow::fromArray( $row );
			}
		}
		/** @var array<string,int> $map */
		$map = array_map( 'intval', (array) ( $a['header_map'] ?? [] ) );
		return new self( $rows, (string) ( $a['delimiter'] ?? ';' ), $map, array_map( 'strval', (array) ( $a['fatal'] ?? [] ) ) );
	}
}
