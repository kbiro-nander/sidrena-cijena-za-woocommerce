<?php
declare(strict_types=1);

if ( ! function_exists( 'wp_remote_get' ) ) {
	function wp_remote_get( $url, $args = [] ) { return $GLOBALS['scwc_test_http_get'][ $url ] ?? [ 'response' => [ 'code' => 404 ], 'body' => '' ]; }
}
if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
	function wp_remote_retrieve_body( $response ) { return is_array( $response ) ? (string) ( $response['body'] ?? '' ) : ''; }
}
if ( ! function_exists( 'get_site_transient' ) ) {
	function get_site_transient( string $key ) { return $GLOBALS['scwc_test_transients'][ $key ] ?? false; }
}
if ( ! function_exists( 'set_site_transient' ) ) {
	function set_site_transient( string $key, $value, int $expiration = 0 ) { $GLOBALS['scwc_test_transients'][ $key ] = $value; return true; }
}
if ( ! function_exists( 'delete_site_transient' ) ) {
	function delete_site_transient( string $key ) { unset( $GLOBALS['scwc_test_transients'][ $key ] ); return true; }
}
if ( ! function_exists( 'wp_kses_post' ) ) {
	function wp_kses_post( $data ) { return (string) $data; }
}
