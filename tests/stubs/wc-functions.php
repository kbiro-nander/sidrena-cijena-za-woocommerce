<?php
/**
 * Minimal WooCommerce function stubs, deterministic for assertions.
 */
declare(strict_types=1);

if ( ! function_exists( 'wc_format_decimal' ) ) {
	function wc_format_decimal( $number, $dp = false, bool $trim_zeros = false ) {
		$number = str_replace( ',', '.', trim( (string) $number ) );
		if ( '' === $number || ! is_numeric( $number ) ) {
			return '';
		}
		if ( false !== $dp && null !== $dp ) {
			$number = number_format( (float) $number, (int) $dp, '.', '' );
		}
		return (string) $number;
	}
}
if ( ! function_exists( 'wc_price' ) ) {
	/** Deterministic Croatian formatting: "12,99 €". */
	function wc_price( $price, array $args = [] ) {
		return '<span class="woocommerce-Price-amount amount">' . number_format( (float) $price, 2, ',', '.' ) . '&nbsp;€</span>';
	}
}
if ( ! function_exists( 'wc_get_price_to_display' ) ) {
	function wc_get_price_to_display( WC_Product $product, array $args = [] ) {
		return (float) ( $args['price'] ?? $product->get_price() );
	}
}
if ( ! function_exists( 'wc_get_price_including_tax' ) ) {
	function wc_get_price_including_tax( WC_Product $product, array $args = [] ) {
		return (float) ( $args['price'] ?? $product->get_price() );
	}
}
if ( ! function_exists( 'wc_get_price_excluding_tax' ) ) {
	function wc_get_price_excluding_tax( WC_Product $product, array $args = [] ) {
		return (float) ( $args['price'] ?? $product->get_price() );
	}
}
if ( ! function_exists( 'wc_format_sale_price' ) ) {
	function wc_format_sale_price( $regular_price, $sale_price ) {
		$r = is_numeric( $regular_price ) ? wc_price( $regular_price ) : $regular_price;
		$s = is_numeric( $sale_price ) ? wc_price( $sale_price ) : $sale_price;
		return '<del aria-hidden="true">' . $r . '</del> <ins>' . $s . '</ins>';
	}
}
if ( ! function_exists( 'wc_format_price_range' ) ) {
	function wc_format_price_range( $from, $to ) {
		$f = is_numeric( $from ) ? wc_price( $from ) : $from;
		$t = is_numeric( $to ) ? wc_price( $to ) : $to;
		return $f . ' &ndash; ' . $t;
	}
}
if ( ! function_exists( 'wc_get_product' ) ) {
	function wc_get_product( $id = false ) {
		$id = is_object( $id ) ? $id->get_id() : (int) $id;
		return WC_Product::$registry[ $id ] ?? false;
	}
}
if ( ! function_exists( 'wc_get_product_id_by_sku' ) ) {
	function wc_get_product_id_by_sku( string $sku ) {
		foreach ( WC_Product::$registry as $id => $p ) {
			if ( $p->get_sku() === $sku ) {
				return $id;
			}
		}
		return 0;
	}
}
if ( ! function_exists( 'wc_string_to_bool' ) ) {
	function wc_string_to_bool( $string ) {
		return is_bool( $string ) ? $string : ( 'yes' === strtolower( (string) $string ) || 1 === $string || 'true' === strtolower( (string) $string ) || '1' === $string );
	}
}
if ( ! function_exists( 'wc_bool_to_string' ) ) {
	function wc_bool_to_string( $bool ) { return wc_string_to_bool( $bool ) ? 'yes' : 'no'; }
}
if ( ! function_exists( 'wc_clean' ) ) {
	function wc_clean( $var ) { return is_array( $var ) ? array_map( 'wc_clean', $var ) : sanitize_text_field( $var ); }
}
if ( ! function_exists( 'wc_get_logger' ) ) {
	function wc_get_logger() {
		return new class() {
			public array $entries = [];
			public function __call( string $name, array $args ) { $this->entries[] = [ $name, $args[0] ?? '', $args[1] ?? [] ]; }
		};
	}
}
