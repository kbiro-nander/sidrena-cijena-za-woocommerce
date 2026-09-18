<?php
/**
 * Uninstall handler. Data is kept unless "Ukloni podatke pri deinstalaciji" is enabled,
 * because the price list must stay available for 30 days.
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$scwc_settings = get_option( 'scwc_settings', [] );
if ( ! is_array( $scwc_settings ) || empty( $scwc_settings['advanced']['remove_data_on_uninstall'] ) ) {
	return;
}

global $wpdb;

// Options.
foreach ( [ 'scwc_settings', 'scwc_db_version', 'scwc_plugin_version', 'scwc_flush_rewrite', 'scwc_last_generation', 'scwc_services_dirty_at', 'scwc_history_seeded_at' ] as $scwc_option ) {
	delete_option( $scwc_option );
}

// Product/variation meta.
$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '\\_scwc\\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

// History table.
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}scwc_price_history" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

// Generated price-list files.
$scwc_upload = wp_upload_dir();
$scwc_dir    = trailingslashit( $scwc_upload['basedir'] ) . 'scwc-cjenik';
if ( is_dir( $scwc_dir ) ) {
	$scwc_iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $scwc_dir, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
	foreach ( $scwc_iterator as $scwc_file ) {
		$scwc_file->isDir() ? rmdir( $scwc_file->getPathname() ) : unlink( $scwc_file->getPathname() ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}
	rmdir( $scwc_dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions
}

// Scheduled actions and per-user notice dismissals.
if ( function_exists( 'as_unschedule_all_actions' ) ) {
	foreach ( [ 'scwc_generate_price_list', 'scwc_daily_sweep', 'scwc_sweep_page', 'scwc_prune', 'scwc_auto_snapshot' ] as $scwc_hook ) {
		as_unschedule_all_actions( $scwc_hook, [], 'scwc' );
	}
}
delete_metadata( 'user', 0, 'scwc_dismissed_notices', '', true );

// Transients.
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_scwc\\_%' OR option_name LIKE '\\_transient\\_timeout\\_scwc\\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
