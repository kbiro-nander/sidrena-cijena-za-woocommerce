<?php
declare(strict_types=1);

namespace SidrenaCijena\Tests\Unit\Admin;

use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use SidrenaCijena\Admin\SettingsPage;
use SidrenaCijena\Display\PriceBadge;
use SidrenaCijena\Reference\ReferencePriceRegistry;
use SidrenaCijena\Settings\Defaults;
use SidrenaCijena\Settings\Sanitizer;
use SidrenaCijena\Settings\Settings;
use SidrenaCijena\Tests\TestCase;

final class SettingsPageTest extends TestCase {
	private Settings $settings;

	protected function setUp(): void {
		parent::setUp();
		$this->settings = ( new Settings( Defaults::all() ) )
			->with( 'outlet.address', 'Ilica 1, Zagreb' )
			->with( 'outlet.label', 'Moja Trgovina' )
			->with( 'price_list.formats', [ 'csv' ] )
			->with( 'price_list.external_cron_key', 'k3y' )
			->with( 'display.loop', false )
			->with( 'reference_prices.base.enabled', true )
			->with( 'reference_prices.base.date', '2026-11-17' )
			->with( 'reference_prices.anchor.auto_snapshot_at', '2026-09-30 23:55' )
			->with( 'reference_prices.anchor.category_overrides', [ [ 'term_ids' => [ 12, 15 ], 'date' => '2025-05-02' ], [ 'term_ids' => [ 3 ], 'date' => '2026-01-01' ] ] );
		unset( $GLOBALS['scwc_test_submenus'], $GLOBALS['scwc_test_registered_settings'], $GLOBALS['scwc_test_styles'], $GLOBALS['scwc_test_scripts'] );
	}

	protected function tearDown(): void {
		unset( $_GET['tab'] );
		parent::tearDown();
	}

	private function page( ?callable $status = null ): SettingsPage {
		$templates = dirname( __DIR__, 3 ) . '/templates';
		return new SettingsPage(
			$this->settings,
			ReferencePriceRegistry::fromSettings( $this->settings ),
			new Sanitizer(),
			new PriceBadge( $this->settings, $templates ),
			$status,
			static fn() => [ [ 'id' => 12, 'name' => 'Mlijeko' ], [ 'id' => 15, 'name' => 'Kruh' ], [ 'id' => 3, 'name' => 'Ostalo' ] ],
			$templates
		);
	}

	private function renderTab( string $tab, ?callable $status = null ): string {
		$_GET['tab'] = $tab;
		ob_start();
		try {
			$this->page( $status )->render();
		} finally {
			$out = (string) ob_get_clean();
		}
		return $out;
	}

	public function test_current_tab_is_validated_with_outlet_default(): void {
		$page = $this->page();
		self::assertSame( 'outlet', $page->currentTab( [] ) );
		self::assertSame( 'display', $page->currentTab( [ 'tab' => 'display' ] ) );
		self::assertSame( 'outlet', $page->currentTab( [ 'tab' => '../etc' ] ) );
		self::assertSame( 'outlet', $page->currentTab( [ 'tab' => [ 'x' ] ] ) );
	}

	public function test_register_adds_menu_init_and_enqueue_hooks(): void {
		Actions\expectAdded( 'admin_menu' )->once()->with( \Mockery::type( 'callable' ) );
		Actions\expectAdded( 'admin_init' )->once()->with( \Mockery::type( 'callable' ) );
		Actions\expectAdded( 'admin_enqueue_scripts' )->once()->with( \Mockery::type( 'callable' ) );
		$this->page()->register();
	}

	public function test_add_menu_registers_woocommerce_submenu_with_capability(): void {
		$this->page()->addMenu();
		$menu = $GLOBALS['scwc_test_submenus'][0];
		self::assertSame( 'woocommerce', $menu[0] );
		self::assertSame( 'Sidrena cijena', $menu[1] );
		self::assertSame( 'manage_woocommerce', $menu[3] );
		self::assertSame( SettingsPage::SLUG, $menu[4] );
		self::assertIsCallable( $menu[5] );
	}

