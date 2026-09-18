<?php
/**
 * Discount percentage from a reference price (floored, never overstated).
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

namespace SidrenaCijena\History;

final class DiscountCalculator {

	public static function percent( float $reference, float $current ): ?int {
		if ( $reference <= 0 || $current >= $reference ) {
			return null;
		}
		$percent = (int) floor( ( $reference - $current ) / $reference * 100 );
		return $percent >= 1 ? $percent : null;
	}
}
