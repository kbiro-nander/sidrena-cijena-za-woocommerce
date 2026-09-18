<?php
/**
 * Activation routine.
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

namespace SidrenaCijena\Lifecycle;

use SidrenaCijena\History\Schema;
use SidrenaCijena\Settings\Sanitizer;
use SidrenaCijena\Settings\Settings;

final class Activator {

	public static function activate(): void {
		Schema::install();
		// Ensure a complete, sanitized settings array (generates the external cron key).
		$stored = get_option( Settings::OPTION, [] );
		update_option( Settings::OPTION, ( new Sanitizer() )->sanitize( is_array( $stored ) ? $stored : [] ), true );
		update_option( 'scwc_plugin_version', SCWC_VERSION );
		update_option( 'scwc_flush_rewrite', 1 );
		\SidrenaCijena\Plugin::instance()->onActivate();
		do_action( 'scwc_activated' );
	}
}
