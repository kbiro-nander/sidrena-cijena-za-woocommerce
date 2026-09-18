<?php
/**
 * Pure SQL builder: lowest active price in a window, including the change point in effect at window start.
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

namespace SidrenaCijena\History;

final class LowestPriceQuery {

	/**
	 * @return array{sql:string,params:array<int,int|string>}
	 */
	public static function build( string $table, int $productId, string $fromUtc, string $toUtc ): array {
		$sql = "SELECT MIN(active_price) FROM (
  SELECT active_price FROM {$table} WHERE product_id = %d AND recorded_at >= %s AND recorded_at < %s
  UNION ALL
  SELECT active_price FROM (
    SELECT active_price FROM {$table} WHERE product_id = %d AND recorded_at < %s ORDER BY recorded_at DESC, id DESC LIMIT 1
  ) carry_in
) w";
		return [
			'sql'    => $sql,
			'params' => [ $productId, $fromUtc, $toUtc, $productId, $fromUtc ],
		];
	}
}
