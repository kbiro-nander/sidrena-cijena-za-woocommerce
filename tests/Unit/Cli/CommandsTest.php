<?php
declare(strict_types=1);

namespace SidrenaCijena\Tests\Unit\Cli;

use SidrenaCijena\Admin\Tools\ImportPreview;
use SidrenaCijena\Admin\Tools\ImportResult;
use SidrenaCijena\Admin\Tools\SnapshotRequest;
use SidrenaCijena\Admin\Tools\SnapshotResult;
use SidrenaCijena\Cli\Commands;
use SidrenaCijena\History\SweepResult;
use SidrenaCijena\PriceList\GenerationResult;
use SidrenaCijena\Settings\Defaults;
use SidrenaCijena\Settings\Settings;
use SidrenaCijena\Tests\TestCase;

final class CommandsTest extends TestCase {
	private array $calls = [];

	protected function setUp(): void {
		parent::setUp();
		\WP_CLI::$out = [];
		$this->calls  = [];
	}

	private function commands( array $over = [] ): Commands {
		$deps = array_merge( [
			'settings'  => new Settings( Defaults::all() ),
			'generate'  => function ( string $reason ) { $this->calls[] = "generate:$reason"; return new GenerationResult( [ [ 'name' => 'a.xml', 'format' => 'xml', 'url' => 'u', 'products' => 3, 'services' => 1 ] ], new \DateTimeImmutable(), $reason, null, 3, 1 ); },
			'snapshot'  => function ( SnapshotRequest $r, int $page ) { $this->calls[] = "snapshot:{$r->typeKey}:$page"; return new SnapshotResult( 5, 4, 1, 0, 0, 0, $page < 2, [] ); },
			'import'    => function ( string $csv, string $type, bool $dry ) { $this->calls[] = "import:$type:" . ( $dry ? 'dry' : 'apply' ); return $dry ? [ 'valid' => 2, 'invalid' => 1, 'errors' => [ 'Redak 3: x' ] ] : [ 'updated' => 2, 'markedNa' => 0, 'errors' => [] ]; },
			'sweep'     => function ( int $page ) { $this->calls[] = "sweep:$page"; return new SweepResult( 2, 1, $page < 2 ); },
			'prune'     => function () { $this->calls[] = 'prune'; },
			'status'    => fn() => [ 'scwc_generate_price_list' => 1790000000, 'scwc_daily_sweep' => null ],
			'reschedule'=> function () { $this->calls[] = 'reschedule'; },
			'history'   => fn( int $id, int $limit ) => [ [ 'recorded_at' => '2026-10-01 00:00:00', 'regular' => 10.0, 'sale' => null, 'active' => 10.0, 'on_sale' => 'ne', 'source' => 'seed' ] ],
		], $over );
		return new Commands( ...array_values( $deps ) );
	}

	public function test_registers_command(): void {
		Commands::registerCommand( $this->commands() );
		self::assertArrayHasKey( 'scwc', $GLOBALS['scwc_test_cli_commands'] );
	}

	public function test_export_runs_generation_and_prints_files(): void {
		$this->commands()->export( [], [] );
		self::assertSame( [ 'generate:cli' ], $this->calls );
		self::assertContains( 'success: Generirano [-]: a.xml (3 proizvoda, 1 usluga)', \WP_CLI::$out );
	}

	public function test_snapshot_pages_until_done(): void {
		$this->commands()->snapshot( [], [ 'key' => 'anchor', 'date' => '2026-09-10' ] );
		self::assertSame( [ 'snapshot:anchor:1', 'snapshot:anchor:2' ], $this->calls );
		self::assertStringContainsString( 'success:', end( \WP_CLI::$out ) );
	}

	public function test_import_dry_run_and_apply(): void {
		$file = tempnam( sys_get_temp_dir(), 'scwc' );
		file_put_contents( $file, "sku;cijena\nA;1" );
		$this->commands()->import( [ $file ], [ 'dry-run' => true ] );
		self::assertSame( [ 'import:anchor:dry' ], $this->calls );
		self::assertContains( 'warning: Redak 3: x', \WP_CLI::$out );
		$this->commands()->import( [ $file ], [ 'key' => 'base' ] );
		self::assertSame( [ 'import:anchor:dry', 'import:base:apply' ], $this->calls );
		unlink( $file );
	}

	public function test_import_missing_file_errors(): void {
		$this->expectException( \RuntimeException::class );
		$this->commands()->import( [ '/nope/x.csv' ], [] );
	}

	public function test_sweep_schedule_prune_history(): void {
		$c = $this->commands();
		$c->sweep( [], [] );
		$c->schedule( [ 'status' ], [] );
		$c->schedule( [ 'reset' ], [] );
		$c->prune( [], [] );
		$c->history( [ '5' ], [] );
		self::assertSame( [ 'sweep:1', 'sweep:2', 'reschedule', 'prune' ], $this->calls );
		self::assertContains( 'table: 1', \WP_CLI::$out );
		self::assertNotEmpty( array_filter( \WP_CLI::$out, fn( $l ) => str_contains( $l, 'scwc_generate_price_list' ) ) );
	}
}
