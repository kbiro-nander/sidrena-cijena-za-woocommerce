<?php
/**
 * Result of a 30-day lowest-price lookup.
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

namespace SidrenaCijena\History;

final class LowestResult {
	public const HISTORY          = 'history';
	public const PARTIAL          = 'partial';
	public const REGULAR_FALLBACK = 'regular_fallback';
	public const INSUFFICIENT     = 'insufficient';

	public function __construct( public readonly ?float $amount, public readonly string $source ) {}
}
