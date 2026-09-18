<?php
declare(strict_types=1);

namespace SidrenaCijena\Tests\Unit\Product;

use Brain\Monkey\Functions;
use SidrenaCijena\Product\BarcodeResolver;
use SidrenaCijena\Product\BrandResolver;
use SidrenaCijena\Product\ProductAdapter;
use SidrenaCijena\Tests\TestCase;

final class ProductAdapterTest extends TestCase {
	private function adapter(): ProductAdapter {
		return new ProductAdapter( new BrandResolver(), new BarcodeResolver() );
	}

	public function test_maps_core_fields_using_edit_context(): void {
		$p = $this->product( [
			'id' => 11, 'sku' => 'ABC', 'name' => 'Majica', 'regular_price' => '20', 'sale_price' => '15',
			'virtual' => false, 'stock_status' => 'outofstock', 'catalog_visibility' => 'hidden',
			'date_created' => new \WC_DateTime( '2026-09-01 10:00:00', new \DateTimeZone( 'UTC' ) ),
			'permalink' => 'https://example.hr/p/majica/', 'global_unique_id' => '3850001', 'category_ids' => [ 4, 9 ],
			'meta' => [ '_scwc_ref_anchor_price' => '18', '_scwc_unit' => 'kom', '_scwc_unit_quantity' => '1', '_scwc_is_service' => 'no' ],
		] );
		$s = $this->adapter()->fromProduct( $p );
		self::assertSame( 11, $s->id );
		self::assertSame( 'ABC', $s->sku );
		self::assertSame( '20', $s->regular );
		self::assertSame( '15', $s->sale );
		self::assertSame( '15', $s->active );
		self::assertTrue( $s->isOnSale );
		self::assertSame( 'outofstock', $s->stockStatus );
		self::assertFalse( $s->visible );
		self::assertSame( '2026-09-01', $s->createdAt?->format( 'Y-m-d' ) );
		self::assertSame( '3850001', $s->barcode );
		self::assertSame( [ 4, 9 ], $s->categoryIds );
		self::assertSame( '18', $s->meta( '_scwc_ref_anchor_price' ) );
		self::assertSame( 'kom', $s->unit );
		self::assertSame( 1.0, $s->unitQuantity );
		self::assertFalse( $s->serviceFlag );
	}

	public function test_variation_inherits_unit_service_and_sale_name_from_parent_when_absent(): void {
		$parent    = $this->product( [ 'id' => 1, 'type' => 'variable', 'category_ids' => [ 2 ], 'meta' => [ '_scwc_unit' => 'l', '_scwc_unit_quantity' => '0.5', '_scwc_is_service' => 'yes', '_scwc_sale_name' => 'Black Friday', '_scwc_exclude_from_price_list' => 'yes' ] ] );
		$variation = $this->product( [ 'id' => 2, 'type' => 'variation', 'parent_id' => 1, 'regular_price' => '5' ] );
		$s = $this->adapter()->fromProduct( $variation, $parent );
		self::assertSame( 'l', $s->unit );
		self::assertSame( 0.5, $s->unitQuantity );
		self::assertTrue( $s->serviceFlag );
		self::assertSame( 'Black Friday', $s->saleName );
		self::assertTrue( $s->excludeFromPriceList );
		self::assertSame( [ 2 ], $s->categoryIds );
	}

	public function test_variation_own_meta_wins_over_parent(): void {
		$parent    = $this->product( [ 'id' => 1, 'type' => 'variable', 'meta' => [ '_scwc_unit' => 'l' ] ] );
		$variation = $this->product( [ 'id' => 2, 'type' => 'variation', 'parent_id' => 1, 'meta' => [ '_scwc_unit' => 'kg' ] ] );
		self::assertSame( 'kg', $this->adapter()->fromProduct( $variation, $parent )->unit );
	}

	public function test_service_flag_null_when_unset(): void {
		self::assertNull( $this->adapter()->fromProduct( $this->product( [ 'id' => 1 ] ) )->serviceFlag );
	}

	public function test_brand_from_core_taxonomy_when_present(): void {
		Functions\when( 'taxonomy_exists' )->justReturn( true );
		Functions\when( 'get_the_terms' )->justReturn( [ (object) [ 'name' => 'Podravka' ], (object) [ 'name' => 'Vegeta' ] ] );
		self::assertSame( 'Podravka, Vegeta', $this->adapter()->fromProduct( $this->product( [ 'id' => 1 ] ) )->brand );
	}

	public function test_brand_for_variation_is_read_from_parent(): void {
		Functions\when( 'taxonomy_exists' )->justReturn( true );
		Functions\expect( 'get_the_terms' )->once()->with( 1, 'product_brand' )->andReturn( [ (object) [ 'name' => 'Podravka' ] ] );
		$parent    = $this->product( [ 'id' => 1, 'type' => 'variable' ] );
		$variation = $this->product( [ 'id' => 2, 'type' => 'variation', 'parent_id' => 1 ] );
		self::assertSame( 'Podravka', $this->adapter()->fromProduct( $variation, $parent )->brand );
	}

	public function test_barcode_falls_back_to_known_meta_keys(): void {
		$p = $this->product( [ 'id' => 1, 'global_unique_id' => '', 'meta' => [ '_ean' => '111' ] ] );
		self::assertSame( '111', $this->adapter()->fromProduct( $p )->barcode );
	}
}
