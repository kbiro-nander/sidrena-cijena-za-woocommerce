<?php
declare(strict_types=1);

namespace SidrenaCijena\Tests\Unit\Display;

use Brain\Monkey\Actions;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use SidrenaCijena\Display\BadgeContext;
use SidrenaCijena\Display\BadgeDataFactory;
use SidrenaCijena\Display\CartFilters;
use SidrenaCijena\Display\PriceBadge;
use SidrenaCijena\Display\PriceFormatter;
use SidrenaCijena\Display\PriceHtmlComposer;
use SidrenaCijena\Display\PriceHtmlFilter;
use SidrenaCijena\Display\RenderGuard;
use SidrenaCijena\Display\RequestContext;
use SidrenaCijena\Display\Shortcode;
use SidrenaCijena\Display\VariationJsonFilter;
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

final class HooksTest extends TestCase {
	private Settings $settings;
	private RequestContext $ctx;

	protected function setUp(): void {
		parent::setUp();
		$this->settings = new Settings( Defaults::all() );
		$this->ctx      = new RequestContext( null, false, false );
	}

	private function factory(): BadgeDataFactory {
		return new BadgeDataFactory(
			$this->settings,
			ReferencePriceRegistry::fromSettings( $this->settings ),
			new ProductAdapter( new BrandResolver(), new BarcodeResolver() ),
			new ReferencePriceRepository( new ReferenceDateResolver( new CategoryOverrideResolver( fn() => [] ) ) ),
			new PriceFormatter(),
			fn( int $id ) => \wc_get_product( $id ) ?: null,
		);
	}

	private function composer(): PriceHtmlComposer {
		return new PriceHtmlComposer( $this->settings, new PriceBadge( $this->settings, dirname( __DIR__, 3 ) . '/templates' ) );
	}

	private function guard(): RenderGuard {
		return new RenderGuard( $this->settings, fn() => $this->ctx );
	}

	public function test_price_html_filter_registers_at_priority_100(): void {
		Filters\expectAdded( 'woocommerce_get_price_html' )->once()->with( \Mockery::type( 'callable' ), 100, 2 );
		( new PriceHtmlFilter( $this->guard(), $this->factory(), $this->composer() ) )->register();
	}

	public function test_price_html_filter_appends_badge_in_loop_context(): void {
		$p    = $this->product( [ 'id' => 1, 'regular_price' => '10', 'meta' => [ '_scwc_ref_anchor_price' => '12' ] ] );
		$html = ( new PriceHtmlFilter( $this->guard(), $this->factory(), $this->composer() ) )->filter( '<span>10</span>', $p );
		self::assertStringContainsString( 'scwc-badge--loop', $html );
		self::assertStringContainsString( '12,00', $html );
	}

	public function test_price_html_filter_uses_single_context_on_the_queried_product(): void {
		Functions\when( 'is_product' )->justReturn( true );
		Functions\when( 'in_the_loop' )->justReturn( true );
		Functions\when( 'get_queried_object_id' )->justReturn( 1 );
		$main    = $this->product( [ 'id' => 1, 'regular_price' => '10', 'meta' => [ '_scwc_ref_anchor_price' => '12' ] ] );
		$related = $this->product( [ 'id' => 2, 'regular_price' => '10', 'meta' => [ '_scwc_ref_anchor_price' => '12' ] ] );
		$filter  = new PriceHtmlFilter( $this->guard(), $this->factory(), $this->composer() );
		self::assertStringContainsString( 'scwc-badge--single', $filter->filter( 'x', $main ) );
		self::assertStringContainsString( 'scwc-badge--loop', $filter->filter( 'x', $related ) );
		$variation = $this->product( [ 'id' => 3, 'type' => 'variation', 'parent_id' => 1, 'regular_price' => '10', 'meta' => [ '_scwc_ref_anchor_price' => '12' ] ] );
		self::assertStringContainsString( 'scwc-badge--single', $filter->filter( 'x', $variation ) );
	}

	public function test_price_html_filter_leaves_html_untouched_when_guard_blocks_or_no_data(): void {
		$filter = new PriceHtmlFilter( $this->guard(), $this->factory(), $this->composer() );
		self::assertSame( 'x', $filter->filter( 'x', $this->product( [ 'id' => 1, 'regular_price' => '10' ] ) ) );
		$this->ctx = new RequestContext( '/wc/v3/products', false, false );
		self::assertSame( 'x', $filter->filter( 'x', $this->product( [ 'id' => 2, 'regular_price' => '10', 'meta' => [ '_scwc_ref_anchor_price' => '12' ] ] ) ) );
	}

