<?php
/**
 * Stubs for the admin Tools page / AJAX tests (json responses, nonce, admin helpers).
 * Each is guarded so Brain Monkey's Functions\when()/expect() can still redefine them per test.
 *
 * wp_send_json_* record the payload in $GLOBALS['scwc_test_json'] and throw
 * RuntimeException('json_sent') so tests can stop execution like WP's die() would.
 */
declare(strict_types=1);

if ( ! function_exists( 'wp_send_json_success' ) ) {
	function wp_send_json_success( $data = null, $status_code = null, $flags = 0 ) {
		$GLOBALS['scwc_test_json'] = [ 'success' => true, 'data' => $data, 'status' => $status_code ];
		throw new RuntimeException( 'json_sent' );
	}
}
if ( ! function_exists( 'wp_send_json_error' ) ) {
	function wp_send_json_error( $data = null, $status_code = null, $flags = 0 ) {
		$GLOBALS['scwc_test_json'] = [ 'success' => false, 'data' => $data, 'status' => $status_code ];
		throw new RuntimeException( 'json_sent' );
	}
}
if ( ! function_exists( 'check_ajax_referer' ) ) {
	function check_ajax_referer( $action = -1, $query_arg = false, $stop = true ) { return true; }
}
if ( ! function_exists( 'wp_add_inline_script' ) ) {
	function wp_add_inline_script( ...$args ) { $GLOBALS['scwc_test_inline_scripts'][] = $args; return true; }
}
if ( ! function_exists( 'add_submenu_page' ) ) {
	function add_submenu_page( ...$args ) { $GLOBALS['scwc_test_submenus'][] = $args; return 'woocommerce_page_' . ( $args[4] ?? '' ); }
}
if ( ! function_exists( 'submit_button' ) ) {
	function submit_button( $text = null, $type = 'primary', $name = 'submit', $wrap = true, $other = null ) {
		echo '<p class="submit"><input type="submit" name="' . htmlspecialchars( (string) $name ) . '" class="button button-' . htmlspecialchars( (string) $type ) . '" value="' . htmlspecialchars( (string) ( $text ?? 'Spremi promjene' ) ) . '" /></p>';
	}
}
if ( ! function_exists( 'wp_die' ) ) {
	function wp_die( $message = '', ...$args ) { throw new RuntimeException( is_string( $message ) ? $message : 'wp_die' ); }
}
if ( ! function_exists( 'wp_localize_script' ) ) {
	function wp_localize_script( ...$args ) { $GLOBALS['scwc_test_localized'][] = $args; return true; }
}
if ( ! function_exists( 'selected' ) ) {
	function selected( $selected, $current = true, $display = true ) {
		$html = (string) $selected === (string) $current ? " selected='selected'" : '';
		if ( $display ) { echo $html; }
		return $html;
	}
}
if ( ! function_exists( 'checked' ) ) {
	function checked( $checked, $current = true, $display = true ) {
		$html = (string) $checked === (string) $current ? " checked='checked'" : '';
		if ( $display ) { echo $html; }
		return $html;
	}
}
