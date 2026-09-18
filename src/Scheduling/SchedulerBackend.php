<?php
/**
 * Abstraction over Action Scheduler / WP-Cron.
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

namespace SidrenaCijena\Scheduling;

interface SchedulerBackend {

	/**
	 * @param array<string,mixed> $args Hook arguments.
	 */
	public function scheduleSingle( int $timestamp, string $hook, array $args, bool $unique ): void;

	/**
	 * @param array<string,mixed> $args Hook arguments.
	 */
	public function enqueueAsync( string $hook, array $args ): void;

	/**
	 * @param array<string,mixed> $args Hook arguments.
	 */
	public function nextScheduled( string $hook, array $args ): ?int;

	public function unscheduleAll( string $hook ): void;

	public function name(): string;
}
