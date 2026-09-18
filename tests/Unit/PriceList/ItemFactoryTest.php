<?php
declare(strict_types=1);

namespace SidrenaCijena\Tests\Unit\PriceList;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use SidrenaCijena\PriceList\Item;
use SidrenaCijena\PriceList\ItemFactory;
use SidrenaCijena\PriceList\ServiceItem;
use SidrenaCijena\Product\ProductSnapshot;
use SidrenaCijena\Product\ServiceRule;
use SidrenaCijena\Reference\CategoryOverrideResolver;
use SidrenaCijena\Reference\ReferenceDateResolver;
use SidrenaCijena\Reference\ReferencePriceRegistry;
use SidrenaCijena\Reference\ReferencePriceRepository;
use SidrenaCijena\Settings\Defaults;
use SidrenaCijena\Settings\Settings;
use SidrenaCijena\Tests\TestCase;

final class ItemFactoryTest extends TestCase {
	private function factory( ?Settings $settings = null ): ItemFactory {
		$settings = $settings ?? new Settings( Defaults::all() );
		return new ItemFactory(
			$settings,
			ReferencePriceRegistry::fromSettings( $settings ),
			new ReferencePriceRepository( new ReferenceDateResolver( new CategoryOverrideResolver( fn() => [] ) ) ),
			new ServiceRule( (string) $settings->get( 'price_list.service_rule' ) ),
		);
	}

	/**
	 * @param array<string,mixed> $a
	 */
	private function snapshot( array $a = [] ): ProductSnapshot {
		return ProductSnapshot::fromArray( array_merge( [ 'id' => 1, 'name' => 'Proizvod', 'regular' => '12.99', 'active' => '12.99' ], $a ) );
	}

	public function test_maps_a_simple_product_to_an_item(): void {
		$s = $this->snapshot(
			[
				'sku'          => 'SKU-1',
				'brand'        => 'Marka',
				'barcode'      => '3850001',
				'unit'         => 'kg',
				'unitQuantity' => 0.5,
				'permalink'    => 'https://example.hr/p/1',
				'meta'         => [ '_scwc_ref_anchor_price' => '14.99' ],
			]
		);
		$item = $this->factory()->make( $this->product( [ 'id' => 1 ] ), $s, null );
		self::assertInstanceOf( Item::class, $item );
		self::assertSame( 'Proizvod', $item->name );
		self::assertSame( 'SKU-1', $item->sku );
		$noSku = $this->factory()->make( $this->product( [ 'id' => 77, 'regular_price' => '1' ] ), \SidrenaCijena\Product\ProductSnapshot::fromArray( [ 'id' => 77, 'regular' => '1', 'active' => '1' ] ), null );
		self::assertSame( '77', $noSku->sku, 'šifra is mandatory: fall back to the product ID' );
		self::assertSame( 'Marka', $item->brand );
		self::assertSame( 'kg', $item->unit );
		self::assertSame( 25.98, $item->unitPrice );
		self::assertSame( 12.99, $item->price );
		self::assertFalse( $item->isSpecialSale );
		self::assertSame( '', $item->saleName );
		self::assertSame( [ 'anchor' => [ 'amount' => 14.99, 'date' => '2026-09-10' ] ], $item->references );
		self::assertNull( $item->lowest30 );
		self::assertSame( '3850001', $item->barcode );
		self::assertTrue( $item->available );
		self::assertSame( 'https://example.hr/p/1', $item->url );
	}

	public function test_on_sale_product_uses_default_sale_name_and_lowest30(): void {
		$s    = $this->snapshot( [ 'sale' => '9.99', 'active' => '9.99', 'isOnSale' => true, 'meta' => [ '_scwc_omnibus_ref_price' => '11.5' ] ] );
		$item = $this->factory()->make( $this->product( [ 'id' => 1 ] ), $s, null );
		self::assertTrue( $item->isSpecialSale );
		self::assertSame( 'Sniženje', $item->saleName );
		self::assertSame( 9.99, $item->price );
		self::assertSame( 11.5, $item->lowest30 );
		self::assertNull( $item->unitPrice );
	}

