<?php
/**
 * Value object: a resolved reference price for one product and type.
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

namespace SidrenaCijena\Reference;

use DateTimeImmutable;

final class ReferencePrice {

	public function __construct(
		public readonly ReferencePriceType $type,
		public readonly ?string $amount,
		public readonly ?DateTimeImmutable $date,
		public readonly string $source,
		public readonly bool $na,
	) {}

	public static function absent( ReferencePriceType $type, ?DateTimeImmutable $date = null, bool $na = false ): self {
		return new self( $type, null, $date, '', $na );
	}

	public function isPresent(): bool {
		return ! $this->na && null !== $this->amount && is_numeric( $this->amount ) && (float) $this->amount > 0;
	}
}
