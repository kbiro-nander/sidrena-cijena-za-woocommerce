<?php
/**
 * Records a product's current prices when they differ from the last known row.
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

namespace SidrenaCijena\History;

use SidrenaCijena\Product\ProductSnapshot;
use SidrenaCijena\Support\Clock;

class Recorder {

	public function __construct( private readonly PriceHistoryRepository $repo, private readonly Clock $clock ) {}

	/**
	 * @return string One of Transition::*.
	 */
	public function record( ProductSnapshot $p, string $source ): string {
		if ( '' === $p->active || ! is_numeric( $p->active ) ) {
			return Transition::NONE;
		}
		$now  = new PriceRecord(
			$p->id,
			$p->parentId,
			is_numeric( $p->regular ) ? (float) $p->regular : null,
			is_numeric( $p->sale ) ? (float) $p->sale : null,
			(float) $p->active,
			$p->isOnSale,
			$source,
			$this->clock->now(),
		);
		$prev = $this->repo->latestFor( $p->id );
		if ( null === $prev ) {
			$this->repo->insert( $now );
			return Transition::FIRST;
		}
		if ( $prev->samePricesAs( $now ) ) {
			return Transition::NONE;
		}
		$this->repo->insert( $now );
		if ( ! $prev->isOnSale && $now->isOnSale ) {
			return Transition::SALE_STARTED;
		}
		if ( $prev->isOnSale && ! $now->isOnSale ) {
			return Transition::SALE_ENDED;
		}
		if ( $prev->isOnSale && $now->isOnSale && $now->active < $prev->active ) {
			return Transition::SALE_LOWERED;
		}
		return Transition::CHANGED;
	}
}
