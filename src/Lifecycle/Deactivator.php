<?php
/**
 * Deactivation routine: unschedule jobs, keep data and files (30-day retention duty).
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

namespace SidrenaCijena\Lifecycle;

final class Deactivator {

	public static function deactivate(): void {
		\SidrenaCijena\Plugin::instance()->onDeactivate();
		do_action( 'scwc_deactivated' );
		flush_rewrite_rules();
	}
}
