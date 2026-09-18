<?php
/**
 * Action Scheduler + WP-Cron stubs recording calls in $GLOBALS['scwc_test_schedule'].
 */
declare(strict_types=1);

if ( ! function_exists( 'scwc_test_schedule_reset' ) ) {
	function scwc_test_schedule_reset() { $GLOBALS['scwc_test_schedule'] = [ 'single' => [], 'async' => [], 'unscheduled' => [], 'wp_single' => [], 'wp_unscheduled' => [] ]; }
}
if ( ! function_exists( 'as_schedule_single_action' ) ) {
	function as_schedule_single_action( $timestamp, $hook, $args = [], $group = '', $unique = false, $priority = 10 ) {
		$GLOBALS['scwc_test_schedule']['single'][] = compact( 'timestamp', 'hook', 'args', 'group', 'unique' );
		return count( $GLOBALS['scwc_test_schedule']['single'] );
	}
}
if ( ! function_exists( 'as_enqueue_async_action' ) ) {
	function as_enqueue_async_action( $hook, $args = [], $group = '', $unique = false, $priority = 10 ) {
		$GLOBALS['scwc_test_schedule']['async'][] = compact( 'hook', 'args', 'group', 'unique' );
		return 1;
	}
}
if ( ! function_exists( 'as_next_scheduled_action' ) ) {
	function as_next_scheduled_action( $hook, $args = null, $group = '' ) {
		foreach ( $GLOBALS['scwc_test_schedule']['single'] ?? [] as $s ) {
			if ( $s['hook'] === $hook && ( null === $args || $s['args'] === $args ) ) {
				return $s['timestamp'];
			}
		}
		return false;
	}
}
if ( ! function_exists( 'as_has_scheduled_action' ) ) {
	function as_has_scheduled_action( $hook, $args = null, $group = '' ) { return false !== as_next_scheduled_action( $hook, $args, $group ); }
}
if ( ! function_exists( 'as_unschedule_all_actions' ) ) {
	function as_unschedule_all_actions( $hook, $args = [], $group = '' ) {
		$GLOBALS['scwc_test_schedule']['unscheduled'][] = $hook;
		$GLOBALS['scwc_test_schedule']['single'] = array_values( array_filter( $GLOBALS['scwc_test_schedule']['single'] ?? [], fn( $s ) => $s['hook'] !== $hook ) );
	}
}
if ( ! function_exists( 'wp_schedule_single_event' ) ) {
	function wp_schedule_single_event( $timestamp, $hook, $args = [], $wp_error = false ) { $GLOBALS['scwc_test_schedule']['wp_single'][] = compact( 'timestamp', 'hook', 'args' ); return true; }
}
if ( ! function_exists( 'wp_next_scheduled' ) ) {
	function wp_next_scheduled( $hook, $args = [] ) {
		foreach ( $GLOBALS['scwc_test_schedule']['wp_single'] ?? [] as $s ) {
			if ( $s['hook'] === $hook && $s['args'] === $args ) { return $s['timestamp']; }
		}
		return false;
	}
}
if ( ! function_exists( 'wp_unschedule_hook' ) ) {
	function wp_unschedule_hook( $hook, $wp_error = false ) { $GLOBALS['scwc_test_schedule']['wp_unscheduled'][] = $hook; $GLOBALS['scwc_test_schedule']['wp_single'] = array_values( array_filter( $GLOBALS['scwc_test_schedule']['wp_single'] ?? [], fn( $s ) => $s['hook'] !== $hook ) ); return 0; }
}
