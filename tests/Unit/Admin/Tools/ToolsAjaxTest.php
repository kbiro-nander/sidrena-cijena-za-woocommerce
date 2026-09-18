<?php
declare(strict_types=1);

namespace SidrenaCijena\Tests\Unit\Admin\Tools;

use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use SidrenaCijena\Admin\Tools\CsvExporter;
use SidrenaCijena\Admin\Tools\CsvImporter;
use SidrenaCijena\Admin\Tools\ImportResult;
use SidrenaCijena\Admin\Tools\SnapshotRequest;
use SidrenaCijena\Admin\Tools\SnapshotResult;
use SidrenaCijena\Admin\Tools\SnapshotService;
use SidrenaCijena\Admin\Tools\ToolsAjax;
use SidrenaCijena\Product\BarcodeResolver;
use SidrenaCijena\Product\BrandResolver;
use SidrenaCijena\Product\ProductAdapter;
use SidrenaCijena\Reference\CategoryOverrideResolver;
use SidrenaCijena\Reference\ReferenceDateResolver;
use SidrenaCijena\Reference\ReferencePriceRegistry;
use SidrenaCijena\Reference\ReferencePriceRepository;
use SidrenaCijena\Reference\ReferencePriceType;
use SidrenaCijena\Settings\Defaults;
use SidrenaCijena\Settings\Settings;
use SidrenaCijena\Tests\TestCase;

