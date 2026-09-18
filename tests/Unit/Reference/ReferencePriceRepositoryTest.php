<?php
declare(strict_types=1);

namespace SidrenaCijena\Tests\Unit\Reference;

use Brain\Monkey\Functions;
use SidrenaCijena\Product\ProductSnapshot;
use SidrenaCijena\Reference\CategoryOverrideResolver;
use SidrenaCijena\Reference\ReferenceDateResolver;
use SidrenaCijena\Reference\ReferencePriceRepository;
use SidrenaCijena\Reference\ReferencePriceType;
use SidrenaCijena\Tests\TestCase;

final class ReferencePriceRepositoryTest extends TestCase {
	private ReferencePriceType $anchor;

	protected function setUp(): void {
		parent::setUp();
		$this->anchor = new ReferencePriceType( 'anchor', 'Cijena na dan', '2026-09-10', [], true, 'SidrenaCijena', 'sidrena_cijena' );
	}

	private function repo(): ReferencePriceRepository {
		return new ReferencePriceRepository( new ReferenceDateResolver( new CategoryOverrideResolver( fn() => [] ) ) );
	}

	public function test_reads_price_and_resolved_date_from_snapshot_meta(): void {
		$snap = ProductSnapshot::fromArray( [ 'id' => 1, 'meta' => [ '_scwc_ref_anchor_price' => '14.99', '_scwc_ref_anchor_source' => 'snapshot' ] ] );
		$ref  = $this->repo()->get( $this->anchor, $snap );
		self::assertTrue( $ref->isPresent() );
		self::assertSame( '14.99', $ref->amount );
		self::assertSame( '2026-09-10', $ref->date?->format( 'Y-m-d' ) );
		self::assertSame( 'snapshot', $ref->source );
	}

	public function test_missing_zero_or_na_are_not_present(): void {
		self::assertFalse( $this->repo()->get( $this->anchor, ProductSnapshot::fromArray( [ 'id' => 1 ] ) )->isPresent() );
		self::assertFalse( $this->repo()->get( $this->anchor, ProductSnapshot::fromArray( [ 'id' => 1, 'meta' => [ '_scwc_ref_anchor_price' => '0' ] ] ) )->isPresent() );
		$na = $this->repo()->get( $this->anchor, ProductSnapshot::fromArray( [ 'id' => 1, 'meta' => [ '_scwc_ref_anchor_price' => '9', '_scwc_ref_anchor_na' => '1' ] ] ) );
		self::assertFalse( $na->isPresent() );
		self::assertTrue( $na->na );
	}

	public function test_variation_inherits_parent_price_only_when_enabled(): void {
		$parent    = ProductSnapshot::fromArray( [ 'id' => 1, 'type' => 'variable', 'meta' => [ '_scwc_ref_anchor_price' => '20' ] ] );
		$variation = ProductSnapshot::fromArray( [ 'id' => 2, 'type' => 'variation', 'parentId' => 1 ] );
		self::assertFalse( $this->repo()->get( $this->anchor, $variation, $parent, false )->isPresent() );
		$inherited = $this->repo()->get( $this->anchor, $variation, $parent, true );
		self::assertSame( '20', $inherited->amount );
		self::assertSame( 'inherited', $inherited->source );
	}

	public function test_set_writes_meta_and_clears_na(): void {
		Functions\expect( 'update_post_meta' )->times( 3 )->withArgs( function ( $id, $key, $value ) {
			static $seen = [];
			$seen[ $key ] = $value;
			return 7 === $id && in_array( $key, [ '_scwc_ref_anchor_price', '_scwc_ref_anchor_date', '_scwc_ref_anchor_source' ], true );
		} );
		Functions\expect( 'delete_post_meta' )->once()->with( 7, '_scwc_ref_anchor_na' );
		$this->repo()->set( $this->anchor, 7, '12.50', '2026-09-10', 'import' );
	}

	public function test_set_without_date_removes_per_product_override(): void {
		Functions\expect( 'update_post_meta' )->times( 2 );
		Functions\expect( 'delete_post_meta' )->twice()->withArgs( fn( $id, $key ) => in_array( $key, [ '_scwc_ref_anchor_date', '_scwc_ref_anchor_na' ], true ) );
		$this->repo()->set( $this->anchor, 7, '12.50', null, 'snapshot' );
	}

	public function test_set_na_and_clear(): void {
		Functions\expect( 'update_post_meta' )->once()->with( 7, '_scwc_ref_anchor_na', '1' );
		Functions\expect( 'update_post_meta' )->once()->with( 7, '_scwc_ref_anchor_source', 'snapshot' );
		Functions\expect( 'delete_post_meta' )->once()->with( 7, '_scwc_ref_anchor_price' );
		$this->repo()->setNa( $this->anchor, 7, 'snapshot' );

		Functions\expect( 'delete_post_meta' )->times( 4 );
		$this->repo()->clear( $this->anchor, 7 );
	}
}
