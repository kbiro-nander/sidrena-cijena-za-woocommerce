<?php
/**
 * Binds scheduled hooks to the services that do the work (injected as callables to keep this decoupled).
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

namespace SidrenaCijena\Scheduling;

use SidrenaCijena\History\SweepResult;
use SidrenaCijena\Settings\Settings;

final class JobRunner {

	/** @var callable(string):void */
	private $generate;
	/** @var callable(int):SweepResult */
	private $sweep;
	/** @var callable():void */
	private $prune;
	/** @var callable(string):void */
	private $snapshot;

	/**
	 * @param callable(string):void     $generate Runs price-list generation for a reason.
	 * @param callable(int):SweepResult $sweep    Runs one sweep page.
	 * @param callable():void           $prune    Prunes history.
	 * @param callable(string):void     $snapshot Runs an auto-snapshot for a reference type key.
	 */
	public function __construct(
		private readonly Scheduler $scheduler,
		callable $generate,
		callable $sweep,
		callable $prune,
		callable $snapshot,
	) {
		$this->generate = $generate;
		$this->sweep    = $sweep;
		$this->prune    = $prune;
		$this->snapshot = $snapshot;
	}

	public function register(): void {
		add_action( Scheduler::HOOK_GENERATE, [ $this, 'generate' ] );
		add_action( Scheduler::HOOK_SWEEP, [ $this, 'sweep' ] );
		add_action( Scheduler::HOOK_SWEEP_PAGE, [ $this, 'sweepPage' ] );
		add_action( Scheduler::HOOK_PRUNE, [ $this, 'prune' ] );
		add_action( Scheduler::HOOK_AUTO_SNAPSHOT, [ $this, 'autoSnapshot' ] );
	}

	/**
	 * @param mixed $reason Reason string (or args array from Action Scheduler).
	 */
	public function generate( $reason = 'scheduled' ): void {
		$reason = is_array( $reason ) ? (string) ( $reason['reason'] ?? 'scheduled' ) : (string) ( $reason ?: 'scheduled' );
		try {
			( $this->generate )( $reason );
		} finally {
			$this->scheduler->ensureScheduled();
		}
	}

	public function sweep(): void {
		$this->sweepPage( 1 );
	}

	/**
	 * @param mixed $page Page number.
	 */
	public function sweepPage( $page = 1 ): void {
		$page   = max( 1, (int) ( is_array( $page ) ? ( $page['page'] ?? 1 ) : $page ) );
		$result = ( $this->sweep )( $page );
		if ( $result->hasMore ) {
			$this->scheduler->enqueue( Scheduler::HOOK_SWEEP_PAGE, [ 'page' => $page + 1 ] );
			return;
		}
		$this->scheduler->ensureScheduled();
	}

	public function prune(): void {
		try {
			( $this->prune )();
		} finally {
			$this->scheduler->ensureScheduled();
		}
	}

	/**
	 * @param mixed $type Reference type key (or args array).
	 */
	public function autoSnapshot( $type = 'anchor' ): void {
		$type = is_array( $type ) ? (string) ( $type['type'] ?? 'anchor' ) : (string) $type;
		( $this->snapshot )( $type );
		// Mark done so the scheduler does not re-run it.
		$settings = Settings::fromOption();
		$at       = (string) $settings->get( "reference_prices.{$type}.auto_snapshot_at", '' );
		update_option( Settings::OPTION, $settings->with( "reference_prices.{$type}.auto_snapshot_done", '' !== $at ? $at : gmdate( 'Y-m-d H:i' ) )->all() );
	}
}
