<?php
/**
 * Value object: row counts produced by a writer.
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

namespace SidrenaCijena\PriceList;

final class WriteStats {

	public function __construct(
		public readonly int $products,
		public readonly int $services,
	) {}
}
