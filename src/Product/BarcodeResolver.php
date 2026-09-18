<?php
/**
 * Resolves a product barcode: core GTIN (WC 9.2+) then common plugin meta keys.
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

namespace SidrenaCijena\Product;

use WC_Product;

final class BarcodeResolver {

	public function resolve( WC_Product $product ): string {
		$code = '';
		if ( method_exists( $product, 'get_global_unique_id' ) ) {
			$code = trim( (string) $product->get_global_unique_id( 'edit' ) );
		}
		if ( '' === $code ) {
			/** @var string[] $keys */
			$keys = apply_filters( 'scwc_barcode_meta_keys', MetaKeys::BARCODE_FALLBACKS );
			foreach ( $keys as $key ) {
				$value = trim( (string) $product->get_meta( $key, true, 'edit' ) );
				if ( '' !== $value ) {
					$code = $value;
					break;
				}
			}
		}
		/** @var string $code */
		$code = apply_filters( 'scwc_product_barcode', $code, $product );
		return $code;
	}
}
