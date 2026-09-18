<?php
/**
 * Adds a `scwc` key to variation JSON so themes/JS can render reference data.
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

namespace SidrenaCijena\Display;

use WC_Product;

final class VariationJsonFilter {

	public function __construct( private readonly RenderGuard $guard, private readonly BadgeDataFactory $factory ) {}

	public function register(): void {
		add_filter( 'woocommerce_available_variation', [ $this, 'filter' ], 10, 3 );
	}

	/**
	 * @param array<string,mixed> $data      Variation data.
	 * @param mixed               $parent    Variable product.
	 * @param mixed               $variation Variation product.
	 * @return array<string,mixed>
	 */
	public function filter( $data, $parent, $variation ): array {
		$data = (array) $data;
		if ( ! $variation instanceof WC_Product || ! $this->guard->shouldRender( $variation, BadgeContext::VARIATION_JSON ) ) {
			return $data;
		}
		$badge        = $this->factory->forProduct( $variation, BadgeContext::VARIATION_JSON );
		$data['scwc'] = $badge ? $badge->toArray() : null;
		return $data;
	}
}
