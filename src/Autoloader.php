<?php
/**
 * Minimal PSR-4 autoloader so the plugin runs from any zip (no Composer needed at runtime).
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

namespace SidrenaCijena;

final class Autoloader {

	public const PREFIX = 'SidrenaCijena\\';

	public static function register( string $baseDir ): void {
		$baseDir = rtrim( $baseDir, '/\\' ) . '/';
		spl_autoload_register(
			static function ( string $class ) use ( $baseDir ): void {
				if ( ! str_starts_with( $class, self::PREFIX ) ) {
					return;
				}
				$file = $baseDir . str_replace( '\\', '/', substr( $class, strlen( self::PREFIX ) ) ) . '.php';
				if ( is_file( $file ) ) {
					require $file;
				}
			}
		);
	}
}
