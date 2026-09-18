<?php
/**
 * Registry of reference-price types built from settings (+ filter).
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

namespace SidrenaCijena\Reference;

use SidrenaCijena\Settings\Settings;

final class ReferencePriceRegistry {

	/** @var array<string,ReferencePriceType> */
	private array $types;

	/**
	 * @param array<string,ReferencePriceType> $types Types keyed by type key.
	 */
	public function __construct( array $types ) {
		$this->types = $types;
	}

	public static function fromSettings( Settings $settings ): self {
		$types = [
			'anchor' => self::build( 'anchor', $settings, true, 'SidrenaCijena', 'sidrena_cijena' ),
			'base'   => self::build( 'base', $settings, false, 'BaznaCijena', 'bazna_cijena' ),
		];
		/** @var array<string,ReferencePriceType> $types */
		$types = apply_filters( 'scwc_reference_price_types', $types, $settings );
		return new self( array_filter( $types, static fn( $t ) => $t instanceof ReferencePriceType ) );
	}

	private static function build( string $key, Settings $settings, bool $alwaysEnabled, string $xml, string $csv ): ReferencePriceType {
		$cfg  = $settings->section( 'reference_prices' )[ $key ] ?? [];
		$date = trim( (string) ( $cfg['date'] ?? '' ) );
		return new ReferencePriceType(
			$key,
			(string) ( $cfg['label'] ?? $key ),
			'' === $date ? null : $date,
			(array) ( $cfg['category_overrides'] ?? [] ),
			$alwaysEnabled || (bool) ( $cfg['enabled'] ?? false ),
			$xml,
			$csv,
		);
	}

	/** @return array<string,ReferencePriceType> */
	public function all(): array {
		return $this->types;
	}

	/** @return array<string,ReferencePriceType> */
	public function enabled(): array {
		return array_filter( $this->types, static fn( ReferencePriceType $t ) => $t->enabled );
	}

	public function get( string $key ): ?ReferencePriceType {
		return $this->types[ $key ] ?? null;
	}

	public function primary(): ReferencePriceType {
		return $this->types['anchor'] ?? array_values( $this->types )[0];
	}
}
