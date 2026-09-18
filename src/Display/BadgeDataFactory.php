<?php
/**
 * Builds BadgeData for a product in a rendering context.
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

namespace SidrenaCijena\Display;

use SidrenaCijena\History\DiscountCalculator;
use SidrenaCijena\Product\MetaKeys;
use SidrenaCijena\Product\ProductAdapter;
use SidrenaCijena\Product\ProductSnapshot;
use SidrenaCijena\Reference\ReferencePrice;
use SidrenaCijena\Reference\ReferencePriceRegistry;
use SidrenaCijena\Reference\ReferencePriceRepository;
use SidrenaCijena\Reference\ReferencePriceType;
use SidrenaCijena\Settings\Settings;
use SidrenaCijena\Support\DateFormat;
use WC_Product;

final class BadgeDataFactory {

	/** @var callable(int):?WC_Product */
	private $loader;

	/**
	 * @param callable(int):?WC_Product $productLoader Loads a product by ID.
	 */
	public function __construct(
		private readonly Settings $settings,
		private readonly ReferencePriceRegistry $registry,
		private readonly ProductAdapter $adapter,
		private readonly ReferencePriceRepository $references,
		private readonly PriceFormatter $formatter,
		callable $productLoader,
	) {
		$this->loader = $productLoader;
	}

	public function forProduct( WC_Product $product, string $context, int $quantity = 1 ): ?BadgeData {
		if ( 'grouped' === $product->get_type() ) {
			return null;
		}
		if ( 'variable' === $product->get_type() ) {
			return $this->forVariable( $product, $context, $quantity );
		}
		$parent = null;
		if ( 'variation' === $product->get_type() && $product->get_parent_id() ) {
			$parent = ( $this->loader )( $product->get_parent_id() );
		}
		$snapshot       = $this->adapter->fromProduct( $product, $parent );
		$parentSnapshot = $parent ? $this->adapter->fromProduct( $parent ) : null;
		$inherit        = (bool) $this->settings->get( 'reference_prices.variation_inherit_parent', false );

		$views = [];
		foreach ( $this->registry->enabled() as $type ) {
			$ref = $this->references->get( $type, $snapshot, $parentSnapshot, $inherit );
			if ( $ref->isPresent() ) {
				$amount  = $this->formatter->display( $product, (string) $ref->amount, $context, $quantity );
				$views[] = $this->view( $ref, $amount, null );
			}
		}

		$current = $this->formatter->display( $product, $snapshot->active, $context, $quantity );
		$regular = '' !== $snapshot->regular ? $this->formatter->display( $product, $snapshot->regular, $context, $quantity ) : null;
		$omnibus = $this->omnibus( $product, $snapshot, $context, $quantity, $current );

		$data = new BadgeData( $product->get_id(), $views, $omnibus, $snapshot->isOnSale, $current, $regular );
		return $data->hasContent() ? $data : null;
	}

	/** @var array<string,array<string,array{min:float,max:float,ref:ReferencePrice}>> */
	private array $rangeCache = [];

	/**
	 * Variable parents show a min–max range over their variations. Children are resolved from
	 * (primed) post meta rather than full product objects to avoid an N+1 on every shop loop.
	 */
	private function forVariable( WC_Product $parent, string $context, int $quantity ): ?BadgeData {
		if ( BadgeContext::LOOP === $context && 'none' === $this->settings->get( 'display.variable_loop', 'range' ) ) {
			return null;
		}
		$types    = $this->registry->enabled();
		$cacheKey = $parent->get_id() . ':' . $context . ':' . $quantity;
		if ( ! isset( $this->rangeCache[ $cacheKey ] ) ) {
			$this->rangeCache[ $cacheKey ] = $this->collectRanges( $parent, $context, $quantity, $types );
		}
		$ranges = $this->rangeCache[ $cacheKey ];
		$views  = [];
		foreach ( $types as $key => $type ) {
			if ( isset( $ranges[ $key ] ) ) {
				$views[] = $this->view( $ranges[ $key ]['ref'], $ranges[ $key ]['min'], $ranges[ $key ]['max'] );
			}
		}
		if ( [] === $views ) {
			return null;
		}
		$current = $this->formatter->display( $parent, (string) $parent->get_price( 'edit' ), $context, $quantity );
		return new BadgeData( $parent->get_id(), $views, null, $parent->is_on_sale( 'edit' ), $current, null );
	}

	/**
	 * @param array<string,ReferencePriceType> $types Enabled types.
	 * @return array<string,array{min:float,max:float,ref:ReferencePrice}>
	 */
	private function collectRanges( WC_Product $parent, string $context, int $quantity, array $types ): array {
		$parentSnapshot = $this->adapter->fromProduct( $parent );
		$inherit        = (bool) $this->settings->get( 'reference_prices.variation_inherit_parent', false );
		$childIds       = array_map( 'intval', $parent instanceof \WC_Product_Variable ? $parent->get_visible_children() : $parent->get_children() );
		if ( [] === $childIds ) {
			return [];
		}
		if ( function_exists( 'update_meta_cache' ) ) {
			update_meta_cache( 'post', $childIds );
		}
		$ranges = [];
		foreach ( $childIds as $childId ) {
			$meta = [];
			foreach ( $types as $type ) {
				foreach ( [ 'price', 'date', 'na', 'source' ] as $suffix ) {
					$value = get_post_meta( $childId, $type->metaKey( $suffix ), true );
					if ( '' !== $value && null !== $value ) {
						$meta[ $type->metaKey( $suffix ) ] = $value;
					}
				}
			}
			$snapshot = ProductSnapshot::fromArray(
				[
					'id'          => $childId,
					'parentId'    => $parent->get_id(),
					'type'        => 'variation',
					'categoryIds' => $parentSnapshot->categoryIds,
					'meta'        => $meta,
				]
			);
			foreach ( $types as $key => $type ) {
				$ref = $this->references->get( $type, $snapshot, $parentSnapshot, $inherit );
				if ( ! $ref->isPresent() ) {
					continue;
				}
				// Variations share the parent's tax class, so the parent is a valid basis for display conversion.
				$amount = $this->formatter->display( $parent, (string) $ref->amount, $context, $quantity );
				if ( ! isset( $ranges[ $key ] ) ) {
					$ranges[ $key ] = [
						'min' => $amount,
						'max' => $amount,
						'ref' => $ref,
					];
				} else {
					$ranges[ $key ]['min'] = min( $ranges[ $key ]['min'], $amount );
					$ranges[ $key ]['max'] = max( $ranges[ $key ]['max'], $amount );
				}
			}
		}
		return $ranges;
	}

	private function view( ReferencePrice $ref, float $amount, ?float $max ): ReferenceView {
		$type    = $ref->type;
		$isRange = null !== $max && abs( $max - $amount ) >= 0.005;
		$html    = $isRange ? wc_format_price_range( $this->formatter->html( $amount ), $this->formatter->html( (float) $max ) ) : $this->formatter->html( $amount );
		$text    = $isRange ? $this->formatter->text( $amount ) . ' – ' . $this->formatter->text( $max ) : $this->formatter->text( $amount );
		$format  = (string) $this->settings->get( 'display.date_format', DateFormat::CROATIAN );
		return new ReferenceView(
			$type->key,
			$type->label,
			$ref->date ? DateFormat::croatian( $ref->date, $format ) : '',
			$ref->date ? $ref->date->format( DateFormat::ISO ) : '',
			$amount,
			$html,
			$isRange ? $max : null,
			$text,
			$isRange,
		);
	}

	private function omnibus( WC_Product $product, ProductSnapshot $snapshot, string $context, int $quantity, float $current ): ?OmnibusView {
		if ( ! $snapshot->isOnSale || ! (bool) $this->settings->get( 'display.omnibus', true ) ) {
			return null;
		}
		$stored = (string) $snapshot->meta( MetaKeys::OMNIBUS_REF_PRICE );
		if ( '' === $stored || ! is_numeric( $stored ) || (float) $stored <= 0 ) {
			return null;
		}
		$amount = $this->formatter->display( $product, $stored, $context, $quantity );
		return new OmnibusView(
			$amount,
			$this->formatter->html( $amount ),
			DiscountCalculator::percent( $amount, $current ),
			(string) ( $snapshot->meta( MetaKeys::OMNIBUS_SOURCE ) ?: 'history' ),
			$this->formatter->text( $amount ),
		);
	}
}
