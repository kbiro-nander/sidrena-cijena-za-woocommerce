<?php
/**
 * Extracts plugin fields from the product-edit form and applies them to a product/variation.
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

namespace SidrenaCijena\Admin;

use SidrenaCijena\Product\MetaKeys;
use SidrenaCijena\Reference\ReferencePriceRegistry;
use SidrenaCijena\Reference\ReferencePriceRepository;
use SidrenaCijena\Support\DateFormat;
use WC_Product;

class ProductSave {

	/** Hidden marker rendered with the general-tab fields; when present, absent checkboxes mean "unchecked". */
	public const GENERAL_MARKER = 'scwc_general_fields';

	/** @var callable():array<string,mixed> */
	private $input;

	/**
	 * @param callable():array<string,mixed>|null $input Returns the raw request array (defaults to $_POST).
	 */
	public function __construct( private readonly ReferencePriceRegistry $registry, ?callable $input = null ) {
		$this->input = $input ?? static function (): array {
			// Nonce and capability are verified by WooCommerce's product meta box before these hooks fire.
			return $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		};
	}

	public function register(): void {
		add_action( 'woocommerce_admin_process_product_object', [ $this, 'onProduct' ], 10, 1 );
		add_action( 'woocommerce_admin_process_variation_object', [ $this, 'onVariation' ], 10, 2 );
	}

	public function onProduct( WC_Product $product ): void {
		$this->apply( $product, $this->extract( ( $this->input )(), null ) );
	}

	public function onVariation( WC_Product $variation, int $loop ): void {
		$this->apply( $variation, $this->extract( ( $this->input )(), $loop ) );
	}

	/**
	 * Map of meta key => value; null means "delete".
	 *
	 * @param array<string,mixed> $post Raw request array.
	 * @param int|null            $loop Variation loop index, null for the parent/simple product.
	 * @return array<string,string|null>
	 */
	public function extract( array $post, ?int $loop ): array {
		$out = [];
		foreach ( $this->registry->enabled() as $key => $type ) {
			$na    = 'yes' === $this->raw( $post, "scwc_ref_{$key}_na", $loop );
			$price = wc_format_decimal( $this->raw( $post, "scwc_ref_{$key}_price", $loop ) );
			$date  = trim( $this->raw( $post, "scwc_ref_{$key}_date", $loop ) );

			$out[ $type->metaKey( 'price' ) ] = ( $na || '' === $price ) ? null : $price;
			$out[ $type->metaKey( 'date' ) ]  = null !== DateFormat::parseIso( $date ) ? $date : null;
			$out[ $type->metaKey( 'na' ) ]    = $na ? '1' : null;
		}
		if ( null !== $loop || '1' !== $this->raw( $post, self::GENERAL_MARKER, null ) ) {
			return $out;
		}
		$qty = wc_format_decimal( $this->raw( $post, 'scwc_unit_quantity', null ) );

		$out[ MetaKeys::IS_SERVICE ]       = 'yes' === $this->raw( $post, 'scwc_is_service', null ) ? 'yes' : 'no';
		$out[ MetaKeys::UNIT ]             = $this->text( $post, 'scwc_unit' );
		$out[ MetaKeys::UNIT_QUANTITY ]    = ( '' !== $qty && (float) $qty > 0 ) ? $qty : null;
		$out[ MetaKeys::SALE_NAME ]        = $this->text( $post, 'scwc_sale_name' );
		$out[ MetaKeys::EXCLUDE ]          = 'yes' === $this->raw( $post, 'scwc_exclude_from_price_list', null ) ? 'yes' : null;
		$out[ MetaKeys::PRICE_ON_REQUEST ] = 'yes' === $this->raw( $post, 'scwc_price_on_request', null ) ? 'yes' : null;
		return $out;
	}

	/**
	 * @param array<string,string|null> $changes Meta key => value (null deletes).
	 */
	public function apply( WC_Product $product, array $changes ): void {
		foreach ( $this->registry->enabled() as $type ) {
			$priceKey = $type->metaKey( 'price' );
			if ( ! array_key_exists( $priceKey, $changes ) ) {
				continue;
			}
			$old   = (string) $product->get_meta( $priceKey );
			$new   = $changes[ $priceKey ];
			$naSet = '1' === ( $changes[ $type->metaKey( 'na' ) ] ?? null );
			if ( null === $new && ! $naSet ) {
				$changes[ $type->metaKey( 'source' ) ] = null;
				$changes[ $type->metaKey( 'date' ) ]   = null;
			} elseif ( $naSet || (string) $new !== $old ) {
				$changes[ $type->metaKey( 'source' ) ] = ReferencePriceRepository::SOURCE_MANUAL;
			}
		}
		foreach ( $changes as $key => $value ) {
			if ( null === $value ) {
				$product->delete_meta_data( $key );
			} else {
				$product->update_meta_data( $key, $value );
			}
		}
	}

	/**
	 * @param array<string,mixed> $post Raw request array.
	 */
	private function raw( array $post, string $name, ?int $loop ): string {
		if ( ! isset( $post[ $name ] ) ) {
			return '';
		}
		$value = $post[ $name ];
		if ( null !== $loop ) {
			$value = is_array( $value ) ? ( $value[ $loop ] ?? '' ) : '';
		}
		return is_scalar( $value ) ? (string) $value : '';
	}

	/**
	 * @param array<string,mixed> $post Raw request array.
	 */
	private function text( array $post, string $name ): ?string {
		$value = wc_clean( wp_unslash( $this->raw( $post, $name, null ) ) );
		$value = is_string( $value ) ? trim( $value ) : '';
		return '' === $value ? null : $value;
	}
}
