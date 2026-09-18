<?php
/**
 * Value object: one parsed CSV import line.
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

namespace SidrenaCijena\Admin\Tools;

final class CsvImportRow {

	/**
	 * @param int         $line      1-based line number in the file (header is line 1).
	 * @param string      $sku       SKU as written.
	 * @param float|null  $amount    Parsed amount (null for "na" rows or unparsable input).
	 * @param string|null $date      Y-m-d per-product date override or null.
	 * @param string      $typeKey   Reference-price type key.
	 * @param bool        $na        Row explicitly marks "no reference price".
	 * @param string|null $error     Validation error(s) or null when valid.
	 * @param int|null    $productId Resolved product/variation ID or null when unmatched.
	 */
	public function __construct(
		public readonly int $line,
		public readonly string $sku,
		public readonly ?float $amount,
		public readonly ?string $date,
		public readonly string $typeKey,
		public readonly bool $na,
		public readonly ?string $error,
		public readonly ?int $productId,
	) {}

	public function isValid(): bool {
		return null === $this->error;
	}

	/**
	 * @return array<string,mixed>
	 */
	public function toArray(): array {
		return [
			'line'       => $this->line,
			'sku'        => $this->sku,
			'amount'     => $this->amount,
			'date'       => $this->date,
			'type'       => $this->typeKey,
			'na'         => $this->na,
			'error'      => $this->error,
			'product_id' => $this->productId,
		];
	}

	/**
	 * @param array<string,mixed> $a Array from toArray().
	 */
	public static function fromArray( array $a ): self {
		return new self(
			(int) ( $a['line'] ?? 0 ),
			(string) ( $a['sku'] ?? '' ),
			isset( $a['amount'] ) ? (float) $a['amount'] : null,
			isset( $a['date'] ) ? (string) $a['date'] : null,
			(string) ( $a['type'] ?? '' ),
			(bool) ( $a['na'] ?? false ),
			isset( $a['error'] ) ? (string) $a['error'] : null,
			isset( $a['product_id'] ) ? (int) $a['product_id'] : null,
		);
	}
}
