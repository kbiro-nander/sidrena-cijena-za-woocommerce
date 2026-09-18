<?php
/**
 * Minimal WordPress function stubs. Each is guarded so Brain Monkey's
 * Functions\when()/expect() can still redefine them per test (via Patchwork).
 */
declare(strict_types=1);

if ( ! function_exists( 'wp_timezone' ) ) {
	function wp_timezone(): DateTimeZone { return new DateTimeZone( 'Europe/Zagreb' ); }
}
if ( ! function_exists( 'wp_timezone_string' ) ) {
	function wp_timezone_string(): string { return 'Europe/Zagreb'; }
}
if ( ! function_exists( 'remove_accents' ) ) {
	function remove_accents( string $string ): string {
		$map = [ 'č' => 'c', 'ć' => 'c', 'š' => 's', 'ž' => 'z', 'đ' => 'd', 'Č' => 'C', 'Ć' => 'C', 'Š' => 'S', 'Ž' => 'Z', 'Đ' => 'D', 'é' => 'e', 'ü' => 'u', 'ö' => 'o', 'ä' => 'a' ];
		return strtr( $string, $map );
	}
}
if ( ! function_exists( 'sanitize_title' ) ) {
	function sanitize_title( string $title ): string {
		$title = strtolower( remove_accents( $title ) );
		$title = preg_replace( '/[^a-z0-9\-]+/', '-', $title ) ?? '';
		return trim( $title, '-' );
	}
}
if ( ! function_exists( 'sanitize_file_name' ) ) {
	function sanitize_file_name( string $name ): string {
		return preg_replace( '/[^A-Za-z0-9._\-]+/', '-', $name ) ?? '';
	}
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $str ): string { return trim( strip_tags( (string) $str ) ); }
}
if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ): string { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) ) ?? ''; }
}
if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $value ) { return is_array( $value ) ? array_map( 'wp_unslash', $value ) : stripslashes( (string) $value ); }
}
if ( ! function_exists( 'wp_kses_post' ) ) {
	function wp_kses_post( $data ): string { return (string) $data; }
}
if ( ! function_exists( 'get_option' ) ) {
	function get_option( string $option, $default = false ) { return $default; }
}
if ( ! function_exists( 'update_option' ) ) {
	function update_option( string $option, $value, $autoload = null ): bool { return true; }
}
if ( ! function_exists( 'delete_option' ) ) {
	function delete_option( string $option ): bool { return true; }
}
if ( ! function_exists( 'get_post_meta' ) ) {
	function get_post_meta( int $post_id, string $key = '', bool $single = false ) { return $single ? '' : []; }
}
if ( ! function_exists( 'update_post_meta' ) ) {
	function update_post_meta( int $post_id, string $key, $value, $prev = '' ) { return true; }
}
if ( ! function_exists( 'delete_post_meta' ) ) {
	function delete_post_meta( int $post_id, string $key, $value = '' ): bool { return true; }
}
if ( ! function_exists( 'get_ancestors' ) ) {
	function get_ancestors( $object_id = 0, $object_type = '', $resource_type = '' ): array { return []; }
}
if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data, int $options = 0, int $depth = 512 ) { return json_encode( $data, $options, $depth ); }
}
if ( ! function_exists( 'is_admin' ) ) {
	function is_admin(): bool { return false; }
}
if ( ! function_exists( 'wp_doing_ajax' ) ) {
	function wp_doing_ajax(): bool { return false; }
}
if ( ! function_exists( 'wp_doing_cron' ) ) {
	function wp_doing_cron(): bool { return false; }
}
if ( ! function_exists( 'is_checkout' ) ) {
	function is_checkout(): bool { return false; }
}
if ( ! function_exists( 'is_cart' ) ) {
	function is_cart(): bool { return false; }
}
if ( ! function_exists( 'is_product' ) ) {
	function is_product(): bool { return false; }
}
if ( ! function_exists( 'in_the_loop' ) ) {
	function in_the_loop(): bool { return false; }
}
if ( ! function_exists( 'get_queried_object_id' ) ) {
	function get_queried_object_id(): int { return 0; }
}
if ( ! function_exists( 'home_url' ) ) {
	function home_url( string $path = '', $scheme = null ): string { return 'https://example.hr' . $path; }
}
if ( ! function_exists( 'get_bloginfo' ) ) {
	function get_bloginfo( string $show = '' ): string { return 'name' === $show ? 'Test trgovina' : ''; }
}
if ( ! function_exists( 'wp_generate_password' ) ) {
	function wp_generate_password( int $length = 12, bool $special = true, bool $extra = false ): string { return substr( str_repeat( 'abcdef0123456789', 4 ), 0, $length ); }
}
if ( ! function_exists( 'taxonomy_exists' ) ) {
	function taxonomy_exists( string $taxonomy ): bool { return false; }
}
if ( ! function_exists( 'get_the_terms' ) ) {
	function get_the_terms( $post, string $taxonomy ) { return false; }
}
if ( ! function_exists( 'current_time' ) ) {
	function current_time( string $type, $gmt = 0 ) { return 'timestamp' === $type ? time() : gmdate( $type ); }
}
if ( ! function_exists( 'wp_date' ) ) {
	function wp_date( string $format, ?int $timestamp = null, ?DateTimeZone $timezone = null ) { return ( new DateTimeImmutable( '@' . ( $timestamp ?? time() ) ) )->setTimezone( $timezone ?? wp_timezone() )->format( $format ); }
}
