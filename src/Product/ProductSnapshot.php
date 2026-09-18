<?php
/**
 * Immutable, framework-free view of a product or variation.
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

namespace SidrenaCijena\Product;

use DateTimeImmutable;

final class ProductSnapshot {

	/**
	 * @param int[]               $categoryIds Category term IDs (variation: parent's).
	 * @param array<string,mixed> $meta        Plugin meta values keyed by meta key.
	 */
	public function __construct(
		public readonly int $id,
		public readonly int $parentId,
		public readonly string $type,
		public readonly string $sku,
		public readonly string $name,
		public readonly string $regular,
		public readonly string $sale,
		public readonly string $active,
		public readonly bool $isOnSale,
		public readonly ?DateTimeImmutable $saleFrom,
		public readonly ?DateTimeImmutable $saleTo,
		public readonly bool $isVirtual,
		public readonly array $categoryIds,
		public readonly string $brand,
		public readonly string $barcode,
		public readonly string $unit,
		public readonly ?float $unitQuantity,
		public readonly ?bool $serviceFlag,
		public readonly string $saleName,
		public readonly bool $excludeFromPriceList,
		public readonly bool $priceOnRequest,
		public readonly string $stockStatus,
		public readonly bool $visible,
		public readonly string $status,
		public readonly ?DateTimeImmutable $createdAt,
		public readonly string $permalink,
		public readonly array $meta,
	) {}

	/**
	 * @param array<string,mixed> $a Partial values; the rest default.
	 */
	public static function fromArray( array $a ): self {
		return new self(
			(int) ( $a['id'] ?? 0 ),
			(int) ( $a['parentId'] ?? 0 ),
			(string) ( $a['type'] ?? 'simple' ),
			(string) ( $a['sku'] ?? '' ),
			(string) ( $a['name'] ?? '' ),
			(string) ( $a['regular'] ?? '' ),
			(string) ( $a['sale'] ?? '' ),
			(string) ( $a['active'] ?? ( $a['regular'] ?? '' ) ),
			(bool) ( $a['isOnSale'] ?? false ),
			$a['saleFrom'] ?? null,
			$a['saleTo'] ?? null,
			(bool) ( $a['isVirtual'] ?? false ),
			array_map( 'intval', (array) ( $a['categoryIds'] ?? [] ) ),
			(string) ( $a['brand'] ?? '' ),
			(string) ( $a['barcode'] ?? '' ),
			(string) ( $a['unit'] ?? '' ),
			isset( $a['unitQuantity'] ) ? (float) $a['unitQuantity'] : null,
			array_key_exists( 'serviceFlag', $a ) ? $a['serviceFlag'] : null,
			(string) ( $a['saleName'] ?? '' ),
			(bool) ( $a['excludeFromPriceList'] ?? false ),
			(bool) ( $a['priceOnRequest'] ?? false ),
			(string) ( $a['stockStatus'] ?? 'instock' ),
			(bool) ( $a['visible'] ?? true ),
			(string) ( $a['status'] ?? 'publish' ),
			$a['createdAt'] ?? null,
			(string) ( $a['permalink'] ?? '' ),
			(array) ( $a['meta'] ?? [] ),
		);
	}

	/**
	 * @return mixed Meta value or '' when absent.
	 */
	public function meta( string $key ) {
		return $this->meta[ $key ] ?? '';
	}

	public function isVariation(): bool {
		return 'variation' === $this->type;
	}

	public function isVariableParent(): bool {
		return 'variable' === $this->type;
	}

	/** Variable and grouped parents carry no price of their own. */
	public function isContainer(): bool {
		return in_array( $this->type, [ 'variable', 'grouped' ], true );
	}
}
