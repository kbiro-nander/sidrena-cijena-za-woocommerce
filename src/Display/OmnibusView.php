<?php
/**
 * Display-ready 30-day lowest price.
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

namespace SidrenaCijena\Display;

final class OmnibusView {

	public function __construct(
		public readonly float $amount,
		public readonly string $amountHtml,
		public readonly ?int $percent,
		public readonly string $source,
		public readonly ?string $amountText = null,
	) {}
}
