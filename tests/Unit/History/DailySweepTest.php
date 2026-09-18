<?php
declare(strict_types=1);

namespace SidrenaCijena\Tests\Unit\History;

use SidrenaCijena\History\DailySweep;
use SidrenaCijena\History\OmnibusStateUpdater;
use SidrenaCijena\History\Recorder;
use SidrenaCijena\History\Transition;
use SidrenaCijena\Product\BarcodeResolver;
use SidrenaCijena\Product\BrandResolver;
use SidrenaCijena\Product\ProductAdapter;
use SidrenaCijena\Tests\TestCase;

final class DailySweepTest extends TestCase {
	public function test_processes_a_page_and_reports_whether_more_remain(): void {
		$ids = [ 1, 2, 3, 4, 5 ];
		foreach ( $ids as $id ) {
			$this->product( [ 'id' => $id, 'regular_price' => '10' ] );
		}
		$recorder = \Mockery::mock( Recorder::class );
		$recorder->shouldReceive( 'record' )->times( 5 )->withArgs( fn( $s, string $source ) => 'sweep' === $source )->andReturn( Transition::NONE, Transition::CHANGED, Transition::NONE, Transition::NONE, Transition::SALE_STARTED );
		$updater = \Mockery::mock( OmnibusStateUpdater::class );
		$updater->shouldReceive( 'apply' )->times( 5 );

		$pages = [ 1 => [ 1, 2, 3 ], 2 => [ 4, 5 ], 3 => [] ];
		$sweep = new DailySweep(
			fn( int $page, int $perPage ) => $pages[ $page ] ?? [],
			fn( int $id ) => \wc_get_product( $id ) ?: null,
			new ProductAdapter( new BrandResolver(), new BarcodeResolver() ),
			$recorder,
			$updater,
		);
		$r1 = $sweep->run( 1, 3 );
		self::assertSame( 3, $r1->processed );
		self::assertSame( 1, $r1->changed );
		self::assertTrue( $r1->hasMore );
		$r2 = $sweep->run( 2, 3 );
		self::assertSame( 2, $r2->processed );
		self::assertSame( 1, $r2->changed );
		self::assertFalse( $r2->hasMore );
	}

	public function test_missing_products_are_skipped(): void {
		$recorder = \Mockery::mock( Recorder::class );
		$recorder->shouldNotReceive( 'record' );
		$updater = \Mockery::mock( OmnibusStateUpdater::class );
		$sweep   = new DailySweep( fn() => [ 999 ], fn( int $id ) => null, new ProductAdapter( new BrandResolver(), new BarcodeResolver() ), $recorder, $updater );
		self::assertSame( 0, $sweep->run( 1, 10 )->processed );
	}
}
