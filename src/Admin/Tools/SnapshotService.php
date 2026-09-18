<?php
/**
 * Copies each product's current REGULAR price into a reference-price type, one page at a time.
 *
 * Sale prices are never copied: the anchor is the regular price on the reference date.
 * Products created after the reference date may instead be flagged "no reference price".
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

namespace SidrenaCijena\Admin\Tools;

use DateTimeZone;
use InvalidArgumentException;
use SidrenaCijena\History\Recorder;
use SidrenaCijena\Product\ProductAdapter;
use SidrenaCijena\Product\ProductSnapshot;
use SidrenaCijena\Reference\ReferencePriceRegistry;
use SidrenaCijena\Reference\ReferencePriceRepository;
use SidrenaCijena\Reference\ReferencePriceType;
use SidrenaCijena\Support\DateFormat;
use SidrenaCijena\Support\Money;
use WC_Product;

class SnapshotService {

	/** @var callable(int,int):int[] */
	private $pager;
	/** @var callable(int):?WC_Product */
	private $loader;

	/**
	 * @param callable(int,int):int[]    $pager  Returns published simple/variation/external IDs for (page, perPage).
	 * @param callable(int):?WC_Product $loader Product loader.
	 */
	public function __construct(
		private readonly ReferencePriceRegistry $registry,
		private readonly ReferencePriceRepository $repository,
		private readonly ProductAdapter $adapter,
		private readonly Recorder $recorder,
		callable $pager,
		callable $loader,
	) {
		$this->pager  = $pager;
		$this->loader = $loader;
	}

	/**
	 * @throws InvalidArgumentException When the requested type key is unknown.
	 */
	public function run( SnapshotRequest $req, int $page, int $perPage ): SnapshotResult {
		$type = $this->registry->get( $req->typeKey );
		if ( null === $type ) {
			throw new InvalidArgumentException( sprintf( 'Unknown reference price type "%s".', $req->typeKey ) );
		}

		$ids      = ( $this->pager )( $page, $perPage );
		$counters = [
			SnapshotResult::ACTION_WRITTEN          => 0,
			SnapshotResult::ACTION_SKIPPED_EXISTING => 0,
			SnapshotResult::ACTION_SKIPPED_ON_SALE  => 0,
			SnapshotResult::ACTION_MARKED_NA        => 0,
			SnapshotResult::ACTION_SKIPPED_NO_PRICE => 0,
		];
		$processed = 0;
		$samples   = [];

		foreach ( $ids as $id ) {
			$product = ( $this->loader )( (int) $id );
			if ( ! $product instanceof WC_Product || in_array( $product->get_type(), [ 'variable', 'grouped' ], true ) ) {
				continue;
			}
			$parent = null;
			if ( 'variation' === $product->get_type() && $product->get_parent_id() ) {
				$parent = ( $this->loader )( $product->get_parent_id() );
			}
			$snapshot       = $this->adapter->fromProduct( $product, $parent );
			$parentSnapshot = $parent instanceof WC_Product ? $this->adapter->fromProduct( $parent ) : null;

			$action = $this->process( $req, $type, $snapshot, $parentSnapshot );
			++$processed;
			++$counters[ $action ];
			if ( count( $samples ) < SnapshotResult::MAX_SAMPLES ) {
				$samples[] = [
					'id'      => $snapshot->id,
					'sku'     => $snapshot->sku,
					'name'    => $snapshot->name,
					'regular' => $snapshot->regular,
					'action'  => $action,
				];
			}
		}

		return new SnapshotResult(
			$processed,
			$counters[ SnapshotResult::ACTION_WRITTEN ],
			$counters[ SnapshotResult::ACTION_SKIPPED_EXISTING ],
			$counters[ SnapshotResult::ACTION_SKIPPED_ON_SALE ],
			$counters[ SnapshotResult::ACTION_MARKED_NA ],
			$counters[ SnapshotResult::ACTION_SKIPPED_NO_PRICE ],
			$perPage > 0 && count( $ids ) >= $perPage,
			$samples,
		);
	}

	/**
	 * @return string One of SnapshotResult::ACTION_*.
	 */
	private function process( SnapshotRequest $req, ReferencePriceType $type, ProductSnapshot $snapshot, ?ProductSnapshot $parent ): string {
		$existing = $this->repository->get( $type, $snapshot, $parent );
		if ( SnapshotRequest::MODE_ONLY_MISSING === $req->mode && ( $existing->isPresent() || $existing->na ) ) {
			return SnapshotResult::ACTION_SKIPPED_EXISTING;
		}

		$referenceDate = $req->date ?? $existing->date?->format( DateFormat::ISO ) ?? $type->defaultDate;
		if ( $req->markNaAfterDate && null !== $referenceDate && $this->createdAfter( $snapshot, $referenceDate ) ) {
			if ( ! $req->dryRun ) {
				$this->repository->setNa( $type, $snapshot->id, ReferencePriceRepository::SOURCE_SNAPSHOT );
			}
			return SnapshotResult::ACTION_MARKED_NA;
		}

		if ( ! Money::isPositive( $snapshot->regular ) ) {
			return SnapshotResult::ACTION_SKIPPED_NO_PRICE;
		}
		if ( $req->skipOnSale && $snapshot->isOnSale ) {
			return SnapshotResult::ACTION_SKIPPED_ON_SALE;
		}

		if ( ! $req->dryRun ) {
			$this->repository->set( $type, $snapshot->id, (string) wc_format_decimal( $snapshot->regular ), $req->date, ReferencePriceRepository::SOURCE_SNAPSHOT );
			$this->recorder->record( $snapshot, ReferencePriceRepository::SOURCE_SNAPSHOT );
		}
		return SnapshotResult::ACTION_WRITTEN;
	}

	/** Compare the site-local calendar day of creation (createdAt is UTC) with the reference date. */
	private function createdAfter( ProductSnapshot $snapshot, string $referenceDate ): bool {
		if ( null === $snapshot->createdAt ) {
			return false;
		}
		$tz = wp_timezone();
		if ( ! $tz instanceof DateTimeZone ) {
			$tz = new DateTimeZone( 'UTC' );
		}
		return $snapshot->createdAt->setTimezone( $tz )->format( DateFormat::ISO ) > $referenceDate;
	}
}
