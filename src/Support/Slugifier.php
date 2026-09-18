<?php
/**
 * ASCII slug helper used for price-list file names.
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

namespace SidrenaCijena\Support;

final class Slugifier {

	/**
	 * Lowercase ASCII, runs of anything non-alphanumeric collapsed to a single dash.
	 */
	public static function filenamePart( string $value ): string {
		$value = strtolower( remove_accents( trim( $value ) ) );
		$value = (string) preg_replace( '/[^a-z0-9]+/', '-', $value );
		return trim( $value, '-' );
	}
}
