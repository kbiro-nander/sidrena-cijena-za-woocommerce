<?php
/**
 * Everything the badge template needs.
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

namespace SidrenaCijena\Display;

final class BadgeData {

	/**
	 * @param ReferenceView[] $references Reference prices in registry order.
	 */
	public function __construct(
		public readonly int $productId,
		public readonly array $references,
		public readonly ?OmnibusView $omnibus,
		public readonly bool $isOnSale,
		public readonly float $currentDisplay,
		public readonly ?float $regularDisplay,
	) {}

	public function hasContent(): bool {
		return [] !== $this->references || null !== $this->omnibus;
	}

	public function onlyKey( string $key ): self {
		$refs = array_values( array_filter( $this->references, static fn( ReferenceView $r ) => $r->key === $key ) );
		return new self( $this->productId, $refs, $this->omnibus, $this->isOnSale, $this->currentDisplay, $this->regularDisplay );
	}

	/**
	 * @return array<string,mixed> JSON-friendly representation (variation JSON, Store API).
	 */
	public function toArray(): array {
		return [
			'references'     => array_map(
				static fn( ReferenceView $r ) => [
					'key'        => $r->key,
					'label'      => $r->label,
					'date'       => $r->dateIso,
					'date_label' => $r->dateFormatted,
					'amount'     => $r->amount,
					'amount_max' => $r->amountMax,
					'formatted'  => $r->amountText ?? $r->amountHtml,
					'is_range'   => $r->isRange,
				],
				$this->references
			),
			'lowest_30_days' => $this->omnibus ? [
				'amount'    => $this->omnibus->amount,
				'formatted' => $this->omnibus->amountText ?? $this->omnibus->amountHtml,
				'percent'   => $this->omnibus->percent,
				'source'    => $this->omnibus->source,
			] : null,
			'is_on_sale'     => $this->isOnSale,
		];
	}
}
