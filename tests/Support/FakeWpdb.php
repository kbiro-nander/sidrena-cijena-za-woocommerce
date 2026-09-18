<?php
declare(strict_types=1);

namespace SidrenaCijena\Tests\Support;

/**
 * Records queries; returns scripted results. prepare() interpolates like wpdb (quoted strings).
 */
final class FakeWpdb {
	public string $prefix = 'wp_';
	public int $insert_id = 0;
	/** @var array<int,string> */
	public array $queries = [];
	/** @var array<int,array<string,mixed>> */
	public array $inserts = [];
	/** @var array<int,mixed> */
	public array $varResults = [];
	/** @var array<int,mixed> */
	public array $rowResults = [];
	/** @var array<int,mixed> */
	public array $resultsResults = [];

	public function get_charset_collate(): string { return 'DEFAULT CHARSET=utf8mb4'; }

	public function prepare( string $query, ...$args ): string {
		if ( 1 === count( $args ) && is_array( $args[0] ) ) {
			$args = $args[0];
		}
		$query = str_replace( [ '%d', '%s', '%f' ], [ '%d', "'%s'", '%F' ], $query );
		return vsprintf( $query, array_map( fn( $a ) => is_string( $a ) ? addslashes( $a ) : $a, $args ) );
	}

	public function insert( string $table, array $data, $format = null ): int {
		$this->inserts[] = $data;
		$this->insert_id = count( $this->inserts );
		return 1;
	}

	public function get_var( string $query ) {
		$this->queries[] = $query;
		return array_shift( $this->varResults );
	}

	public function get_row( string $query, $output = ARRAY_A ) {
		$this->queries[] = $query;
		return array_shift( $this->rowResults );
	}

	public function get_results( string $query, $output = ARRAY_A ) {
		$this->queries[] = $query;
		return array_shift( $this->resultsResults ) ?? [];
	}

	public function query( string $query ) {
		$this->queries[] = $query;
		return 1;
	}
}