	public function test_variation_json_filter_adds_scwc_key(): void {
		Filters\expectAdded( 'woocommerce_available_variation' )->once()->with( \Mockery::type( 'callable' ), 10, 3 );
		$f = new VariationJsonFilter( $this->guard(), $this->factory() );
		$f->register();
		$parent    = $this->product( [ 'id' => 1, 'type' => 'variable' ] );
		$variation = $this->product( [ 'id' => 2, 'type' => 'variation', 'parent_id' => 1, 'regular_price' => '10', 'sale_price' => '8', 'meta' => [ '_scwc_ref_anchor_price' => '12', '_scwc_omnibus_ref_price' => '9' ] ] );
		$out       = $f->filter( [ 'variation_id' => 2 ], $parent, $variation );
		self::assertSame( 'anchor', $out['scwc']['references'][0]['key'] );
		self::assertSame( 12.0, $out['scwc']['references'][0]['amount'] );
		self::assertSame( '2026-09-10', $out['scwc']['references'][0]['date'] );
		self::assertSame( 9.0, $out['scwc']['lowest_30_days']['amount'] );
		self::assertSame( 11, $out['scwc']['lowest_30_days']['percent'] );
		$none = $f->filter( [ 'variation_id' => 3 ], $parent, $this->product( [ 'id' => 3, 'type' => 'variation', 'parent_id' => 1, 'regular_price' => '10' ] ) );
		self::assertNull( $none['scwc'] );
	}

	public function test_cart_filters_register_expected_hooks(): void {
		Filters\expectAdded( 'woocommerce_cart_item_price' )->once()->with( \Mockery::type( 'callable' ), 10, 3 );
		Filters\expectAdded( 'woocommerce_cart_item_subtotal' )->once()->with( \Mockery::type( 'callable' ), 10, 3 );
		Filters\expectAdded( 'woocommerce_get_item_data' )->once()->with( \Mockery::type( 'callable' ), 10, 2 );
		( new CartFilters( $this->settings, $this->guard(), $this->factory(), $this->composer(), fn() => $this->ctx ) )->register();
	}

	public function test_cart_item_price_gets_compact_badge(): void {
		$p    = $this->product( [ 'id' => 1, 'regular_price' => '10', 'meta' => [ '_scwc_ref_anchor_price' => '12' ] ] );
		$cf   = new CartFilters( $this->settings, $this->guard(), $this->factory(), $this->composer(), fn() => $this->ctx );
		$html = $cf->itemPrice( '<span>10</span>', [ 'data' => $p, 'quantity' => 2 ], 'key' );
		self::assertStringContainsString( 'scwc-badge--cart', $html );
		self::assertStringContainsString( '12,00', $html );
		self::assertStringNotContainsString( 'Cijena na dan', $html, 'compact format in cart' );
	}

	public function test_cart_item_price_in_mini_cart_uses_mini_cart_context_and_can_be_disabled(): void {
		$p  = $this->product( [ 'id' => 1, 'regular_price' => '10', 'meta' => [ '_scwc_ref_anchor_price' => '12' ] ] );
		$cf = new CartFilters( $this->settings, $this->guard(), $this->factory(), $this->composer(), fn() => $this->ctx );
		do_action( 'woocommerce_before_mini_cart' );
		self::assertStringContainsString( 'scwc-badge--mini_cart', $cf->itemPrice( '<span>10</span>', [ 'data' => $p, 'quantity' => 1 ], 'key' ), 'on by default: the mini-cart shows retail prices' );
		$this->settings = $this->settings->with( 'display.mini_cart', false );
		$cf = new CartFilters( $this->settings, $this->guard(), $this->factory(), $this->composer(), fn() => $this->ctx );
		self::assertSame( '<span>10</span>', $cf->itemPrice( '<span>10</span>', [ 'data' => $p, 'quantity' => 1 ], 'key' ) );
	}

	public function test_checkout_subtotal_modes(): void {
		Functions\when( 'is_checkout' )->justReturn( true );
		$p  = $this->product( [ 'id' => 1, 'regular_price' => '10', 'meta' => [ '_scwc_ref_anchor_price' => '12' ] ] );
		$cf = new CartFilters( $this->settings, $this->guard(), $this->factory(), $this->composer(), fn() => $this->ctx );
		$unit = $cf->itemSubtotal( '<span>30</span>', [ 'data' => $p, 'quantity' => 3 ], 'k' );
		self::assertStringContainsString( '12,00', $unit );
		self::assertStringContainsString( 'scwc-badge--checkout', $unit );

		$this->settings = $this->settings->with( 'display.checkout', 'total' );
		$cf = new CartFilters( $this->settings, $this->guard(), $this->factory(), $this->composer(), fn() => $this->ctx );
		self::assertStringContainsString( '36,00', $cf->itemSubtotal( '<span>30</span>', [ 'data' => $p, 'quantity' => 3 ], 'k' ) );

		$this->settings = $this->settings->with( 'display.checkout', 'none' );
		$cf = new CartFilters( $this->settings, $this->guard(), $this->factory(), $this->composer(), fn() => $this->ctx );
		self::assertSame( '<span>30</span>', $cf->itemSubtotal( '<span>30</span>', [ 'data' => $p, 'quantity' => 3 ], 'k' ) );
	}

