<?php
/**
 * Converts stored amounts to display amounts (tax mode aware) and HTML.
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

namespace SidrenaCijena\Display;

use WC_Product;

final class PriceFormatter {

	/**
	 * @param string $amount Stored (tax-agnostic, like _regular_price) amount.
	 */
	public function display( WC_Product $product, string $amount, string $context, int $quantity = 1 ): float {
		$args = [
			'price' => $amount,
			'qty'   => $quantity,
		];
		if ( in_array( $context, BadgeContext::CART_LIKE, true ) && function_exists( 'WC' ) && WC()->cart ) {
			return WC()->cart->display_prices_including_tax()
				? (float) wc_get_price_including_tax( $product, $args )
				: (float) wc_get_price_excluding_tax( $product, $args );
		}
		return (float) wc_get_price_to_display( $product, $args );
	}

	public function html( float $amount ): string {
		return wc_price( $amount );
	}

	/** Plain text ("14,99 €") for contexts that strip HTML. */
	public function text( float $amount ): string {
		$text = html_entity_decode( wp_strip_all_tags( wc_price( $amount ) ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		return str_replace( "\u{00A0}", ' ', $text );
	}
}