	public function test_register_setting_uses_sanitizer_and_defaults(): void {
		$this->page()->registerSetting();
		$reg = $GLOBALS['scwc_test_registered_settings'][ Settings::OPTION ];
		self::assertSame( SettingsPage::GROUP, $reg['group'] );
		self::assertSame( 'array', $reg['args']['type'] );
		self::assertIsCallable( $reg['args']['sanitize_callback'] );
		self::assertSame( Defaults::all(), $reg['args']['default'] );
		self::assertSame( 30, ( $reg['args']['sanitize_callback'] )( [ 'price_list' => [ 'retention_days' => 2 ] ] )['price_list']['retention_days'] );
	}

	public function test_assets_enqueued_only_on_own_screen(): void {
		$this->page()->enqueue( 'edit.php' );
		self::assertArrayNotHasKey( 'scwc_test_styles', $GLOBALS );
		$this->page()->enqueue( 'woocommerce_page_scwc-settings' );
		self::assertStringContainsString( 'assets/css/admin.css', $GLOBALS['scwc_test_styles'][0][1] );
		self::assertStringContainsString( 'assets/js/admin-settings.js', $GLOBALS['scwc_test_scripts'][0][1] );
	}

	public function test_render_requires_manage_woocommerce(): void {
		Functions\when( 'current_user_can' )->justReturn( false );
		$this->expectException( \RuntimeException::class );
		$this->renderTab( 'outlet' );
	}

	public function test_outlet_tab_renders_form_tabs_fields_preview_and_urls(): void {
		$html = $this->renderTab( 'outlet' );
		self::assertStringContainsString( '<div class="wrap scwc-settings">', $html );
		self::assertStringContainsString( '<h1>Sidrena cijena', $html );
		self::assertStringContainsString( 'nav-tab-wrapper', $html );
		self::assertMatchesRegularExpression( '/page=scwc-settings(&amp;|&#038;|&)tab=display/', $html );
		self::assertStringContainsString( 'nav-tab nav-tab-active">Prodajni objekt', $html );
		self::assertStringContainsString( '<form method="post" action="https://example.hr/wp-admin/options.php">', $html );
		self::assertStringContainsString( 'name="option_page" value="scwc_settings_group"', $html );
		self::assertStringContainsString( 'name="scwc_settings[_tab]" value="outlet"', $html );
		self::assertStringContainsString( 'name="scwc_settings[outlet][address]" value="Ilica 1, Zagreb"', $html );
		self::assertStringContainsString( 'type="submit"', $html );
		self::assertStringContainsString( 'webshop_ilica-1-zagreb_moja-trgovina_1_YYYYMMDD_HHMMSS.xml', $html );
		self::assertStringContainsString( 'https://example.hr/cjenik/', $html );
		self::assertStringContainsString( 'https://example.hr/cjenik/latest.xml', $html );
		self::assertStringContainsString( 'https://example.hr/cjenik/latest.csv', $html );
		self::assertStringNotContainsString( '<select id="scwc_settings_display_position"', $html, 'other tabs rendered only as hidden inputs' );
		self::assertStringContainsString( '<input type="hidden" name="scwc_settings[display][position]" value="after" />', $html );
	}

	public function test_outlet_tab_lists_additional_outlets_with_their_own_file_names_and_urls(): void {
		$this->settings = $this->settings->with( 'outlets.additional', [ [ 'form' => 'poslovnica', 'address' => 'Vukovarska 5', 'label' => 'ZG-02', 'storage_number' => '3' ] ] );
		$html           = $this->renderTab( 'outlet' );
		self::assertStringContainsString( 'scwc-outlets', $html );
		self::assertStringContainsString( 'name="scwc_settings[outlets][additional][0][label]" value="ZG-02"', $html );
		self::assertStringContainsString( 'poslovnica_vukovarska-5_zg-02_3_YYYYMMDD_HHMMSS.xml', $html );
		self::assertStringContainsString( 'https://example.hr/cjenik/zg-02/latest.xml', $html );
		self::assertStringContainsString( 'https://example.hr/cjenik/moja-trgovina/latest.xml', $html );
		self::assertStringContainsString( 'https://example.hr/cjenik/latest.xml', $html );
	}

