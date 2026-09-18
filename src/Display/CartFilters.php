<?php
/**
 * Classic cart/checkout price filters and Store API item_data rows.
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

namespace SidrenaCijena\Display;

use SidrenaCijena\Settings\Settings;
use WC_Product;

final class CartFilters {

	/** @var callable():RequestContext */
	private $context;

	/**
	 * @param callable():RequestContext $contextProvider Request context.
	 */
	public function __construct(
		private readonly Settings $settings,
		private readonly RenderGuard $guard,
		private readonly BadgeDataFactory $factory,
		private readonly PriceHtmlComposer $composer,
		callable $contextProvider,
	) {
		$this->context = $contextProvider;
	}

	public function register(): void {
		add_filter( 'woocommerce_cart_item_price', [ $this, 'itemPrice' ], 10, 3 );
		add_filter( 'woocommerce_cart_item_subtotal', [ $this, 'itemSubtotal' ], 10, 3 );
		add_filter( 'woocommerce_get_item_data', [ $this, 'itemData' ], 10, 2 );
	}

	/**
	 * @param mixed               $html     Price HTML.
	 * @param array<string,mixed> $cartItem Cart item.
	 */
	public function itemPrice( $html, $cartItem, string $cartItemKey = '' ): string {
		$html    = (string) $html;
		$product = $cartItem['data'] ?? null;
		if ( ! $product instanceof WC_Product ) {
			return $html;
		}
		$context = did_action( 'woocommerce_before_mini_cart' ) > did_action( 'woocommerce_after_mini_cart' ) ? BadgeContext::MINI_CART : BadgeContext::CART;
		if ( ! $this->guard->shouldRender( $product, $context ) ) {
			return $html;
		}
		$data = $this->factory->forProduct( $product, $context );
		return $data ? $this->composer->compose( $html, $product, $data, $context ) : $html;
	}

	/**
	 * @param mixed               $html     Subtotal HTML.
	 * @param array<string,mixed> $cartItem Cart item.
	 */
	public function itemSubtotal( $html, $cartItem, string $cartItemKey = '' ): string {
		$html    = (string) $html;
		$product = $cartItem['data'] ?? null;
		if ( ! $product instanceof WC_Product || ! is_checkout() || is_cart() ) {
			return $html;
		}
		$mode = (string) $this->settings->get( 'display.checkout', 'unit' );
		if ( 'none' === $mode || ! $this->guard->shouldRender( $product, BadgeContext::CHECKOUT ) ) {
			return $html;
		}
		$qty  = 'total' === $mode ? max( 1, (int) ( $cartItem['quantity'] ?? 1 ) ) : 1;
		$data = $this->factory->forProduct( $product, BadgeContext::CHECKOUT, $qty );
		return $data ? $this->composer->compose( $html, $product, $data, BadgeContext::CHECKOUT ) : $html;
	}

	/**
	 * Block cart/checkout render item_data rows; classic cart already carries the badge.
	 *
	 * @param mixed               $itemData Existing rows.
	 * @param array<string,mixed> $cartItem Cart item.
	 * @return array<int,array<string,string>>
	 */
	public function itemData( $itemData, $cartItem ): array {
		$itemData = is_array( $itemData ) ? $itemData : [];
		$product  = $cartItem['data'] ?? null;
		if ( ! $product instanceof WC_Product || ! (bool) $this->settings->get( 'display.item_data', true ) ) {
			return $itemData;
		}
		if ( ! ( $this->context )()->isStoreApi() || ! $this->guard->shouldRender( $product, BadgeContext::CART ) ) {
			return $itemData;
		}
		$data = $this->factory->forProduct( $product, BadgeContext::CART );
		if ( ! $data ) {
			return $itemData;
		}
		if ( $data->omnibus ) {
			$itemData[] = [
				'key'   => (string) $this->settings->get( 'display.omnibus_label', '' ),
				'value' => (string) ( $data->omnibus->amountText ?? $data->omnibus->amountHtml ),
			];
		}
		foreach ( $data->references as $ref ) {
			$itemData[] = [
				'key'   => $ref->fullLabel(),
				'value' => (string) ( $ref->amountText ?? $ref->amountHtml ),
			];
		}
		return $itemData;
	}
}
