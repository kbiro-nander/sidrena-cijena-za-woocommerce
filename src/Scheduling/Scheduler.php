<?php
/**
 * Schedules the daily jobs at local (site timezone) times via self-rescheduling single actions.
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

namespace SidrenaCijena\Scheduling;

use DateTimeImmutable;
use SidrenaCijena\Settings\Settings;
use SidrenaCijena\Support\Clock;

class Scheduler {

	public const HOOK_GENERATE      = 'scwc_generate_price_list';
	public const HOOK_SWEEP         = 'scwc_daily_sweep';
	public const HOOK_SWEEP_PAGE    = 'scwc_sweep_page';
	public const HOOK_PRUNE         = 'scwc_prune';
	public const HOOK_AUTO_SNAPSHOT = 'scwc_auto_snapshot';
	public const HOOKS              = [ self::HOOK_GENERATE, self::HOOK_SWEEP, self::HOOK_SWEEP_PAGE, self::HOOK_PRUNE, self::HOOK_AUTO_SNAPSHOT ];
	public const WATCHDOG_TRANSIENT = 'scwc_schedule_check';

	public function __construct(
		private readonly SchedulerBackend $backend,
		private readonly Settings $settings,
		private readonly Clock $clock,
	) {}

	public function backend(): SchedulerBackend {
		return $this->backend;
	}

	/** Next occurrence of HH:MM in the site timezone, as a UTC Unix timestamp. */
	public function nextRunTimestamp( string $hhmm ): int {
		[ $h, $m ] = array_map( 'intval', explode( ':', $hhmm . ':0' ) );
		$local     = $this->clock->nowLocal();
		$candidate = $local->setTime( $h, $m, 0 );
		if ( $candidate <= $local ) {
			$candidate = $candidate->modify( '+1 day' )->setTime( $h, $m, 0 );
		}
		return $candidate->getTimestamp();
	}

	public function ensureScheduled(): void {
		if ( (bool) $this->settings->get( 'price_list.enabled', true ) ) {
			$this->ensure( self::HOOK_GENERATE, [], $this->nextRunTimestamp( (string) $this->settings->get( 'price_list.generate_time', '06:00' ) ) );
		}
		if ( (bool) $this->settings->get( 'history.enabled', true ) ) {
			$this->ensure( self::HOOK_SWEEP, [], $this->nextRunTimestamp( (string) $this->settings->get( 'history.sweep_time', '00:30' ) ) );
			$this->ensure( self::HOOK_PRUNE, [], $this->clock->now()->modify( '+7 days' )->getTimestamp() );
		}
		foreach ( $this->settings->section( 'reference_prices' ) as $key => $cfg ) {
			if ( ! is_array( $cfg ) || empty( $cfg['enabled'] ) || empty( $cfg['auto_snapshot_at'] ) ) {
				continue;
			}
			if ( ! empty( $cfg['auto_snapshot_done'] ) && $cfg['auto_snapshot_done'] === $cfg['auto_snapshot_at'] ) {
				continue;
			}
			$at = DateTimeImmutable::createFromFormat( 'Y-m-d H:i', (string) $cfg['auto_snapshot_at'], $this->clock->timezone() );
			if ( false === $at ) {
				continue;
			}
			$this->ensure( self::HOOK_AUTO_SNAPSHOT, [ 'type' => (string) $key ], max( $at->getTimestamp(), $this->clock->now()->getTimestamp() ) );
		}
	}

	public function reschedule(): void {
		foreach ( self::HOOKS as $hook ) {
			$this->backend->unscheduleAll( $hook );
		}
		$this->ensureScheduled();
	}

	public function unscheduleAll(): void {
		foreach ( self::HOOKS as $hook ) {
			$this->backend->unscheduleAll( $hook );
		}
	}

	/**
	 * Schedule a debounced generation run. Deduplicated by (hook, args) ourselves: Action Scheduler's
	 * `unique` flag ignores args and would collide with the pending daily action.
	 */
	public function scheduleGenerationSoon( int $delaySeconds, string $reason ): void {
		$args = [ 'reason' => $reason ];
		if ( null !== $this->backend->nextScheduled( self::HOOK_GENERATE, $args ) ) {
			return;
		}
		$this->backend->scheduleSingle( $this->clock->now()->getTimestamp() + max( 0, $delaySeconds ), self::HOOK_GENERATE, $args, false );
	}

	/**
	 * @param array<string,mixed> $args Hook args.
	 */
	public function enqueue( string $hook, array $args = [] ): void {
		$this->backend->enqueueAsync( $hook, $args );
	}

	/**
	 * @return array<string,int|null> Hook → next UTC timestamp.
	 */
	public function status(): array {
		$status = [];
		foreach ( self::HOOKS as $hook ) {
			$status[ $hook ] = $this->backend->nextScheduled( $hook, [] );
		}
		$status[ self::HOOK_AUTO_SNAPSHOT ] = null;
		foreach ( array_keys( $this->settings->section( 'reference_prices' ) ) as $key ) {
			$next = $this->backend->nextScheduled( self::HOOK_AUTO_SNAPSHOT, [ 'type' => (string) $key ] );
			if ( null !== $next ) {
				$status[ self::HOOK_AUTO_SNAPSHOT ] = $next;
			}
		}
		return $status;
	}

	public function registerWatchdog(): void {
		add_action( 'init', [ $this, 'watchdog' ], 20 );
	}

	/** Hourly self-check so a lost schedule (e.g. after a failed run) repairs itself. */
	public function watchdog(): void {
		if ( get_transient( self::WATCHDOG_TRANSIENT ) ) {
			return;
		}
		set_transient( self::WATCHDOG_TRANSIENT, 1, HOUR_IN_SECONDS );
		$this->ensureScheduled();
	}

	/**
	 * @param array<string,mixed> $args Hook args.
	 */
	private function ensure( string $hook, array $args, int $timestamp ): void {
		if ( null === $this->backend->nextScheduled( $hook, $args ) ) {
			$this->backend->scheduleSingle( $timestamp, $hook, $args, false );
		}
	}
}