	public function test_hidden_inputs_carry_outlet_rows_to_other_tabs(): void {
		$this->settings = $this->settings->with( 'outlets.additional', [ [ 'form' => 'poslovnica', 'address' => 'Vukovarska 5', 'label' => 'ZG-02', 'storage_number' => '3' ] ] );
		$html           = $this->page()->hiddenInputsForOtherTabs( 'display' );
		self::assertStringContainsString( 'name="scwc_settings[outlets][additional][0][label]" value="ZG-02"', $html );
		self::assertStringNotContainsString( 'scwc_settings[outlets]', $this->page()->hiddenInputsForOtherTabs( 'outlet' ), 'outlets belong to the outlet tab' );
	}

	public function test_filename_preview_uses_na_for_empty_parts(): void {
		$this->settings = new Settings( Defaults::all() );
		self::assertSame( 'webshop_na_na_1_YYYYMMDD_HHMMSS.xml', $this->page()->filenamePreview() );
	}

	public function test_hidden_inputs_for_other_tabs_cover_nested_and_list_values(): void {
		$html = $this->page()->hiddenInputsForOtherTabs( 'display' );
		self::assertStringNotContainsString( '[display]', $html );
		self::assertStringContainsString( 'name="scwc_settings[outlet][address]" value="Ilica 1, Zagreb"', $html );
		self::assertStringContainsString( 'name="scwc_settings[price_list][formats][]" value="csv"', $html );
		self::assertStringNotContainsString( 'name="scwc_settings[price_list][formats][]" value="xml"', $html );
		self::assertStringContainsString( 'name="scwc_settings[price_list][enabled]" value="1"', $html );
		self::assertStringContainsString( 'name="scwc_settings[advanced][debug_log]" value="0"', $html );
		self::assertStringContainsString( 'name="scwc_settings[reference_prices][anchor][category_overrides][0][term_ids][]" value="12"', $html );
		self::assertStringContainsString( 'name="scwc_settings[reference_prices][anchor][category_overrides][0][term_ids][]" value="15"', $html );
		self::assertStringContainsString( 'name="scwc_settings[reference_prices][anchor][category_overrides][0][date]" value="2025-05-02"', $html );
		self::assertStringContainsString( 'name="scwc_settings[reference_prices][anchor][category_overrides][1][term_ids][]" value="3"', $html );
		self::assertStringContainsString( 'name="scwc_settings[reference_prices][anchor][auto_snapshot_at]" value="2026-09-30 23:55"', $html );
		self::assertStringContainsString( 'name="scwc_settings[reference_prices][base][enabled]" value="1"', $html );
	}

	/** Parse the rendered hidden inputs like a browser + PHP would, run them through the Sanitizer, and compare. */
	public function test_hidden_inputs_round_trip_through_sanitizer_without_losing_values(): void {
		foreach ( array_keys( SettingsPage::tabs() ) as $tab ) {
			$html = $this->page()->hiddenInputsForOtherTabs( $tab );
			preg_match_all( '/<input type="hidden" name="([^"]+)" value="([^"]*)" \/>/', $html, $m, PREG_SET_ORDER );
			$pairs = [];
			foreach ( $m as $input ) {
				$pairs[] = rawurlencode( html_entity_decode( $input[1], ENT_QUOTES ) ) . '=' . rawurlencode( html_entity_decode( $input[2], ENT_QUOTES ) );
			}
			parse_str( implode( '&', $pairs ), $post );
			$out     = ( new Sanitizer() )->sanitize( $post['scwc_settings'] );
			$section = SettingsPage::sectionForTab( $tab );
			$expect  = $this->settings->all();
			unset( $expect[ $section ], $out[ $section ] );
			self::assertSame( $expect, $out, "tab {$tab}" );
		}
	}

