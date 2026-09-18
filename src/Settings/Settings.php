<?php
/**
 * Read-only, immutable settings accessor with dotted paths.
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

namespace SidrenaCijena\Settings;

final class Settings {

	public const OPTION = 'scwc_settings';

	/** @var array<string,mixed> */
	private array $data;

	/**
	 * @param array<string,mixed> $data Settings array (already merged with defaults if desired).
	 */
	public function __construct( array $data ) {
		$this->data = $data;
	}

	/** Load from the WordPress option, merged over defaults. */
	public static function fromOption(): self {
		$stored = get_option( self::OPTION, [] );
		$stored = is_array( $stored ) ? $stored : [];
		return new self( self::mergeDeep( Defaults::all(), $stored ) );
	}

	/**
	 * @param mixed $default Value when the path is absent.
	 * @return mixed
	 */
	public function get( string $path, $default = null ) {
		$node = $this->data;
		foreach ( explode( '.', $path ) as $segment ) {
			if ( ! is_array( $node ) || ! array_key_exists( $segment, $node ) ) {
				return $default;
			}
			$node = $node[ $segment ];
		}
		return $node;
	}

	/**
	 * @return array<string,mixed>
	 */
	public function section( string $name ): array {
		$value = $this->data[ $name ] ?? [];
		return is_array( $value ) ? $value : [];
	}

	/**
	 * @return array<string,mixed>
	 */
	public function all(): array {
		return $this->data;
	}

	/**
	 * @param mixed $value New value.
	 */
	public function with( string $path, $value ): self {
		$data = $this->data;
		$node = &$data;
		foreach ( explode( '.', $path ) as $segment ) {
			if ( ! isset( $node[ $segment ] ) || ! is_array( $node[ $segment ] ) ) {
				$node[ $segment ] = [];
			}
			$node = &$node[ $segment ];
		}
		$node = $value;
		return new self( $data );
	}

	/**
	 * Recursive merge where $override wins; list arrays (e.g. formats, overrides) are replaced, not merged.
	 *
	 * @param array<string,mixed> $base     Base array.
	 * @param array<string,mixed> $override Override array.
	 * @return array<string,mixed>
	 */
	public static function mergeDeep( array $base, array $override ): array {
		foreach ( $override as $key => $value ) {
			if ( is_array( $value ) && isset( $base[ $key ] ) && is_array( $base[ $key ] ) && ! array_is_list( $base[ $key ] ) ) {
				$base[ $key ] = self::mergeDeep( $base[ $key ], $value );
			} else {
				$base[ $key ] = $value;
			}
		}
		return $base;
	}
}
