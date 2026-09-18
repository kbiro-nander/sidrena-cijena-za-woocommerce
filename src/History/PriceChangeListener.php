<?php
/**
 * Listens to WooCommerce product saves and feeds the history + omnibus state.
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

namespace SidrenaCijena\History;

use SidrenaCijena\Product\ProductAdapter;
use WC_Product;

final class PriceChangeListener {

	public const PRICE_PROPS = [ 'price', 'regular_price', 'sale_price', 'date_on_sale_from', 'date_on_sale_to' ];

	/** @var callable(int):?WC_Product */
	private $loader;

	/**
	 * @param callable(int):?WC_Product $productLoader Product loader.
	 */
	public function __construct(
		private readonly ProductAdapter $adapter,
		private readonly Recorder $recorder,
		private readonly OmnibusStateUpdater $updater,
		callable $productLoader,
	) {
		$this->loader = $productLoader;
	}

	public function register(): void {
		add_action( 'woocommerce_product_object_updated_props', [ $this, 'onUpdatedProps' ], 10, 2 );
		add_action( 'woocommerce_new_product', [ $this, 'onNewProduct' ] );
		add_action( 'woocommerce_new_product_variation', [ $this, 'onNewProduct' ] );
		add_action( 'woocommerce_delete_product', [ $this, 'onDeleted' ] );
		add_action( 'woocommerce_delete_product_variation', [ $this, 'onDeleted' ] );
		add_action( 'woocommerce_trash_product', [ $this, 'onDeleted' ] );
	}

	/**
	 * @param mixed    $product Product.
	 * @param string[] $props   Updated prop names.
	 */
	public function onUpdatedProps( $product, $props ): void {
		if ( ! $product instanceof WC_Product || [] === array_intersect( (array) $props, self::PRICE_PROPS ) ) {
			return;
		}
		$this->process( $product, 'save' );
	}

	/**
	 * @param mixed $productId Product ID.
	 */
	public function onNewProduct( $productId ): void {
		$product = ( $this->loader )( (int) $productId );
		if ( $product instanceof WC_Product ) {
			$this->process( $product, 'save' );
		}
	}

	/**
	 * @param mixed $productId Product ID.
	 */
	public function onDeleted( $productId ): void {
		do_action( 'scwc_product_removed', (int) $productId );
	}

	public function process( WC_Product $product, string $source ): string {
		if ( in_array( $product->get_type(), [ 'variable', 'grouped' ], true ) ) {
			return Transition::NONE;
		}
		$parent = null;
		if ( 'variation' === $product->get_type() && $product->get_parent_id() ) {
			$parent = ( $this->loader )( $product->get_parent_id() );
		}
		$snapshot   = $this->adapter->fromProduct( $product, $parent );
		$transition = $this->recorder->record( $snapshot, $source );
		$this->updater->apply( $snapshot, $transition );
		if ( Transition::NONE !== $transition ) {
			do_action( 'scwc_price_changed', $snapshot, $transition );
		}
		return $transition;
	}
}
