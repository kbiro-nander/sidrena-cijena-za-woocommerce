<?php
/**
 * Value object: the sales outlet ("prodajni objekt") described in the price list.
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

namespace SidrenaCijena\PriceList;

use SidrenaCijena\Settings\Settings;

final class Outlet {

	public function __construct(
		public readonly string $form,
		public readonly string $address,
		public readonly string $label,
		public readonly string $storageNumber,
		public readonly string $merchantName,
		public readonly string $url,
	) {}

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

	/** Address and label are mandatory; form and storage number have defaults. */
	public function isComplete(): bool {
		return '' !== $this->address && '' !== $this->label;
	}
}