	public function test_custom_sale_name_wins_and_lowest30_ignored_when_not_on_sale(): void {
		$onSale = $this->snapshot( [ 'sale' => '9.99', 'active' => '9.99', 'isOnSale' => true, 'saleName' => 'Black Friday' ] );
		self::assertSame( 'Black Friday', $this->factory()->make( $this->product( [ 'id' => 1 ] ), $onSale, null )->saleName );
		$regular = $this->snapshot( [ 'meta' => [ '_scwc_omnibus_ref_price' => '11.5' ] ] );
		self::assertNull( $this->factory()->make( $this->product( [ 'id' => 1 ] ), $regular, null )->lowest30 );
	}

	public function test_excluded_products_yield_null(): void {
		$f = $this->factory();
		$p = $this->product( [ 'id' => 1 ] );
		self::assertNull( $f->make( $p, $this->snapshot( [ 'excludeFromPriceList' => true ] ), null ) );
		self::assertNull( $f->make( $p, $this->snapshot( [ 'priceOnRequest' => true ] ), null ) );
		self::assertNull( $f->make( $p, $this->snapshot( [ 'active' => '' ] ), null ) );
		self::assertNull( $f->make( $p, $this->snapshot( [ 'active' => 'abc' ] ), null ) );
		self::assertNull( $f->make( $p, $this->snapshot( [ 'status' => 'draft' ] ), null ) );
		self::assertNotNull( $f->make( $p, $this->snapshot( [ 'visible' => false ] ), null ), 'hidden products are listed by default (purchasable via URL)' );
		$strict = $this->factory( ( new Settings( Defaults::all() ) )->with( 'price_list.include_hidden', false ) );
		self::assertNull( $strict->make( $p, $this->snapshot( [ 'visible' => false ] ), null ) );
		self::assertNull( $f->make( $p, $this->snapshot( [ 'type' => 'variable' ] ), null ) );
		self::assertNull( $f->make( $p, $this->snapshot( [ 'type' => 'grouped' ] ), null ) );
	}

	public function test_hidden_products_included_when_setting_allows(): void {
		$settings = ( new Settings( Defaults::all() ) )->with( 'price_list.include_hidden', true );
		self::assertNotNull( $this->factory( $settings )->make( $this->product( [ 'id' => 1 ] ), $this->snapshot( [ 'visible' => false ] ), null ) );
	}

	public function test_out_of_stock_handling_follows_setting(): void {
		$s    = $this->snapshot( [ 'stockStatus' => 'outofstock' ] );
		$item = $this->factory()->make( $this->product( [ 'id' => 1 ] ), $s, null );
		self::assertNotNull( $item );
		self::assertFalse( $item->available );
		$settings = ( new Settings( Defaults::all() ) )->with( 'price_list.include_out_of_stock', false );
		self::assertNull( $this->factory( $settings )->make( $this->product( [ 'id' => 1 ] ), $s, null ) );
		self::assertTrue( $this->factory()->make( $this->product( [ 'id' => 1 ] ), $this->snapshot( [ 'stockStatus' => 'onbackorder' ] ), null )->available );
	}

	public function test_tax_mode_excl_uses_excluding_tax_conversion(): void {
		$settings = ( new Settings( Defaults::all() ) )->with( 'price_list.tax_mode', 'excl' );
		Functions\expect( 'wc_get_price_excluding_tax' )->atLeast()->once()->andReturnUsing( fn( $p, array $args ) => (float) $args['price'] * 0.8 );
		Functions\expect( 'wc_get_price_including_tax' )->never();
		$s    = $this->snapshot( [ 'active' => '10', 'meta' => [ '_scwc_ref_anchor_price' => '20' ] ] );
		$item = $this->factory( $settings )->make( $this->product( [ 'id' => 1 ] ), $s, null );
		self::assertSame( 8.0, $item->price );
		self::assertSame( 16.0, $item->references['anchor']['amount'] );
	}

