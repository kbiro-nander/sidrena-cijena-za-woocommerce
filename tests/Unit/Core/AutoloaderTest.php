<?php
declare(strict_types=1);

namespace SidrenaCijena\Tests\Unit\Core;

use SidrenaCijena\Tests\TestCase;

final class AutoloaderTest extends TestCase {
	public function test_loads_namespaced_classes_from_src_and_ignores_other_namespaces(): void {
		$dir = sys_get_temp_dir() . '/scwc-autoload-' . uniqid();
		mkdir( $dir . '/src/Deep', 0755, true );
		file_put_contents( $dir . '/src/Deep/Probe.php', '<?php namespace SidrenaCijena\Deep; final class Probe { public const OK = true; }' );
		require_once dirname( __DIR__, 3 ) . '/src/Autoloader.php';
		\SidrenaCijena\Autoloader::register( $dir . '/src' );
		self::assertTrue( class_exists( 'SidrenaCijena\Deep\Probe' ) );
		self::assertFalse( class_exists( 'SidrenaCijena\Deep\Missing' ) );
		self::assertFalse( class_exists( 'Other\Thing' ) );
		unlink( $dir . '/src/Deep/Probe.php' );
		rmdir( $dir . '/src/Deep' );
		rmdir( $dir . '/src' );
		rmdir( $dir );
	}
}
