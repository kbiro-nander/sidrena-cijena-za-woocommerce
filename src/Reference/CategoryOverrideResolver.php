<?php
/**
 * Resolves a category-level reference date override (first match in settings order wins).
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

namespace SidrenaCijena\Reference;

final class CategoryOverrideResolver {

	/** @var callable(int):int[] */
	private $ancestors;

	/**
	 * @param callable(int):int[] $ancestorsProvider Returns ancestor term IDs for a term ID.
	 */
	public function __construct( callable $ancestorsProvider ) {
		$this->ancestors = $ancestorsProvider;
	}

	public static function forWordPress(): self {
		return new self( static fn( int $termId ): array => array_map( 'intval', get_ancestors( $termId, 'product_cat', 'taxonomy' ) ) );
	}

	/**
	 * @param array<int,array{term_ids:int[],date:string}> $overrides   Overrides in settings order.
	 * @param int[]                                        $categoryIds Product categories.
	 * @return string|null Y-m-d date or null.
	 */
	public function resolve( array $overrides, array $categoryIds ): ?string {
		if ( [] === $overrides || [] === $categoryIds ) {
			return null;
		}
		$expanded = [];
		foreach ( $categoryIds as $id ) {
			$expanded[] = (int) $id;
			foreach ( ( $this->ancestors )( (int) $id ) as $ancestor ) {
				$expanded[] = (int) $ancestor;
			}
		}
		$expanded = array_unique( $expanded );
		foreach ( $overrides as $override ) {
			$ids = array_map( 'intval', (array) ( $override['term_ids'] ?? [] ) );
			if ( [] !== array_intersect( $ids, $expanded ) ) {
				return (string) $override['date'];
			}
		}
		return null;
	}
}
