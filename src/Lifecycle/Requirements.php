<?php
/**
 * Runtime requirement checks.
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

namespace SidrenaCijena\Lifecycle;

final class Requirements {

	public const MIN_PHP = '8.1';
	public const MIN_WC  = '9.0';

	/**
	 * @param string|null $wcVersion WooCommerce version or null when inactive.
	 * @return string[] Human-readable problems (empty when all good).
	 */
	public static function problems( string $phpVersion, ?string $wcVersion ): array {
		$problems = [];
		if ( version_compare( $phpVersion, self::MIN_PHP, '<' ) ) {
			$problems[] = sprintf( 'Sidrena cijena za WooCommerce zahtijeva PHP %s ili noviji (trenutno %s).', self::MIN_PHP, $phpVersion );
		}
		if ( null === $wcVersion ) {
			$problems[] = 'Sidrena cijena za WooCommerce zahtijeva aktivan WooCommerce.';
		} elseif ( version_compare( $wcVersion, self::MIN_WC, '<' ) ) {
			$problems[] = sprintf( 'Sidrena cijena za WooCommerce zahtijeva WooCommerce %s ili noviji (trenutno %s).', self::MIN_WC, $wcVersion );
		}
		return $problems;
	}

	/**
	 * @return string[]
	 */
	public static function check(): array {
		$wc = defined( 'WC_VERSION' ) ? (string) constant( 'WC_VERSION' ) : null;
		return self::problems( PHP_VERSION, $wc );
	}
}
