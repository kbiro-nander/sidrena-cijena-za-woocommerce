<?php
/**
 * Removes history older than the retention period (never the newest row per product).
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

namespace SidrenaCijena\History;

use SidrenaCijena\Settings\Settings;
use SidrenaCijena\Support\Clock;

final class Pruner {

	public function __construct(
		private readonly PriceHistoryRepository $repo,
		private readonly Settings $settings,
		private readonly Clock $clock,
	) {}

	public function run(): void {
		$days = max( 31, (int) $this->settings->get( 'history.retention_days', 400 ) );
		$this->repo->pruneBefore( $this->clock->now()->modify( "-{$days} days" ) );
	}
}
