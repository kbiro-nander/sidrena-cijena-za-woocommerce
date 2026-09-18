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
		Actions\expectAdded( 'template_redirect' )->once();
		Filters\expectAdded( 'pre_set_site_transient_update_plugins' )->once();
		Actions\expectAdded( 'scwc_generate_price_list' )->once();
		Actions\expectAdded( 'scwc_snapshot_completed' )->atLeast()->once();

		$plugin = new Plugin();
		$plugin->boot();

		self::assertInstanceOf( Settings::class, $plugin->get( Settings::class ) );
		self::assertInstanceOf( PriceBadge::class, $plugin->get( PriceBadge::class ) );
		self::assertInstanceOf( PriceHtmlFilter::class, $plugin->get( PriceHtmlFilter::class ) );
		self::assertArrayHasKey( 'sidrena_cijena', $GLOBALS['scwc_test_shortcodes'] );
	}

	public function test_admin_services_register_when_in_admin(): void {
		\Brain\Monkey\Functions\when( 'is_admin' )->justReturn( true );
		Actions\expectAdded( 'admin_menu' )->atLeast()->twice();
		Actions\expectAdded( 'woocommerce_product_options_general_product_data' )->atLeast()->once();
		Actions\expectAdded( 'woocommerce_variation_options_pricing' )->once();
		Actions\expectAdded( 'woocommerce_admin_process_product_object' )->once();
		Actions\expectAdded( 'wp_ajax_scwc_tool_step' )->once();
		Actions\expectAdded( 'admin_post_scwc_generate_now' )->once();
		Actions\expectAdded( 'admin_notices' )->atLeast()->once();
		( new Plugin() )->boot();
	}

	public function test_activation_schedules_jobs_and_seeds_history(): void {
		scwc_test_schedule_reset();
		( new Plugin() )->onActivate();
		$hooks = array_column( $GLOBALS['scwc_test_schedule']['single'], 'hook' );
		self::assertContains( 'scwc_generate_price_list', $hooks );
		self::assertSame( 'scwc_sweep_page', $GLOBALS['scwc_test_schedule']['async'][0]['hook'] );
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
