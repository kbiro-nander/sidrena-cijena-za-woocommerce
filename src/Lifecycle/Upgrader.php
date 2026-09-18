<?php
/**
 * Version-gated upgrade routine.
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

namespace SidrenaCijena\Lifecycle;

use SidrenaCijena\History\Schema;

final class Upgrader {

	public static function maybeUpgrade(): void {
		$installed = (string) get_option( 'scwc_plugin_version', '' );
		if ( $installed === SCWC_VERSION && ! Schema::needsUpgrade() ) {
			return;
		}
		Schema::install();
		update_option( 'scwc_plugin_version', SCWC_VERSION );
		update_option( 'scwc_flush_rewrite', 1 );
		do_action( 'scwc_upgraded', $installed, SCWC_VERSION );
	}
}
