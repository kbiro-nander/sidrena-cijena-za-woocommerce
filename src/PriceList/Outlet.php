<?php
/**
 * Value object: one sales outlet ("prodajni objekt") described in a price list.
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

namespace SidrenaCijena\PriceList;

use SidrenaCijena\Settings\Settings;
use SidrenaCijena\Support\Slugifier;

final class Outlet {

	public const PRIMARY_FALLBACK_KEY = 'webshop';

	/** URL/manifest key (ASCII slug of the label, unique across outlets). */
	public readonly string $key;

	public function __construct(
		public readonly string $form,
		public readonly string $address,
		public readonly string $label,
		public readonly string $storageNumber,
		public readonly string $merchantName,
		public readonly string $url,
		string $key = '',
		private readonly bool $primary = true,
	) {
		if ( '' === $key ) {
			$key = Slugifier::filenamePart( $label );
		}
		$this->key = '' === $key ? self::PRIMARY_FALLBACK_KEY : $key;
	}

	/** The primary outlet (the webshop itself) from the `outlet.*` settings. */
	public static function fromSettings( Settings $settings ): self {
		$merchant = trim( (string) $settings->get( 'outlet.merchant_name', '' ) );
		if ( '' === $merchant ) {
			$merchant = (string) get_bloginfo( 'name' );
		}
		return new self(
			trim( (string) $settings->get( 'outlet.form', 'webshop' ) ),
			trim( (string) $settings->get( 'outlet.address', '' ) ),
			trim( (string) $settings->get( 'outlet.label', '' ) ),
			trim( (string) $settings->get( 'outlet.storage_number', '1' ) ),
			$merchant,
			(string) home_url( '/' ),
		);
	}

	public function withKey( string $key, bool $primary ): self {
		return new self( $this->form, $this->address, $this->label, $this->storageNumber, $this->merchantName, $this->url, $key, $primary );
	}

	public function isPrimary(): bool {
		return $this->primary;
	}

	/** Address and label are mandatory; form and storage number have defaults. */
	public function isComplete(): bool {
		return '' !== $this->address && '' !== $this->label;
	}
}
