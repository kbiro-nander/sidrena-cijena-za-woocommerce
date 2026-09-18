<?php
/**
 * Date formatting/parsing helpers.
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

namespace SidrenaCijena\Support;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

final class DateFormat {

	public const CROATIAN = 'j. n. Y.';
	public const ISO      = 'Y-m-d';

	/** Croatian convention: "10. 9. 2026." */
	public static function croatian( DateTimeInterface $date, string $format = self::CROATIAN ): string {
		return $date->format( $format );
	}

	/** Strict Y-m-d parsing; anything else yields null. */
	public static function parseIso( ?string $value, ?DateTimeZone $tz = null ): ?DateTimeImmutable {
		$value = trim( (string) $value );
		if ( 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
			return null;
		}
		$date = DateTimeImmutable::createFromFormat( '!' . self::ISO, $value, $tz ?? new DateTimeZone( 'UTC' ) );
		if ( false === $date || $date->format( self::ISO ) !== $value ) {
			return null;
		}
		return $date;
	}
}
