<?php
/**
 * Streams price-list rows over all published products (variations expanded, parents excluded).
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

namespace SidrenaCijena\PriceList;

use Generator;
use SidrenaCijena\Product\ProductAdapter;
use SidrenaCijena\Product\ProductSnapshot;
use WC_Product;

class Collector {

	/** @var callable(int,int):int[] */
	private $pager;
	/** @var callable(int):?WC_Product */
	private $loader;
	/** @var callable(ProductSnapshot):void|null */
	private $observer;

	/**
	 * @param callable(int,int):int[]                 $pager    Returns parent-level product IDs for (page, perPage).
	 * @param callable(int):?WC_Product              $loader   Product loader.
	 * @param callable(ProductSnapshot):void|null    $observer Called for every non-container snapshot seen.
	 */
	public function __construct(
		callable $pager,
		callable $loader,
		private readonly ProductAdapter $adapter,
		private readonly ItemFactory $factory,
		private readonly int $perPage = 200,
		?callable $observer = null,
	) {
		$this->pager    = $pager;
		$this->loader   = $loader;
		$this->observer = $observer;
	}

	/**
	 * @return Generator<int,Item|ServiceItem>
	 */
	public function items(): Generator {
		$page = 1;
		do {
			$ids   = ( $this->pager )( $page, $this->perPage );
			$count = count( $ids );
			foreach ( $ids as $id ) {
				$product = ( $this->loader )( (int) $id );
				if ( ! $product instanceof WC_Product ) {
					continue;
				}
				if ( 'variable' === $product->get_type() ) {
					yield from $this->variations( $product );
					continue;
				}
				$item = $this->make( $product, null, null );
				if ( null !== $item ) {
					yield $item;
				}
			}
			++$page;
		} while ( $this->perPage > 0 && $count >= $this->perPage );
	}

	/**
	 * @return Generator<int,Item|ServiceItem>
	 */
	private function variations( WC_Product $parent ): Generator {
		$parentSnapshot = $this->adapter->fromProduct( $parent );
		foreach ( $parent->get_children() as $childId ) {
			$child = ( $this->loader )( (int) $childId );
			if ( ! $child instanceof WC_Product ) {
				continue;
			}
			$item = $this->make( $child, $parent, $parentSnapshot );
			if ( null !== $item ) {
				yield $item;
			}
		}
	}

	/**
	 * @return Item|ServiceItem|null
	 */
	private function make( WC_Product $product, ?WC_Product $parent, ?ProductSnapshot $parentSnapshot ) {
		$snapshot = $this->adapter->fromProduct( $product, $parent );
		if ( $snapshot->isContainer() ) {
			return null;
		}
		if ( null !== $this->observer ) {
			( $this->observer )( $snapshot );
		}
		return $this->factory->make( $product, $snapshot, $parentSnapshot );
	}

	/**
	 * Default pager over published parent-level products.
	 *
	 * @return callable(int,int):int[]
	 */
	/**
	 * wc_get_products() arguments for one page of top-level products. No product-type whitelist:
	 * third-party types (bundles, subscriptions, …) are the trader's products too; containers are
	 * skipped later by ProductSnapshot::isContainer().
	 *
	 * @return array<string,mixed>
	 */
	public static function pagerArgs( int $page, int $perPage ): array {
		return [
			'status'  => 'publish',
			'limit'   => $perPage,
			'page'    => $page,
			'orderby' => 'ID',
			'order'   => 'ASC',
			'return'  => 'ids',
		];
	}

	/**
	 * @return callable(int,int):int[]
	 */
	public static function wcPager(): callable {
		return static function ( int $page, int $perPage ): array {
			$ids = wc_get_products( self::pagerArgs( $page, $perPage ) );
			return array_map( 'intval', is_array( $ids ) ? $ids : [] );
		};
	}
}
