<?php
/**
 * Snapshot of the facts admin notices are based on (gathered lazily by the plugin).
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

namespace SidrenaCijena\Notices;

final class Environment {

	/**
	 * @param array<string,mixed>|null $lastGeneration scwc_last_generation option.
	 * @param string[]                 $dismissed      Dismissed notice ids for the current user.
	 */
	public function __construct(
		public readonly ?int $nextGeneration,
		public readonly ?array $lastGeneration,
		public readonly int $missingAnchors,
		public readonly bool $blockCart,
		public readonly bool $prettyPermalinks,
		public readonly string $wcVersion,
		public readonly string $screenId,
		public readonly array $dismissed,
	) {}

	public function isWooCommerceScreen(): bool {
		$id = $this->screenId;
		return str_contains( $id, 'woocommerce' ) || str_contains( $id, 'scwc' ) || str_contains( $id, 'product' ) || str_contains( $id, 'wc-' );
	}
}
