<?php
declare(strict_types=1);

if ( ! function_exists( 'wp_remote_head' ) ) {
	function wp_remote_head( $url, $args = [] ) { return $GLOBALS['scwc_test_http'][ $url ] ?? [ 'response' => [ 'code' => 200 ] ]; }
}
if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
	function wp_remote_retrieve_response_code( $response ) { return is_array( $response ) ? (int) ( $response['response']['code'] ?? 0 ) : 0; }
}
