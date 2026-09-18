<?php
/**
 * Decides whether a badge may be rendered for a product in a context.
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

namespace SidrenaCijena\Display;

use SidrenaCijena\Settings\Settings;
use WC_Product;

final class RenderGuard {

	/** @var callable():RequestContext */
	private $context;

	/**
	 * @param callable():RequestContext $contextProvider Lazily evaluated request context.
	 */
	public function __construct( private readonly Settings $settings, callable $contextProvider ) {
		$this->context = $contextProvider;
	}

	public function shouldRender( WC_Product $product, string $context ): bool {
		$allowed = $this->contextEnabled( $context ) && 'grouped' !== $product->get_type();
		if ( $allowed ) {
			$request = ( $this->context )();
			$allowed = ! $request->isAdminScreen() && ! $request->isWcRest() && ! $request->isEmail();
		}
		/** @var bool $allowed */
		$allowed = apply_filters( 'scwc_should_render_badge', $allowed, $product, $context );
		return $allowed;
	}

	private function contextEnabled( string $context ): bool {
		return match ( $context ) {
			BadgeContext::LOOP      => (bool) $this->settings->get( 'display.loop', true ),
			BadgeContext::SINGLE    => (bool) $this->settings->get( 'display.single', true ),
			BadgeContext::CART      => (bool) $this->settings->get( 'display.cart', true ),
			BadgeContext::MINI_CART => (bool) $this->settings->get( 'display.mini_cart', false ),
			BadgeContext::CHECKOUT  => 'none' !== $this->settings->get( 'display.checkout', 'unit' ),
			default                 => true,
		};
	}
}
