<?php
declare(strict_types=1);

namespace SidrenaCijena\Tests\Unit\Admin\Tools;

use Brain\Monkey\Functions;
use SidrenaCijena\Admin\Tools\SnapshotRequest;
use SidrenaCijena\Admin\Tools\SnapshotResult;
use SidrenaCijena\Admin\Tools\SnapshotService;
use SidrenaCijena\History\Recorder;
use SidrenaCijena\History\Transition;
use SidrenaCijena\Product\BarcodeResolver;
use SidrenaCijena\Product\BrandResolver;
use SidrenaCijena\Product\ProductAdapter;
use SidrenaCijena\Reference\CategoryOverrideResolver;
use SidrenaCijena\Reference\ReferenceDateResolver;
use SidrenaCijena\Reference\ReferencePriceRegistry;
use SidrenaCijena\Reference\ReferencePriceRepository;
use SidrenaCijena\Reference\ReferencePriceType;
use SidrenaCijena\Tests\TestCase;

final class SnapshotServiceTest extends TestCase {
	private ReferencePriceRegistry $registry;
	/** @var Recorder&\Mockery\MockInterface */
	private $recorder;

	protected function setUp(): void {
		parent::setUp();
		$this->registry = new ReferencePriceRegistry(
			[ 'anchor' => new ReferencePriceType( 'anchor', 'Cijena na dan', '2026-09-10', [], true, 'SidrenaCijena', 'sidrena_cijena' ) ]
		);
		$this->recorder = \Mockery::mock( Recorder::class );
		$this->recorder->shouldReceive( 'record' )->byDefault()->andReturn( Transition::FIRST );
	}

	/**
	 * @param array<int,int[]> $pages
	 */
	private function service( array $pages ): SnapshotService {
		return new SnapshotService(
			$this->registry,
			new ReferencePriceRepository( new ReferenceDateResolver( new CategoryOverrideResolver( fn() => [] ) ) ),
			new ProductAdapter( new BrandResolver(), new BarcodeResolver() ),
			$this->recorder,
			fn( int $page, int $perPage ) => $pages[ $page ] ?? [],
			fn( int $id ) => \wc_get_product( $id ) ?: null,
		);
	}

	/**
	 * @param array<string,mixed> $over
	 */
	private function request( array $over = [] ): SnapshotRequest {
		return new SnapshotRequest(
			$over['typeKey'] ?? 'anchor',
			$over['date'] ?? null,
			$over['mode'] ?? SnapshotRequest::MODE_ONLY_MISSING,
			$over['skipOnSale'] ?? false,
			$over['dryRun'] ?? false,
			$over['markNaAfterDate'] ?? true,
		);
	}

	public function test_request_date_equal_to_type_default_is_not_stored_as_override(): void {
		$this->product( [ 'id' => 8, 'regular_price' => '10' ] );
		Functions\expect( 'update_post_meta' )->once()->with( 8, '_scwc_ref_anchor_price', '10' );
		Functions\expect( 'update_post_meta' )->once()->with( 8, '_scwc_ref_anchor_source', 'snapshot' );
		Functions\expect( 'update_post_meta' )->never()->with( 8, '_scwc_ref_anchor_date', \Mockery::any() );
		Functions\expect( 'delete_post_meta' )->atLeast()->once();
		$this->recorder->shouldReceive( 'record' )->once()->andReturn( Transition::FIRST );
		$this->service( [ 1 => [ 8 ] ] )->run( $this->request( [ 'date' => '2026-09-10' ] ), 1, 100 );
	}

	public function test_copies_regular_not_sale_price_and_seeds_history(): void {
		$this->product( [ 'id' => 5, 'sku' => 'A5', 'regular_price' => '100', 'sale_price' => '80' ] );
		Functions\expect( 'update_post_meta' )->once()->with( 5, '_scwc_ref_anchor_price', '100' );
		Functions\expect( 'update_post_meta' )->once()->with( 5, '_scwc_ref_anchor_source', 'snapshot' );
		$this->recorder->shouldReceive( 'record' )->once()->withArgs( fn( $snap, string $source ) => 5 === $snap->id && 'snapshot' === $source )->andReturn( Transition::FIRST );

		$result = $this->service( [ 1 => [ 5 ] ] )->run( $this->request(), 1, 100 );

		self::assertSame( 1, $result->processed );
		self::assertSame( 1, $result->written );
		self::assertFalse( $result->hasMore );
		self::assertSame( 'written', $result->samples[0]['action'] );
		self::assertSame( 'A5', $result->samples[0]['sku'] );
	}

