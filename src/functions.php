<?php
/**
 * Public template functions for theme authors.
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

use SidrenaCijena\Display\BadgeContext;
use SidrenaCijena\Display\BadgeDataFactory;
use SidrenaCijena\Display\ReferenceView;
use SidrenaCijena\Display\OmnibusView;
use SidrenaCijena\Display\Shortcode;
use SidrenaCijena\Plugin;

if ( ! function_exists( 'scwc_resolve_product' ) ) {
	/**
	 * @param int|WC_Product|null $product Product ID, object or null for the global product.
	 */
	function scwc_resolve_product( $product = null ): ?WC_Product {
		if ( $product instanceof WC_Product ) {
			return $product;
		}
		if ( null === $product || 0 === $product ) {
			$global = $GLOBALS['product'] ?? null;
			return $global instanceof WC_Product ? $global : null;
		}
		$loaded = wc_get_product( (int) $product );
		return $loaded instanceof WC_Product ? $loaded : null;
	}
}

if ( ! function_exists( 'sidrena_cijena' ) ) {
	/**
	 * Render the reference-price badge (sidrena cijena + najniža cijena u 30 dana) for a product.
	 *
	 * @param int|WC_Product|null $product Product ID, object, or null for the current product.
	 * @param array{key?:string,context?:string} $args Optional: `key` limits to one reference type; `context` for styling.
	 */
	function sidrena_cijena( $product = null, array $args = [] ): string {
		$wc = scwc_resolve_product( $product );
		if ( ! $wc ) {
			return '';
		}
		return Plugin::instance()->get( Shortcode::class )->renderFor( $wc, (string) ( $args['key'] ?? '' ), (string) ( $args['context'] ?? BadgeContext::SHORTCODE ) );
	}
}

if ( ! function_exists( 'scwc_get_reference_price' ) ) {
	/**
	 * Display-ready reference price (null when the product has none).
	 *
	 * @param int|WC_Product|null $product Product.
	 */
	function scwc_get_reference_price( $product = null, string $key = 'anchor' ): ?ReferenceView {
		$wc = scwc_resolve_product( $product );
		if ( ! $wc ) {
			return null;
		}
		$data = Plugin::instance()->get( BadgeDataFactory::class )->forProduct( $wc, BadgeContext::SHORTCODE );
		if ( ! $data ) {
			return null;
		}
		foreach ( $data->references as $ref ) {
			if ( $ref->key === $key ) {
				return $ref;
			}
		}
		return null;
	}
}

if ( ! function_exists( 'scwc_get_lowest_30_day_price' ) ) {
	/**
	 * Lowest price in the 30 days before the current sale (null when not on sale / unknown).
	 *
	 * @param int|WC_Product|null $product Product.
	 */
	function scwc_get_lowest_30_day_price( $product = null ): ?OmnibusView {
		$wc = scwc_resolve_product( $product );
		if ( ! $wc ) {
			return null;
		}
		$data = Plugin::instance()->get( BadgeDataFactory::class )->forProduct( $wc, BadgeContext::SHORTCODE );
		return $data?->omnibus;
	}
}

if ( ! function_exists( 'scwc_discount_percent' ) ) {
	/**
	 * Discount % computed from the 30-day lowest price (null when not applicable).
	 *
	 * @param int|WC_Product|null $product Product.
	 */
	function scwc_discount_percent( $product = null ): ?int {
		return scwc_get_lowest_30_day_price( $product )?->percent;
	}
}
