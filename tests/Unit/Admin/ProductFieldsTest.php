<?php
declare(strict_types=1);

namespace SidrenaCijena\Tests\Unit\Admin;

use Brain\Monkey\Actions;
use SidrenaCijena\Admin\ProductFields;
use SidrenaCijena\Reference\ReferencePriceRegistry;
use SidrenaCijena\Settings\Defaults;
use SidrenaCijena\Settings\Settings;
use SidrenaCijena\Tests\TestCase;

final class ProductFieldsTest extends TestCase {
	/** @param array<string,string> $meta */
	private function fields( array $meta = [], bool $base = false ): ProductFields {
		$settings = new Settings( Defaults::all() );
		if ( $base ) {
			$settings = $settings->with( 'reference_prices.base.enabled', true )->with( 'reference_prices.base.date', '2026-11-17' );
		}
		return new ProductFields(
			ReferencePriceRegistry::fromSettings( $settings ),
			$settings,
			static fn( int $id, string $key ) => $meta[ $key ] ?? '',
			static fn() => 42,
		);
	}

	private function capture( callable $fn ): string {
		ob_start();
		$fn();
		return (string) ob_get_clean();
	}

	public function test_register_adds_pricing_and_general_hooks(): void {
		Actions\expectAdded( 'woocommerce_product_options_general_product_data' )->once()->with( \Mockery::type( 'callable' ), 5 );
		Actions\expectAdded( 'woocommerce_product_options_general_product_data' )->once()->with( \Mockery::type( 'callable' ) );
		$this->fields()->register();
	}

	public function test_pricing_renders_anchor_inputs_with_label_date_and_values(): void {
		$html = $this->capture( fn() => $this->fields( [ '_scwc_ref_anchor_price' => '14.99', '_scwc_ref_anchor_date' => '2026-09-10', '_scwc_ref_anchor_source' => 'snapshot' ] )->renderPricing() );
		self::assertStringContainsString( 'class="options_group scwc-reference-prices"', $html );
		self::assertStringContainsString( 'name="scwc_ref_anchor_price" value="14.99"', $html );
		self::assertStringContainsString( 'data-type="price"', $html );
		self::assertStringContainsString( 'Cijena na dan (10. 9. 2026.) (€)', $html );
		self::assertStringContainsString( 'type="date" id="scwc_ref_anchor_date" name="scwc_ref_anchor_date" value="2026-09-10"', $html );
		self::assertStringContainsString( 'Ostavite prazno za zadani datum', $html );
		self::assertStringContainsString( 'name="scwc_ref_anchor_na" value="yes"', $html );
		self::assertStringContainsString( 'Nema referentne cijene (proizvod uveden nakon referentnog datuma)', $html );
		self::assertStringContainsString( 'Izvor', $html );
		self::assertStringContainsString( 'snapshot', $html );
		self::assertStringNotContainsString( 'scwc_ref_base_price', $html );
	}

	public function test_pricing_renders_base_type_when_enabled_and_na_checked(): void {
		$html = $this->capture( fn() => $this->fields( [ '_scwc_ref_base_na' => '1' ], true )->renderPricing() );
		self::assertStringContainsString( 'name="scwc_ref_base_price"', $html );
		self::assertStringContainsString( 'Bazna cijena na dan (17. 11. 2026.) (€)', $html );
		self::assertMatchesRegularExpression( '/name="scwc_ref_base_na" value="yes" checked="checked"/', $html );
		self::assertDoesNotMatchRegularExpression( '/name="scwc_ref_anchor_na" value="yes" checked/', $html );
	}

	public function test_pricing_label_without_date_when_type_has_no_default_date(): void {
		$settings = ( new Settings( Defaults::all() ) )->with( 'reference_prices.base.enabled', true );
		$fields   = new ProductFields( ReferencePriceRegistry::fromSettings( $settings ), $settings, static fn() => '', static fn() => 1 );
		$html     = $this->capture( fn() => $fields->renderPricing() );
		self::assertStringContainsString( '>Bazna cijena na dan (€)<', $html );
	}

	public function test_pricing_has_no_source_line_without_meta(): void {
		$html = $this->capture( fn() => $this->fields()->renderPricing() );
		self::assertStringNotContainsString( 'scwc-ref-source', $html );
	}

	public function test_general_renders_marker_and_all_fields(): void {
		$html = $this->capture( fn() => $this->fields( [ '_scwc_is_service' => 'yes', '_scwc_unit' => 'kg', '_scwc_unit_quantity' => '0.5', '_scwc_sale_name' => 'Akcija' ] )->renderGeneral() );
		self::assertStringContainsString( 'type="hidden" name="scwc_general_fields" value="1"', $html );
		self::assertMatchesRegularExpression( '/name="scwc_is_service" value="yes" checked="checked"/', $html );
		self::assertStringContainsString( 'Ovo je usluga', $html );
		self::assertStringContainsString( 'name="scwc_unit" value="kg"', $html );
		self::assertStringContainsString( 'Jedinica mjere (kg, l, m, kom)', $html );
		self::assertStringContainsString( 'name="scwc_unit_quantity" value="0.5" data-type="decimal"', $html );
		self::assertStringContainsString( 'Neto količina u jedinici mjere', $html );
		self::assertStringContainsString( 'name="scwc_sale_name" value="Akcija"', $html );
		self::assertStringContainsString( 'placeholder="Sniženje"', $html, 'default sale name from settings' );
		self::assertStringContainsString( 'Naziv posebnog oblika prodaje', $html );
		self::assertStringContainsString( 'name="scwc_exclude_from_price_list" value="yes"', $html );
		self::assertStringContainsString( 'Isključi iz cjenika', $html );
		self::assertStringContainsString( 'name="scwc_price_on_request" value="yes"', $html );
		self::assertStringContainsString( 'Cijena na upit (isključeno iz cjenika)', $html );
		self::assertStringNotContainsString( 'scwc-omnibus-panel', $html );
	}

	public function test_general_shows_omnibus_panel_only_with_meta(): void {
		$html = $this->capture( fn() => $this->fields( [ '_scwc_omnibus_ref_price' => '12.49', '_scwc_omnibus_sale_start' => '2026-09-01', '_scwc_omnibus_ref_source' => 'history' ] )->renderGeneral() );
		self::assertStringContainsString( 'scwc-omnibus-panel', $html );
		self::assertStringContainsString( 'Najniža cijena u 30 dana', $html );
		self::assertStringContainsString( '12.49', $html );
		self::assertStringContainsString( '2026-09-01', $html );
		self::assertStringContainsString( 'history', $html );
	}

	public function test_default_providers_read_global_post_and_post_meta(): void {
		$GLOBALS['post'] = (object) [ 'ID' => 7 ];
		\Brain\Monkey\Functions\expect( 'get_post_meta' )->atLeast()->once()->with( 7, \Mockery::type( 'string' ), true )->andReturn( '' );
		$settings = new Settings( Defaults::all() );
		$html     = $this->capture( fn() => ( new ProductFields( ReferencePriceRegistry::fromSettings( $settings ), $settings ) )->renderPricing() );
		self::assertStringContainsString( 'scwc_ref_anchor_price', $html );
		unset( $GLOBALS['post'] );
	}
}
