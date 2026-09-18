<?php
declare(strict_types=1);

namespace SidrenaCijena\Tests\Unit\Admin;

use Brain\Monkey\Actions;
use SidrenaCijena\Admin\ProductSave;
use SidrenaCijena\Reference\ReferencePriceRegistry;
use SidrenaCijena\Reference\ReferencePriceRepository;
use SidrenaCijena\Settings\Defaults;
use SidrenaCijena\Settings\Settings;
use SidrenaCijena\Tests\TestCase;

final class ProductSaveTest extends TestCase {
	private function registry( bool $base = false ): ReferencePriceRegistry {
		$settings = new Settings( Defaults::all() );
		if ( $base ) {
			$settings = $settings->with( 'reference_prices.base.enabled', true );
		}
		return ReferencePriceRegistry::fromSettings( $settings );
	}

	private function save( bool $base = false, ?array $input = null ): ProductSave {
		return new ProductSave( $this->registry( $base ), null === $input ? null : static fn() => $input );
	}

	public function test_extract_simple_product_reference_and_general_fields(): void {
		$post = [
			'scwc_ref_anchor_price'          => '12,50',
			'scwc_ref_anchor_date'           => '2026-09-10',
			'scwc_general_fields'            => '1',
			'scwc_is_service'                => 'yes',
			'scwc_unit'                      => ' kg ',
			'scwc_unit_quantity'             => '0,5',
			'scwc_sale_name'                 => 'Black Friday',
			'scwc_exclude_from_price_list'   => 'yes',
			'scwc_price_on_request'          => 'yes',
		];
		$out = $this->save()->extract( $post, null );
		self::assertSame( '12.50', $out['_scwc_ref_anchor_price'] );
		self::assertSame( '2026-09-10', $out['_scwc_ref_anchor_date'] );
		self::assertNull( $out['_scwc_ref_anchor_na'] );
		self::assertSame( 'yes', $out['_scwc_is_service'] );
		self::assertSame( 'kg', $out['_scwc_unit'] );
		self::assertSame( '0.5', $out['_scwc_unit_quantity'] );
		self::assertSame( 'Black Friday', $out['_scwc_sale_name'] );
		self::assertSame( 'yes', $out['_scwc_exclude_from_price_list'] );
		self::assertSame( 'yes', $out['_scwc_price_on_request'] );
		self::assertArrayNotHasKey( '_scwc_ref_base_price', $out, 'base type disabled' );
	}

	public function test_extract_includes_base_type_when_enabled(): void {
		$out = $this->save( true )->extract( [ 'scwc_ref_base_price' => '9' ], null );
		self::assertSame( '9', $out['_scwc_ref_base_price'] );
	}

	public function test_absent_checkboxes_with_general_marker_mean_no_or_delete(): void {
		$out = $this->save()->extract( [ 'scwc_general_fields' => '1' ], null );
		self::assertSame( 'no', $out['_scwc_is_service'] );
		self::assertNull( $out['_scwc_exclude_from_price_list'] );
		self::assertNull( $out['_scwc_price_on_request'] );
		self::assertNull( $out['_scwc_unit'] );
		self::assertNull( $out['_scwc_sale_name'] );
	}

	public function test_general_fields_untouched_without_marker(): void {
		$out = $this->save()->extract( [ 'scwc_ref_anchor_price' => '1' ], null );
		self::assertArrayNotHasKey( '_scwc_is_service', $out );
		self::assertArrayNotHasKey( '_scwc_unit', $out );
		self::assertArrayNotHasKey( '_scwc_exclude_from_price_list', $out );
	}

	public function test_na_checkbox_clears_price(): void {
		$out = $this->save()->extract( [ 'scwc_ref_anchor_price' => '12', 'scwc_ref_anchor_na' => 'yes' ], null );
		self::assertSame( '1', $out['_scwc_ref_anchor_na'] );
		self::assertNull( $out['_scwc_ref_anchor_price'] );
	}

	public function test_invalid_date_quantity_and_price_are_dropped(): void {
		$out = $this->save()->extract( [ 'scwc_ref_anchor_price' => 'abc', 'scwc_ref_anchor_date' => '10.09.2026', 'scwc_general_fields' => '1', 'scwc_unit_quantity' => '-2' ], null );
		self::assertNull( $out['_scwc_ref_anchor_price'] );
		self::assertNull( $out['_scwc_ref_anchor_date'] );
		self::assertNull( $out['_scwc_unit_quantity'] );
		self::assertNull( $this->save()->extract( [ 'scwc_general_fields' => '1', 'scwc_unit_quantity' => '0' ], null )['_scwc_unit_quantity'] );
	}