	public function test_tax_mode_incl_rounds_to_two_decimals(): void {
		Functions\expect( 'wc_get_price_including_tax' )->atLeast()->once()->andReturnUsing( fn( $p, array $args ) => (float) $args['price'] * 1.25 );
		$item = $this->factory()->make( $this->product( [ 'id' => 1 ] ), $this->snapshot( [ 'active' => '9.99' ] ), null );
		self::assertSame( 12.49, $item->price );
	}

	public function test_missing_reference_keeps_resolved_date_and_na_hides_amount(): void {
		$f    = $this->factory();
		$item = $f->make( $this->product( [ 'id' => 1 ] ), $this->snapshot(), null );
		self::assertSame( [ 'anchor' => [ 'amount' => null, 'date' => '2026-09-10' ] ], $item->references );
		$na = $f->make( $this->product( [ 'id' => 1 ] ), $this->snapshot( [ 'meta' => [ '_scwc_ref_anchor_price' => '5', '_scwc_ref_anchor_na' => '1' ] ] ), null );
		self::assertNull( $na->references['anchor']['amount'] );
	}

	public function test_second_reference_type_in_registry_order(): void {
		$settings = ( new Settings( Defaults::all() ) )->with( 'reference_prices.base.enabled', true )->with( 'reference_prices.base.date', '2026-11-17' );
		$s        = $this->snapshot( [ 'meta' => [ '_scwc_ref_anchor_price' => '12', '_scwc_ref_base_price' => '11' ] ] );
		$item     = $this->factory( $settings )->make( $this->product( [ 'id' => 1 ] ), $s, null );
		self::assertSame( [ 'anchor', 'base' ], array_keys( $item->references ) );
		self::assertSame( [ 'amount' => 11.0, 'date' => '2026-11-17' ], $item->references['base'] );
	}

	public function test_variation_inherits_parent_reference_when_enabled(): void {
		$settings = ( new Settings( Defaults::all() ) )->with( 'reference_prices.variation_inherit_parent', true );
		$parent   = ProductSnapshot::fromArray( [ 'id' => 10, 'type' => 'variable', 'meta' => [ '_scwc_ref_anchor_price' => '7', '_scwc_ref_anchor_date' => '2026-02-02' ] ] );
		$child    = $this->snapshot( [ 'id' => 11, 'parentId' => 10, 'type' => 'variation', 'active' => '8' ] );
		$item     = $this->factory( $settings )->make( $this->product( [ 'id' => 11, 'type' => 'variation' ] ), $child, $parent );
		self::assertSame( [ 'amount' => 7.0, 'date' => '2026-02-02' ], $item->references['anchor'] );
		self::assertNull( $this->factory()->make( $this->product( [ 'id' => 11, 'type' => 'variation' ] ), $child, $parent )->references['anchor']['amount'] );
	}

	public function test_virtual_product_becomes_service_item(): void {
		$s    = $this->snapshot( [ 'name' => 'Montaža', 'isVirtual' => true, 'sale' => '40', 'active' => '40', 'isOnSale' => true, 'meta' => [ '_scwc_ref_anchor_price' => '50' ] ] );
		$item = $this->factory()->make( $this->product( [ 'id' => 1, 'virtual' => true ] ), $s, null );
		self::assertInstanceOf( ServiceItem::class, $item );
		self::assertSame( 'Montaža', $item->name );
		self::assertSame( 40.0, $item->price );
		self::assertTrue( $item->isSpecialSale );
		self::assertSame( 'Sniženje', $item->saleName );
		self::assertSame( [ 'anchor' => [ 'amount' => 50.0, 'date' => '2026-09-10' ] ], $item->references );
	}

	public function test_item_filter_can_drop_or_replace(): void {
		Filters\expectApplied( 'scwc_price_list_item' )->once()->with( \Mockery::type( Item::class ), \Mockery::type( ProductSnapshot::class ) )->andReturn( null );
		self::assertNull( $this->factory()->make( $this->product( [ 'id' => 1 ] ), $this->snapshot(), null ) );
	}
}
