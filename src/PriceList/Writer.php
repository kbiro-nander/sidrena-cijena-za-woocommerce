<?php
/**
 * Price-list output format writer.
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

namespace SidrenaCijena\PriceList;

use DateTimeImmutable;
use SidrenaCijena\Reference\ReferencePriceType;

interface Writer {

	/**
	 * Write all rows to $path (created/overwritten).
	 *
	 * @param iterable<Item|ServiceItem> $items            Rows.
	 * @param Outlet                     $outlet           Sales outlet.
	 * @param DateTimeImmutable          $generatedAtLocal Generation time in the site's timezone.
	 * @param string                     $path             Absolute target path.
	 * @param ReferencePriceType[]       $types            Enabled reference types in registry order.
	 */
	public function write( iterable $items, Outlet $outlet, DateTimeImmutable $generatedAtLocal, string $path, array $types ): WriteStats;
}
