<?php
/**
 * Resolves the reference date for a product and type:
 * product/variation override → parent override → category override → type default → filter.
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

namespace SidrenaCijena\Reference;

use DateTimeImmutable;
use SidrenaCijena\Product\ProductSnapshot;
use SidrenaCijena\Support\DateFormat;

final class ReferenceDateResolver {

	public function __construct( private readonly CategoryOverrideResolver $categories ) {}

	public function resolve( ReferencePriceType $type, ProductSnapshot $product, ?ProductSnapshot $parent = null ): ?DateTimeImmutable {
		$date = DateFormat::parseIso( (string) $product->meta( $type->metaKey( 'date' ) ) );
		if ( null === $date && $parent ) {
			$date = DateFormat::parseIso( (string) $parent->meta( $type->metaKey( 'date' ) ) );
		}
		if ( null === $date ) {
			$categoryIds = ( $product->isVariation() && $parent ) ? $parent->categoryIds : $product->categoryIds;
			$date        = DateFormat::parseIso( $this->categories->resolve( $type->categoryOverrides, $categoryIds ) );
		}
		if ( null === $date ) {
			$date = DateFormat::parseIso( $type->defaultDate );
		}
		/** @var DateTimeImmutable|null $date */
		$date = apply_filters( 'scwc_reference_date', $date, $type, $product, $parent );
		return $date;
	}
}
