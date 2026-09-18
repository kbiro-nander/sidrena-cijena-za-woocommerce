<?php
declare(strict_types=1);

namespace SidrenaCijena\Tests\Unit\Display;

use SidrenaCijena\Display\BadgeContext;
use SidrenaCijena\Display\BadgeData;
use SidrenaCijena\Display\BadgeDataFactory;
use SidrenaCijena\Display\PriceFormatter;
use SidrenaCijena\Product\BarcodeResolver;
use SidrenaCijena\Product\BrandResolver;
use SidrenaCijena\Product\ProductAdapter;
use SidrenaCijena\Reference\CategoryOverrideResolver;
use SidrenaCijena\Reference\ReferenceDateResolver;
use SidrenaCijena\Reference\ReferencePriceRegistry;
use SidrenaCijena\Reference\ReferencePriceRepository;
use SidrenaCijena\Settings\Defaults;
use SidrenaCijena\Settings\Settings;
use SidrenaCijena\Tests\TestCase;

final class BadgeDataFactoryTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\Functions\when( 'get_post_meta' )->alias( function ( int $id, string $key = '', bool $single = false ) {
			$p = \WC_Product::$registry[ $id ] ?? null;
			if ( ! $p ) {
				return $single ? '' : [];
			}
			return '' === $key ? $p->all_meta() : $p->get_meta( $key );
		} );
	}

	private function factory( ?Settings $settings = null ): BadgeDataFactory {
		$settings = $settings ?? new Settings( Defaults::all() );
		return new BadgeDataFactory(
			$settings,
			ReferencePriceRegistry::fromSettings( $settings ),
			new ProductAdapter( new BrandResolver(), new BarcodeResolver() ),
			new ReferencePriceRepository( new ReferenceDateResolver( new CategoryOverrideResolver( fn() => [] ) ) ),
			new PriceFormatter(),
			fn( int $id ) => \wc_get_product( $id ) ?: null,
		);
	}

	public function test_simple_product_with_anchor(): void {
		$p = $this->product( [ 'id' => 1, 'regular_price' => '12.99', 'meta' => [ '_scwc_ref_anchor_price' => '14.99' ] ] );
		$d = $this->factory()->forProduct( $p, BadgeContext::LOOP );
		self::assertInstanceOf( BadgeData::class, $d );
		self::assertCount( 1, $d->references );
		$ref = $d->references[0];
		self::assertSame( 'anchor', $ref->key );
		self::assertSame( 'Cijena na dan', $ref->label );
		self::assertSame( '10. 9. 2026.', $ref->dateFormatted );
		self::assertSame( '2026-09-10', $ref->dateIso );
		self::assertSame( 14.99, $ref->amount );
		self::assertStringContainsString( '14,99', $ref->amountHtml );
		self::assertFalse( $ref->isRange );
		self::assertNull( $d->omnibus );
		self::assertFalse( $d->isOnSale );
		self::assertSame( 12.99, $d->currentDisplay );
	}

	public function test_returns_null_when_nothing_to_show(): void {
		self::assertNull( $this->factory()->forProduct( $this->product( [ 'id' => 1, 'regular_price' => '5' ] ), BadgeContext::LOOP ) );
		self::assertNull( $this->factory()->forProduct( $this->product( [ 'id' => 1, 'regular_price' => '5', 'meta' => [ '_scwc_ref_anchor_price' => '4', '_scwc_ref_anchor_na' => '1' ] ] ), BadgeContext::LOOP ) );
	}

	public function test_on_sale_product_exposes_omnibus_reference_and_percent(): void {
		$p = $this->product( [ 'id' => 1, 'regular_price' => '20', 'sale_price' => '10', 'meta' => [ '_scwc_ref_anchor_price' => '18', '_scwc_omnibus_ref_price' => '15', '_scwc_omnibus_ref_source' => 'history' ] ] );
		$d = $this->factory()->forProduct( $p, BadgeContext::SINGLE );
		self::assertTrue( $d->isOnSale );
		self::assertSame( 20.0, $d->regularDisplay );
		self::assertSame( 10.0, $d->currentDisplay );
		self::assertSame( 15.0, $d->omnibus->amount );
		self::assertSame( 33, $d->omnibus->percent );
		self::assertSame( 'history', $d->omnibus->source );
	}

	public function test_omnibus_omitted_when_disabled_or_not_on_sale_or_missing(): void {
		$off = ( new Settings( Defaults::all() ) )->with( 'display.omnibus', false );
		$p   = $this->product( [ 'id' => 1, 'regular_price' => '20', 'sale_price' => '10', 'meta' => [ '_scwc_ref_anchor_price' => '18', '_scwc_omnibus_ref_price' => '15' ] ] );
		self::assertNull( $this->factory( $off )->forProduct( $p, BadgeContext::SINGLE )->omnibus );
		$notOnSale = $this->product( [ 'id' => 2, 'regular_price' => '20', 'meta' => [ '_scwc_ref_anchor_price' => '18', '_scwc_omnibus_ref_price' => '15' ] ] );
		self::assertNull( $this->factory()->forProduct( $notOnSale, BadgeContext::SINGLE )->omnibus );
		$noHistory = $this->product( [ 'id' => 3, 'regular_price' => '20', 'sale_price' => '10', 'meta' => [ '_scwc_ref_anchor_price' => '18' ] ] );
		self::assertNull( $this->factory()->forProduct( $noHistory, BadgeContext::SINGLE )->omnibus );
	}

	public function test_omnibus_alone_is_enough_to_render(): void {
		$p = $this->product( [ 'id' => 1, 'regular_price' => '20', 'sale_price' => '10', 'meta' => [ '_scwc_omnibus_ref_price' => '15' ] ] );
		$d = $this->factory()->forProduct( $p, BadgeContext::SINGLE );
		self::assertNotNull( $d );
		self::assertSame( [], $d->references );
	}

	public function test_variable_parent_shows_range_over_variations_with_reference(): void {
		$this->product( [ 'id' => 11, 'type' => 'variation', 'parent_id' => 10, 'regular_price' => '10', 'meta' => [ '_scwc_ref_anchor_price' => '9' ] ] );
		$this->product( [ 'id' => 12, 'type' => 'variation', 'parent_id' => 10, 'regular_price' => '12', 'meta' => [ '_scwc_ref_anchor_price' => '13' ] ] );
		$this->product( [ 'id' => 13, 'type' => 'variation', 'parent_id' => 10, 'regular_price' => '15' ] ); // no anchor
		$parent = $this->product( [ 'id' => 10, 'type' => 'variable', 'children' => [ 11, 12, 13 ] ] );
		$d      = $this->factory()->forProduct( $parent, BadgeContext::LOOP );
		$ref    = $d->references[0];
		self::assertTrue( $ref->isRange );
		self::assertSame( 9.0, $ref->amount );
		self::assertSame( 13.0, $ref->amountMax );
		self::assertStringContainsString( '9,00', $ref->amountHtml );
		self::assertStringContainsString( '13,00', $ref->amountHtml );
		self::assertNull( $d->omnibus );
	}

	public function test_variable_loop_none_setting_suppresses_badge_in_loop_only(): void {
		$this->product( [ 'id' => 51, 'type' => 'variation', 'parent_id' => 50, 'regular_price' => '10', 'meta' => [ '_scwc_ref_anchor_price' => '9' ] ] );
		$parent   = $this->product( [ 'id' => 50, 'type' => 'variable', 'children' => [ 51 ] ] );
		$settings = ( new Settings( Defaults::all() ) )->with( 'display.variable_loop', 'none' );
		self::assertNull( $this->factory( $settings )->forProduct( $parent, BadgeContext::LOOP ) );
		self::assertNotNull( $this->factory( $settings )->forProduct( $parent, BadgeContext::SINGLE ) );
	}

	public function test_variable_range_reads_child_meta_without_loading_child_products(): void {
		$this->product( [ 'id' => 61, 'type' => 'variation', 'parent_id' => 60, 'regular_price' => '10', 'meta' => [ '_scwc_ref_anchor_price' => '9' ] ] );
		$parent = $this->product( [ 'id' => 60, 'type' => 'variable', 'children' => [ 61 ] ] );
		$loads  = 0;
		$f      = new BadgeDataFactory(
			new Settings( Defaults::all() ),
			ReferencePriceRegistry::fromSettings( new Settings( Defaults::all() ) ),
			new ProductAdapter( new BrandResolver(), new BarcodeResolver() ),
			new ReferencePriceRepository( new ReferenceDateResolver( new CategoryOverrideResolver( fn() => [] ) ) ),
			new PriceFormatter(),
			function ( int $id ) use ( &$loads ) { $loads++; return \wc_get_product( $id ) ?: null; },
		);
		$d = $f->forProduct( $parent, BadgeContext::LOOP );
		self::assertSame( 9.0, $d->references[0]->amount );
		self::assertSame( 0, $loads, 'children resolved from post meta (primed cache), not full product objects' );
		$f->forProduct( $parent, BadgeContext::LOOP );
		self::assertSame( 0, $loads );
	}

	public function test_variable_parent_with_equal_min_max_is_not_a_range(): void {
		$this->product( [ 'id' => 21, 'type' => 'variation', 'parent_id' => 20, 'regular_price' => '10', 'meta' => [ '_scwc_ref_anchor_price' => '9' ] ] );
		$this->product( [ 'id' => 22, 'type' => 'variation', 'parent_id' => 20, 'regular_price' => '12', 'meta' => [ '_scwc_ref_anchor_price' => '9' ] ] );
		$parent = $this->product( [ 'id' => 20, 'type' => 'variable', 'children' => [ 21, 22 ] ] );
		self::assertFalse( $this->factory()->forProduct( $parent, BadgeContext::LOOP )->references[0]->isRange );
	}

	public function test_variable_parent_without_any_reference_yields_null(): void {
		$this->product( [ 'id' => 31, 'type' => 'variation', 'parent_id' => 30, 'regular_price' => '10' ] );
		$parent = $this->product( [ 'id' => 30, 'type' => 'variable', 'children' => [ 31 ] ] );
		self::assertNull( $this->factory()->forProduct( $parent, BadgeContext::LOOP ) );
	}

	public function test_variation_uses_parent_for_dates_and_inheritance(): void {
		$settings = ( new Settings( Defaults::all() ) )->with( 'reference_prices.variation_inherit_parent', true );
		$parent   = $this->product( [ 'id' => 40, 'type' => 'variable', 'meta' => [ '_scwc_ref_anchor_price' => '7', '_scwc_ref_anchor_date' => '2026-02-02' ] ] );
		$var      = $this->product( [ 'id' => 41, 'type' => 'variation', 'parent_id' => 40, 'regular_price' => '8' ] );
		$d        = $this->factory( $settings )->forProduct( $var, BadgeContext::SINGLE );
		self::assertSame( 7.0, $d->references[0]->amount );
		self::assertSame( '2. 2. 2026.', $d->references[0]->dateFormatted );
	}

	public function test_quantity_multiplies_amounts_for_checkout_totals(): void {
		$p = $this->product( [ 'id' => 1, 'regular_price' => '10', 'meta' => [ '_scwc_ref_anchor_price' => '12' ] ] );
		$d = $this->factory()->forProduct( $p, BadgeContext::CHECKOUT, 3 );
		self::assertSame( 36.0, $d->references[0]->amount );
	}

	public function test_second_reference_type_rendered_in_registry_order(): void {
		$settings = ( new Settings( Defaults::all() ) )->with( 'reference_prices.base.enabled', true )->with( 'reference_prices.base.date', '2026-11-17' );
		$p        = $this->product( [ 'id' => 1, 'regular_price' => '10', 'meta' => [ '_scwc_ref_anchor_price' => '12', '_scwc_ref_base_price' => '11' ] ] );
		$d        = $this->factory( $settings )->forProduct( $p, BadgeContext::SINGLE );
		self::assertSame( [ 'anchor', 'base' ], array_map( fn( $r ) => $r->key, $d->references ) );
		self::assertSame( '17. 11. 2026.', $d->references[1]->dateFormatted );
	}

	public function test_custom_date_format_setting(): void {
		$settings = ( new Settings( Defaults::all() ) )->with( 'display.date_format', 'd.m.Y' );
		$p        = $this->product( [ 'id' => 1, 'regular_price' => '10', 'meta' => [ '_scwc_ref_anchor_price' => '12' ] ] );
		self::assertSame( '10.09.2026', $this->factory( $settings )->forProduct( $p, BadgeContext::SINGLE )->references[0]->dateFormatted );
	}
}
