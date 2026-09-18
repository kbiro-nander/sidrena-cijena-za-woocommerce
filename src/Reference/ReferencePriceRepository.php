<?php
/**
 * Reads reference prices from snapshots and writes them to post meta.
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

namespace SidrenaCijena\Reference;

use SidrenaCijena\Product\ProductSnapshot;
use SidrenaCijena\Support\DateFormat;

final class ReferencePriceRepository {

	public const SOURCE_MANUAL    = 'manual';
	public const SOURCE_SNAPSHOT  = 'snapshot';
	public const SOURCE_IMPORT    = 'import';
	public const SOURCE_AUTO      = 'auto';
	public const SOURCE_INHERITED = 'inherited';

	public function __construct( private readonly ReferenceDateResolver $dates ) {}

	public function get( ReferencePriceType $type, ProductSnapshot $product, ?ProductSnapshot $parent = null, bool $inheritParent = false ): ReferencePrice {
		$date = $this->dates->resolve( $type, $product, $parent );
		if ( '1' === (string) $product->meta( $type->metaKey( 'na' ) ) ) {
			return ReferencePrice::absent( $type, $date, true );
		}
		$amount = (string) $product->meta( $type->metaKey( 'price' ) );
		$source = (string) $product->meta( $type->metaKey( 'source' ) );
		if ( '' === $amount && $inheritParent && $parent && $product->isVariation() ) {
			if ( '1' === (string) $parent->meta( $type->metaKey( 'na' ) ) ) {
				return ReferencePrice::absent( $type, $date, true );
			}
			$amount = (string) $parent->meta( $type->metaKey( 'price' ) );
			$source = '' === $amount ? '' : self::SOURCE_INHERITED;
		}
		if ( '' === $amount ) {
			return ReferencePrice::absent( $type, $date );
		}
		return new ReferencePrice( $type, $amount, $date, $source, false );
	}

	/**
	 * @param string      $amount Decimal string (already wc_format_decimal'd).
	 * @param string|null $date   Per-product date override (Y-m-d) or null to clear.
	 */
	public function set( ReferencePriceType $type, int $productId, string $amount, ?string $date, string $source ): void {
		update_post_meta( $productId, $type->metaKey( 'price' ), $amount );
		update_post_meta( $productId, $type->metaKey( 'source' ), $source );
		if ( null !== $date && null !== DateFormat::parseIso( $date ) ) {
			update_post_meta( $productId, $type->metaKey( 'date' ), $date );
		} else {
			delete_post_meta( $productId, $type->metaKey( 'date' ) );
		}
		delete_post_meta( $productId, $type->metaKey( 'na' ) );
	}

	public function setNa( ReferencePriceType $type, int $productId, string $source ): void {
		update_post_meta( $productId, $type->metaKey( 'na' ), '1' );
		update_post_meta( $productId, $type->metaKey( 'source' ), $source );
		delete_post_meta( $productId, $type->metaKey( 'price' ) );
	}

	public function clear( ReferencePriceType $type, int $productId ): void {
		foreach ( [ 'price', 'date', 'source', 'na' ] as $suffix ) {
			delete_post_meta( $productId, $type->metaKey( $suffix ) );
		}
	}
}
