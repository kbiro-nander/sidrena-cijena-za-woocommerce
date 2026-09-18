<?php
/**
 * Lowest price in the 30 days before a sale started (ZZP čl. 19).
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

namespace SidrenaCijena\History;

use DateTimeImmutable;

class LowestPriceCalculator {

	public const WINDOW_DAYS = 30;

	public function __construct( private readonly PriceHistoryRepository $repo ) {}

	public function forSaleStart( int $productId, DateTimeImmutable $saleStart ): LowestResult {
		$from   = $saleStart->modify( '-' . self::WINDOW_DAYS . ' days' );
		$lowest = $this->repo->lowestActiveBetween( $productId, $from, $saleStart );
		if ( null === $lowest ) {
			return new LowestResult( null, LowestResult::INSUFFICIENT );
		}
		$earliest = $this->repo->earliestRecordedAt( $productId );
		$complete = null !== $earliest && $earliest <= $from;
		return new LowestResult( $lowest, $complete ? LowestResult::HISTORY : LowestResult::PARTIAL );
	}
}
