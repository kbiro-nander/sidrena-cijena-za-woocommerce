<?php
/**
 * Value object: outcome of one price-list generation run.
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

namespace SidrenaCijena\PriceList;

use DateTimeImmutable;

final class GenerationResult {

	/**
	 * @param array<int,array{name:string,format:string,url:string,products:int,services:int}> $files       Written files.
	 * @param DateTimeImmutable                                                                $generatedAt Generation time (site-local).
	 */
	public function __construct(
		public readonly array $files,
		public readonly DateTimeImmutable $generatedAt,
		public readonly string $reason,
		public readonly ?string $error,
		public readonly int $products,
		public readonly int $services,
	) {}

	public function ok(): bool {
		return null === $this->error;
	}
}
