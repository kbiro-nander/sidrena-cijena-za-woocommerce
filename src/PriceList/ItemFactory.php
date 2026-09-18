<?php
/**
 * Turns product snapshots into price-list rows (or drops them).
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

namespace SidrenaCijena\PriceList;

use SidrenaCijena\Product\MetaKeys;
use SidrenaCijena\Product\ProductSnapshot;
use SidrenaCijena\Product\ServiceRule;
use SidrenaCijena\Product\UnitPrice;
use SidrenaCijena\Reference\ReferencePriceRegistry;
use SidrenaCijena\Reference\ReferencePriceRepository;
use SidrenaCijena\Settings\Settings;
use WC_Product;

class ItemFactory {

	public function __construct(
		private readonly Settings $settings,
		private readonly ReferencePriceRegistry $registry,
		private readonly ReferencePriceRepository $references,
		private readonly ServiceRule $serviceRule,
	) {}

	/**
	 * @param WC_Product           $product  Product (needed for tax conversion).
	 * @param ProductSnapshot      $snapshot Snapshot of $product.
	 * @param ProductSnapshot|null $parent   Parent snapshot for variations.
	 * @return Item|ServiceItem|null Null when the product is not listed.
	 */
	public function make( WC_Product $product, ProductSnapshot $snapshot, ?ProductSnapshot $parent ) {
		if ( ! $this->isListed( $snapshot ) ) {
			return null;
		}

		$price    = $this->toDisplay( $product, $snapshot->active );
		$onSale   = $snapshot->isOnSale;
		$saleName = '';
		if ( $onSale ) {
			$saleName = '' !== $snapshot->saleName ? $snapshot->saleName : (string) $this->settings->get( 'price_list.sale_name_default', '' );
		}
		$references = $this->references( $product, $snapshot, $parent );

		if ( $this->serviceRule->isService( $snapshot ) ) {
			$item = new ServiceItem( $snapshot->name, $price, $onSale, $saleName, $references );
		} else {
			$lowest30 = null;
			$omnibus  = $snapshot->meta( MetaKeys::OMNIBUS_REF_PRICE );
			if ( $onSale && is_numeric( $omnibus ) ) {
				$lowest30 = $this->toDisplay( $product, (string) $omnibus );
			}
			$item = new Item(
				$snapshot->name,
				$snapshot->sku,
				$snapshot->brand,
				$snapshot->unit,
				UnitPrice::compute( $price, $snapshot->unitQuantity ),
				$price,
				$onSale,
				$saleName,
				$references,
				$lowest30,
				$snapshot->barcode,
				in_array( $snapshot->stockStatus, [ 'instock', 'onbackorder' ], true ),
				$snapshot->permalink,
			);
		}

		/** @var Item|ServiceItem|null $item */
		$item = apply_filters( 'scwc_price_list_item', $item, $snapshot );
		return ( $item instanceof Item || $item instanceof ServiceItem ) ? $item : null;
	}

	/** Convert a stored amount to the price-list display amount (tax mode from settings), 2 dp. */
	public function toDisplay( WC_Product $product, string $amount ): float {
		$args = [ 'price' => $amount ];
		$raw  = 'excl' === (string) $this->settings->get( 'price_list.tax_mode', 'incl' )
			? wc_get_price_excluding_tax( $product, $args )
			: wc_get_price_including_tax( $product, $args );
		return round( (float) $raw, 2 );
	}

	private function isListed( ProductSnapshot $s ): bool {
		if ( $s->excludeFromPriceList || $s->priceOnRequest || $s->isContainer() ) {
			return false;
		}
		if ( '' === $s->active || ! is_numeric( $s->active ) ) {
			return false;
		}
		if ( 'publish' !== $s->status ) {
			return false;
		}
		if ( ! $s->visible && ! (bool) $this->settings->get( 'price_list.include_hidden', false ) ) {
			return false;
		}
		if ( 'outofstock' === $s->stockStatus && ! (bool) $this->settings->get( 'price_list.include_out_of_stock', true ) ) {
			return false;
		}
		return true;
	}

	/**
	 * @return array<string,array{amount:?float,date:?string}>
	 */
	private function references( WC_Product $product, ProductSnapshot $snapshot, ?ProductSnapshot $parent ): array {
		$inherit = (bool) $this->settings->get( 'reference_prices.variation_inherit_parent', false );
		$out     = [];
		foreach ( $this->registry->enabled() as $key => $type ) {
			$ref         = $this->references->get( $type, $snapshot, $parent, $inherit );
			$out[ $key ] = [
				'amount' => $ref->isPresent() ? $this->toDisplay( $product, (string) $ref->amount ) : null,
				'date'   => $ref->date ? $ref->date->format( 'Y-m-d' ) : null,
			];
		}
		return $out;
	}
}
