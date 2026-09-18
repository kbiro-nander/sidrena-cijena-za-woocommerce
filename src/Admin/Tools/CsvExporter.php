<?php
/**
 * Exports reference prices as CSV rows (one row per product per enabled type; re-importable).
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

namespace SidrenaCijena\Admin\Tools;

use Generator;
use SidrenaCijena\Product\ProductAdapter;
use SidrenaCijena\Reference\ReferencePriceRegistry;
use SidrenaCijena\Reference\ReferencePriceRepository;
use SidrenaCijena\Support\DateFormat;
use WC_Product;

class CsvExporter {

	public const HEADER = [ 'sku', 'naziv', 'vrsta_proizvoda', 'redovna_cijena', 'tip', 'cijena', 'datum', 'izvor', 'na' ];

	/** @var callable(int,int):int[] */
	private $pager;
	/** @var callable(int):?WC_Product */
	private $loader;

	/**
	 * @param callable(int,int):int[]    $pager  Returns product/variation IDs for (page, perPage).
	 * @param callable(int):?WC_Product $loader Product loader.
	 */
	public function __construct(
		private readonly ReferencePriceRegistry $registry,
		private readonly ReferencePriceRepository $repository,
		private readonly ProductAdapter $adapter,
		callable $pager,
		callable $loader,
	) {
		$this->pager  = $pager;
		$this->loader = $loader;
	}

	/**
	 * Yields the header (page 1 only) and then one row per product per enabled type.
	 * The generator's return value tells whether another page may exist.
	 *
	 * @param bool $onlyMissing Only rows whose reference is absent and not explicitly "na".
	 * @return Generator<int,string[],mixed,bool>
	 */
	public function rows( bool $onlyMissing = false, int $page = 1, int $perPage = 500 ): Generator {
		if ( 1 === $page ) {
			yield self::HEADER;
		}
		$ids = ( $this->pager )( $page, $perPage );
		foreach ( $ids as $id ) {
			$product = ( $this->loader )( (int) $id );
			if ( ! $product instanceof WC_Product || in_array( $product->get_type(), [ 'variable', 'grouped' ], true ) ) {
				continue;
			}
			$parent = null;
			if ( 'variation' === $product->get_type() && $product->get_parent_id() ) {
				$parent = ( $this->loader )( $product->get_parent_id() );
			}
			$snapshot       = $this->adapter->fromProduct( $product, $parent );
			$parentSnapshot = $parent instanceof WC_Product ? $this->adapter->fromProduct( $parent ) : null;

			foreach ( $this->registry->enabled() as $type ) {
				$ref = $this->repository->get( $type, $snapshot, $parentSnapshot );
				if ( $onlyMissing && ( $ref->isPresent() || $ref->na ) ) {
					continue;
				}
				$override = (string) $snapshot->meta( $type->metaKey( 'date' ) );
				yield [
					$snapshot->sku,
					$snapshot->name,
					$snapshot->type,
					$snapshot->regular,
					$type->key,
					$ref->isPresent() ? (string) $ref->amount : '',
					null === DateFormat::parseIso( $override ) ? '' : $override,
					$ref->source,
					$ref->na ? '1' : '',
				];
			}
		}
		return $perPage > 0 && count( $ids ) >= $perPage;
	}

	/**
	 * @param iterable<string[]> $rows      Rows (header first).
	 * @param string             $delimiter Field delimiter.
	 * @return string UTF-8 with BOM, "\n" line endings.
	 */
	public static function toCsvString( iterable $rows, string $delimiter = ';' ): string {
		$handle = fopen( 'php://temp', 'r+' );
		if ( false === $handle ) {
			return '';
		}
		fwrite( $handle, "\xEF\xBB\xBF" );
		foreach ( $rows as $row ) {
			fputcsv( $handle, $row, $delimiter, '"', '\\', "\n" );
		}
		rewind( $handle );
		$csv = stream_get_contents( $handle );
		fclose( $handle );
		return false === $csv ? '' : $csv;
	}
}
