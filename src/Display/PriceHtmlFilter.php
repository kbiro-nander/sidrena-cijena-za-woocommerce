<?php
/**
 * woocommerce_get_price_html → price + badge (shop loop, single product, variations, blocks).
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

namespace SidrenaCijena\Display;

use WC_Product;

final class PriceHtmlFilter {

	private bool $rendering = false;

	public function __construct(
		private readonly RenderGuard $guard,
		private readonly BadgeDataFactory $factory,
		private readonly PriceHtmlComposer $composer,
	) {}

	public function register(): void {
		add_filter( 'woocommerce_get_price_html', [ $this, 'filter' ], 100, 2 );
	}

	/**
	 * @param mixed $html    WC price HTML.
	 * @param mixed $product Product.
	 */
	public function filter( $html, $product ): string {
		$html = (string) $html;
		if ( $this->rendering || ! $product instanceof WC_Product || '' === trim( $html ) ) {
			return $html;
		}
		$context = $this->detectContext( $product );
		if ( ! $this->guard->shouldRender( $product, $context ) ) {
			return $html;
		}
		$this->rendering = true;
		try {
			$data = $this->factory->forProduct( $product, $context );
			return $data ? $this->composer->compose( $html, $product, $data, $context ) : $html;
		} finally {
			$this->rendering = false;
		}
	}

	private function detectContext( WC_Product $product ): string {
		if ( is_product() && in_the_loop() ) {
			$mainId = 'variation' === $product->get_type() ? $product->get_parent_id() : $product->get_id();
			if ( get_queried_object_id() === $mainId ) {
				return BadgeContext::SINGLE;
			}
		}
		return BadgeContext::LOOP;
	}
}
