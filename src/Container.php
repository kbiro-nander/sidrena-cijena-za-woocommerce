<?php
/**
 * Minimal lazy service container.
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

namespace SidrenaCijena;

use InvalidArgumentException;

final class Container {

	/** @var array<string,callable> */
	private array $factories = [];
	/** @var array<string,mixed> */
	private array $instances = [];

	public function set( string $id, callable $factory ): void {
		$this->factories[ $id ] = $factory;
		unset( $this->instances[ $id ] );
	}

	public function has( string $id ): bool {
		return isset( $this->factories[ $id ] ) || array_key_exists( $id, $this->instances );
	}

	/**
	 * @return mixed
	 */
	public function get( string $id ) {
		if ( ! array_key_exists( $id, $this->instances ) ) {
			if ( ! isset( $this->factories[ $id ] ) ) {
				throw new InvalidArgumentException( "Unknown service: {$id}" );
			}
			$this->instances[ $id ] = ( $this->factories[ $id ] )( $this );
		}
		return $this->instances[ $id ];
	}
}
