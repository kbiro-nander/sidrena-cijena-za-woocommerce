<?php
declare(strict_types=1);

namespace SidrenaCijena\Tests\Unit\History;

use DateTimeImmutable;
use DateTimeZone;
use SidrenaCijena\History\LowestPriceCalculator;
use SidrenaCijena\History\LowestResult;
use SidrenaCijena\History\PriceHistoryRepository;
use SidrenaCijena\Tests\Support\FakeWpdb;
use SidrenaCijena\Tests\TestCase;

final class LowestPriceCalculatorTest extends TestCase {
	private FakeWpdb $wpdb;

	protected function setUp(): void {
		parent::setUp();
		$this->wpdb = new FakeWpdb();
	}

	private function calc(): LowestPriceCalculator {
		return new LowestPriceCalculator( new PriceHistoryRepository( $this->wpdb, 'wp_scwc_price_history' ) );
	}

	private function utc( string $s ): DateTimeImmutable {
		return new DateTimeImmutable( $s, new DateTimeZone( 'UTC' ) );
	}

	public function test_full_coverage_yields_history_source(): void {
		$this->wpdb->varResults[] = '12.0000';           // lowest
		$this->wpdb->varResults[] = '2026-08-01 00:00:00'; // earliest row, before window start
		$r = $this->calc()->forSaleStart( 5, $this->utc( '2026-10-01 10:00:00' ) );
		self::assertSame( 12.0, $r->amount );
		self::assertSame( LowestResult::HISTORY, $r->source );
		self::assertStringContainsString( "recorded_at >= '2026-09-01 10:00:00'", $this->wpdb->queries[0], 'window = 30 days before sale start' );
	}

	public function test_partial_coverage_when_history_younger_than_30_days(): void {
		$this->wpdb->varResults[] = '12.0000';
		$this->wpdb->varResults[] = '2026-09-20 00:00:00';
		$r = $this->calc()->forSaleStart( 5, $this->utc( '2026-10-01 10:00:00' ) );
		self::assertSame( 12.0, $r->amount );
		self::assertSame( LowestResult::PARTIAL, $r->source );
	}

	public function test_no_rows_is_insufficient(): void {
		$this->wpdb->varResults[] = null;
		$r = $this->calc()->forSaleStart( 5, $this->utc( '2026-10-01 10:00:00' ) );
		self::assertNull( $r->amount );
		self::assertSame( LowestResult::INSUFFICIENT, $r->source );
	}
}
