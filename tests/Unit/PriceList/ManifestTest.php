<?php
declare(strict_types=1);

namespace SidrenaCijena\Tests\Unit\PriceList;

use SidrenaCijena\PriceList\Manifest;
use SidrenaCijena\PriceList\Storage;
use SidrenaCijena\Tests\TestCase;

final class ManifestTest extends TestCase {
	private string $dir;
	private Storage $storage;

	protected function setUp(): void {
		parent::setUp();
		$this->dir     = sys_get_temp_dir() . '/scwc-manifest-' . uniqid();
		$this->storage = new Storage( $this->dir, 'https://example.hr/u/scwc-cjenik' );
		$this->storage->ensure();
	}

	protected function tearDown(): void {
		array_map( 'unlink', glob( $this->dir . '/*.*' ) ?: [] );
		@rmdir( $this->dir . '/tmp' );
		@rmdir( $this->dir );
		parent::tearDown();
	}

	/**
	 * @return array<string,mixed>
	 */
	private function entry( string $name, string $format, string $utc ): array {
		return [
			'name'             => $name,
			'format'           => $format,
			'generated_at'     => str_replace( ' ', 'T', $utc ) . '+02:00',
			'generated_at_utc' => $utc,
			'reason'           => 'scheduled',
			'products'         => 120,
			'services'         => 3,
			'size'             => 12345,
			'sha256'           => str_repeat( 'a', 64 ),
		];
	}

	public function test_empty_when_file_missing_or_corrupt(): void {
		$m = new Manifest( $this->storage );
		self::assertSame( [], $m->entries() );
		self::assertNull( $m->latest( 'xml' ) );
		file_put_contents( $this->dir . '/manifest.json', '{not json' );
		self::assertSame( [], ( new Manifest( $this->storage ) )->entries() );
		file_put_contents( $this->dir . '/manifest.json', '{"generated":"nope"}' );
		self::assertSame( [], ( new Manifest( $this->storage ) )->entries() );
	}

	public function test_add_prepends_and_persists(): void {
		$m = new Manifest( $this->storage );
		$m->add( $this->entry( 'a.xml', 'xml', '2026-10-01 04:00:12' ) );
		$m->add( $this->entry( 'a.csv', 'csv', '2026-10-01 04:00:13' ) );
		$m->add( $this->entry( 'b.xml', 'xml', '2026-10-02 04:00:12' ) );
		self::assertSame( [ 'b.xml', 'a.csv', 'a.xml' ], array_column( $m->entries(), 'name' ) );

		$json = json_decode( (string) file_get_contents( $this->dir . '/manifest.json' ), true );
		self::assertSame( [ 'b.xml', 'a.csv', 'a.xml' ], array_column( $json['generated'], 'name' ) );
		self::assertSame( 120, $json['generated'][0]['products'] );

		$reloaded = new Manifest( $this->storage );
		self::assertSame( 'b.xml', $reloaded->latest( 'xml' )['name'] );
		self::assertSame( 'a.csv', $reloaded->latest( 'csv' )['name'] );
		self::assertTrue( $reloaded->has( 'a.xml' ) );
		self::assertFalse( $reloaded->has( 'zzz.xml' ) );
		self::assertSame( '2026-10-01 04:00:12', $reloaded->entry( 'a.xml' )['generated_at_utc'] );
		self::assertNull( $reloaded->entry( 'zzz.xml' ) );
	}

	public function test_add_replaces_existing_entry_with_same_name(): void {
		$m = new Manifest( $this->storage );
		$m->add( $this->entry( 'a.xml', 'xml', '2026-10-01 04:00:12' ) );
		$m->add( array_merge( $this->entry( 'a.xml', 'xml', '2026-10-01 05:00:12' ), [ 'products' => 7 ] ) );
		self::assertCount( 1, $m->entries() );
		self::assertSame( 7, $m->entry( 'a.xml' )['products'] );
	}

	public function test_remove_and_save(): void {
		$m = new Manifest( $this->storage );
		$m->add( $this->entry( 'a.xml', 'xml', '2026-10-01 04:00:12' ) );
		$m->add( $this->entry( 'b.xml', 'xml', '2026-10-02 04:00:12' ) );
		$m->remove( 'b.xml' );
		$m->remove( 'missing.xml' );
		self::assertSame( [ 'a.xml' ], array_column( $m->entries(), 'name' ) );
		self::assertSame( [ 'a.xml' ], array_column( ( new Manifest( $this->storage ) )->entries(), 'name' ) );
		self::assertSame( 'a.xml', $m->latest( 'xml' )['name'] );
	}

	public function test_load_is_lazy_and_reload_picks_up_disk_changes(): void {
		$m = new Manifest( $this->storage );
		$m->add( $this->entry( 'a.xml', 'xml', '2026-10-01 04:00:12' ) );
		$other = new Manifest( $this->storage );
		$other->add( $this->entry( 'b.xml', 'xml', '2026-10-02 04:00:12' ) );
		$m->load();
		self::assertSame( [ 'b.xml', 'a.xml' ], array_column( $m->entries(), 'name' ) );
	}
}
