<?php
/**
 * Money parsing/formatting helpers (locale-tolerant input, canonical output).
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

namespace SidrenaCijena\Support;

final class Money {

	/**
	 * Parse "12,99", "1.234,50", "1,234.50", "12.99 €" into a float. Null when not a number.
	 *
	 * @param string|int|float|null $value Raw value.
	 */
	public static function parse( $value ): ?float {
		if ( is_int( $value ) || is_float( $value ) ) {
			return (float) $value;
		}
		$value = trim( (string) $value );
		$value = (string) preg_replace( '/[^\d,.\-]/', '', $value );
		if ( '' === $value ) {
			return null;
		}
		$lastComma = strrpos( $value, ',' );
		$lastDot   = strrpos( $value, '.' );
		if ( false !== $lastComma && ( false === $lastDot || $lastComma > $lastDot ) ) {
			// Comma is the decimal separator; dots are thousands.
			$value = str_replace( '.', '', $value );
			$value = str_replace( ',', '.', $value );
		} else {
			// Dot is the decimal separator (or none); commas are thousands.
			$value = str_replace( ',', '', $value );
		}
		if ( ! is_numeric( $value ) ) {
			return null;
		}
		return (float) $value;
	}

	/**
	 * Canonical decimal string with a dot and fixed decimal places.
	 *
	 * @param string|int|float $value Numeric value.
	 */
	public static function toDecimal( $value, int $decimals = 2 ): string {
		$float = is_string( $value ) ? (float) $value : (float) $value;
		return number_format( $float, $decimals, '.', '' );
	}

	/**
	 * @param string|int|float|null $value Raw value.
	 */
	public static function isPositive( $value ): bool {
		if ( null === $value || '' === $value ) {
			return false;
		}
		return is_numeric( $value ) && (float) $value > 0;
	}
}
