<?php
declare(strict_types=1);

namespace SidrenaCijena\Tests\Unit\Scheduling;

use SidrenaCijena\Scheduling\ActionSchedulerBackend;
use SidrenaCijena\Scheduling\SchedulerBackend;
use SidrenaCijena\Scheduling\WpCronBackend;
use SidrenaCijena\Tests\TestCase;

final class BackendsTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		scwc_test_schedule_reset();
	}

	public function test_action_scheduler_backend_uses_scwc_group(): void {
		$b = new ActionSchedulerBackend();
		self::assertInstanceOf( SchedulerBackend::class, $b );
		$b->scheduleSingle( 123, 'h', [ 'a' => 1 ], true );
		self::assertSame( 'scwc', $GLOBALS['scwc_test_schedule']['single'][0]['group'] );
		self::assertSame( 123, $b->nextScheduled( 'h', [ 'a' => 1 ] ) );
		self::assertNull( $b->nextScheduled( 'h', [ 'a' => 2 ] ) );
		\Brain\Monkey\Functions\when( 'as_next_scheduled_action' )->justReturn( true );
		self::assertNull( $b->nextScheduled( 'h', [ 'a' => 1 ] ), 'in-progress (true) is not a future occurrence' );
		$b->enqueueAsync( 'h2', [] );
		self::assertSame( 'h2', $GLOBALS['scwc_test_schedule']['async'][0]['hook'] );
		$b->unscheduleAll( 'h' );
		self::assertNull( $b->nextScheduled( 'h', [ 'a' => 1 ] ) );
		self::assertSame( 'action_scheduler', $b->name() );
	}

	public function test_wp_cron_backend_emulates_unique_and_async(): void {
		$b = new WpCronBackend();
		$b->scheduleSingle( 500, 'h', [ 'x' => 1 ], true );
		$b->scheduleSingle( 600, 'h', [ 'x' => 1 ], true );
		self::assertCount( 1, $GLOBALS['scwc_test_schedule']['wp_single'], 'unique = skip when already scheduled' );
		self::assertSame( 500, $b->nextScheduled( 'h', [ 'x' => 1 ] ) );
		$b->enqueueAsync( 'h3', [ 'p' => 2 ] );
		self::assertSame( 'h3', $GLOBALS['scwc_test_schedule']['wp_single'][1]['hook'] );
		$b->unscheduleAll( 'h' );
		self::assertNull( $b->nextScheduled( 'h', [ 'x' => 1 ] ) );
		self::assertSame( 'wp_cron', $b->name() );
	}
}
