<?php
declare(strict_types=1);

namespace SidrenaCijena\Tests\Unit\History;

use SidrenaCijena\History\PriceHistoryRepository;
use SidrenaCijena\History\PriceRecord;
use SidrenaCijena\History\Recorder;
use SidrenaCijena\History\Transition;
use SidrenaCijena\Product\ProductSnapshot;
use SidrenaCijena\Tests\Support\FixedClock;
use SidrenaCijena\Tests\Support\FakeWpdb;
use SidrenaCijena\Tests\TestCase;

final class RecorderTest extends TestCase {
	private FakeWpdb $wpdb;
	private Recorder $recorder;

	protected function setUp(): void {
		parent::setUp();
		$this->wpdb     = new FakeWpdb();
		$this->recorder = new Recorder( new PriceHistoryRepository( $this->wpdb, 'wp_scwc_price_history' ), new FixedClock( '2026-10-01 05:00:00' ) );
	}

	private function latest( ?float $regular, ?float $sale, float $active, bool $onSale ): void {
		$this->wpdb->rowResults[] = [ 'id' => '1', 'product_id' => '5', 'parent_id' => '0', 'regular_price' => $regular, 'sale_price' => $sale, 'active_price' => $active, 'is_on_sale' => $onSale ? '1' : '0', 'source' => 'save', 'recorded_at' => '2026-09-01 00:00:00' ];
	}

	private function snap( string $regular, string $sale, bool $onSale ): ProductSnapshot {
		return ProductSnapshot::fromArray( [ 'id' => 5, 'regular' => $regular, 'sale' => $sale, 'active' => $onSale ? $sale : $regular, 'isOnSale' => $onSale ] );
	}

	public function test_first_row_is_inserted_with_source_and_clock_time(): void {
		$this->wpdb->rowResults[] = null;
		$t = $this->recorder->record( $this->snap( '20', '', false ), 'seed' );
		self::assertSame( Transition::FIRST, $t );
		self::assertSame( 'seed', $this->wpdb->inserts[0]['source'] );
		self::assertSame( '2026-10-01 05:00:00', $this->wpdb->inserts[0]['recorded_at'] );
		self::assertSame( '20.0000', $this->wpdb->inserts[0]['active_price'] );
		self::assertNull( $this->wpdb->inserts[0]['sale_price'] );
	}

	public function test_no_row_when_unchanged(): void {
		$this->latest( 20.0, null, 20.0, false );
		self::assertSame( Transition::NONE, $this->recorder->record( $this->snap( '20', '', false ), 'sweep' ) );
		self::assertSame( [], $this->wpdb->inserts );
	}

	public function test_detects_sale_started(): void {
		$this->latest( 20.0, null, 20.0, false );
		self::assertSame( Transition::SALE_STARTED, $this->recorder->record( $this->snap( '20', '15', true ), 'save' ) );
		self::assertCount( 1, $this->wpdb->inserts );
		self::assertSame( 1, $this->wpdb->inserts[0]['is_on_sale'] );
	}

	public function test_detects_sale_lowered_and_sale_ended(): void {
		$this->latest( 20.0, 15.0, 15.0, true );
		self::assertSame( Transition::SALE_LOWERED, $this->recorder->record( $this->snap( '20', '12', true ), 'save' ) );
		$this->latest( 20.0, 12.0, 12.0, true );
		self::assertSame( Transition::SALE_ENDED, $this->recorder->record( $this->snap( '20', '', false ), 'save' ) );
	}

	public function test_regular_price_change_is_plain_change(): void {
		$this->latest( 20.0, null, 20.0, false );
		self::assertSame( Transition::CHANGED, $this->recorder->record( $this->snap( '22', '', false ), 'save' ) );
		$this->latest( 20.0, 15.0, 15.0, true );
		self::assertSame( Transition::CHANGED, $this->recorder->record( $this->snap( '20', '16', true ), 'save' ), 'sale raised = change, not lowered' );
	}

	public function test_products_without_price_are_ignored(): void {
		self::assertSame( Transition::NONE, $this->recorder->record( ProductSnapshot::fromArray( [ 'id' => 5, 'regular' => '', 'active' => '' ] ), 'save' ) );
		self::assertSame( [], $this->wpdb->queries );
	}
}
