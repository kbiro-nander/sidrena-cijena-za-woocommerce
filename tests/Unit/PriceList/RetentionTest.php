<?php
declare(strict_types=1);

namespace SidrenaCijena\Tests\Unit\PriceList;

use SidrenaCijena\PriceList\Manifest;
use SidrenaCijena\PriceList\Retention;
use SidrenaCijena\PriceList\Storage;
use SidrenaCijena\Tests\Support\FixedClock;
use SidrenaCijena\Tests\TestCase;

final class RetentionTest extends TestCase {
	private string $dir;
	private Storage $storage;
	private Manifest $manifest;

	protected function setUp(): void {
		parent::setUp();
		$this->dir      = sys_get_temp_dir() . '/scwc-retention-' . uniqid();
		$this->storage  = new Storage( $this->dir, 'https://example.hr/u/scwc-cjenik' );
		$this->storage->ensure();
		$this->manifest = new Manifest( $this->storage );
	}

	protected function tearDown(): void {
		array_map( 'unlink', glob( $this->dir . '/*.*' ) ?: [] );
		@rmdir( $this->dir . '/tmp' );
		@rmdir( $this->dir );
		parent::tearDown();
	}

	private function file( string $name, string $format, string $utc, bool $onDisk = true ): void {
		if ( $onDisk ) {
			file_put_contents( $this->storage->path( $name ), 'x' );
		}
		$this->manifest->add( [ 'name' => $name, 'format' => $format, 'generated_at' => $utc, 'generated_at_utc' => $utc, 'reason' => 'scheduled', 'products' => 1, 'services' => 0, 'size' => 1, 'sha256' => '' ] );
	}

	private function retention( string $nowUtc = '2026-10-01 04:00:12' ): Retention {
		return new Retention( $this->manifest, $this->storage, new FixedClock( $nowUtc ) );
	}

	public function test_deletes_files_older_than_the_window_from_disk_and_manifest(): void {
		$this->file( 'old_20260801_060000.xml', 'xml', '2026-08-01 04:00:00' );
		$this->file( 'old_20260801_060000.csv', 'csv', '2026-08-01 04:00:00' );
		$this->file( 'mid_20260915_060000.xml', 'xml', '2026-09-15 04:00:00' );
		$this->file( 'new_20261001_060000.xml', 'xml', '2026-10-01 04:00:00' );
		$this->file( 'new_20261001_060000.csv', 'csv', '2026-10-01 04:00:00' );
		$removed = $this->retention()->prune( 35 );
		self::assertEqualsCanonicalizing( [ 'old_20260801_060000.xml', 'old_20260801_060000.csv' ], $removed );
		self::assertFileDoesNotExist( $this->storage->path( 'old_20260801_060000.xml' ) );
		self::assertFileExists( $this->storage->path( 'mid_20260915_060000.xml' ) );
		self::assertFalse( $this->manifest->has( 'old_20260801_060000.csv' ) );
		self::assertTrue( $this->manifest->has( 'mid_20260915_060000.xml' ) );
		self::assertCount( 3, ( new Manifest( $this->storage ) )->entries() );
	}

	public function test_window_is_never_shorter_than_thirty_days(): void {
		$this->file( 'a_20260910_060000.xml', 'xml', '2026-09-10 04:00:00' ); // 21 days old
		$this->file( 'b_20261001_060000.xml', 'xml', '2026-10-01 04:00:00' );
		self::assertSame( [], $this->retention()->prune( 1 ) );
		self::assertFileExists( $this->storage->path( 'a_20260910_060000.xml' ) );
	}

	public function test_newest_file_of_each_format_is_kept_even_if_old(): void {
		$this->file( 'a_20260101_060000.xml', 'xml', '2026-01-01 04:00:00' );
		$this->file( 'b_20260201_060000.xml', 'xml', '2026-02-01 04:00:00' );
		$this->file( 'c_20260101_060000.csv', 'csv', '2026-01-01 04:00:00' );
		self::assertSame( [ 'a_20260101_060000.xml' ], $this->retention()->prune( 30 ) );
		self::assertTrue( $this->manifest->has( 'b_20260201_060000.xml' ) );
		self::assertTrue( $this->manifest->has( 'c_20260101_060000.csv' ) );
	}

	public function test_newest_file_of_each_outlet_and_format_is_kept(): void {
		$this->file( 'webshop_a_web1_1_20260101_060000.xml', 'xml', '2026-01-01 04:00:00' );
		$this->file( 'webshop_a_web1_1_20260201_060000.xml', 'xml', '2026-02-01 04:00:00' );
		$this->manifest->add( [ 'name' => 'poslovnica_b_zg02_3_20260101_060000.xml', 'format' => 'xml', 'outlet' => 'zg-02', 'generated_at' => '', 'generated_at_utc' => '2026-01-01 04:00:00', 'reason' => 'scheduled', 'products' => 1, 'services' => 0, 'size' => 1, 'sha256' => '' ] );
		file_put_contents( $this->storage->path( 'poslovnica_b_zg02_3_20260101_060000.xml' ), 'x' );
		self::assertSame( [ 'webshop_a_web1_1_20260101_060000.xml' ], $this->retention()->prune( 30 ) );
		self::assertTrue( $this->manifest->has( 'poslovnica_b_zg02_3_20260101_060000.xml' ), 'the other outlet\'s only file survives' );
	}

	public function test_entries_whose_file_vanished_are_dropped(): void {
		$this->file( 'gone_20261001_060000.xml', 'xml', '2026-10-01 04:00:00', false );
		$this->file( 'here_20261001_060100.xml', 'xml', '2026-10-01 04:01:00' );
		self::assertSame( [ 'gone_20261001_060000.xml' ], $this->retention()->prune( 35 ) );
		self::assertSame( [ 'here_20261001_060100.xml' ], array_column( $this->manifest->entries(), 'name' ) );
	}

	public function test_entries_without_a_parsable_date_are_left_alone(): void {
		$this->file( 'weird_x.xml', 'xml', 'not-a-date' );
		$this->file( 'new_20261001_060000.xml', 'xml', '2026-10-01 04:00:00' );
		self::assertSame( [], $this->retention()->prune( 35 ) );
		self::assertTrue( $this->manifest->has( 'weird_x.xml' ) );
	}

	public function test_orphaned_outlet_groups_are_pruned_by_age_when_known_keys_are_given(): void {
		$this->file( 'webshop_a_web1_1_20260101_060000.xml', 'xml', '2026-01-01 04:00:00' );
		$this->manifest->add( [ 'name' => 'poslovnica_gone_x_1_20260101_060000.xml', 'format' => 'xml', 'outlet' => 'gone', 'generated_at' => '', 'generated_at_utc' => '2026-01-01 04:00:00', 'reason' => 'scheduled', 'products' => 1, 'services' => 0, 'size' => 1, 'sha256' => '' ] );
		file_put_contents( $this->storage->path( 'poslovnica_gone_x_1_20260101_060000.xml' ), 'x' );
		self::assertSame( [ 'poslovnica_gone_x_1_20260101_060000.xml' ], $this->retention()->prune( 30, 'web1', [ 'web1' ] ) );
		self::assertTrue( $this->manifest->has( 'webshop_a_web1_1_20260101_060000.xml' ), 'known outlet keeps its newest file' );
	}
}
