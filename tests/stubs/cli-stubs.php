<?php
declare(strict_types=1);

if ( ! class_exists( 'WP_CLI' ) ) {
	class WP_CLI {
		public static array $out = [];
		public static function log( $m ) { self::$out[] = "log: $m"; }
		public static function line( $m = '' ) { self::$out[] = "line: $m"; }
		public static function success( $m ) { self::$out[] = "success: $m"; }
		public static function warning( $m ) { self::$out[] = "warning: $m"; }
		public static function error( $m, $exit = true ) { self::$out[] = "error: $m"; throw new RuntimeException( (string) $m ); }
		public static function add_command( $name, $callable, $args = [] ) { $GLOBALS['scwc_test_cli_commands'][ $name ] = $callable; }
	}
}
if ( ! function_exists( 'WP_CLI\Utils\format_items' ) ) {
	eval( 'namespace WP_CLI\Utils; function format_items( $format, $items, $fields ) { \WP_CLI::$out[] = "table: " . count( $items ); }' );
}