	public function test_only_missing_preserves_existing_and_na(): void {
		$this->product( [ 'id' => 1, 'regular_price' => '10', 'meta' => [ '_scwc_ref_anchor_price' => '9.50' ] ] );
		$this->product( [ 'id' => 2, 'regular_price' => '10', 'meta' => [ '_scwc_ref_anchor_na' => '1' ] ] );
		Functions\expect( 'update_post_meta' )->never();
		$this->recorder->shouldNotReceive( 'record' );

		$result = $this->service( [ 1 => [ 1, 2 ] ] )->run( $this->request(), 1, 100 );

		self::assertSame( 2, $result->skippedExisting );
		self::assertSame( 0, $result->written );
	}

	public function test_overwrite_replaces_existing_value(): void {
		$this->product( [ 'id' => 1, 'regular_price' => '12.5', 'meta' => [ '_scwc_ref_anchor_price' => '9.50' ] ] );
		Functions\expect( 'update_post_meta' )->once()->with( 1, '_scwc_ref_anchor_price', '12.5' );
		Functions\expect( 'update_post_meta' )->once()->with( 1, '_scwc_ref_anchor_source', 'snapshot' );

		$result = $this->service( [ 1 => [ 1 ] ] )->run( $this->request( [ 'mode' => SnapshotRequest::MODE_OVERWRITE ] ), 1, 100 );

		self::assertSame( 1, $result->written );
	}

	public function test_explicit_date_is_stored_as_per_product_override(): void {
		$this->product( [ 'id' => 1, 'regular_price' => '10' ] );
		Functions\expect( 'update_post_meta' )->once()->with( 1, '_scwc_ref_anchor_price', '10' );
		Functions\expect( 'update_post_meta' )->once()->with( 1, '_scwc_ref_anchor_source', 'snapshot' );
		Functions\expect( 'update_post_meta' )->once()->with( 1, '_scwc_ref_anchor_date', '2026-09-01' );

		$this->service( [ 1 => [ 1 ] ] )->run( $this->request( [ 'date' => '2026-09-01' ] ), 1, 100 );
	}

	public function test_marks_na_when_created_after_reference_date(): void {
		// 2026-09-10 23:30 UTC is 2026-09-11 in Europe/Zagreb → after the anchor date.
		$this->product( [ 'id' => 3, 'regular_price' => '10', 'date_created' => new \WC_DateTime( '2026-09-10 23:30:00', new \DateTimeZone( 'UTC' ) ) ] );
		Functions\expect( 'update_post_meta' )->once()->with( 3, '_scwc_ref_anchor_na', '1' );
		Functions\expect( 'update_post_meta' )->once()->with( 3, '_scwc_ref_anchor_source', 'snapshot' );
		$this->recorder->shouldNotReceive( 'record' );

		$result = $this->service( [ 1 => [ 3 ] ] )->run( $this->request(), 1, 100 );

		self::assertSame( 1, $result->markedNa );
		self::assertSame( 0, $result->written );
		self::assertSame( 'marked_na', $result->samples[0]['action'] );
	}

	public function test_created_on_reference_date_is_written_and_na_marking_can_be_disabled(): void {
		$this->product( [ 'id' => 3, 'regular_price' => '10', 'date_created' => new \WC_DateTime( '2026-09-10 10:00:00', new \DateTimeZone( 'UTC' ) ) ] );
		$this->product( [ 'id' => 4, 'regular_price' => '10', 'date_created' => new \WC_DateTime( '2026-12-01 10:00:00', new \DateTimeZone( 'UTC' ) ) ] );
		Functions\expect( 'update_post_meta' )->never()->with( \Mockery::any(), '_scwc_ref_anchor_na', \Mockery::any() );
		Functions\expect( 'update_post_meta' )->times( 4 );

		$result = $this->service( [ 1 => [ 3, 4 ] ] )->run( $this->request( [ 'markNaAfterDate' => false ] ), 1, 100 );

		self::assertSame( 2, $result->written );
		self::assertSame( 0, $result->markedNa );
	}

	public function test_skip_on_sale_skips_discounted_products(): void {
		$this->product( [ 'id' => 1, 'regular_price' => '100', 'sale_price' => '80' ] );
		$this->product( [ 'id' => 2, 'regular_price' => '50' ] );
		Functions\expect( 'update_post_meta' )->once()->with( 2, '_scwc_ref_anchor_price', '50' );
		Functions\expect( 'update_post_meta' )->once()->with( 2, '_scwc_ref_anchor_source', 'snapshot' );

		$result = $this->service( [ 1 => [ 1, 2 ] ] )->run( $this->request( [ 'skipOnSale' => true ] ), 1, 100 );

		self::assertSame( 1, $result->skippedOnSale );
		self::assertSame( 1, $result->written );
		self::assertSame( 'skipped_on_sale', $result->samples[0]['action'] );
	}

