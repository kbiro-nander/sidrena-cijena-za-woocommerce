<?php
declare(strict_types=1);

namespace SidrenaCijena\Tests\Unit\PriceList;

use SidrenaCijena\PriceList\Collector;
use SidrenaCijena\PriceList\Item;
use SidrenaCijena\PriceList\ItemFactory;
use SidrenaCijena\PriceList\ServiceItem;
use SidrenaCijena\Product\BarcodeResolver;
use SidrenaCijena\Product\BrandResolver;
use SidrenaCijena\Product\ProductAdapter;
use SidrenaCijena\Product\ProductSnapshot;
use SidrenaCijena\Product\ServiceRule;
use SidrenaCijena\Reference\CategoryOverrideResolver;
use SidrenaCijena\Reference\ReferenceDateResolver;
use SidrenaCijena\Reference\ReferencePriceRegistry;
use SidrenaCijena\Reference\ReferencePriceRepository;
use SidrenaCijena\Settings\Defaults;
use SidrenaCijena\Settings\Settings;
use SidrenaCijena\Tests\TestCase;

final class CollectorTest extends TestCase {
	private function factory(): ItemFactory {
		$settings = new Settings( Defaults::all() );
		return new ItemFactory(
			$settings,
			ReferencePriceRegistry::fromSettings( $settings ),
			new ReferencePriceRepository( new ReferenceDateResolver( new CategoryOverrideResolver( fn() => [] ) ) ),
			new ServiceRule(),
		);
	}

	/**
	 * @param array<int,int[]> $pages
	 */
	private function collector( array $pages, int $perPage, ?callable $observer = null ): Collector {
		return new Collector(
			fn( int $page, int $per ) => $pages[ $page ] ?? [],
			fn( int $id ) => \wc_get_product( $id ) ?: null,
			new ProductAdapter( new BrandResolver(), new BarcodeResolver() ),
			$this->factory(),
			$perPage,
			$observer,
		);
	}

	public function test_pages_until_a_short_page_and_yields_items(): void {
		foreach ( [ 1, 2, 3, 4 ] as $id ) {
			$this->product( [ 'id' => $id, 'name' => 'P' . $id, 'regular_price' => '10' ] );
		}
		$this->product( [ 'id' => 5, 'name' => 'Usluga', 'regular_price' => '99', 'virtual' => true ] );
		$calls = [];
		$pager = function ( int $page, int $per ) use ( &$calls ) {
			$calls[] = [ $page, $per ];
			return [ 1 => [ 1, 2 ], 2 => [ 3, 4 ], 3 => [ 5 ] ][ $page ] ?? [];
		};
		$collector = new Collector( $pager, fn( int $id ) => \wc_get_product( $id ) ?: null, new ProductAdapter( new BrandResolver(), new BarcodeResolver() ), $this->factory(), 2 );
		$items     = iterator_to_array( $collector->items(), false );
		self::assertCount( 5, $items );
		self::assertSame( [ 'P1', 'P2', 'P3', 'P4' ], array_map( fn( $i ) => $i->name, array_slice( $items, 0, 4 ) ) );
		self::assertInstanceOf( ServiceItem::class, $items[4] );
		self::assertSame( [ [ 1, 2 ], [ 2, 2 ], [ 3, 2 ] ], $calls );
	}

	public function test_variable_products_are_expanded_into_variations_only(): void {
		$this->product( [ 'id' => 11, 'type' => 'variation', 'parent_id' => 10, 'name' => 'Majica - S', 'regular_price' => '10' ] );
		$this->product( [ 'id' => 12, 'type' => 'variation', 'parent_id' => 10, 'name' => 'Majica - M', 'regular_price' => '12', 'meta' => [ '_scwc_ref_anchor_price' => '13' ] ] );
		$this->product( [ 'id' => 10, 'type' => 'variable', 'name' => 'Majica', 'children' => [ 11, 12, 999 ], 'meta' => [ '_scwc_unit' => 'kom' ] ] );
		$items = iterator_to_array( $this->collector( [ 1 => [ 10 ] ], 50 )->items(), false );
		self::assertCount( 2, $items );
		self::assertContainsOnlyInstancesOf( Item::class, $items );
		self::assertSame( [ 'Majica - S', 'Majica - M' ], array_map( fn( Item $i ) => $i->name, $items ) );
		self::assertSame( 'kom', $items[0]->unit ); // inherited from parent via adapter
		self::assertSame( 13.0, $items[1]->references['anchor']['amount'] );
	}

	public function test_missing_and_excluded_products_are_skipped(): void {
		$this->product( [ 'id' => 1, 'regular_price' => '10', 'meta' => [ '_scwc_exclude_from_price_list' => 'yes' ] ] );
		$this->product( [ 'id' => 2, 'regular_price' => '10', 'catalog_visibility' => 'hidden' ] );
		$this->product( [ 'id' => 3, 'regular_price' => '10' ] );
		$items = iterator_to_array( $this->collector( [ 1 => [ 1, 2, 404, 3 ] ], 50 )->items(), false );
		self::assertCount( 1, $items );
	}

	public function test_observer_sees_every_non_container_snapshot(): void {
		$this->product( [ 'id' => 1, 'regular_price' => '10', 'meta' => [ '_scwc_exclude_from_price_list' => 'yes' ] ] );
		$this->product( [ 'id' => 11, 'type' => 'variation', 'parent_id' => 10, 'regular_price' => '10' ] );
		$this->product( [ 'id' => 10, 'type' => 'variable', 'children' => [ 11 ] ] );
		$seen = [];
		$obs  = function ( ProductSnapshot $s ) use ( &$seen ) {
			$seen[] = $s->id;
		};
		iterator_to_array( $this->collector( [ 1 => [ 1, 10 ] ], 50, $obs )->items(), false );
		self::assertSame( [ 1, 11 ], $seen );
	}

	public function test_wc_pager_is_a_callable(): void {
		self::assertIsCallable( Collector::wcPager() );
	}
}
