<?php
/**
 * Outcome of one sweep page.
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

namespace SidrenaCijena\History;

final class SweepResult {
	public function __construct( public readonly int $processed, public readonly int $changed, public readonly bool $hasMore ) {}
}
