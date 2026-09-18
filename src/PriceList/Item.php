<?php
/**
 * Value object: one product row of the price list.
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

namespace SidrenaCijena\PriceList;

final class Item {

	/**
	 * @param array<string,array{amount:?float,date:?string}> $references Reference prices keyed by type key.
	 */
	public function __construct(
		public readonly string $name,
		public readonly string $sku,
		public readonly string $brand,
		public readonly string $unit,
		public readonly ?float $unitPrice,
		public readonly float $price,
		public readonly bool $isSpecialSale,
		public readonly string $saleName,
		public readonly array $references,
		public readonly ?float $lowest30,
		public readonly string $barcode,
		public readonly bool $available,
		public readonly string $url,
	) {}
}