final class ToolsAjaxTest extends TestCase {
	/** @var SnapshotService&\Mockery\MockInterface */
	private $snapshot;
	private ReferencePriceRegistry $registry;
	private ReferencePriceRepository $repository;

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['scwc_test_json']       = null;
		$GLOBALS['scwc_test_transients'] = [];
		$this->snapshot   = \Mockery::mock( SnapshotService::class );
		$this->registry   = new ReferencePriceRegistry( [ 'anchor' => new ReferencePriceType( 'anchor', 'Cijena na dan', '2026-09-10', [], true, 'SidrenaCijena', 'sidrena_cijena' ) ] );
		$this->repository = new ReferencePriceRepository( new ReferenceDateResolver( new CategoryOverrideResolver( fn() => [] ) ) );
	}

	/**
	 * @param array<string,mixed>  $post
	 * @param array<string,mixed>  $files
	 * @param array<int,int[]>     $pages
	 */
	private function ajax( array $post, array $files = [], array $pages = [] ): ToolsAjax {
		$loader = fn( int $id ) => \wc_get_product( $id ) ?: null;
		return new ToolsAjax(
			$this->snapshot,
			new CsvImporter( $this->registry, $this->repository, fn( string $sku ) => (int) \wc_get_product_id_by_sku( $sku ), $loader ),
			new CsvExporter( $this->registry, $this->repository, new ProductAdapter( new BrandResolver(), new BarcodeResolver() ), fn( int $page, int $perPage ) => $pages[ $page ] ?? [], $loader ),
			new Settings( Defaults::all() ),
			fn() => $post,
			fn() => $files,
		);
	}

	/**
	 * @return array{success:bool,data:mixed,status:mixed}
	 */
	private function handle( ToolsAjax $ajax ): array {
		try {
			$ajax->handle();
			self::fail( 'No JSON response was sent.' );
		} catch ( \RuntimeException $e ) {
			self::assertSame( 'json_sent', $e->getMessage() );
		}
		return $GLOBALS['scwc_test_json'];
	}

	public function test_registers_ajax_action(): void {
		$ajax = $this->ajax( [] );
		Actions\expectAdded( 'wp_ajax_scwc_tool_step' )->once()->with( [ $ajax, 'handle' ] );
		$ajax->register();
	}

	public function test_unknown_tool_is_rejected(): void {
		$response = $this->handle( $this->ajax( [ 'tool' => 'nope' ] ) );
		self::assertFalse( $response['success'] );
		self::assertNotEmpty( $response['data']['message'] );
	}

	public function test_missing_capability_is_403(): void {
		Functions\when( 'current_user_can' )->justReturn( false );
		$this->snapshot->shouldNotReceive( 'run' );
		$response = $this->handle( $this->ajax( [ 'tool' => 'snapshot' ] ) );
		self::assertFalse( $response['success'] );
		self::assertSame( 403, $response['status'] );
	}

	public function test_snapshot_step_reports_next_page_and_fires_completion_on_last_page(): void {
		$this->snapshot->shouldReceive( 'run' )->once()->withArgs( fn( SnapshotRequest $r, int $page, int $perPage ) => 'anchor' === $r->typeKey && 1 === $page && 100 === $perPage )
			->andReturn( new SnapshotResult( 100, 90, 10, 0, 0, 0, true, [] ) );
		$this->snapshot->shouldReceive( 'run' )->once()->withArgs( fn( SnapshotRequest $r, int $page ) => 2 === $page )
			->andReturn( new SnapshotResult( 5, 5, 0, 0, 0, 0, false, [] ) );
		Actions\expectDone( 'scwc_snapshot_completed' )->once()->with( \Mockery::type( SnapshotRequest::class ) );

		$args  = [ 'type' => 'anchor', 'mode' => 'overwrite' ];
		$first = $this->handle( $this->ajax( [ 'tool' => 'snapshot', 'page' => '1', 'args' => $args ] ) );
		self::assertTrue( $first['success'] );
		self::assertFalse( $first['data']['done'] );
		self::assertSame( 2, $first['data']['next_page'] );
		self::assertSame( 90, $first['data']['result']['written'] );
		self::assertStringContainsString( 'Stranica 1', $first['data']['log'] );

		$second = $this->handle( $this->ajax( [ 'tool' => 'snapshot', 'page' => '2', 'args' => $args ] ) );
		self::assertTrue( $second['data']['done'] );
	}

	public function test_dry_run_snapshot_does_not_fire_completion(): void {
		$this->snapshot->shouldReceive( 'run' )->once()->andReturn( new SnapshotResult( 1, 1, 0, 0, 0, 0, false, [] ) );
		Actions\expectDone( 'scwc_snapshot_completed' )->never();
		$response = $this->handle( $this->ajax( [ 'tool' => 'snapshot', 'args' => [ 'dry_run' => '1' ] ] ) );
		self::assertTrue( $response['data']['done'] );
	}

	public function test_import_preview_then_apply_round_trip_through_transient(): void {
		$this->product( [ 'id' => 1, 'sku' => 'A-1', 'regular_price' => '10' ] );
		$csv     = "sku;cijena;datum\nA-1;12,50;2026-09-01\nZZZ;1;\n";
		$preview = $this->handle( $this->ajax( [ 'tool' => 'import_preview', 'args' => [ 'csv' => $csv, 'type' => 'anchor', 'delimiter' => 'auto' ] ] ) );
		self::assertTrue( $preview['success'] );
		self::assertSame( 1, $preview['data']['valid'] );
		self::assertSame( 1, $preview['data']['invalid'] );
		self::assertSame( 1, $preview['data']['unmatched'] );
		self::assertCount( 2, $preview['data']['rows'] );
		self::assertCount( 1, $preview['data']['errors'] );
		$token = $preview['data']['token'];
		self::assertNotEmpty( $token );
		self::assertArrayHasKey( 'scwc_import_' . $token, $GLOBALS['scwc_test_transients'] );

		Functions\expect( 'update_post_meta' )->once()->with( 1, '_scwc_ref_anchor_price', '12.5' );
		Functions\expect( 'update_post_meta' )->once()->with( 1, '_scwc_ref_anchor_source', 'import' );
		Functions\expect( 'update_post_meta' )->once()->with( 1, '_scwc_ref_anchor_date', '2026-09-01' );
		Actions\expectDone( 'scwc_import_completed' )->once()->with( \Mockery::type( ImportResult::class ) );

		$apply = $this->handle( $this->ajax( [ 'tool' => 'import_apply', 'args' => [ 'token' => $token ] ] ) );
		self::assertTrue( $apply['success'] );
		self::assertSame( 1, $apply['data']['updated'] );
		self::assertSame( 1, $apply['data']['skipped'] );
		self::assertArrayNotHasKey( 'scwc_import_' . $token, $GLOBALS['scwc_test_transients'] );
	}

	public function test_import_preview_reads_uploaded_file_and_honours_delimiter(): void {
		$this->product( [ 'id' => 1, 'sku' => 'A-1', 'regular_price' => '10' ] );
		$tmp = tempnam( sys_get_temp_dir(), 'scwc' );
		file_put_contents( $tmp, "sku,cijena\nA-1,\"12,5\"\n" );
		$files    = [ 'file' => [ 'tmp_name' => $tmp, 'error' => 0, 'name' => 'x.csv' ] ];
		$response = $this->handle( $this->ajax( [ 'tool' => 'import_preview', 'args' => [ 'delimiter' => ',' ] ], $files ) );
		unlink( $tmp );
		self::assertTrue( $response['success'] );
		self::assertSame( 1, $response['data']['valid'] );
		self::assertSame( ',', $response['data']['delimiter'] );
	}

	public function test_import_apply_with_expired_token_fails(): void {
		$response = $this->handle( $this->ajax( [ 'tool' => 'import_apply', 'args' => [ 'token' => 'missing' ] ] ) );
		self::assertFalse( $response['success'] );
	}

	public function test_export_returns_csv_and_filename(): void {
		$this->product( [ 'id' => 1, 'sku' => 'A-1', 'name' => 'Prvi', 'regular_price' => '10' ] );
		$this->product( [ 'id' => 2, 'sku' => 'B-2', 'name' => 'Drugi', 'regular_price' => '20', 'meta' => [ '_scwc_ref_anchor_price' => '19' ] ] );
		$response = $this->handle( $this->ajax( [ 'tool' => 'export', 'args' => [ 'only_missing' => '1' ] ], [], [ 1 => [ 1, 2 ] ] ) );
		self::assertTrue( $response['success'] );
		self::assertMatchesRegularExpression( '/^sidrene-cijene-\d{8}\.csv$/', $response['data']['filename'] );
		self::assertStringStartsWith( "\xEF\xBB\xBF" . 'sku;naziv;', $response['data']['csv'] );
		self::assertStringContainsString( "\nA-1;Prvi;", $response['data']['csv'] );
		self::assertStringNotContainsString( 'B-2', $response['data']['csv'] );
		self::assertSame( 1, $response['data']['rows'] );
	}
}
