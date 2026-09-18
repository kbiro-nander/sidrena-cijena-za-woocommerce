<?php
/**
 * WP-Cron fallback backend.
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

namespace SidrenaCijena\Scheduling;

final class WpCronBackend implements SchedulerBackend {

	public function scheduleSingle( int $timestamp, string $hook, array $args, bool $unique ): void {
		if ( $unique && null !== $this->nextScheduled( $hook, $args ) ) {
			return;
		}
		wp_schedule_single_event( $timestamp, $hook, array_values( $args ) );
	}

	public function enqueueAsync( string $hook, array $args ): void {
		wp_schedule_single_event( time(), $hook, array_values( $args ) );
	}

	public function nextScheduled( string $hook, array $args ): ?int {
		$next = wp_next_scheduled( $hook, array_values( $args ) );
		return false === $next ? null : (int) $next;
	}

	public function unscheduleAll( string $hook ): void {
		wp_unschedule_hook( $hook );
	}

	public function name(): string {
		return 'wp_cron';
	}
}
