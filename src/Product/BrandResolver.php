<?php
/**
 * Resolves a product's brand name (core WooCommerce Brands, WC 9.6+).
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

namespace SidrenaCijena\Product;

final class BrandResolver {

	public const TAXONOMY = 'product_brand';

	public function resolve( int $productId ): string {
		$brand = '';
		if ( taxonomy_exists( self::TAXONOMY ) ) {
			$terms = get_the_terms( $productId, self::TAXONOMY );
			if ( is_array( $terms ) ) {
				$names = array_map( static fn( $t ) => (string) $t->name, $terms );
				$brand = implode( ', ', $names );
			}
		}
		/** @var string $brand */
		$brand = apply_filters( 'scwc_product_brand', $brand, $productId );
		return $brand;
	}
}
