<?php
declare(strict_types=1);

if ( ! class_exists( 'Automattic\WooCommerce\StoreApi\Schemas\V1\CartItemSchema' ) ) {
	eval( 'namespace Automattic\WooCommerce\StoreApi\Schemas\V1; class CartItemSchema { const IDENTIFIER = "cart-item"; } class ProductSchema { const IDENTIFIER = "product"; }' );
}
if ( ! function_exists( 'woocommerce_store_api_register_endpoint_data' ) ) {
	function woocommerce_store_api_register_endpoint_data( array $args ) { $GLOBALS['scwc_test_store_api'][] = $args; return true; }
}
