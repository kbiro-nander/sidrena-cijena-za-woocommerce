<?php
/**
 * Deletes price-list files older than the retention window (never the newest per format).
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

namespace SidrenaCijena\PriceList;

use DateTimeImmutable;
use DateTimeZone;
use SidrenaCijena\Support\Clock;

class Retention {

	public const MIN_DAYS = 30;

	public function __construct(
		private readonly Manifest $manifest,
		private readonly Storage $storage,
		private readonly Clock $clock,
	) {}

	/**
	 * @param int $days Retention in days (floored to MIN_DAYS).
	 * @return string[] Names removed from the manifest (and disk where present).
	 */
	/**
	 * @param string $primaryKey Key of the primary outlet; entries without an outlet key count as its files.
	 * @return string[] Removed file names.
	 */
	public function prune( int $days, string $primaryKey = '' ): array {
		$days   = max( self::MIN_DAYS, $days );
		$cutoff = $this->clock->now()->modify( sprintf( '-%d days', $days ) );

		$newest  = $this->newestPerFormat( $primaryKey );
		$removed = [];
		foreach ( $this->manifest->entries() as $entry ) {
			$name = (string) ( $entry['name'] ?? '' );
			if ( '' === $name ) {
				continue;
			}
			if ( ! $this->storage->exists( $name ) ) {
				$removed[] = $name;
				continue;
			}
			if ( in_array( $name, $newest, true ) ) {
				continue;
			}
			$generated = $this->parseUtc( (string) ( $entry['generated_at_utc'] ?? '' ) );
			if ( null !== $generated && $generated < $cutoff ) {
				$this->storage->delete( $name );
				$removed[] = $name;
			}
		}
		foreach ( $removed as $name ) {
			$this->manifest->remove( $name );
		}
		return $removed;
	}

	/**
	 * @return string[] Name of the newest existing entry per format.
	 */
	/**
	 * Newest existing file per (outlet, format); legacy entries without an outlet key belong to the primary.
	 *
	 * @return string[]
	 */
	private function newestPerFormat( string $primaryKey ): array {
		$newest = [];
		foreach ( $this->manifest->entries() as $entry ) {
			$outlet = (string) ( $entry['outlet'] ?? '' );
			$group  = ( '' === $outlet ? $primaryKey : $outlet ) . '|' . (string) ( $entry['format'] ?? '' );
			$name   = (string) ( $entry['name'] ?? '' );
			if ( isset( $newest[ $group ] ) || ! $this->storage->exists( $name ) ) {
				continue;
			}
			$newest[ $group ] = $name;
		}
		return array_values( $newest );
	}

	private function parseUtc( string $value ): ?DateTimeImmutable {
		$date = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $value, new DateTimeZone( 'UTC' ) );
		return false === $date ? null : $date;
	}
}