	public function test_extract_variation_uses_loop_index_and_skips_general_fields(): void {
		$post = [
			'scwc_ref_anchor_price' => [ 0 => '5', 1 => '7,25' ],
			'scwc_ref_anchor_date'  => [ 0 => '', 1 => '2025-05-02' ],
			'scwc_ref_anchor_na'    => [ 0 => 'yes' ],
			'scwc_general_fields'   => '1',
			'scwc_is_service'       => 'yes',
		];
		$out = $this->save()->extract( $post, 1 );
		self::assertSame( '7.25', $out['_scwc_ref_anchor_price'] );
		self::assertSame( '2025-05-02', $out['_scwc_ref_anchor_date'] );
		self::assertNull( $out['_scwc_ref_anchor_na'] );
		self::assertArrayNotHasKey( '_scwc_is_service', $out );

		$first = $this->save()->extract( $post, 0 );
		self::assertSame( '1', $first['_scwc_ref_anchor_na'] );
		self::assertNull( $first['_scwc_ref_anchor_price'] );
		self::assertNull( $first['_scwc_ref_anchor_date'] );

		$missing = $this->save()->extract( $post, 5 );
		self::assertNull( $missing['_scwc_ref_anchor_price'] );
	}

	public function test_apply_updates_and_deletes_meta_and_marks_manual_source_on_change(): void {
		$p = $this->product( [ 'id' => 1, 'meta' => [ '_scwc_ref_anchor_price' => '10', '_scwc_ref_anchor_source' => 'snapshot', '_scwc_unit' => 'kg' ] ] );
		$this->save()->apply( $p, [ '_scwc_ref_anchor_price' => '12', '_scwc_ref_anchor_date' => null, '_scwc_ref_anchor_na' => null, '_scwc_unit' => null, '_scwc_sale_name' => 'Akcija' ] );
		self::assertSame( '12', $p->get_meta( '_scwc_ref_anchor_price' ) );
		self::assertSame( ReferencePriceRepository::SOURCE_MANUAL, $p->get_meta( '_scwc_ref_anchor_source' ) );
		self::assertFalse( $p->meta_exists( '_scwc_unit' ) );
		self::assertSame( 'Akcija', $p->get_meta( '_scwc_sale_name' ) );
	}

	public function test_apply_keeps_source_when_price_unchanged(): void {
		$p = $this->product( [ 'id' => 1, 'meta' => [ '_scwc_ref_anchor_price' => '12', '_scwc_ref_anchor_source' => 'snapshot' ] ] );
		$this->save()->apply( $p, [ '_scwc_ref_anchor_price' => '12', '_scwc_ref_anchor_na' => null ] );
		self::assertSame( 'snapshot', $p->get_meta( '_scwc_ref_anchor_source' ) );
	}

	public function test_apply_clearing_price_without_na_removes_source_and_date(): void {
		$p = $this->product( [ 'id' => 1, 'meta' => [ '_scwc_ref_anchor_price' => '12', '_scwc_ref_anchor_source' => 'import', '_scwc_ref_anchor_date' => '2026-09-10' ] ] );
		$this->save()->apply( $p, [ '_scwc_ref_anchor_price' => null, '_scwc_ref_anchor_date' => '2026-09-10', '_scwc_ref_anchor_na' => null ] );
		self::assertFalse( $p->meta_exists( '_scwc_ref_anchor_price' ) );
		self::assertFalse( $p->meta_exists( '_scwc_ref_anchor_source' ) );
		self::assertFalse( $p->meta_exists( '_scwc_ref_anchor_date' ) );
	}

	public function test_apply_na_sets_manual_source_and_removes_price(): void {
		$p = $this->product( [ 'id' => 1, 'meta' => [ '_scwc_ref_anchor_price' => '12', '_scwc_ref_anchor_source' => 'import' ] ] );
		$this->save()->apply( $p, [ '_scwc_ref_anchor_price' => null, '_scwc_ref_anchor_na' => '1' ] );
		self::assertFalse( $p->meta_exists( '_scwc_ref_anchor_price' ) );
		self::assertSame( '1', $p->get_meta( '_scwc_ref_anchor_na' ) );
		self::assertSame( ReferencePriceRepository::SOURCE_MANUAL, $p->get_meta( '_scwc_ref_anchor_source' ) );
	}

	public function test_register_adds_both_woocommerce_hooks(): void {
		Actions\expectAdded( 'woocommerce_admin_process_product_object' )->once()->with( \Mockery::type( 'callable' ), 10, 1 );
		Actions\expectAdded( 'woocommerce_admin_process_variation_object' )->once()->with( \Mockery::type( 'callable' ), 10, 2 );
		$this->save()->register();
	}

	public function test_on_product_and_on_variation_read_injected_input(): void {
		$save = $this->save( false, [ 'scwc_ref_anchor_price' => [ 2 => '8' ], 'scwc_general_fields' => '1', 'scwc_unit' => 'l' ] );
		$v    = $this->product( [ 'id' => 3, 'type' => 'variation' ] );
		$save->onVariation( $v, 2 );
		self::assertSame( '8', $v->get_meta( '_scwc_ref_anchor_price' ) );
		self::assertSame( 'manual', $v->get_meta( '_scwc_ref_anchor_source' ) );
		self::assertFalse( $v->meta_exists( '_scwc_unit' ) );

		$save = $this->save( false, [ 'scwc_ref_anchor_price' => '8', 'scwc_general_fields' => '1', 'scwc_unit' => 'l' ] );
		$p    = $this->product( [ 'id' => 4 ] );
		$save->onProduct( $p );
		self::assertSame( '8', $p->get_meta( '_scwc_ref_anchor_price' ) );
		self::assertSame( 'l', $p->get_meta( '_scwc_unit' ) );
		self::assertSame( 'no', $p->get_meta( '_scwc_is_service' ) );
	}
}
