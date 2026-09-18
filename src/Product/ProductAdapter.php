<?php
/**
 * The only place that reads WC_Product getters; produces ProductSnapshot DTOs.
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

namespace SidrenaCijena\Product;

use DateTimeImmutable;
use DateTimeZone;
use WC_Product;

final class ProductAdapter {

	public function __construct(
		private readonly BrandResolver $brands,
		private readonly BarcodeResolver $barcodes,
	) {}

	/**
	 * @param WC_Product      $product Product or variation.
	 * @param WC_Product|null $parent  Parent for variations (loaded by caller when available).
	 */
	public function fromProduct( WC_Product $product, ?WC_Product $parent = null ): ProductSnapshot {
		$isVariation = 'variation' === $product->get_type();
		$brandSource = ( $isVariation && $parent ) ? $parent->get_id() : $product->get_id();
		$categories  = ( $isVariation && $parent ) ? $parent->get_category_ids( 'edit' ) : $product->get_category_ids( 'edit' );

		$meta = $this->collectMeta( $product, $parent );

		$unitQty = $meta[ MetaKeys::UNIT_QUANTITY ] ?? '';
		$flag    = $meta[ MetaKeys::IS_SERVICE ] ?? '';

		return new ProductSnapshot(
			$product->get_id(),
			$product->get_parent_id( 'edit' ),
			$product->get_type(),
			(string) $product->get_sku( 'edit' ),
			(string) $product->get_name( 'edit' ),
			(string) $product->get_regular_price( 'edit' ),
			(string) $product->get_sale_price( 'edit' ),
			(string) $product->get_price( 'edit' ),
			$product->is_on_sale( 'edit' ),
			$this->toImmutable( $product->get_date_on_sale_from( 'edit' ) ),
			$this->toImmutable( $product->get_date_on_sale_to( 'edit' ) ),
			$product->is_virtual(),
			array_map( 'intval', $categories ),
			$this->brands->resolve( $brandSource ),
			$this->barcodes->resolve( $product ),
			(string) ( $meta[ MetaKeys::UNIT ] ?? '' ),
			is_numeric( $unitQty ) ? (float) $unitQty : null,
			'' === $flag ? null : ( 'yes' === $flag ),
			(string) ( $meta[ MetaKeys::SALE_NAME ] ?? '' ),
			'yes' === ( $meta[ MetaKeys::EXCLUDE ] ?? '' ),
			'yes' === ( $meta[ MetaKeys::PRICE_ON_REQUEST ] ?? '' ),
			(string) $product->get_stock_status( 'edit' ),
			in_array( $product->get_catalog_visibility( 'edit' ), [ 'visible', 'catalog' ], true ),
			(string) $product->get_status( 'edit' ),
			$this->toImmutable( $product->get_date_created( 'edit' ) ),
			(string) $product->get_permalink(),
			$meta,
		);
	}

	/**
	 * All plugin meta (prefix _scwc_) from the product, with inheritable keys filled from the parent.
	 *
	 * @return array<string,mixed>
	 */
	private function collectMeta( WC_Product $product, ?WC_Product $parent ): array {
		$meta = [];
		foreach ( $this->pluginMetaKeys( $product ) as $key ) {
			$value = $product->get_meta( $key, true, 'edit' );
			if ( '' !== $value && null !== $value ) {
				$meta[ $key ] = $value;
			}
		}
		if ( $parent ) {
			foreach ( MetaKeys::INHERITED as $key ) {
				if ( ! isset( $meta[ $key ] ) ) {
					$value = $parent->get_meta( $key, true, 'edit' );
					if ( '' !== $value && null !== $value ) {
						$meta[ $key ] = $value;
					}
				}
			}
		}
		return $meta;
	}

	/**
	 * @return string[]
	 */
	private function pluginMetaKeys( WC_Product $product ): array {
		$keys   = MetaKeys::INHERITED;
		$keys[] = MetaKeys::OMNIBUS_REF_PRICE;
		$keys[] = MetaKeys::OMNIBUS_SALE_START;
		$keys[] = MetaKeys::OMNIBUS_SOURCE;
		// Reference-price keys are discovered dynamically (any type key).
		foreach ( $this->allMetaKeys( $product ) as $key ) {
			if ( str_starts_with( $key, MetaKeys::REF_PREFIX ) ) {
				$keys[] = $key;
			}
		}
		return array_values( array_unique( $keys ) );
	}

	/**
	 * @return string[]
	 */
	private function allMetaKeys( WC_Product $product ): array {
		if ( method_exists( $product, 'all_meta' ) ) {
			// Test stub.
			return array_keys( $product->all_meta() );
		}
		$keys = [];
		foreach ( $product->get_meta_data() as $item ) {
			$data   = $item->get_data();
			$keys[] = (string) $data['key'];
		}
		return $keys;
	}

	/**
	 * @param mixed $date WC_DateTime|null.
	 */
	private function toImmutable( $date ): ?DateTimeImmutable {
		if ( ! $date instanceof \DateTimeInterface ) {
			return null;
		}
		return DateTimeImmutable::createFromInterface( $date )->setTimezone( new DateTimeZone( 'UTC' ) );
	}
}
