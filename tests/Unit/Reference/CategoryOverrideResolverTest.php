<?php
declare(strict_types=1);

namespace SidrenaCijena\Tests\Unit\Reference;

use SidrenaCijena\Reference\CategoryOverrideResolver;
use SidrenaCijena\Tests\TestCase;

final class CategoryOverrideResolverTest extends TestCase {
	private function resolver( array $ancestors = [] ): CategoryOverrideResolver {
		return new CategoryOverrideResolver( fn( int $termId ): array => $ancestors[ $termId ] ?? [] );
	}

	public function test_returns_null_without_overrides_or_categories(): void {
		self::assertNull( $this->resolver()->resolve( [], [ 1, 2 ] ) );
		self::assertNull( $this->resolver()->resolve( [ [ 'term_ids' => [ 9 ], 'date' => '2025-05-02' ] ], [] ) );
	}

	public function test_direct_category_match_returns_override_date(): void {
		$overrides = [ [ 'term_ids' => [ 12, 15 ], 'date' => '2025-05-02' ] ];
		self::assertSame( '2025-05-02', $this->resolver()->resolve( $overrides, [ 7, 15 ] ) );
		self::assertNull( $this->resolver()->resolve( $overrides, [ 7, 8 ] ) );
	}

	public function test_matches_ancestor_category(): void {
		// Category 40 ("Mlijeko") is a child of 12 ("Hrana").
		$resolver  = $this->resolver( [ 40 => [ 12 ] ] );
		$overrides = [ [ 'term_ids' => [ 12 ], 'date' => '2025-05-02' ] ];
		self::assertSame( '2025-05-02', $resolver->resolve( $overrides, [ 40 ] ) );
	}

	public function test_first_matching_override_in_settings_order_wins(): void {
		$overrides = [
			[ 'term_ids' => [ 5 ], 'date' => '2025-05-02' ],
			[ 'term_ids' => [ 5, 6 ], 'date' => '2026-01-01' ],
		];
		self::assertSame( '2025-05-02', $this->resolver()->resolve( $overrides, [ 6, 5 ] ) );
		self::assertSame( '2026-01-01', $this->resolver()->resolve( $overrides, [ 6 ] ) );
	}
}
