<?php
/**
 * Exposes reference-price data to WooCommerce Blocks via the Store API (cart items + products).
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

namespace SidrenaCijena\StoreApi;

use SidrenaCijena\Display\BadgeContext;
use SidrenaCijena\Display\BadgeDataFactory;
use SidrenaCijena\Display\PriceBadge;
use WC_Product;

final class ExtendStoreApi {

	public const NAMESPACE = 'sidrena-cijena';

	public function __construct( private readonly BadgeDataFactory $factory, private readonly PriceBadge $badge ) {}

	public function register(): void {
		add_action( 'woocommerce_blocks_loaded', [ $this, 'registerEndpointData' ] );
	}

	public function registerEndpointData(): void {
		if ( ! function_exists( 'woocommerce_store_api_register_endpoint_data' ) ) {
			return;
		}
		$cartItem = '\Automattic\WooCommerce\StoreApi\Schemas\V1\CartItemSchema';
		$product  = '\Automattic\WooCommerce\StoreApi\Schemas\V1\ProductSchema';
		if ( ! class_exists( $cartItem ) || ! class_exists( $product ) ) {
			return;
		}
		woocommerce_store_api_register_endpoint_data(
			[
				'endpoint'        => constant( $cartItem . '::IDENTIFIER' ),
				'namespace'       => self::NAMESPACE,
				'schema_type'     => ARRAY_A,
				'schema_callback' => [ $this, 'schema' ],
				'data_callback'   => function ( $cartItem ): array {
					$product = is_array( $cartItem ) ? ( $cartItem['data'] ?? null ) : null;
					return $this->data( $product instanceof WC_Product ? $product : null );
				},
			]
		);
		woocommerce_store_api_register_endpoint_data(
			[
				'endpoint'        => constant( $product . '::IDENTIFIER' ),
				'namespace'       => self::NAMESPACE,
				'schema_type'     => ARRAY_A,
				'schema_callback' => [ $this, 'schema' ],
				'data_callback'   => fn( $product ): array => $this->data( $product instanceof WC_Product ? $product : null ),
			]
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	public function schema(): array {
		return [
			'references'     => [
				'description' => __( 'Referentne cijene (sidrena/bazna) s datumom.', 'sidrena-cijena-za-woocommerce' ),
				'type'        => 'array',
				'readonly'    => true,
			],
			'lowest_30_days' => [
				'description' => __( 'Najniža cijena u 30 dana prije sniženja.', 'sidrena-cijena-za-woocommerce' ),
				'type'        => [ 'object', 'null' ],
				'readonly'    => true,
			],
			'is_on_sale'     => [
				'type'     => 'boolean',
				'readonly' => true,
			],
			'badge_html'     => [
				'description' => __( 'Gotov HTML oznake za prikaz.', 'sidrena-cijena-za-woocommerce' ),
				'type'        => 'string',
				'readonly'    => true,
			],
		];
	}

	/**
	 * @return array<string,mixed>
	 */
	private function data( ?WC_Product $product ): array {
		$empty = [ 'references' => [], 'lowest_30_days' => null, 'is_on_sale' => false, 'badge_html' => '' ];
		if ( ! $product ) {
			return $empty;
		}
		$data = $this->factory->forProduct( $product, BadgeContext::STORE_API );
		if ( ! $data ) {
			return $empty;
		}
		return $data->toArray() + [ 'badge_html' => $this->badge->render( $data, BadgeContext::STORE_API ) ];
	}
}
