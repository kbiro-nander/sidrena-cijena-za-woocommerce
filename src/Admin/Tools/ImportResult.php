<?php
/**
 * Value object: outcome of applying a CSV import.
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

namespace SidrenaCijena\Admin\Tools;

final class ImportResult {

	/**
	 * @param string[] $errors Line-prefixed messages for skipped rows.
	 */
	public function __construct(
		public readonly int $updated,
		public readonly int $markedNa,
		public readonly int $skipped,
		public readonly array $errors,
	) {}

	/**
	 * @return array<string,mixed>
	 */
	public function toArray(): array {
		return [
			'updated'   => $this->updated,
			'marked_na' => $this->markedNa,
			'skipped'   => $this->skipped,
			'errors'    => $this->errors,
		];
	}
}
