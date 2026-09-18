<?php
declare(strict_types=1);

namespace SidrenaCijena\Tests\Unit\History;

use Brain\Monkey\Actions;
use SidrenaCijena\History\OmnibusStateUpdater;
use SidrenaCijena\History\PriceChangeListener;
use SidrenaCijena\History\Recorder;
use SidrenaCijena\History\Transition;
use SidrenaCijena\Product\BarcodeResolver;
use SidrenaCijena\Product\BrandResolver;
use SidrenaCijena\Product\ProductAdapter;
use SidrenaCijena\Product\ProductSnapshot;
use SidrenaCijena\Tests\TestCase;

final class PriceChangeListenerTest extends TestCase {
	/** @var \Mockery\MockInterface&Recorder */
	private $recorder;
	/** @var \Mockery\MockInterface&OmnibusStateUpdater */
	private $updater;

	protected function setUp(): void {
		parent::setUp();
		$this->recorder = \Mockery::mock( Recorder::class );
		$this->updater  = \Mockery::mock( OmnibusStateUpdater::class );
	}

	private function listener(): PriceChangeListener {
		return new PriceChangeListener( new ProductAdapter( new BrandResolver(), new BarcodeResolver() ), $this->recorder, $this->updater, fn( int $id ) => \wc_get_product( $id ) ?: null );
	}

	public function test_registers_hooks(): void {
		Actions\expectAdded( 'woocommerce_product_object_updated_props' )->once()->with( \Mockery::type( 'callable' ), 10, 2 );
		Actions\expectAdded( 'woocommerce_new_product' )->once();
		Actions\expectAdded( 'woocommerce_new_product_variation' )->once();
		Actions\expectAdded( 'woocommerce_delete_product' )->once();
		$this->listener()->register();
	}

	public function test_price_props_trigger_record_update_and_action(): void {
		$p = $this->product( [ 'id' => 5, 'regular_price' => '20', 'sale_price' => '15' ] );
		$this->recorder->shouldReceive( 'record' )->once()->withArgs( fn( ProductSnapshot $s, string $source ) => 5 === $s->id && 'save' === $source )->andReturn( Transition::SALE_STARTED );
		$this->updater->shouldReceive( 'apply' )->once()->withArgs( fn( ProductSnapshot $s, string $t ) => Transition::SALE_STARTED === $t );
		Actions\expectDone( 'scwc_price_changed' )->once()->with( \Mockery::type( ProductSnapshot::class ), Transition::SALE_STARTED );
		$this->listener()->onUpdatedProps( $p, [ 'sale_price', 'price' ] );
	}

	public function test_scheduled_sale_props_also_count(): void {
		$p = $this->product( [ 'id' => 5, 'regular_price' => '20' ] );
		$this->recorder->shouldReceive( 'record' )->once()->andReturn( Transition::NONE );
		$this->updater->shouldReceive( 'apply' )->once();
		Actions\expectDone( 'scwc_price_changed' )->never();
		$this->listener()->onUpdatedProps( $p, [ 'date_on_sale_from' ] );
	}

	public function test_non_price_props_and_container_products_are_ignored(): void {
		$this->recorder->shouldNotReceive( 'record' );
		$this->listener()->onUpdatedProps( $this->product( [ 'id' => 5 ] ), [ 'name', 'stock_status' ] );
		$this->listener()->onUpdatedProps( $this->product( [ 'id' => 6, 'type' => 'variable' ] ), [ 'price' ] );
		$this->listener()->onUpdatedProps( $this->product( [ 'id' => 7, 'type' => 'grouped' ] ), [ 'price' ] );
	}

	public function test_variation_snapshot_gets_parent_loaded(): void {
		$this->product( [ 'id' => 1, 'type' => 'variable', 'category_ids' => [ 3 ] ] );
		$v = $this->product( [ 'id' => 2, 'type' => 'variation', 'parent_id' => 1, 'regular_price' => '9' ] );
		$this->recorder->shouldReceive( 'record' )->once()->withArgs( fn( ProductSnapshot $s ) => [ 3 ] === $s->categoryIds )->andReturn( Transition::CHANGED );
		$this->updater->shouldReceive( 'apply' )->once();
		$this->listener()->onUpdatedProps( $v, [ 'regular_price' ] );
	}

	public function test_new_product_seeds_history(): void {
		$p = $this->product( [ 'id' => 5, 'regular_price' => '20' ] );
		$this->recorder->shouldReceive( 'record' )->once()->withArgs( fn( ProductSnapshot $s, string $source ) => 'save' === $source )->andReturn( Transition::FIRST );
		$this->updater->shouldReceive( 'apply' )->once();
		$this->listener()->onNewProduct( 5 );
	}

	public function test_delete_fires_action_only(): void {
		Actions\expectDone( 'scwc_product_removed' )->once()->with( 5 );
		$this->listener()->onDeleted( 5 );
	}
}
