<?php
/**
 * Value object: parameters of a "copy current regular prices as reference price" run.
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

namespace SidrenaCijena\Admin\Tools;

use SidrenaCijena\Settings\Settings;
use SidrenaCijena\Support\DateFormat;

final class SnapshotRequest {

	public const MODE_ONLY_MISSING = 'only_missing';
	public const MODE_OVERWRITE    = 'overwrite';

	/**
	 * @param string      $typeKey         Reference-price type key.
	 * @param string|null $date            Y-m-d per-product date override; null = use the type's default date.
	 * @param string      $mode            One of MODE_*.
	 * @param bool        $skipOnSale      Skip products currently on sale.
	 * @param bool        $dryRun          Count only, write nothing.
	 * @param bool        $markNaAfterDate Mark products created after the reference date as "no reference price".
	 */
	public function __construct(
		public readonly string $typeKey,
		public readonly ?string $date,
		public readonly string $mode,
		public readonly bool $skipOnSale,
		public readonly bool $dryRun,
		public readonly bool $markNaAfterDate,
	) {}

	/**
	 * Build from request args (all values may be strings); invalid values fall back to safe defaults.
	 *
	 * @param array<string,mixed> $a        Raw args (type|type_key, date, mode, skip_on_sale, dry_run).
	 * @param Settings            $settings Plugin settings (reference_prices.auto_na_after_date).
	 */
	public static function fromArray( array $a, Settings $settings ): self {
		$typeKey = sanitize_key( (string) ( $a['type'] ?? $a['type_key'] ?? 'anchor' ) );
		$date    = trim( (string) ( $a['date'] ?? '' ) );
		$mode    = (string) ( $a['mode'] ?? self::MODE_ONLY_MISSING );
		return new self(
			'' === $typeKey ? 'anchor' : $typeKey,
			null === DateFormat::parseIso( $date ) ? null : $date,
			in_array( $mode, [ self::MODE_ONLY_MISSING, self::MODE_OVERWRITE ], true ) ? $mode : self::MODE_ONLY_MISSING,
			self::truthy( $a['skip_on_sale'] ?? false ),
			self::truthy( $a['dry_run'] ?? false ),
			(bool) $settings->get( 'reference_prices.auto_na_after_date', true ),
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	public function toArray(): array {
		return [
			'type'               => $this->typeKey,
			'date'               => $this->date,
			'mode'               => $this->mode,
			'skip_on_sale'       => $this->skipOnSale,
			'dry_run'            => $this->dryRun,
			'mark_na_after_date' => $this->markNaAfterDate,
		];
	}

	/**
	 * @param mixed $value Checkbox-like value.
	 */
	public static function truthy( $value ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}
		return in_array( strtolower( trim( (string) $value ) ), [ '1', 'true', 'yes', 'da', 'on' ], true );
	}
}
