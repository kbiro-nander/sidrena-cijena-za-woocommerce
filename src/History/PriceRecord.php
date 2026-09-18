<?php
/**
 * One price-history change point.
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

namespace SidrenaCijena\History;

use DateTimeImmutable;
use DateTimeZone;

final class PriceRecord {

	public function __construct(
		public readonly int $productId,
		public readonly int $parentId,
		public readonly ?float $regular,
		public readonly ?float $sale,
		public readonly float $active,
		public readonly bool $isOnSale,
		public readonly string $source,
		public readonly DateTimeImmutable $recordedAt,
		public readonly ?int $id = null,
	) {}

	/**
	 * @param array<string,mixed> $row Database row.
	 */
	public static function fromRow( array $row ): self {
		return new self(
			(int) $row['product_id'],
			(int) ( $row['parent_id'] ?? 0 ),
			isset( $row['regular_price'] ) ? (float) $row['regular_price'] : null,
			isset( $row['sale_price'] ) ? (float) $row['sale_price'] : null,
			(float) $row['active_price'],
			(bool) (int) $row['is_on_sale'],
			(string) $row['source'],
			new DateTimeImmutable( (string) $row['recorded_at'], new DateTimeZone( 'UTC' ) ),
			isset( $row['id'] ) ? (int) $row['id'] : null,
		);
	}

	/** Same prices and sale state (ignores source/time). */
	public function samePricesAs( self $other ): bool {
		return $this->isOnSale === $other->isOnSale
			&& self::eq( $this->regular, $other->regular )
			&& self::eq( $this->sale, $other->sale )
			&& self::eq( $this->active, $other->active );
	}

	private static function eq( ?float $a, ?float $b ): bool {
		if ( null === $a || null === $b ) {
			return $a === $b;
		}
		return abs( $a - $b ) < 0.00005;
	}
}
