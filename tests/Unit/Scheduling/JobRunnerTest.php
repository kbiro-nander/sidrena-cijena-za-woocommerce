<?php
declare(strict_types=1);

namespace SidrenaCijena\Tests\Unit\Scheduling;

use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use SidrenaCijena\History\SweepResult;
use SidrenaCijena\Scheduling\ActionSchedulerBackend;
use SidrenaCijena\Scheduling\JobRunner;
use SidrenaCijena\Scheduling\Scheduler;
use SidrenaCijena\Settings\Defaults;
use SidrenaCijena\Settings\Settings;
use SidrenaCijena\Tests\Support\FixedClock;
use SidrenaCijena\Tests\TestCase;

final class JobRunnerTest extends TestCase {
	private array $calls = [];
	private Scheduler $scheduler;

	protected function setUp(): void {
		parent::setUp();
		scwc_test_schedule_reset();
		$this->calls     = [];
		$this->scheduler = new Scheduler( new ActionSchedulerBackend(), new Settings( Defaults::all() ), new FixedClock( '2026-10-01 03:00:00' ) );
	}

	private function runner( bool $sweepHasMore = false ): JobRunner {
		return new JobRunner(
			$this->scheduler,
			function ( string $reason ) { $this->calls[] = "generate:{$reason}"; },
			function ( int $page ) use ( $sweepHasMore ) { $this->calls[] = "sweep:{$page}"; return new SweepResult( 10, 1, $sweepHasMore && $page < 3 ); },
			function () { $this->calls[] = 'prune'; },
			function ( string $type ) { $this->calls[] = "snapshot:{$type}"; },
		);
	}

	public function test_registers_all_job_hooks(): void {
		Actions\expectAdded( Scheduler::HOOK_GENERATE )->once();
		Actions\expectAdded( Scheduler::HOOK_SWEEP )->once();
		Actions\expectAdded( Scheduler::HOOK_SWEEP_PAGE )->once();
		Actions\expectAdded( Scheduler::HOOK_PRUNE )->once();
		Actions\expectAdded( Scheduler::HOOK_AUTO_SNAPSHOT )->once();
		$this->runner()->register();
	}

	public function test_generate_runs_and_reschedules_daily_job(): void {
		$this->runner()->generate( 'scheduled' );
		self::assertSame( [ 'generate:scheduled' ], $this->calls );
		self::assertContains( Scheduler::HOOK_GENERATE, array_column( $GLOBALS['scwc_test_schedule']['single'], 'hook' ) );
	}

	public function test_sweep_chains_pages_until_done_then_reschedules(): void {
		$r = $this->runner( true );
		$r->sweep();
		self::assertSame( [ 'sweep:1' ], $this->calls );
		$async = $GLOBALS['scwc_test_schedule']['async'];
		self::assertSame( Scheduler::HOOK_SWEEP_PAGE, $async[0]['hook'] );
		self::assertSame( [ 'page' => 2 ], $async[0]['args'] );
		$r->sweepPage( 3 );
		self::assertSame( [ 'sweep:1', 'sweep:3' ], $this->calls );
		self::assertCount( 1, $GLOBALS['scwc_test_schedule']['async'], 'no further page enqueued when done' );
		self::assertContains( Scheduler::HOOK_SWEEP, array_column( $GLOBALS['scwc_test_schedule']['single'], 'hook' ) );
	}

	public function test_auto_snapshot_runs_and_marks_done(): void {
		Functions\expect( 'update_option' )->once()->withArgs( function ( string $option, $value ) {
			return 'scwc_settings' === $option && '' !== $value['reference_prices']['anchor']['auto_snapshot_done'];
		} );
		$this->runner()->autoSnapshot( 'anchor' );
		self::assertSame( [ 'snapshot:anchor' ], $this->calls );
	}

	public function test_prune_runs_and_reschedules(): void {
		$this->runner()->prune();
		self::assertSame( [ 'prune' ], $this->calls );
		self::assertContains( Scheduler::HOOK_PRUNE, array_column( $GLOBALS['scwc_test_schedule']['single'], 'hook' ) );
	}
}
