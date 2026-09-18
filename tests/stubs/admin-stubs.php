<?php
/**
 * WordPress/WooCommerce admin function stubs (settings API, product meta-box fields).
 * Each is guarded so Brain Monkey's Functions\when()/expect() can still redefine them per test.
 */
declare(strict_types=1);

if ( ! function_exists( 'scwc_test_attr_string' ) ) {
	/** @param array<string,mixed> $attrs */
	function scwc_test_attr_string( array $attrs ) {
		$out = '';
		foreach ( $attrs as $k => $v ) {
			if ( null === $v || false === $v ) {
				continue;
			}
			$out .= ' ' . $k . '="' . htmlspecialchars( (string) $v, ENT_QUOTES ) . '"';
		}
		return $out;
	}
}
if ( ! function_exists( 'woocommerce_wp_text_input' ) ) {
	function woocommerce_wp_text_input( array $field ) {
		$name = $field['name'] ?? $field['id'];
		$type = $field['type'] ?? 'text';
		echo '<p class="form-field ' . htmlspecialchars( (string) ( $field['wrapper_class'] ?? '' ) ) . '">';
		echo '<label for="' . htmlspecialchars( (string) $field['id'] ) . '">' . htmlspecialchars( (string) ( $field['label'] ?? '' ) ) . '</label>';
		echo '<input' . scwc_test_attr_string( [ 'type' => $type, 'id' => $field['id'], 'name' => $name, 'value' => (string) ( $field['value'] ?? '' ), 'data-type' => $field['data_type'] ?? null, 'placeholder' => $field['placeholder'] ?? null ] ) . ' />';
		if ( ! empty( $field['description'] ) ) {
			echo '<span class="description">' . htmlspecialchars( (string) $field['description'] ) . '</span>';
		}
		echo '</p>';
	}
}
if ( ! function_exists( 'woocommerce_wp_checkbox' ) ) {
	function woocommerce_wp_checkbox( array $field ) {
		$name  = $field['name'] ?? $field['id'];
		$value = (string) ( $field['value'] ?? '' );
		$cb    = (string) ( $field['cbvalue'] ?? 'yes' );
		echo '<p class="form-field ' . htmlspecialchars( (string) ( $field['wrapper_class'] ?? '' ) ) . '">';
		echo '<label for="' . htmlspecialchars( (string) $field['id'] ) . '">' . htmlspecialchars( (string) ( $field['label'] ?? '' ) ) . '</label>';
		echo '<input' . scwc_test_attr_string( [ 'type' => 'checkbox', 'id' => $field['id'], 'name' => $name, 'value' => $cb, 'checked' => $value === $cb ? 'checked' : null ] ) . ' />';
		if ( ! empty( $field['description'] ) ) {
			echo '<span class="description">' . htmlspecialchars( (string) $field['description'] ) . '</span>';
		}
		echo '</p>';
	}
}
if ( ! function_exists( 'woocommerce_wp_select' ) ) {
	function woocommerce_wp_select( array $field ) {
		$name  = $field['name'] ?? $field['id'];
		$value = (string) ( $field['value'] ?? '' );
		echo '<p class="form-field"><label for="' . htmlspecialchars( (string) $field['id'] ) . '">' . htmlspecialchars( (string) ( $field['label'] ?? '' ) ) . '</label>';
		echo '<select' . scwc_test_attr_string( [ 'id' => $field['id'], 'name' => $name ] ) . '>';
		foreach ( (array) ( $field['options'] ?? [] ) as $k => $label ) {
			echo '<option value="' . htmlspecialchars( (string) $k ) . '"' . ( (string) $k === $value ? ' selected="selected"' : '' ) . '>' . htmlspecialchars( (string) $label ) . '</option>';
		}
		echo '</select></p>';
	}
}
if ( ! function_exists( 'woocommerce_wp_hidden_input' ) ) {
	function woocommerce_wp_hidden_input( array $field ) {
		echo '<input type="hidden" id="' . htmlspecialchars( (string) $field['id'] ) . '" name="' . htmlspecialchars( (string) ( $field['name'] ?? $field['id'] ) ) . '" value="' . htmlspecialchars( (string) ( $field['value'] ?? '' ) ) . '" />';
	}
}
if ( ! function_exists( 'add_submenu_page' ) ) {
	function add_submenu_page( ...$args ) { $GLOBALS['scwc_test_submenus'][] = $args; return 'woocommerce_page_' . ( $args[4] ?? '' ); }
}
if ( ! function_exists( 'register_setting' ) ) {
	function register_setting( $group, $name, $args = [] ) { $GLOBALS['scwc_test_registered_settings'][ $name ] = [ 'group' => $group, 'args' => $args ]; }
}
if ( ! function_exists( 'add_settings_error' ) ) {
	function add_settings_error( ...$args ) { $GLOBALS['scwc_test_settings_errors'][] = $args; }
}
if ( ! function_exists( 'settings_fields' ) ) {
	function settings_fields( $group ) { echo '<input type="hidden" name="option_page" value="' . htmlspecialchars( (string) $group ) . '" /><input type="hidden" name="action" value="update" /><input type="hidden" name="_wpnonce" value="nonce" />'; }
}
if ( ! function_exists( 'settings_errors' ) ) {
	function settings_errors( ...$args ) {}
}
if ( ! function_exists( 'get_terms' ) ) {
	function get_terms( $args = [], $deprecated = '' ) { return []; }
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
if ( ! function_exists( 'submit_button' ) ) {
	function submit_button( $text = null, $type = 'primary', $name = 'submit', $wrap = true, $other = null ) {
		echo '<p class="submit"><input type="submit" name="' . htmlspecialchars( (string) $name ) . '" class="button button-' . htmlspecialchars( (string) $type ) . '" value="' . htmlspecialchars( (string) ( $text ?? 'Spremi promjene' ) ) . '" /></p>';
	}
}
if ( ! function_exists( 'get_current_screen' ) ) {
	function get_current_screen() { return $GLOBALS['scwc_test_screen'] ?? null; }
}
if ( ! function_exists( 'wp_die' ) ) {
	function wp_die( $message = '', ...$args ) { throw new RuntimeException( is_string( $message ) ? $message : 'wp_die' ); }
}
if ( ! function_exists( 'wp_nonce_url' ) ) {
	function wp_nonce_url( $url, $action = -1, $name = '_wpnonce' ) { return add_query_arg( $name, 'nonce', $url ); }
}
if ( ! function_exists( 'date_i18n' ) ) {
	function date_i18n( $format, $timestamp = null ) { return ( new DateTimeImmutable( '@' . ( $timestamp ?? time() ) ) )->setTimezone( wp_timezone() )->format( $format ); }
}
if ( ! function_exists( 'wp_enqueue_style' ) ) {
	function wp_enqueue_style( ...$args ) { $GLOBALS['scwc_test_styles'][] = $args; }
}
if ( ! function_exists( 'wp_enqueue_script' ) ) {
	function wp_enqueue_script( ...$args ) { $GLOBALS['scwc_test_scripts'][] = $args; }
}
if ( ! function_exists( 'wp_localize_script' ) ) {
	function wp_localize_script( ...$args ) { $GLOBALS['scwc_test_localized'][] = $args; return true; }
}
