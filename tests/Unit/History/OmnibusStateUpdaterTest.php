<?php
declare(strict_types=1);

namespace SidrenaCijena\Tests\Unit\History;

use Brain\Monkey\Functions;
use DateTimeImmutable;
use DateTimeZone;
use SidrenaCijena\History\LowestPriceCalculator;
use SidrenaCijena\History\LowestResult;
use SidrenaCijena\History\OmnibusStateUpdater;
use SidrenaCijena\History\Transition;
use SidrenaCijena\Product\ProductSnapshot;
use SidrenaCijena\Settings\Defaults;
use SidrenaCijena\Settings\Settings;
use SidrenaCijena\Tests\Support\FixedClock;
use SidrenaCijena\Tests\TestCase;

final class OmnibusStateUpdaterTest extends TestCase {
	/** @var \Mockery\MockInterface&LowestPriceCalculator */
	private $calc;

	protected function setUp(): void {
		parent::setUp();
		$this->calc = \Mockery::mock( LowestPriceCalculator::class );
	}

	private function updater( string $fallback = 'regular' ): OmnibusStateUpdater {
		$settings = ( new Settings( Defaults::all() ) )->with( 'history.omnibus_fallback', $fallback );
		return new OmnibusStateUpdater( $this->calc, $settings, new FixedClock( '2026-10-01 10:00:00' ) );
	}

	private function snap( array $over = [] ): ProductSnapshot {
		return ProductSnapshot::fromArray( array_merge( [ 'id' => 5, 'regular' => '20', 'sale' => '15', 'active' => '15', 'isOnSale' => true ], $over ) );
	}

	public function test_sale_started_stores_reference_and_sale_start(): void {
		$this->calc->shouldReceive( 'forSaleStart' )->once()->withArgs( fn( int $id, DateTimeImmutable $start ) => 5 === $id && '2026-10-01 10:00:00' === $start->format( 'Y-m-d H:i:s' ) )->andReturn( new LowestResult( 12.0, LowestResult::HISTORY ) );
		Functions\expect( 'update_post_meta' )->once()->with( 5, '_scwc_omnibus_ref_price', '12.0000' );
		Functions\expect( 'update_post_meta' )->once()->with( 5, '_scwc_omnibus_sale_start', '2026-10-01 10:00:00' );
		Functions\expect( 'update_post_meta' )->once()->with( 5, '_scwc_omnibus_ref_source', 'history' );
		$this->updater()->apply( $this->snap(), Transition::SALE_STARTED );
	}

	public function test_scheduled_sale_uses_date_on_sale_from_when_in_the_past(): void {
		$from = new DateTimeImmutable( '2026-09-30 00:00:00', new DateTimeZone( 'UTC' ) );
		$this->calc->shouldReceive( 'forSaleStart' )->once()->withArgs( fn( int $id, DateTimeImmutable $start ) => '2026-09-30 00:00:00' === $start->format( 'Y-m-d H:i:s' ) )->andReturn( new LowestResult( 12.0, LowestResult::HISTORY ) );
		Functions\expect( 'update_post_meta' )->times( 3 );
		$this->updater()->apply( $this->snap( [ 'saleFrom' => $from ] ), Transition::SALE_STARTED );
	}

	public function test_insufficient_history_falls_back_to_regular_price_when_configured(): void {
		$this->calc->shouldReceive( 'forSaleStart' )->once()->andReturn( new LowestResult( null, LowestResult::INSUFFICIENT ) );
		Functions\expect( 'update_post_meta' )->once()->with( 5, '_scwc_omnibus_ref_price', '20.0000' );
		Functions\expect( 'update_post_meta' )->once()->with( 5, '_scwc_omnibus_sale_start', \Mockery::type( 'string' ) );
		Functions\expect( 'update_post_meta' )->once()->with( 5, '_scwc_omnibus_ref_source', 'regular_fallback' );
		$this->updater( 'regular' )->apply( $this->snap(), Transition::SALE_STARTED );
	}

	public function test_insufficient_history_clears_meta_when_fallback_is_hide(): void {
		$this->calc->shouldReceive( 'forSaleStart' )->once()->andReturn( new LowestResult( null, LowestResult::INSUFFICIENT ) );
		Functions\expect( 'delete_post_meta' )->times( 3 );
		$this->updater( 'hide' )->apply( $this->snap(), Transition::SALE_STARTED );
	}

	public function test_progressive_reduction_keeps_existing_reference(): void {
		$this->calc->shouldNotReceive( 'forSaleStart' );
		Functions\expect( 'update_post_meta' )->never();
		$this->updater()->apply( $this->snap( [ 'sale' => '12', 'active' => '12', 'meta' => [ '_scwc_omnibus_ref_price' => '15', '_scwc_omnibus_sale_start' => '2026-09-25 00:00:00' ] ] ), Transition::SALE_LOWERED );
	}

	public function test_on_sale_with_missing_meta_self_heals_on_any_transition(): void {
		$this->calc->shouldReceive( 'forSaleStart' )->once()->andReturn( new LowestResult( 14.0, LowestResult::PARTIAL ) );
		Functions\expect( 'update_post_meta' )->times( 3 );
		$this->updater()->apply( $this->snap(), Transition::NONE );
	}

	public function test_sale_ended_or_not_on_sale_clears_meta(): void {
		Functions\expect( 'delete_post_meta' )->times( 3 )->withArgs( fn( $id, $key ) => 5 === $id && str_starts_with( $key, '_scwc_omnibus_' ) );
		$this->updater()->apply( $this->snap( [ 'isOnSale' => false, 'active' => '20', 'meta' => [ '_scwc_omnibus_ref_price' => '15' ] ] ), Transition::SALE_ENDED );
	}

	public function test_not_on_sale_without_meta_does_nothing(): void {
		Functions\expect( 'delete_post_meta' )->never();
		$this->updater()->apply( $this->snap( [ 'isOnSale' => false, 'active' => '20' ] ), Transition::CHANGED );
	}
}
