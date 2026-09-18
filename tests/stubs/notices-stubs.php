<?php
declare(strict_types=1);

if ( ! function_exists( 'get_current_user_id' ) ) {
	function get_current_user_id() { return 1; }
}
if ( ! function_exists( 'get_user_meta' ) ) {
	function get_user_meta( $user_id, $key = '', $single = false ) { return $GLOBALS['scwc_test_user_meta'][ $user_id ][ $key ] ?? ( $single ? '' : [] ); }
}
if ( ! function_exists( 'update_user_meta' ) ) {
	function update_user_meta( $user_id, $key, $value, $prev = '' ) { $GLOBALS['scwc_test_user_meta'][ $user_id ][ $key ] = $value; return true; }
}
if ( ! function_exists( 'wp_safe_redirect' ) ) {
	function wp_safe_redirect( $location, $status = 302 ) { $GLOBALS['scwc_test_redirect'] = $location; return true; }
}
if ( ! function_exists( 'wp_get_referer' ) ) {
	function wp_get_referer() { return false; }
}
if ( ! function_exists( 'wp_die' ) ) {
	function wp_die( $message = '', $title = '', $args = [] ) { throw new RuntimeException( 'wp_die: ' . ( is_string( $message ) ? $message : '' ) ); }
}
if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $url ) { return $url; }
}
