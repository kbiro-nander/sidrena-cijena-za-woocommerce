<?php
/**
 * Unit price ("cijena za jedinicu mjere").
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

namespace SidrenaCijena\Product;

final class UnitPrice {

	public static function compute( float $price, ?float $quantity ): ?float {
		if ( null === $quantity || $quantity <= 0 ) {
			return null;
		}
		return round( $price / $quantity, 2 );
	}
}
