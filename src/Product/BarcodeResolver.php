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
		if ( self::coreGtinAvailable() ) {
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

	/** Core GTIN/EAN field exists since WooCommerce 9.2. */
	public static function coreGtinAvailable(): bool {
		return defined( 'WC_VERSION' ) && version_compare( (string) constant( 'WC_VERSION' ), '9.2', '>=' );
	}
}
