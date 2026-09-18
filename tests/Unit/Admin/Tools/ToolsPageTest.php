<?php
declare(strict_types=1);

namespace SidrenaCijena\Tests\Unit\Admin\Tools;

use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use SidrenaCijena\Admin\Tools\ToolsPage;
use SidrenaCijena\Reference\ReferencePriceRegistry;
use SidrenaCijena\Settings\Defaults;
use SidrenaCijena\Settings\Settings;
use SidrenaCijena\Tests\TestCase;

final class ToolsPageTest extends TestCase {
	private Settings $settings;

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['scwc_test_submenus']  = [];
		$GLOBALS['scwc_test_scripts']   = [];
		$GLOBALS['scwc_test_localized'] = [];
		$this->settings = new Settings( Defaults::all() );
	}

	private function page(): ToolsPage {
		return new ToolsPage( $this->settings, ReferencePriceRegistry::fromSettings( $this->settings ), dirname( __DIR__, 4 ) . '/templates' );
	}

	public function test_register_hooks_menu_and_assets(): void {
		$page = $this->page();
		Actions\expectAdded( 'admin_menu' )->once()->with( [ $page, 'addMenu' ] );
		Actions\expectAdded( 'admin_enqueue_scripts' )->once()->with( [ $page, 'enqueue' ] );
		$page->register();
	}

	public function test_add_menu_registers_woocommerce_submenu(): void {
		$page = $this->page();
		$page->addMenu();
		$menu = $GLOBALS['scwc_test_submenus'][0];
		self::assertSame( 'woocommerce', $menu[0] );
		self::assertStringContainsString( 'Alati', $menu[1] );
		self::assertSame( 'manage_woocommerce', $menu[3] );
		self::assertSame( ToolsPage::SLUG, $menu[4] );
		self::assertSame( [ $page, 'render' ], $menu[5] );
	}

	public function test_enqueue_only_on_own_screen(): void {
		$page = $this->page();
		$page->enqueue( 'edit.php' );
		self::assertSame( [], $GLOBALS['scwc_test_scripts'] );

		$page->enqueue( 'woocommerce_page_scwc-tools' );
		self::assertSame( 'scwc-admin-tools', $GLOBALS['scwc_test_scripts'][0][0] );
		self::assertStringEndsWith( 'assets/js/admin-tools.js', $GLOBALS['scwc_test_scripts'][0][1] );
		$localized = $GLOBALS['scwc_test_localized'][0];
		self::assertSame( 'scwc-admin-tools', $localized[0] );
		self::assertSame( 'scwcTools', $localized[1] );
		self::assertSame( 'https://example.hr/wp-admin/admin-ajax.php', $localized[2]['ajaxUrl'] );
		self::assertSame( 'nonce', $localized[2]['nonce'] );
		self::assertIsArray( $localized[2]['i18n'] );
	}

	public function test_render_outputs_three_sections(): void {
		ob_start();
		$this->page()->render();
		$html = (string) ob_get_clean();
		self::assertStringContainsString( 'Snimi trenutne redovne cijene', $html );
		self::assertStringContainsString( 'Uvoz iz CSV-a', $html );
		self::assertStringContainsString( 'Izvoz referentnih cijena', $html );
		self::assertStringContainsString( '<option value="anchor"', $html );
		self::assertStringContainsString( 'value="2026-09-10"', $html );
		self::assertStringContainsString( 'data:text/csv', $html );
		self::assertStringContainsString( '<progress', $html );
	}

	public function test_render_requires_capability(): void {
		Functions\when( 'current_user_can' )->justReturn( false );
		$this->expectException( \RuntimeException::class );
		$this->page()->render();
	}
}
