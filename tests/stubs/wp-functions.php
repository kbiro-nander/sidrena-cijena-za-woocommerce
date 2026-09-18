<?php
/**
 * Minimal WordPress function stubs. Each is guarded so Brain Monkey's
 * Functions\when()/expect() can still redefine them per test (via Patchwork).
 */
declare(strict_types=1);

if ( ! function_exists( 'wp_timezone' ) ) {
	function wp_timezone() { return new DateTimeZone( 'Europe/Zagreb' ); }
}
if ( ! function_exists( 'wp_timezone_string' ) ) {
	function wp_timezone_string() { return 'Europe/Zagreb'; }
}
if ( ! function_exists( 'remove_accents' ) ) {
	function remove_accents( string $string ) {
		$map = [ 'č' => 'c', 'ć' => 'c', 'š' => 's', 'ž' => 'z', 'đ' => 'd', 'Č' => 'C', 'Ć' => 'C', 'Š' => 'S', 'Ž' => 'Z', 'Đ' => 'D', 'é' => 'e', 'ü' => 'u', 'ö' => 'o', 'ä' => 'a' ];
		return strtr( $string, $map );
	}
}
if ( ! function_exists( 'sanitize_title' ) ) {
	function sanitize_title( string $title ) {
		$title = strtolower( remove_accents( $title ) );
		$title = preg_replace( '/[^a-z0-9\-]+/', '-', $title ) ?? '';
		return trim( $title, '-' );
	}
}
if ( ! function_exists( 'sanitize_file_name' ) ) {
	function sanitize_file_name( string $name ) {
		return preg_replace( '/[^A-Za-z0-9._\-]+/', '-', $name ) ?? '';
	}
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $str ) { return trim( strip_tags( (string) $str ) ); }
}
if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) ) ?? ''; }
}
if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $value ) { return is_array( $value ) ? array_map( 'wp_unslash', $value ) : stripslashes( (string) $value ); }
}
if ( ! function_exists( 'wp_kses_post' ) ) {
	function wp_kses_post( $data ) { return (string) $data; }
}
if ( ! function_exists( 'get_option' ) ) {
	function get_option( string $option, $default = false ) { return $default; }
}
if ( ! function_exists( 'update_option' ) ) {
	function update_option( string $option, $value, $autoload = null ) { return true; }
}
if ( ! function_exists( 'delete_option' ) ) {
	function delete_option( string $option ) { return true; }
}
if ( ! function_exists( 'get_post_meta' ) ) {
	function get_post_meta( int $post_id, string $key = '', bool $single = false ) { return $single ? '' : []; }
}
if ( ! function_exists( 'update_post_meta' ) ) {
	function update_post_meta( int $post_id, string $key, $value, $prev = '' ) { return true; }
}
if ( ! function_exists( 'delete_post_meta' ) ) {
	function delete_post_meta( int $post_id, string $key, $value = '' ) { return true; }
}
if ( ! function_exists( 'get_ancestors' ) ) {
	function get_ancestors( $object_id = 0, $object_type = '', $resource_type = '' ) { return []; }
}
if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data, int $options = 0, int $depth = 512 ) { return json_encode( $data, $options, $depth ); }
}
if ( ! function_exists( 'is_admin' ) ) {
	function is_admin() { return false; }
}
if ( ! function_exists( 'wp_doing_ajax' ) ) {
	function wp_doing_ajax() { return false; }
}
if ( ! function_exists( 'wp_doing_cron' ) ) {
	function wp_doing_cron() { return false; }
}
if ( ! function_exists( 'is_checkout' ) ) {
	function is_checkout() { return false; }
}
if ( ! function_exists( 'is_cart' ) ) {
	function is_cart() { return false; }
}
if ( ! function_exists( 'is_product' ) ) {
	function is_product() { return false; }
}
if ( ! function_exists( 'in_the_loop' ) ) {
	function in_the_loop() { return false; }
}
if ( ! function_exists( 'get_queried_object_id' ) ) {
	function get_queried_object_id() { return 0; }
}
if ( ! function_exists( 'home_url' ) ) {
	function home_url( string $path = '', $scheme = null ) { return 'https://example.hr' . $path; }
}
if ( ! function_exists( 'get_bloginfo' ) ) {
	function get_bloginfo( string $show = '' ) { return 'name' === $show ? 'Test trgovina' : ''; }
}
if ( ! function_exists( 'wp_generate_password' ) ) {
	function wp_generate_password( int $length = 12, bool $special = true, bool $extra = false ) { return substr( str_repeat( 'abcdef0123456789', 4 ), 0, $length ); }
}
if ( ! function_exists( 'taxonomy_exists' ) ) {
	function taxonomy_exists( string $taxonomy ) { return false; }
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
if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $text, $remove_breaks = false ) { return trim( strip_tags( (string) $text ) ); }
}
if ( ! function_exists( 'shortcode_atts' ) ) {
	function shortcode_atts( array $pairs, $atts, $shortcode = '' ) {
		$atts = (array) $atts;
		$out  = [];
		foreach ( $pairs as $name => $default ) {
			$out[ $name ] = array_key_exists( $name, $atts ) ? $atts[ $name ] : $default;
		}
		return $out;
	}
}
if ( ! function_exists( 'add_shortcode' ) ) {
	function add_shortcode( string $tag, callable $cb ) { $GLOBALS['scwc_test_shortcodes'][ $tag ] = $cb; }
}
if ( ! function_exists( 'wp_enqueue_style' ) ) {
	function wp_enqueue_style( ...$args ) { $GLOBALS['scwc_test_styles'][] = $args; }
}
if ( ! function_exists( 'wp_enqueue_script' ) ) {
	function wp_enqueue_script( ...$args ) { $GLOBALS['scwc_test_scripts'][] = $args; }
}
if ( ! function_exists( 'plugins_url' ) ) {
	function plugins_url( string $path = '', string $plugin = '' ) { return 'https://example.hr/wp-content/plugins/sidrena-cijena-za-woocommerce/' . ltrim( $path, '/' ); }
}
if ( ! function_exists( 'plugin_dir_path' ) ) {
	function plugin_dir_path( string $file ) { return rtrim( dirname( $file ), '/' ) . '/'; }
}
if ( ! function_exists( 'get_ancestors' ) ) {
	function get_ancestors( $object_id = 0, $object_type = '', $resource_type = '' ) { return []; }
}
if ( ! function_exists( 'load_plugin_textdomain' ) ) {
	function load_plugin_textdomain( ...$args ) { return true; }
}
if ( ! function_exists( 'plugin_basename' ) ) {
	function plugin_basename( string $file ) { return 'sidrena-cijena-za-woocommerce/' . basename( $file ); }
}
if ( ! function_exists( 'flush_rewrite_rules' ) ) {
	function flush_rewrite_rules( $hard = true ) { $GLOBALS['scwc_test_flushed'] = ( $GLOBALS['scwc_test_flushed'] ?? 0 ) + 1; }
}
if ( ! defined( 'SCWC_PLUGIN_URL' ) ) {
	define( 'SCWC_PLUGIN_URL', 'https://example.hr/wp-content/plugins/sidrena-cijena-za-woocommerce/' );
}
if ( ! function_exists( 'get_transient' ) ) {
	function get_transient( string $key ) { return $GLOBALS['scwc_test_transients'][ $key ] ?? false; }
}
if ( ! function_exists( 'set_transient' ) ) {
	function set_transient( string $key, $value, int $expiration = 0 ) { $GLOBALS['scwc_test_transients'][ $key ] = $value; return true; }
}
if ( ! function_exists( 'delete_transient' ) ) {
	function delete_transient( string $key ) { unset( $GLOBALS['scwc_test_transients'][ $key ] ); return true; }
}
if ( ! function_exists( 'wp_upload_dir' ) ) {
	function wp_upload_dir( $time = null, bool $create_dir = true ) {
		$base = sys_get_temp_dir() . '/scwc-test-uploads';
		return [ 'basedir' => $base, 'baseurl' => 'https://example.hr/wp-content/uploads', 'error' => false ];
	}
}
if ( ! function_exists( 'wp_mkdir_p' ) ) {
	function wp_mkdir_p( string $target ) { return is_dir( $target ) || mkdir( $target, 0755, true ); }
}
if ( ! function_exists( 'wp_normalize_path' ) ) {
	function wp_normalize_path( string $path ) { return str_replace( '\\', '/', $path ); }
}
if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( string $cap, ...$args ) { return true; }
}
if ( ! function_exists( 'wp_verify_nonce' ) ) {
	function wp_verify_nonce( $nonce, $action = -1 ) { return 1; }
}
if ( ! function_exists( 'wp_create_nonce' ) ) {
	function wp_create_nonce( $action = -1 ) { return 'nonce'; }
}
if ( ! function_exists( 'wp_nonce_field' ) ) {
	function wp_nonce_field( $action = -1, $name = '_wpnonce', $referer = true, $display = true ) { $html = '<input type="hidden" name="' . $name . '" value="nonce" />'; if ( $display ) { echo $html; } return $html; }
}
if ( ! function_exists( 'admin_url' ) ) {
	function admin_url( string $path = '', string $scheme = 'admin' ) { return 'https://example.hr/wp-admin/' . ltrim( $path, '/' ); }
}
if ( ! function_exists( 'add_query_arg' ) ) {
	function add_query_arg( ...$args ) {
		if ( is_array( $args[0] ) ) { $params = $args[0]; $url = $args[1] ?? ''; } else { $params = [ $args[0] => $args[1] ]; $url = $args[2] ?? ''; }
		return $url . ( str_contains( $url, '?' ) ? '&' : '?' ) . http_build_query( $params );
	}
}
if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data, int $options = 0, int $depth = 512 ) { return json_encode( $data, $options, $depth ); }
}
