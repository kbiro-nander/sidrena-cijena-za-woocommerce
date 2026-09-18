<?php
declare(strict_types=1);

namespace SidrenaCijena\Tests\Unit\History;

use DateTimeImmutable;
use DateTimeZone;
use SidrenaCijena\History\LowestPriceQuery;
use SidrenaCijena\History\PriceHistoryRepository;
use SidrenaCijena\History\PriceRecord;
use SidrenaCijena\Tests\Support\FakeWpdb;
use SidrenaCijena\Tests\TestCase;

final class PriceHistoryRepositoryTest extends TestCase {
	private FakeWpdb $wpdb;

	protected function setUp(): void {
		parent::setUp();
		$this->wpdb = new FakeWpdb();
	}

	private function repo(): PriceHistoryRepository {
		return new PriceHistoryRepository( $this->wpdb, 'wp_scwc_price_history' );
	}

	private function utc( string $s ): DateTimeImmutable {
		return new DateTimeImmutable( $s, new DateTimeZone( 'UTC' ) );
	}

	public function test_insert_writes_all_columns_in_utc(): void {
		$record = new PriceRecord( 5, 2, 20.0, 15.0, 15.0, true, 'save', $this->utc( '2026-10-01 06:00:00' ) );
		$id     = $this->repo()->insert( $record );
		self::assertSame( 1, $id );
		self::assertSame( [ 'product_id' => 5, 'parent_id' => 2, 'regular_price' => '20.0000', 'sale_price' => '15.0000', 'active_price' => '15.0000', 'is_on_sale' => 1, 'source' => 'save', 'recorded_at' => '2026-10-01 06:00:00' ], $this->wpdb->inserts[0] );
	}

	public function test_latest_for_maps_row_to_record(): void {
		$this->wpdb->rowResults[] = [ 'id' => '9', 'product_id' => '5', 'parent_id' => '0', 'regular_price' => '20.0000', 'sale_price' => null, 'active_price' => '20.0000', 'is_on_sale' => '0', 'source' => 'seed', 'recorded_at' => '2026-09-20 00:30:00' ];
		$rec = $this->repo()->latestFor( 5 );
		self::assertSame( 9, $rec->id );
		self::assertSame( 20.0, $rec->regular );
		self::assertNull( $rec->sale );
		self::assertFalse( $rec->isOnSale );
		self::assertSame( 'seed', $rec->source );
		self::assertSame( '2026-09-20 00:30:00', $rec->recordedAt->format( 'Y-m-d H:i:s' ) );
		self::assertStringContainsString( 'WHERE product_id = 5 ORDER BY recorded_at DESC, id DESC LIMIT 1', $this->wpdb->queries[0] );
		$this->wpdb->rowResults[] = null;
		self::assertNull( $this->repo()->latestFor( 6 ) );
	}

	public function test_lowest_query_has_window_and_carry_in(): void {
		$built = LowestPriceQuery::build( 'wp_scwc_price_history', 5, '2026-09-01 10:00:00', '2026-10-01 10:00:00' );
		$sql   = $this->wpdb->prepare( $built['sql'], $built['params'] );
		self::assertStringContainsString( "recorded_at >= '2026-09-01 10:00:00' AND recorded_at < '2026-10-01 10:00:00'", $sql );
		self::assertStringContainsString( 'UNION ALL', $sql );
		self::assertStringContainsString( "recorded_at < '2026-09-01 10:00:00' ORDER BY recorded_at DESC, id DESC LIMIT 1", $sql, 'carry-in row: last change before the window' );
		self::assertStringStartsWith( 'SELECT MIN(active_price)', $sql );
	}

	public function test_lowest_active_between_returns_float_or_null(): void {
		$this->wpdb->varResults[] = '12.5000';
		self::assertSame( 12.5, $this->repo()->lowestActiveBetween( 5, $this->utc( '2026-09-01' ), $this->utc( '2026-10-01' ) ) );
		$this->wpdb->varResults[] = null;
		self::assertNull( $this->repo()->lowestActiveBetween( 5, $this->utc( '2026-09-01' ), $this->utc( '2026-10-01' ) ) );
	}

	public function test_earliest_recorded_at(): void {
		$this->wpdb->varResults[] = '2026-09-20 00:30:00';
		self::assertSame( '2026-09-20 00:30:00', $this->repo()->earliestRecordedAt( 5 )?->format( 'Y-m-d H:i:s' ) );
		self::assertStringContainsString( 'MIN(recorded_at)', $this->wpdb->queries[0] );
	}

	public function test_prune_keeps_latest_row_per_product(): void {
		$this->repo()->pruneBefore( $this->utc( '2025-08-01 00:00:00' ) );
		$sql = $this->wpdb->queries[0];
		self::assertStringStartsWith( 'DELETE h FROM wp_scwc_price_history h', $sql );
		self::assertStringContainsString( "recorded_at < '2025-08-01 00:00:00'", $sql );
		self::assertStringContainsString( 'MAX(id)', $sql );
		self::assertStringContainsString( 'GROUP BY product_id', $sql );
	}

	public function test_delete_for_product_and_count(): void {
		$this->repo()->deleteFor( 5 );
		self::assertStringContainsString( 'DELETE FROM wp_scwc_price_history WHERE product_id = 5', $this->wpdb->queries[0] );
		$this->wpdb->varResults[] = '42';
		self::assertSame( 42, $this->repo()->count() );
	}

	public function test_history_for_product_returns_records_newest_first(): void {
		$this->wpdb->resultsResults[] = [
			[ 'id' => '2', 'product_id' => '5', 'parent_id' => '0', 'regular_price' => '20.0000', 'sale_price' => '15.0000', 'active_price' => '15.0000', 'is_on_sale' => '1', 'source' => 'save', 'recorded_at' => '2026-10-02 00:00:00' ],
			[ 'id' => '1', 'product_id' => '5', 'parent_id' => '0', 'regular_price' => '20.0000', 'sale_price' => null, 'active_price' => '20.0000', 'is_on_sale' => '0', 'source' => 'seed', 'recorded_at' => '2026-09-01 00:00:00' ],
		];
		$rows = $this->repo()->historyFor( 5, 60 );
		self::assertCount( 2, $rows );
		self::assertSame( 15.0, $rows[0]->active );
		self::assertStringContainsString( 'ORDER BY recorded_at DESC, id DESC LIMIT 60', $this->wpdb->queries[0] );
	}
}
