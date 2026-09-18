<?php
/**
 * Display-ready reference price.
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

namespace SidrenaCijena\Display;

final class ReferenceView {

	public function __construct(
		public readonly string $key,
		public readonly string $label,
		public readonly string $dateFormatted,
		public readonly string $dateIso,
		public readonly float $amount,
		public readonly string $amountHtml,
		public readonly ?float $amountMax,
		public readonly ?string $amountText,
		public readonly bool $isRange,
	) {}

	/** Label including the date, e.g. "Cijena na dan 10. 9. 2026.". */
	public function fullLabel(): string {
		return trim( $this->label . ' ' . $this->dateFormatted );
	}
}
