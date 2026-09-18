<?php
/**
 * Value object: counters and samples of one snapshot batch (mergeable across batches).
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

namespace SidrenaCijena\Admin\Tools;

final class SnapshotResult {

	public const MAX_SAMPLES = 20;

	public const ACTION_WRITTEN          = 'written';
	public const ACTION_SKIPPED_EXISTING = 'skipped_existing';
	public const ACTION_SKIPPED_ON_SALE  = 'skipped_on_sale';
	public const ACTION_SKIPPED_NO_PRICE = 'skipped_no_price';
	public const ACTION_MARKED_NA        = 'marked_na';

	/** @var array<int,array{id:int,sku:string,name:string,regular:string,action:string}> */
	public readonly array $samples;

	/**
	 * @param array<int,array{id:int,sku:string,name:string,regular:string,action:string}> $samples Up to MAX_SAMPLES example rows.
	 */
	public function __construct(
		public readonly int $processed,
		public readonly int $written,
		public readonly int $skippedExisting,
		public readonly int $skippedOnSale,
		public readonly int $markedNa,
		public readonly int $skippedNoPrice,
		public readonly bool $hasMore,
		array $samples = [],
	) {
		$this->samples = array_slice( array_values( $samples ), 0, self::MAX_SAMPLES );
	}

	public static function empty(): self {
		return new self( 0, 0, 0, 0, 0, 0, false, [] );
	}

	/** Sum counters; hasMore and samples come from the later batch (samples appended, capped). */
	public function merge( self $other ): self {
		return new self(
			$this->processed + $other->processed,
			$this->written + $other->written,
			$this->skippedExisting + $other->skippedExisting,
			$this->skippedOnSale + $other->skippedOnSale,
			$this->markedNa + $other->markedNa,
			$this->skippedNoPrice + $other->skippedNoPrice,
			$other->hasMore,
			array_merge( $this->samples, $other->samples ),
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	public function toArray(): array {
		return [
			'processed'        => $this->processed,
			'written'          => $this->written,
			'skipped_existing' => $this->skippedExisting,
			'skipped_on_sale'  => $this->skippedOnSale,
			'marked_na'        => $this->markedNa,
			'skipped_no_price' => $this->skippedNoPrice,
			'has_more'         => $this->hasMore,
			'samples'          => $this->samples,
		];
	}
}