	public function test_reference_tab_has_type_blocks_overrides_and_snapshot_fields(): void {
		$html = $this->renderTab( 'reference' );
		self::assertStringContainsString( '<input type="hidden" name="scwc_settings[reference_prices][anchor][enabled]" value="1" />', $html );
		self::assertMatchesRegularExpression( '/name="scwc_settings\[reference_prices\]\[base\]\[enabled\]" value="1" checked/', $html );
		self::assertStringContainsString( 'Omogući', $html );
		self::assertStringContainsString( 'name="scwc_settings[reference_prices][anchor][date]" value="2026-09-10"', $html );
		self::assertStringContainsString( 'data-name="scwc_settings[reference_prices][anchor][category_overrides]"', $html );
		self::assertStringContainsString( 'data-name="scwc_settings[reference_prices][base][category_overrides]"', $html );
		self::assertStringContainsString( '<option value="15" selected="selected">Kruh</option>', $html );
		self::assertStringContainsString( 'Dodaj FMCG kategorije (2. 5. 2025.)', $html );
		self::assertStringContainsString( 'type="datetime-local" id="scwc_settings_reference_prices_anchor_auto_snapshot_at" name="scwc_settings[reference_prices][anchor][auto_snapshot_at]" value="2026-09-30T23:55"', $html );
		self::assertStringContainsString( 'name="scwc_settings[reference_prices][anchor][auto_snapshot_done]" value=""', $html );
		self::assertStringContainsString( '<h3>Sidrena (dodatna) cijena</h3>', $html );
		self::assertStringContainsString( 'name="scwc_settings[display][omnibus_label]" value="Najniža cijena u 30 dana prije sniženja"', $html, 'other tab carried as hidden' );
	}

	public function test_display_tab_renders_live_badge_preview_with_sample_data(): void {
		$html = $this->renderTab( 'display' );
		self::assertStringContainsString( 'scwc-preview', $html );
		self::assertStringContainsString( 'scwc-badge scwc-badge--single', $html );
		self::assertStringContainsString( '12,99', $html );
		self::assertStringContainsString( '14,99', $html );
		self::assertStringContainsString( 'Cijena na dan 10. 9. 2026.', $html );
		self::assertStringContainsString( '12,49', $html );
		self::assertStringContainsString( '&minus;23&nbsp;%', $html );
		self::assertStringContainsString( '<textarea id="scwc_settings_display_format"', $html );
	}

	public function test_price_list_tab_shows_status_box_and_action_links(): void {
		$html = $this->renderTab( 'price_list', static fn() => [ [ 'label' => 'Zadnje generiranje', 'value' => '<strong>danas</strong>' ] ] );
		self::assertStringContainsString( 'scwc-status', $html );
		self::assertStringContainsString( '<th>Zadnje generiranje</th><td><strong>danas</strong></td>', $html );
		self::assertMatchesRegularExpression( '/admin-post\.php\?action=scwc_generate_now(&amp;|&#038;|&)_wpnonce=nonce/', $html );
		self::assertStringContainsString( 'action=scwc_run_sweep', $html );
		self::assertStringContainsString( 'action=scwc_reschedule', $html );
		self::assertStringContainsString( 'Generiraj sada', $html );
		self::assertMatchesRegularExpression( '/name="scwc_settings\[price_list\]\[formats\]\[\]" value="csv" checked/', $html );
	}

	public function test_price_list_tab_without_status_provider_says_no_data(): void {
		$html = $this->renderTab( 'price_list' );
		self::assertStringContainsString( 'scwc-status', $html );
		self::assertStringContainsString( 'Nema podataka', $html );
	}

	public function test_history_and_advanced_tabs_render_their_fields(): void {
		self::assertStringContainsString( 'name="scwc_settings[history][sweep_time]" value="00:30"', $this->renderTab( 'history' ) );
		self::assertStringContainsString( 'name="scwc_settings[advanced][remove_data_on_uninstall]" value="1"', $this->renderTab( 'advanced' ) );
	}
}
