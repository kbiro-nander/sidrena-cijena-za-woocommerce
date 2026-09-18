<?php
/**
 * Price-history table access.
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

namespace SidrenaCijena\History;

use DateTimeImmutable;
use DateTimeZone;

final class PriceHistoryRepository {

	private const DT = 'Y-m-d H:i:s';

	/**
	 * @param \wpdb|object $wpdb  Database.
	 * @param string       $table Fully-prefixed table name.
	 */
	public function __construct( private readonly object $wpdb, private readonly string $table ) {}

	public function insert( PriceRecord $r ): int {
		$this->wpdb->insert(
			$this->table,
			[
				'product_id'    => $r->productId,
				'parent_id'     => $r->parentId,
				'regular_price' => null === $r->regular ? null : number_format( $r->regular, 4, '.', '' ),
				'sale_price'    => null === $r->sale ? null : number_format( $r->sale, 4, '.', '' ),
				'active_price'  => number_format( $r->active, 4, '.', '' ),
				'is_on_sale'    => $r->isOnSale ? 1 : 0,
				'source'        => $r->source,
				'recorded_at'   => $r->recordedAt->setTimezone( new DateTimeZone( 'UTC' ) )->format( self::DT ),
			]
		);
		return (int) $this->wpdb->insert_id;
	}

	public function latestFor( int $productId ): ?PriceRecord {
		$row = $this->wpdb->get_row(
			$this->wpdb->prepare( "SELECT * FROM {$this->table} WHERE product_id = %d ORDER BY recorded_at DESC, id DESC LIMIT 1", $productId ),
			ARRAY_A
		);
		return is_array( $row ) ? PriceRecord::fromRow( $row ) : null;
	}

	public function lowestActiveBetween( int $productId, DateTimeImmutable $from, DateTimeImmutable $to ): ?float {
		$q   = LowestPriceQuery::build( $this->table, $productId, $this->utc( $from ), $this->utc( $to ) );
		$val = $this->wpdb->get_var( $this->wpdb->prepare( $q['sql'], $q['params'] ) );
		return null === $val || '' === $val ? null : (float) $val;
	}

	public function earliestRecordedAt( int $productId ): ?DateTimeImmutable {
		$val = $this->wpdb->get_var( $this->wpdb->prepare( "SELECT MIN(recorded_at) FROM {$this->table} WHERE product_id = %d", $productId ) );
		return null === $val || '' === $val ? null : new DateTimeImmutable( (string) $val, new DateTimeZone( 'UTC' ) );
	}

	/**
	 * @return PriceRecord[] Newest first.
	 */
	public function historyFor( int $productId, int $limit = 100 ): array {
		$rows = $this->wpdb->get_results(
			$this->wpdb->prepare( "SELECT * FROM {$this->table} WHERE product_id = %d ORDER BY recorded_at DESC, id DESC LIMIT %d", $productId, $limit ),
			ARRAY_A
		);
		return array_map( [ PriceRecord::class, 'fromRow' ], is_array( $rows ) ? $rows : [] );
	}

	/** Delete rows older than $before, but always keep the newest row of every product. */
	public function pruneBefore( DateTimeImmutable $before ): void {
		$this->wpdb->query(
			$this->wpdb->prepare(
				"DELETE h FROM {$this->table} h
LEFT JOIN (SELECT MAX(id) AS keep_id FROM {$this->table} GROUP BY product_id) k ON k.keep_id = h.id
WHERE k.keep_id IS NULL AND h.recorded_at < %s",
				$this->utc( $before )
			)
		);
	}

	public function deleteFor( int $productId ): void {
		$this->wpdb->query( $this->wpdb->prepare( "DELETE FROM {$this->table} WHERE product_id = %d", $productId ) );
	}

	public function count(): int {
		return (int) $this->wpdb->get_var( "SELECT COUNT(*) FROM {$this->table}" );
	}

	private function utc( DateTimeImmutable $d ): string {
		return $d->setTimezone( new DateTimeZone( 'UTC' ) )->format( self::DT );
	}
}
