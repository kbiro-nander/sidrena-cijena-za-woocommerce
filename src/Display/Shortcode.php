<?php
/**
 * [sidrena_cijena id="" key="" context=""] and the sidrena_cijena() template function backend.
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

namespace SidrenaCijena\Display;

use WC_Product;

final class Shortcode {

	public const TAG = 'sidrena_cijena';

	/** @var callable(int):?WC_Product */
	private $loader;

	/**
	 * @param callable(int):?WC_Product $productLoader Loads a product by ID.
	 */
	public function __construct(
		private readonly RenderGuard $guard,
		private readonly BadgeDataFactory $factory,
		private readonly PriceBadge $badge,
		callable $productLoader,
	) {
		$this->loader = $productLoader;
	}

	public function register(): void {
		add_shortcode( self::TAG, [ $this, 'render' ] );
	}

	/**
	 * @param mixed $atts Shortcode attributes.
	 */
	public function render( $atts = [] ): string {
		$atts    = shortcode_atts( [ 'id' => 0, 'key' => '', 'context' => BadgeContext::SHORTCODE ], $atts, self::TAG );
		$product = $this->resolveProduct( (int) $atts['id'] );
		if ( ! $product ) {
			return '';
		}
		return $this->renderFor( $product, (string) $atts['key'], (string) $atts['context'] );
	}

	public function renderFor( WC_Product $product, string $key = '', string $context = BadgeContext::SHORTCODE ): string {
		if ( ! $this->guard->shouldRender( $product, $context ) ) {
			return '';
		}
		$data = $this->factory->forProduct( $product, $context );
		if ( ! $data ) {
			return '';
		}
		if ( '' !== $key ) {
			$data = $data->onlyKey( $key );
		}
		return $this->badge->render( $data, $context );
	}

	private function resolveProduct( int $id ): ?WC_Product {
		if ( $id > 0 ) {
			return ( $this->loader )( $id );
		}
		$global = $GLOBALS['product'] ?? null;
		return $global instanceof WC_Product ? $global : null;
	}
}