	public function test_products_without_regular_price_are_skipped(): void {
		$this->product( [ 'id' => 1, 'regular_price' => '' ] );
		$this->product( [ 'id' => 2, 'regular_price' => '0' ] );
		$this->product( [ 'id' => 3, 'regular_price' => 'abc' ] );
		Functions\expect( 'update_post_meta' )->never();

		$result = $this->service( [ 1 => [ 1, 2, 3 ] ] )->run( $this->request(), 1, 100 );

		self::assertSame( 3, $result->skippedNoPrice );
		self::assertSame( 3, $result->processed );
	}

	public function test_dry_run_writes_nothing_but_counts(): void {
		$this->product( [ 'id' => 1, 'regular_price' => '10' ] );
		$this->product( [ 'id' => 2, 'regular_price' => '10', 'date_created' => new \WC_DateTime( '2026-12-01 10:00:00', new \DateTimeZone( 'UTC' ) ) ] );
		Functions\expect( 'update_post_meta' )->never();
		Functions\expect( 'delete_post_meta' )->never();
		$this->recorder->shouldNotReceive( 'record' );

		$result = $this->service( [ 1 => [ 1, 2 ] ] )->run( $this->request( [ 'dryRun' => true ] ), 1, 100 );

		self::assertSame( 1, $result->written );
		self::assertSame( 1, $result->markedNa );
		self::assertCount( 2, $result->samples );
	}

	public function test_variation_is_snapshotted_with_its_parent_and_containers_are_skipped(): void {
		$this->product( [ 'id' => 10, 'type' => 'variable', 'children' => [ 11 ], 'category_ids' => [ 7 ] ] );
		$this->product( [ 'id' => 11, 'type' => 'variation', 'parent_id' => 10, 'sku' => 'V11', 'regular_price' => '20' ] );
		$this->product( [ 'id' => 12, 'type' => 'grouped' ] );
		Functions\expect( 'update_post_meta' )->once()->with( 11, '_scwc_ref_anchor_price', '20' );
		Functions\expect( 'update_post_meta' )->once()->with( 11, '_scwc_ref_anchor_source', 'snapshot' );
		$this->recorder->shouldReceive( 'record' )->once()->withArgs( fn( $snap ) => 11 === $snap->id && 10 === $snap->parentId && [ 7 ] === $snap->categoryIds )->andReturn( Transition::FIRST );

		$result = $this->service( [ 1 => [ 10, 11, 12 ] ] )->run( $this->request(), 1, 100 );

		self::assertSame( 1, $result->processed );
		self::assertSame( 1, $result->written );
	}

	public function test_has_more_follows_page_size_and_results_merge(): void {
		foreach ( [ 1, 2, 3 ] as $id ) {
			$this->product( [ 'id' => $id, 'regular_price' => '10' ] );
		}
		$service = $this->service( [ 1 => [ 1, 2 ], 2 => [ 3 ], 3 => [] ] );
		$r1      = $service->run( $this->request( [ 'dryRun' => true ] ), 1, 2 );
		$r2      = $service->run( $this->request( [ 'dryRun' => true ] ), 2, 2 );
		self::assertTrue( $r1->hasMore );
		self::assertFalse( $r2->hasMore );
		$merged = $r1->merge( $r2 );
		self::assertSame( 3, $merged->processed );
		self::assertSame( 3, $merged->written );
		self::assertFalse( $merged->hasMore );
		self::assertCount( 3, $merged->samples );
		self::assertSame( 3, $merged->toArray()['written'] );
	}

	public function test_samples_are_capped_at_twenty(): void {
		$ids = range( 1, 25 );
		foreach ( $ids as $id ) {
			$this->product( [ 'id' => $id, 'regular_price' => '10' ] );
		}
		$result = $this->service( [ 1 => $ids ] )->run( $this->request( [ 'dryRun' => true ] ), 1, 100 );
		self::assertCount( SnapshotResult::MAX_SAMPLES, $result->samples );
		self::assertSame( 25, $result->written );
	}

	public function test_unknown_type_throws(): void {
		$this->expectException( \InvalidArgumentException::class );
		$this->service( [ 1 => [ 1 ] ] )->run( $this->request( [ 'typeKey' => 'nope' ] ), 1, 100 );
	}
}
