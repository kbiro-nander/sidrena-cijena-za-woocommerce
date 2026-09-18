<?php
declare(strict_types=1);

namespace SidrenaCijena\Tests\Unit\Core;

use Brain\Monkey\Actions;
use Brain\Monkey\Filters;
use SidrenaCijena\Display\PriceBadge;
use SidrenaCijena\Display\PriceHtmlFilter;
use SidrenaCijena\Plugin;
use SidrenaCijena\Settings\Settings;
use SidrenaCijena\Tests\TestCase;

final class PluginTest extends TestCase {
	public function test_boot_wires_services_and_registers_frontend_hooks(): void {
		Filters\expectAdded( 'woocommerce_get_price_html' )->once();
		Filters\expectAdded( 'woocommerce_available_variation' )->once();
		Filters\expectAdded( 'woocommerce_cart_item_price' )->once();
		Actions\expectAdded( 'wp_enqueue_scripts' )->once();
		Actions\expectAdded( 'init' )->atLeast()->once();
		Actions\expectAdded( 'woocommerce_product_object_updated_props' )->once();
		Actions\expectAdded( 'scwc_price_changed' )->once();
		Actions\expectAdded( 'woocommerce_blocks_loaded' )->once();

		$plugin = new Plugin();
		$plugin->boot();

		self::assertInstanceOf( Settings::class, $plugin->get( Settings::class ) );
		self::assertInstanceOf( PriceBadge::class, $plugin->get( PriceBadge::class ) );
		self::assertInstanceOf( PriceHtmlFilter::class, $plugin->get( PriceHtmlFilter::class ) );
		self::assertArrayHasKey( 'sidrena_cijena', $GLOBALS['scwc_test_shortcodes'] );
	}

	public function test_boot_is_idempotent(): void {
		Filters\expectAdded( 'woocommerce_get_price_html' )->once();
		$plugin = new Plugin();
		$plugin->boot();
		$plugin->boot();
	}

	public function test_template_function_renders_badge_for_product(): void {
		$plugin = new Plugin();
		$plugin->boot();
		Plugin::setInstance( $plugin );
		$this->product( [ 'id' => 77, 'regular_price' => '10', 'meta' => [ '_scwc_ref_anchor_price' => '12' ] ] );
		$html = sidrena_cijena( 77 );
		self::assertStringContainsString( 'scwc-badge', $html );
		self::assertStringContainsString( '12,00', $html );
		self::assertSame( '', sidrena_cijena( 9999 ) );
		self::assertSame( 12.0, scwc_get_reference_price( 77 )?->amount );
		self::assertNull( scwc_get_reference_price( 9999 ) );
	}
}
