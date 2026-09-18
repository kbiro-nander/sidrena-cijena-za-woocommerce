<?php
declare(strict_types=1);

namespace SidrenaCijena\Tests\Unit\Scheduling;

use Brain\Monkey\Actions;
use SidrenaCijena\Scheduling\ActionSchedulerBackend;
use SidrenaCijena\Scheduling\Scheduler;
use SidrenaCijena\Settings\Defaults;
use SidrenaCijena\Settings\Settings;
use SidrenaCijena\Tests\Support\FixedClock;
use SidrenaCijena\Tests\TestCase;

final class SchedulerTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		scwc_test_schedule_reset();
	}

	private function scheduler( string $nowUtc, ?Settings $settings = null ): Scheduler {
		return new Scheduler( new ActionSchedulerBackend(), $settings ?? new Settings( Defaults::all() ), new FixedClock( $nowUtc ) );
	}

	public function test_next_run_is_today_when_time_still_ahead_in_local_time(): void {
		// 03:00 UTC = 05:00 Zagreb (CEST) → 06:00 local today = 04:00 UTC.
		$ts = $this->scheduler( '2026-10-01 03:00:00' )->nextRunTimestamp( '06:00' );
		self::assertSame( '2026-10-01 04:00:00', gmdate( 'Y-m-d H:i:s', $ts ) );
	}

	public function test_next_run_rolls_to_tomorrow_when_time_passed(): void {
		$ts = $this->scheduler( '2026-10-01 05:00:00' )->nextRunTimestamp( '06:00' );
		self::assertSame( '2026-10-02 04:00:00', gmdate( 'Y-m-d H:i:s', $ts ) );
	}

	public function test_dst_transition_keeps_local_time(): void {
		// 25 Oct 2026 Europe/Zagreb switches CEST→CET at 03:00. Run at 06:00 local on 26 Oct = 05:00 UTC.
		$ts = $this->scheduler( '2026-10-25 20:00:00' )->nextRunTimestamp( '06:00' );
		self::assertSame( '2026-10-26 05:00:00', gmdate( 'Y-m-d H:i:s', $ts ) );
		self::assertSame( '06:00', ( new \DateTimeImmutable( '@' . $ts ) )->setTimezone( new \DateTimeZone( 'Europe/Zagreb' ) )->format( 'H:i' ) );
	}

	public function test_ensure_scheduled_creates_generate_sweep_and_prune_actions_once(): void {
		$s = $this->scheduler( '2026-10-01 03:00:00' );
		$s->ensureScheduled();
		$hooks = array_column( $GLOBALS['scwc_test_schedule']['single'], 'hook' );
		self::assertSame( [ Scheduler::HOOK_GENERATE, Scheduler::HOOK_SWEEP, Scheduler::HOOK_PRUNE ], $hooks );
		self::assertFalse( $GLOBALS['scwc_test_schedule']['single'][0]['unique'], 'dedupe is by (hook,args) in Scheduler, not AS unique' );
		self::assertSame( 'scwc', $GLOBALS['scwc_test_schedule']['single'][0]['group'] );
		self::assertSame( '2026-10-01 04:00:00', gmdate( 'Y-m-d H:i:s', $GLOBALS['scwc_test_schedule']['single'][0]['timestamp'] ) );
		self::assertSame( '2026-10-01 22:30:00', gmdate( 'Y-m-d H:i:s', $GLOBALS['scwc_test_schedule']['single'][1]['timestamp'] ), 'sweep 00:30 local next day' );
		$s->ensureScheduled();
		self::assertCount( 3, $GLOBALS['scwc_test_schedule']['single'], 'idempotent' );
	}

	public function test_disabled_features_are_not_scheduled(): void {
		$settings = ( new Settings( Defaults::all() ) )->with( 'price_list.enabled', false )->with( 'history.enabled', false );
		$this->scheduler( '2026-10-01 03:00:00', $settings )->ensureScheduled();
		self::assertSame( [], array_column( $GLOBALS['scwc_test_schedule']['single'], 'hook' ) );
	}

	public function test_auto_snapshot_scheduled_for_enabled_types_with_pending_datetime(): void {
		$settings = ( new Settings( Defaults::all() ) )
			->with( 'reference_prices.base.enabled', true )
			->with( 'reference_prices.base.auto_snapshot_at', '2026-11-17 00:05' )
			->with( 'reference_prices.anchor.auto_snapshot_at', '2026-09-10 00:05' )
			->with( 'reference_prices.anchor.auto_snapshot_done', '2026-09-10 00:05' );
		$this->scheduler( '2026-10-01 03:00:00', $settings )->ensureScheduled();
		$snaps = array_values( array_filter( $GLOBALS['scwc_test_schedule']['single'], fn( $s ) => Scheduler::HOOK_AUTO_SNAPSHOT === $s['hook'] ) );
		self::assertCount( 1, $snaps, 'anchor already done, base pending' );
		self::assertSame( [ 'type' => 'base' ], $snaps[0]['args'] );
		self::assertSame( '2026-11-16 23:05:00', gmdate( 'Y-m-d H:i:s', $snaps[0]['timestamp'] ), '00:05 Zagreb (CET) = 23:05 UTC previous day' );
	}

	public function test_debounced_generation_coexists_with_daily_action(): void {
		$s = $this->scheduler( '2026-10-01 03:00:00' );
		$s->ensureScheduled();
		$s->scheduleGenerationSoon( 60, 'change' );
		$generate = array_values( array_filter( $GLOBALS['scwc_test_schedule']['single'], fn( $x ) => Scheduler::HOOK_GENERATE === $x['hook'] ) );
		self::assertCount( 2, $generate, 'daily ([]) and on-change ([reason]) both pending' );
	}

	public function test_two_reference_types_each_get_their_own_auto_snapshot(): void {
		$settings = ( new Settings( Defaults::all() ) )
			->with( 'reference_prices.anchor.auto_snapshot_at', '2026-10-05 00:05' )
			->with( 'reference_prices.base.enabled', true )
			->with( 'reference_prices.base.auto_snapshot_at', '2026-11-17 00:05' );
		$this->scheduler( '2026-10-01 03:00:00', $settings )->ensureScheduled();
		$snaps = array_values( array_filter( $GLOBALS['scwc_test_schedule']['single'], fn( $x ) => Scheduler::HOOK_AUTO_SNAPSHOT === $x['hook'] ) );
		self::assertCount( 2, $snaps );
		self::assertFalse( $snaps[0]['unique'] );
	}

	public function test_running_action_does_not_block_scheduling_the_next_occurrence(): void {
		// Action Scheduler reports `true` for an in-progress action; that must count as "nothing pending".
		\Brain\Monkey\Functions\when( 'as_next_scheduled_action' )->justReturn( true );
		$this->scheduler( '2026-10-01 03:00:00' )->ensureScheduled();
		self::assertContains( Scheduler::HOOK_GENERATE, array_column( $GLOBALS['scwc_test_schedule']['single'], 'hook' ) );
	}

	public function test_auto_snapshot_in_the_past_runs_immediately(): void {
		$settings = ( new Settings( Defaults::all() ) )->with( 'reference_prices.anchor.auto_snapshot_at', '2026-09-10 00:05' );
		$this->scheduler( '2026-10-01 03:00:00', $settings )->ensureScheduled();
		$snaps = array_values( array_filter( $GLOBALS['scwc_test_schedule']['single'], fn( $s ) => Scheduler::HOOK_AUTO_SNAPSHOT === $s['hook'] ) );
		self::assertCount( 1, $snaps );
		self::assertSame( strtotime( '2026-10-01 03:00:00 UTC' ), $snaps[0]['timestamp'], 'clamped to now' );
	}

	public function test_reschedule_clears_then_recreates(): void {
		$s = $this->scheduler( '2026-10-01 03:00:00' );
		$s->ensureScheduled();
		$s->reschedule();
		self::assertContains( Scheduler::HOOK_GENERATE, $GLOBALS['scwc_test_schedule']['unscheduled'] );
		self::assertCount( 3, $GLOBALS['scwc_test_schedule']['single'] );
	}

	public function test_status_reports_next_runs(): void {
		$s = $this->scheduler( '2026-10-01 03:00:00' );
		$s->ensureScheduled();
		$status = $s->status();
		self::assertSame( '2026-10-01 04:00:00', gmdate( 'Y-m-d H:i:s', $status[ Scheduler::HOOK_GENERATE ] ) );
		self::assertNull( $status[ Scheduler::HOOK_AUTO_SNAPSHOT ] );
	}

	public function test_watchdog_registers_init_hook_and_throttles_by_transient(): void {
		Actions\expectAdded( 'init' )->once();
		$s = $this->scheduler( '2026-10-01 03:00:00' );
		$s->registerWatchdog();
		unset( $GLOBALS['scwc_test_transients']['scwc_schedule_check'] );
		$s->watchdog();
		self::assertCount( 3, $GLOBALS['scwc_test_schedule']['single'] );
		scwc_test_schedule_reset();
		$s->watchdog();
		self::assertCount( 0, $GLOBALS['scwc_test_schedule']['single'], 'throttled within the hour' );
	}

	public function test_schedule_generation_soon_is_unique_and_delayed(): void {
		$s = $this->scheduler( '2026-10-01 03:00:00' );
		$s->scheduleGenerationSoon( 300, 'change' );
		$s->scheduleGenerationSoon( 300, 'change' );
		$single = $GLOBALS['scwc_test_schedule']['single'];
		self::assertCount( 1, $single, 'second call is deduplicated by (hook,args), not by Action Scheduler unique flag' );
		self::assertSame( [ 'reason' => 'change' ], $single[0]['args'] );
		self::assertFalse( $single[0]['unique'], 'AS unique ignores args and would collide with the daily action' );
		self::assertSame( strtotime( '2026-10-01 03:05:00 UTC' ), $single[0]['timestamp'] );
	}

	public function test_on_change_generation_before_08_00_never_slips_past_the_deadline(): void {
		// 05:58 UTC = 07:58 Zagreb (CEST): +300 s would be 08:03 → run immediately.
		$this->scheduler( '2026-10-01 05:58:00' )->scheduleGenerationSoon( 300, 'change' );
		self::assertSame( strtotime( '2026-10-01 05:58:00 UTC' ), $GLOBALS['scwc_test_schedule']['single'][0]['timestamp'] );
		scwc_test_schedule_reset();
		// 07:40 local + 5 min = 07:45 → still before the 07:55 safety margin, normal debounce.
		$this->scheduler( '2026-10-01 05:40:00' )->scheduleGenerationSoon( 300, 'change' );
		self::assertSame( strtotime( '2026-10-01 05:45:00 UTC' ), $GLOBALS['scwc_test_schedule']['single'][0]['timestamp'] );
		scwc_test_schedule_reset();
		// 10:00 local: deadline already passed for today, normal debounce.
		$this->scheduler( '2026-10-01 08:00:00' )->scheduleGenerationSoon( 300, 'change' );
		self::assertSame( strtotime( '2026-10-01 08:05:00 UTC' ), $GLOBALS['scwc_test_schedule']['single'][0]['timestamp'] );
	}
}
