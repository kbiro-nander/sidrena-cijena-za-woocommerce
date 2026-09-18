<?php
/**
 * Value object: one service ("usluga") row of the price list.
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

namespace SidrenaCijena\PriceList;

final class ServiceItem {

	/**
	 * @param array<string,array{amount:?float,date:?string}> $references Reference prices keyed by type key.
	 */
	public function __construct(
		public readonly string $name,
		public readonly float $price,
		public readonly bool $isSpecialSale,
		public readonly string $saleName,
		public readonly array $references,
	) {}
}
