<?php
/**
 * All outlets that receive their own price-list file: the webshop first, then additional poslovnice.
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

namespace SidrenaCijena\PriceList;

use SidrenaCijena\Settings\Settings;
use SidrenaCijena\Support\Slugifier;

final class Outlets {

	/**
	 * @return Outlet[] Primary outlet first; keys unique.
	 */
	public static function fromSettings( Settings $settings ): array {
		$primary = Outlet::fromSettings( $settings );
		$used    = [];
		$outlets = [ $primary->withKey( self::unique( $primary->key, $used ), true ) ];
		foreach ( (array) $settings->get( 'outlets.additional', [] ) as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$label     = trim( (string) ( $row['label'] ?? '' ) );
			$key       = Slugifier::filenamePart( $label );
			$outlets[] = new Outlet(
				trim( (string) ( $row['form'] ?? 'poslovnica' ) ) ?: 'poslovnica',
				trim( (string) ( $row['address'] ?? '' ) ),
				$label,
				trim( (string) ( $row['storage_number'] ?? '1' ) ) ?: '1',
				$primary->merchantName,
				$primary->url,
				self::unique( '' === $key ? 'poslovnica' : $key, $used ),
				false
			);
		}
		return $outlets;
	}

	/**
	 * @param Outlet[] $outlets Outlets.
	 */
	public static function byKey( array $outlets, string $key ): ?Outlet {
		foreach ( $outlets as $outlet ) {
			if ( $outlet->key === $key ) {
				return $outlet;
			}
		}
		return null;
	}

	/**
	 * @param Outlet[] $outlets Outlets.
	 */
	public static function primary( array $outlets ): ?Outlet {
		return $outlets[0] ?? null;
	}

	/**
	 * @param array<string,bool> $used Keys already taken (by reference).
	 */
	private static function unique( string $key, array &$used ): string {
		$candidate = $key;
		$n         = 1;
		while ( isset( $used[ $candidate ] ) ) {
			++$n;
			$candidate = $key . '-' . $n;
		}
		$used[ $candidate ] = true;
		return $candidate;
	}
}
