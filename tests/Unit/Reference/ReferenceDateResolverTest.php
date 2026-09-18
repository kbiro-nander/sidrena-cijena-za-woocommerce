<?php
declare(strict_types=1);

namespace SidrenaCijena\Tests\Unit\Reference;

use Brain\Monkey\Filters;
use SidrenaCijena\Product\ProductSnapshot;
use SidrenaCijena\Reference\CategoryOverrideResolver;
use SidrenaCijena\Reference\ReferenceDateResolver;
use SidrenaCijena\Reference\ReferencePriceType;
use SidrenaCijena\Tests\TestCase;

final class ReferenceDateResolverTest extends TestCase {
	private function type( array $overrides = [], ?string $default = '2026-09-10' ): ReferencePriceType {
		return new ReferencePriceType( 'anchor', 'Cijena na dan', $default, $overrides, true, 'SidrenaCijena', 'sidrena_cijena' );
	}

	private function resolver(): ReferenceDateResolver {
		return new ReferenceDateResolver( new CategoryOverrideResolver( fn() => [] ) );
	}

	private function snapshot( array $over = [] ): ProductSnapshot {
		return ProductSnapshot::fromArray( array_merge( [ 'id' => 10, 'categoryIds' => [ 12 ] ], $over ) );
	}

	public function test_falls_back_to_type_default_date(): void {
		self::assertSame( '2026-09-10', $this->resolver()->resolve( $this->type(), $this->snapshot() )?->format( 'Y-m-d' ) );
	}

	public function test_returns_null_when_type_has_no_date(): void {
		self::assertNull( $this->resolver()->resolve( $this->type( [], '' ), $this->snapshot() ) );
	}

	public function test_product_override_beats_category_override(): void {
		$type = $this->type( [ [ 'term_ids' => [ 12 ], 'date' => '2025-05-02' ] ] );
		$snap = $this->snapshot( [ 'meta' => [ '_scwc_ref_anchor_date' => '2026-01-15' ] ] );
		self::assertSame( '2026-01-15', $this->resolver()->resolve( $type, $snap )?->format( 'Y-m-d' ) );
	}

	public function test_category_override_beats_default(): void {
		$type = $this->type( [ [ 'term_ids' => [ 12 ], 'date' => '2025-05-02' ] ] );
		self::assertSame( '2025-05-02', $this->resolver()->resolve( $type, $this->snapshot() )?->format( 'Y-m-d' ) );
	}

	public function test_variation_uses_own_override_then_parent_override_then_parent_categories(): void {
		$type      = $this->type( [ [ 'term_ids' => [ 12 ], 'date' => '2025-05-02' ] ] );
		$parent    = $this->snapshot( [ 'id' => 1, 'type' => 'variable', 'categoryIds' => [ 12 ] ] );
		$variation = $this->snapshot( [ 'id' => 2, 'type' => 'variation', 'parentId' => 1, 'categoryIds' => [] ] );
		self::assertSame( '2025-05-02', $this->resolver()->resolve( $type, $variation, $parent )?->format( 'Y-m-d' ) );

		$parent2 = $this->snapshot( [ 'id' => 1, 'type' => 'variable', 'meta' => [ '_scwc_ref_anchor_date' => '2026-03-03' ] ] );
		self::assertSame( '2026-03-03', $this->resolver()->resolve( $type, $variation, $parent2 )?->format( 'Y-m-d' ) );

		$variation2 = $this->snapshot( [ 'id' => 2, 'type' => 'variation', 'parentId' => 1, 'meta' => [ '_scwc_ref_anchor_date' => '2026-04-04' ] ] );
		self::assertSame( '2026-04-04', $this->resolver()->resolve( $type, $variation2, $parent2 )?->format( 'Y-m-d' ) );
	}

	public function test_invalid_product_override_is_ignored(): void {
		$snap = $this->snapshot( [ 'meta' => [ '_scwc_ref_anchor_date' => 'garbage' ] ] );
		self::assertSame( '2026-09-10', $this->resolver()->resolve( $this->type(), $snap )?->format( 'Y-m-d' ) );
	}

	public function test_filter_can_override_resolved_date(): void {
		Filters\expectApplied( 'scwc_reference_date' )->once()->andReturn( new \DateTimeImmutable( '2024-01-01' ) );
		self::assertSame( '2024-01-01', $this->resolver()->resolve( $this->type(), $this->snapshot() )?->format( 'Y-m-d' ) );
	}
}
