<?php
/**
 * A reference-price type (anchor "sidrena", base "bazna", or custom).
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

namespace SidrenaCijena\Reference;

use SidrenaCijena\Product\MetaKeys;

final class ReferencePriceType {

	/**
	 * @param array<int,array{term_ids:int[],date:string}> $categoryOverrides Category-specific dates.
	 */
	public function __construct(
		public readonly string $key,
		public readonly string $label,
		public readonly ?string $defaultDate,
		public readonly array $categoryOverrides,
		public readonly bool $enabled,
		public readonly string $xmlElement,
		public readonly string $csvColumn,
	) {}

	public function metaKey( string $suffix ): string {
		return MetaKeys::REF_PREFIX . $this->key . '_' . $suffix;
	}
}
