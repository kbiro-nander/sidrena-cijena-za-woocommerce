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
	public function latest( string $format ): ?array {
		foreach ( $this->entries() as $entry ) {
			if ( ( $entry['format'] ?? '' ) === $format ) {
				return $entry;
			}
		}
		return null;
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
