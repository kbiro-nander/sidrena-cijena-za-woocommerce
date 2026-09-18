<?php
/**
 * Re-records every product's current prices (catches direct DB/ERP changes and date-window sales).
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

namespace SidrenaCijena\History;

use SidrenaCijena\Product\ProductAdapter;
use WC_Product;

final class DailySweep {

	/** @var callable(int,int):int[] */
	private $pager;
	/** @var callable(int):?WC_Product */
	private $loader;

	/**
	 * @param callable(int,int):int[]    $pager         Returns product/variation IDs for (page, perPage).
	 * @param callable(int):?WC_Product $productLoader Product loader.
	 */
	public function __construct(
		callable $pager,
		callable $productLoader,
		private readonly ProductAdapter $adapter,
		private readonly Recorder $recorder,
		private readonly OmnibusStateUpdater $updater,
	) {
		$this->pager  = $pager;
		$this->loader = $productLoader;
	}

	public function run( int $page, int $perPage, string $source = 'sweep' ): SweepResult {
		$ids       = ( $this->pager )( $page, $perPage );
		$processed = 0;
		$changed   = 0;
		foreach ( $ids as $id ) {
			$product = ( $this->loader )( (int) $id );
			if ( ! $product instanceof WC_Product || in_array( $product->get_type(), [ 'variable', 'grouped' ], true ) ) {
				continue;
			}
			$parent = null;
			if ( 'variation' === $product->get_type() && $product->get_parent_id() ) {
				$parent = ( $this->loader )( $product->get_parent_id() );
			}
			$snapshot   = $this->adapter->fromProduct( $product, $parent );
			$transition = $this->recorder->record( $snapshot, $source );
			$this->updater->apply( $snapshot, $transition );
			++$processed;
			if ( Transition::NONE !== $transition ) {
				++$changed;
			}
		}
		return new SweepResult( $processed, $changed, count( $ids ) >= $perPage && $perPage > 0 );
	}

	/**
	 * Default pager over published simple/variation/external products.
	 *
	 * @return callable(int,int):int[]
	 */
	public static function wcPager(): callable {
		return static function ( int $page, int $perPage ): array {
			$ids = wc_get_products(
				[
					'status'  => 'publish',
					'type'    => [ 'simple', 'variation', 'external' ],
					'limit'   => $perPage,
					'page'    => $page,
					'orderby' => 'ID',
					'order'   => 'ASC',
					'return'  => 'ids',
				]
			);
			return array_map( 'intval', is_array( $ids ) ? $ids : [] );
		};
	}
}