	public function test_subtotal_untouched_on_cart_page(): void {
		Functions\when( 'is_checkout' )->justReturn( false );
		$p  = $this->product( [ 'id' => 1, 'regular_price' => '10', 'meta' => [ '_scwc_ref_anchor_price' => '12' ] ] );
		$cf = new CartFilters( $this->settings, $this->guard(), $this->factory(), $this->composer(), fn() => $this->ctx );
		self::assertSame( '<span>30</span>', $cf->itemSubtotal( '<span>30</span>', [ 'data' => $p, 'quantity' => 3 ], 'k' ) );
	}

	public function test_item_data_rows_only_added_for_store_api_requests(): void {
		$p  = $this->product( [ 'id' => 1, 'regular_price' => '10', 'sale_price' => '8', 'meta' => [ '_scwc_ref_anchor_price' => '12', '_scwc_omnibus_ref_price' => '9' ] ] );
		$cf = new CartFilters( $this->settings, $this->guard(), $this->factory(), $this->composer(), fn() => $this->ctx );
		self::assertSame( [], $cf->itemData( [], [ 'data' => $p, 'quantity' => 1 ] ), 'classic cart already shows the badge' );
		$this->ctx = new RequestContext( '/wc/store/v1/cart', false, false );
		$rows      = $cf->itemData( [ [ 'key' => 'Boja', 'value' => 'crna' ] ], [ 'data' => $p, 'quantity' => 1 ] );
		self::assertCount( 3, $rows );
		self::assertSame( 'Najniža cijena u 30 dana prije sniženja', $rows[1]['key'] );
		self::assertSame( '9,00 €', $rows[1]['value'] );
		self::assertSame( 'Cijena na dan 10. 9. 2026.', $rows[2]['key'] );
		self::assertSame( '12,00 €', $rows[2]['value'] );
	}

	public function test_item_data_rows_added_on_block_cart_page_render(): void {
		Functions\when( 'is_cart' )->justReturn( true );
		Functions\when( 'has_block' )->justReturn( true );
		Functions\when( 'get_queried_object_id' )->justReturn( 9 );
		$p  = $this->product( [ 'id' => 1, 'regular_price' => '10', 'meta' => [ '_scwc_ref_anchor_price' => '12' ] ] );
		$cf = new CartFilters( $this->settings, $this->guard(), $this->factory(), $this->composer(), fn() => $this->ctx );
		self::assertCount( 1, $cf->itemData( [], [ 'data' => $p, 'quantity' => 1 ] ), 'blocks hydrate via internal REST during page render' );
		Functions\when( 'has_block' )->justReturn( false );
		self::assertSame( [], $cf->itemData( [], [ 'data' => $p, 'quantity' => 1 ] ), 'classic cart page keeps the badge only' );
	}

	public function test_shortcode_renders_badge_for_given_product(): void {
		$this->product( [ 'id' => 5, 'regular_price' => '10', 'meta' => [ '_scwc_ref_anchor_price' => '12' ] ] );
		$sc = new Shortcode( $this->guard(), $this->factory(), new PriceBadge( $this->settings, dirname( __DIR__, 3 ) . '/templates' ), fn( int $id ) => \wc_get_product( $id ) ?: null );
		$sc->register();
		self::assertArrayHasKey( 'sidrena_cijena', $GLOBALS['scwc_test_shortcodes'] );
		$html = $sc->render( [ 'id' => '5' ] );
		self::assertStringContainsString( 'scwc-badge--shortcode', $html );
		self::assertSame( '', $sc->render( [ 'id' => '999' ] ) );
	}

	public function test_shortcode_can_limit_to_one_reference_key(): void {
		$this->settings = $this->settings->with( 'reference_prices.base.enabled', true )->with( 'reference_prices.base.date', '2026-11-17' );
		$this->product( [ 'id' => 6, 'regular_price' => '10', 'meta' => [ '_scwc_ref_anchor_price' => '12', '_scwc_ref_base_price' => '11' ] ] );
		$sc   = new Shortcode( $this->guard(), $this->factory(), new PriceBadge( $this->settings, dirname( __DIR__, 3 ) . '/templates' ), fn( int $id ) => \wc_get_product( $id ) ?: null );
		$html = $sc->render( [ 'id' => '6', 'key' => 'base' ] );
		self::assertStringContainsString( 'data-scwc-ref="base"', $html );
		self::assertStringNotContainsString( 'data-scwc-ref="anchor"', $html );
	}
}
