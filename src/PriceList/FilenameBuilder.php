<?php
/**
 * Builds price-list file names: {form}_{address}_{label}_{storage}_{Ymd}_{His}.{ext}.
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

namespace SidrenaCijena\PriceList;

use DateTimeImmutable;
use SidrenaCijena\Support\Slugifier;

class FilenameBuilder {

	public const PATTERN = '/^[a-z0-9][a-z0-9._-]*\.(xml|csv)$/';

	/**
	 * @param DateTimeImmutable $localTime Generation time in the site's timezone.
	 * @param string            $ext       Extension without dot (xml|csv).
	 */
	public function build( Outlet $outlet, DateTimeImmutable $localTime, string $ext ): string {
		$parts = [
			$this->part( $outlet->form ),
			$this->part( $outlet->address ),
			$this->part( $outlet->label ),
			$this->part( $outlet->storageNumber ),
			$localTime->format( 'Ymd' ),
			$localTime->format( 'His' ),
		];
		return implode( '_', $parts ) . '.' . strtolower( $ext );
	}

	public static function isValid( string $name ): bool {
		return 1 === preg_match( self::PATTERN, $name );
	}

	private function part( string $value ): string {
		$slug = Slugifier::filenamePart( $value );
		return '' === $slug ? 'na' : $slug;
	}
}
