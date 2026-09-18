<?php
/**
 * Writes the price list as CSV (products and services in one file, first column "vrsta").
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

namespace SidrenaCijena\PriceList;

use DateTimeImmutable;
use RuntimeException;
use SidrenaCijena\Reference\ReferencePriceType;
use SidrenaCijena\Support\Money;

class CsvWriter implements Writer {

	private const BOM = "\xEF\xBB\xBF";

	public function __construct(
		private readonly string $delimiter = ';',
		private readonly bool $bom = true,
	) {}

	/**
	 * {@inheritDoc}
	 */
	public function write( iterable $items, Outlet $outlet, DateTimeImmutable $generatedAtLocal, string $path, array $types ): WriteStats {
		$fh = fopen( $path, 'wb' );
		if ( false === $fh ) {
			throw new RuntimeException( esc_html( sprintf( /* translators: %s: file path */ __( 'Nije moguće pisati u datoteku %s.', 'sidrena-cijena-za-woocommerce' ), $path ) ) );
		}
		if ( $this->bom ) {
			fwrite( $fh, self::BOM );
		}

		/** @var string[] $header */
		$header = apply_filters( 'scwc_price_list_csv_header', $this->header( $types ) );
		$this->line( $fh, $header );

		$products = 0;
		$services = 0;
		foreach ( $items as $item ) {
			if ( $item instanceof Item ) {
				$row = $this->productRow( $item, $types );
				++$products;
			} elseif ( $item instanceof ServiceItem ) {
				$row = $this->serviceRow( $item, $types );
				++$services;
			} else {
				continue;
			}
			/** @var array<int,string> $row */
			$row = apply_filters( 'scwc_price_list_csv_row', $row, $item );
			$this->line( $fh, $row );
		}
		fclose( $fh );

		return new WriteStats( $products, $services );
	}

	/**
	 * @param ReferencePriceType[] $types Types in order.
	 * @return string[]
	 */
	public function header( array $types ): array {
		$header = [ 'vrsta', 'naziv', 'šifra', 'marka', 'jedinica_mjere', 'cijena_za_jedinicu_mjere', 'maloprodajna_cijena', 'posebni_oblik_prodaje', 'naziv_posebnog_oblika_prodaje' ];
		foreach ( $types as $type ) {
			$header[] = $type->csvColumn;
			$header[] = $type->csvColumn . '_datum';
		}
		return array_merge( $header, [ 'najniža_cijena_30_dana', 'barkod', 'dostupnost', 'url' ] );
	}

	/**
	 * @param ReferencePriceType[] $types Types in order.
	 * @return string[]
	 */
	private function productRow( Item $item, array $types ): array {
		$row   = [
			'proizvod',
			$item->name,
			$item->sku,
			$item->brand,
			$item->unit,
			$this->money( $item->unitPrice ),
			$this->money( $item->price ),
			$item->isSpecialSale ? 'da' : 'ne',
			$item->saleName,
		];
		$row   = array_merge( $row, $this->referenceColumns( $item->references, $types ) );
		$row[] = $this->money( $item->lowest30 );
		$row[] = $item->barcode;
		$row[] = $item->available ? 'dostupno' : 'nedostupno';
		$row[] = $item->url;
		return $row;
	}

	/**
	 * @param ReferencePriceType[] $types Types in order.
	 * @return string[]
	 */
	private function serviceRow( ServiceItem $item, array $types ): array {
		$row = [ 'usluga', $item->name, '', '', '', '', $this->money( $item->price ), $item->isSpecialSale ? 'da' : 'ne', $item->saleName ];
		$row = array_merge( $row, $this->referenceColumns( $item->references, $types ) );
		return array_merge( $row, [ '', '', '', '' ] );
	}

	/**
	 * @param array<string,array{amount:?float,date:?string}> $references Row references.
	 * @param ReferencePriceType[]                             $types      Types in order.
	 * @return string[]
	 */
	private function referenceColumns( array $references, array $types ): array {
		$cols = [];
		foreach ( $types as $type ) {
			$ref    = $references[ $type->key ] ?? [
				'amount' => null,
				'date'   => null,
			];
			$cols[] = $this->money( $ref['amount'] );
			$cols[] = (string) ( $ref['date'] ?? '' );
		}
		return $cols;
	}

	/**
	 * @param resource $fh     Open file handle.
	 * @param string[] $fields Row.
	 */
	private function line( $fh, array $fields ): void {
		fputcsv( $fh, array_map( 'strval', $fields ), $this->delimiter, '"', '', "\n" );
	}

	private function money( ?float $amount ): string {
		return null === $amount ? '' : Money::toDecimal( $amount );
	}
}
