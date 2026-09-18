<?php
declare(strict_types=1);

namespace SidrenaCijena\Tests\Unit\Admin;

use Brain\Monkey\Actions;
use SidrenaCijena\Admin\VariationFields;
use SidrenaCijena\Reference\ReferencePriceRegistry;
use SidrenaCijena\Settings\Defaults;
use SidrenaCijena\Settings\Settings;
use SidrenaCijena\Tests\TestCase;

final class VariationFieldsTest extends TestCase {
	/** @param array<int,array<string,string>> $meta */
	private function fields( array $meta = [], bool $base = false ): VariationFields {
		$settings = new Settings( Defaults::all() );
		if ( $base ) {
			$settings = $settings->with( 'reference_prices.base.enabled', true );
		}
		return new VariationFields( ReferencePriceRegistry::fromSettings( $settings ), static fn( int $id, string $key ) => $meta[ $id ][ $key ] ?? '' );
	}

	private function capture( callable $fn ): string {
		ob_start();
		$fn();
		return (string) ob_get_clean();
	}

	public function test_register_adds_variation_pricing_hook_with_three_args(): void {
		Actions\expectAdded( 'woocommerce_variation_options_pricing' )->once()->with( \Mockery::type( 'callable' ), 10, 3 );
		$this->fields()->register();
	}

	public function test_renders_loop_indexed_names_and_prefilled_values(): void {
		$variation = (object) [ 'ID' => 55 ];
		$html      = $this->capture( fn() => $this->fields( [ 55 => [ '_scwc_ref_anchor_price' => '9.90', '_scwc_ref_anchor_date' => '2025-05-02', '_scwc_ref_anchor_na' => '1' ] ] )->render( 3, [], $variation ) );
		self::assertStringContainsString( 'class="form-row form-row-full scwc-variation-reference"', $html );
		self::assertStringContainsString( 'id="scwc_ref_anchor_price_3" name="scwc_ref_anchor_price[3]" value="9.90"', $html );
		self::assertStringContainsString( 'id="scwc_ref_anchor_date_3" name="scwc_ref_anchor_date[3]" value="2025-05-02"', $html );
		self::assertMatchesRegularExpression( '/id="scwc_ref_anchor_na_3" name="scwc_ref_anchor_na\[3\]" value="yes" checked="checked"/', $html );
		self::assertStringContainsString( 'form-row form-row-first', $html );
		self::assertStringContainsString( 'Cijena na dan (10. 9. 2026.) (€)', $html );
		self::assertStringNotContainsString( 'scwc_ref_base_price', $html );
	}

	public function test_renders_base_type_when_enabled_and_empty_values(): void {
		$html = $this->capture( fn() => $this->fields( [], true )->render( 0, [], (object) [ 'ID' => 9 ] ) );
		self::assertStringContainsString( 'name="scwc_ref_base_price[0]" value=""', $html );
		self::assertDoesNotMatchRegularExpression( '/scwc_ref_anchor_na\[0\]" value="yes" checked/', $html );
	}
}
