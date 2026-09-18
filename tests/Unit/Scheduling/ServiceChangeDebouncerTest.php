<?php
declare(strict_types=1);

namespace SidrenaCijena\Tests\Unit\Scheduling;

use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use SidrenaCijena\History\Transition;
use SidrenaCijena\Product\ProductSnapshot;
use SidrenaCijena\Product\ServiceRule;
use SidrenaCijena\Scheduling\ActionSchedulerBackend;
use SidrenaCijena\Scheduling\Scheduler;
use SidrenaCijena\Scheduling\ServiceChangeDebouncer;
use SidrenaCijena\Settings\Defaults;
use SidrenaCijena\Settings\Settings;
use SidrenaCijena\Tests\Support\FixedClock;
use SidrenaCijena\Tests\TestCase;

final class ServiceChangeDebouncerTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		scwc_test_schedule_reset();
	}

	private function debouncer( string $mode = 'services', bool $enabled = true ): ServiceChangeDebouncer {
		$settings  = ( new Settings( Defaults::all() ) )->with( 'price_list.regenerate_on_change', $mode )->with( 'price_list.enabled', $enabled );
		$scheduler = new Scheduler( new ActionSchedulerBackend(), $settings, new FixedClock( '2026-10-01 03:00:00' ) );
		return new ServiceChangeDebouncer( $scheduler, $settings, new ServiceRule( 'virtual_or_flag' ) );
	}

	private function generated(): array {
		return array_values( array_filter( $GLOBALS['scwc_test_schedule']['single'], fn( $s ) => Scheduler::HOOK_GENERATE === $s['hook'] ) );
	}

	public function test_registers_listeners(): void {
		Actions\expectAdded( 'scwc_price_changed' )->once()->with( \Mockery::type( 'callable' ), 10, 2 );
		Actions\expectAdded( 'scwc_product_removed' )->once();
		Actions\expectAdded( 'scwc_snapshot_completed' )->once();
		Actions\expectAdded( 'scwc_import_completed' )->once();
		$this->debouncer()->register();
	}

	public function test_service_change_schedules_debounced_generation(): void {
		Functions\expect( 'update_option' )->once()->with( 'scwc_services_dirty_at', \Mockery::type( 'int' ), false );
		$this->debouncer()->onPriceChanged( ProductSnapshot::fromArray( [ 'id' => 1, 'isVirtual' => true ] ), Transition::CHANGED );
		$g = $this->generated();
		self::assertCount( 1, $g );
		self::assertSame( [ 'reason' => 'change' ], $g[0]['args'] );
		self::assertSame( strtotime( '2026-10-01 03:05:00 UTC' ), $g[0]['timestamp'], 'default debounce 300 s' );
	}

	public function test_goods_change_ignored_in_services_mode_but_not_in_all_mode(): void {
		$goods = ProductSnapshot::fromArray( [ 'id' => 1, 'isVirtual' => false ] );
		$this->debouncer( 'services' )->onPriceChanged( $goods, Transition::CHANGED );
		self::assertCount( 0, $this->generated() );
		Functions\when( 'update_option' )->justReturn( true );
		$this->debouncer( 'all' )->onPriceChanged( $goods, Transition::CHANGED );
		self::assertCount( 1, $this->generated() );
	}

	public function test_never_mode_and_disabled_price_list_do_nothing(): void {
		$service = ProductSnapshot::fromArray( [ 'id' => 1, 'isVirtual' => true ] );
		$this->debouncer( 'never' )->onPriceChanged( $service, Transition::CHANGED );
		$this->debouncer( 'services', false )->onPriceChanged( $service, Transition::CHANGED );
		self::assertCount( 0, $this->generated() );
	}

	public function test_bulk_events_always_touch(): void {
		Functions\when( 'update_option' )->justReturn( true );
		$this->debouncer()->onBulkChange();
		$this->debouncer()->onProductRemoved( 5 );
		self::assertCount( 1, $this->generated(), 'both touches collapse into one pending on-change run' );
	}
}
