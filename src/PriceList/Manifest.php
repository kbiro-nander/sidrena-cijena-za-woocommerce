<?php
/**
 * manifest.json: list of generated price-list files, newest first.
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

namespace SidrenaCijena\PriceList;

class Manifest {

	public const FILENAME = 'manifest.json';

	/** @var array<int,array<string,mixed>>|null Null until loaded. */
	private ?array $entries = null;

	public function __construct( private readonly Storage $storage ) {}

	/** (Re)read the manifest from disk; a missing or corrupt file is treated as empty. */
	public function load(): void {
		$this->entries = [];
		$path          = $this->path();
		if ( ! is_file( $path ) ) {
			return;
		}
		$json = json_decode( (string) file_get_contents( $path ), true );
		if ( ! is_array( $json ) || ! isset( $json['generated'] ) || ! is_array( $json['generated'] ) ) {
			return;
		}
		foreach ( $json['generated'] as $entry ) {
			if ( is_array( $entry ) && isset( $entry['name'] ) && is_string( $entry['name'] ) ) {
				$this->entries[] = $entry;
			}
		}
	}

	/**
	 * @return array<int,array<string,mixed>> Newest first.
	 */
	public function entries(): array {
		if ( null === $this->entries ) {
			$this->load();
		}
		return $this->entries ?? [];
	}

	/**
	 * Prepend an entry (replacing any entry with the same name) and save.
	 *
	 * @param array<string,mixed> $entry Entry with at least name/format/generated_at_utc.
	 */
	public function add( array $entry ): void {
		$entries = $this->entries();
		$name    = (string) ( $entry['name'] ?? '' );
		$entries = array_values( array_filter( $entries, static fn( array $e ) => ( $e['name'] ?? '' ) !== $name ) );
		array_unshift( $entries, $entry );
		$this->entries = $entries;
		$this->save();
	}

	/**
	 * @return array<string,mixed>|null
	 */
	/**
	 * Newest entry of a format. With $outletKey only that outlet's files count; entries without an
	 * outlet key (pre-1.1 manifests) belong to $primaryKey.
	 *
	 * @return array<string,mixed>|null
	 */
	public function latest( string $format, ?string $outletKey = null, string $primaryKey = '' ): ?array {
		foreach ( $this->entries() as $entry ) {
			if ( ( $entry['format'] ?? '' ) !== $format ) {
				continue;
			}
			if ( null === $outletKey || self::belongsTo( $entry, $outletKey, $primaryKey ) ) {
				return $entry;
			}
		}
		return null;
	}

	/**
	 * Entries of one outlet, newest first. Entries without an outlet key (pre-1.1 manifests) belong to the primary.
	 *
	 * @param string $outletKey  Outlet key.
	 * @param string $primaryKey Key of the primary outlet.
	 * @return array<int,array<string,mixed>>
	 */
	public function entriesFor( string $outletKey, string $primaryKey ): array {
		return array_values( array_filter( $this->entries(), static fn( array $e ) => self::belongsTo( $e, $outletKey, $primaryKey ) ) );
	}

	/**
	 * Distinct outlet keys present in the manifest (legacy entries mapped to $primaryKey), in first-seen order.
	 *
	 * @return string[]
	 */
	public function outletKeys( string $primaryKey ): array {
		$keys = [];
		foreach ( $this->entries() as $entry ) {
			$key          = (string) ( $entry['outlet'] ?? '' );
			$key          = '' === $key ? $primaryKey : $key;
			$keys[ $key ] = true;
		}
		return array_keys( $keys );
	}

	/**
	 * @param array<string,mixed> $entry Manifest entry.
	 */
	private static function belongsTo( array $entry, string $outletKey, string $primaryKey ): bool {
		$key = (string) ( $entry['outlet'] ?? '' );
		return $key === $outletKey || ( '' === $key && '' !== $primaryKey && $outletKey === $primaryKey );
	}

	/**
	 * @return array<string,mixed>|null
	 */
	public function entry( string $name ): ?array {
		foreach ( $this->entries() as $entry ) {
			if ( ( $entry['name'] ?? '' ) === $name ) {
				return $entry;
			}
		}
		return null;
	}

	public function has( string $name ): bool {
		return null !== $this->entry( $name );
	}

	public function remove( string $name ): void {
		$entries = $this->entries();
		$kept    = array_values( array_filter( $entries, static fn( array $e ) => ( $e['name'] ?? '' ) !== $name ) );
		if ( count( $kept ) === count( $entries ) ) {
			return;
		}
		$this->entries = $kept;
		$this->save();
	}

	public function save(): void {
		$json = wp_json_encode( [ 'generated' => array_values( $this->entries() ) ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		file_put_contents( $this->path(), false === $json ? '{"generated":[]}' : $json, LOCK_EX );
	}

	private function path(): string {
		return $this->storage->path( self::FILENAME );
	}
}
