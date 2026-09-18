<?php
declare(strict_types=1);

namespace SidrenaCijena\Tests\Unit\PriceList;

use SidrenaCijena\PriceList\Storage;
use SidrenaCijena\Tests\TestCase;

final class StorageTest extends TestCase {
	private string $dir;

	protected function setUp(): void {
		parent::setUp();
		$this->dir = sys_get_temp_dir() . '/scwc-storage-' . uniqid();
	}

	protected function tearDown(): void {
		$this->rmdir( $this->dir );
		parent::tearDown();
	}

	private function rmdir( string $dir ): void {
		if ( ! is_dir( $dir ) ) {
			return;
		}
		foreach ( scandir( $dir ) ?: [] as $entry ) {
			if ( '.' === $entry || '..' === $entry ) {
				continue;
			}
			$path = $dir . '/' . $entry;
			is_dir( $path ) ? $this->rmdir( $path ) : unlink( $path );
		}
		rmdir( $dir );
	}

	private function storage(): Storage {
		return new Storage( $this->dir, 'https://example.hr/wp-content/uploads/scwc-cjenik' );
	}

	public function test_ensure_creates_dir_tmp_and_index(): void {
		$s = $this->storage();
		$s->ensure();
		self::assertDirectoryExists( $this->dir );
		self::assertDirectoryExists( $this->dir . '/tmp' );
		self::assertSame( '<?php // Silence is golden.', trim( (string) file_get_contents( $this->dir . '/index.php' ) ) );
		self::assertSame( $this->dir, $s->dir() );
		$s->ensure(); // idempotent
	}

	public function test_paths_and_publish_moves_tmp_to_final(): void {
		$s = $this->storage();
		$s->ensure();
		$name = 'webshop_a_b_1_20261001_060012.xml';
		self::assertSame( $this->dir . '/tmp/' . $name, $s->tmpPath( $name ) );
		self::assertSame( $this->dir . '/' . $name, $s->path( $name ) );
		file_put_contents( $s->tmpPath( $name ), 'hello' );
		self::assertFalse( $s->exists( $name ) );
		$s->publish( $name );
		self::assertTrue( $s->exists( $name ) );
		self::assertFileDoesNotExist( $s->tmpPath( $name ) );
		self::assertSame( 5, $s->size( $name ) );
		$s->delete( $name );
		self::assertFalse( $s->exists( $name ) );
		self::assertSame( 0, $s->size( $name ) );
		$s->delete( $name ); // no error for missing
	}

	public function test_url_encodes_name(): void {
		self::assertSame( 'https://example.hr/wp-content/uploads/scwc-cjenik/a_b.xml', $this->storage()->url( 'a_b.xml' ) );
		self::assertSame( 'https://example.hr/wp-content/uploads/scwc-cjenik/a%20b.xml', ( new Storage( $this->dir, 'https://example.hr/wp-content/uploads/scwc-cjenik/' ) )->url( 'a b.xml' ) );
	}

	public function test_list_files_returns_only_matching_names_sorted(): void {
		$s = $this->storage();
		$s->ensure();
		foreach ( [ 'b_20261002_060000.xml', 'a_20261001_060000.csv', 'manifest.json', 'index.php', 'Bad.xml' ] as $f ) {
			file_put_contents( $this->dir . '/' . $f, 'x' );
		}
		file_put_contents( $this->dir . '/tmp/c_20261003_060000.xml', 'x' );
		self::assertSame( [ 'a_20261001_060000.csv', 'b_20261002_060000.xml' ], $s->listFiles() );
	}

	public function test_list_files_on_missing_dir_is_empty(): void {
		self::assertSame( [], $this->storage()->listFiles() );
	}

	public function test_from_uploads_uses_upload_dir(): void {
		$s = Storage::fromUploads();
		self::assertSame( \wp_upload_dir()['basedir'] . '/scwc-cjenik', $s->dir() );
		self::assertSame( 'https://example.hr/wp-content/uploads/scwc-cjenik/x.xml', $s->url( 'x.xml' ) );
	}
}
