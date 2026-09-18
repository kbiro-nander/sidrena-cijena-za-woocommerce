<?php
/**
 * Action Scheduler backend (bundled with WooCommerce).
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

namespace SidrenaCijena\Scheduling;

final class ActionSchedulerBackend implements SchedulerBackend {

	public const GROUP = 'scwc';

	public static function available(): bool {
		return function_exists( 'as_schedule_single_action' ) && function_exists( 'as_next_scheduled_action' );
	}

	public function scheduleSingle( int $timestamp, string $hook, array $args, bool $unique ): void {
		as_schedule_single_action( $timestamp, $hook, $args, self::GROUP, $unique );
	}

	public function enqueueAsync( string $hook, array $args ): void {
		as_enqueue_async_action( $hook, $args, self::GROUP );
	}

	public function nextScheduled( string $hook, array $args ): ?int {
		$next = as_next_scheduled_action( $hook, $args, self::GROUP );
		// `true` means "running right now" – there is no future occurrence pending, so the caller may schedule one.
		return is_int( $next ) ? $next : null;
	}

	public function unscheduleAll( string $hook ): void {
		as_unschedule_all_actions( $hook, [], self::GROUP );
	}

	public function name(): string {
		return 'action_scheduler';
	}
}
