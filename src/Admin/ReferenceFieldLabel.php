<?php
/**
 * Shared label helper for reference-price inputs on product and variation panels.
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

namespace SidrenaCijena\Admin;

use SidrenaCijena\Reference\ReferencePriceType;
use SidrenaCijena\Support\DateFormat;

final class ReferenceFieldLabel {

	/** "Cijena na dan (10. 9. 2026.) (€)" or "Bazna cijena na dan (€)" when the type has no default date. */
	public static function price( ReferencePriceType $type ): string {
		$date = DateFormat::parseIso( $type->defaultDate );
		$base = null === $date ? $type->label : sprintf( '%s (%s)', $type->label, DateFormat::croatian( $date ) );
		return $base . ' (€)';
	}

	public static function date( ReferencePriceType $type ): string {
		/* translators: %s: reference price type label */
		return sprintf( __( '%s – datum za ovaj proizvod', 'sidrena-cijena-za-woocommerce' ), $type->label );
	}

	public static function na(): string {
		return __( 'Nema referentne cijene (proizvod uveden nakon referentnog datuma)', 'sidrena-cijena-za-woocommerce' );
	}

	public static function dateDescription(): string {
		return __( 'Ostavite prazno za zadani datum', 'sidrena-cijena-za-woocommerce' );
	}

	/** Human label for a `_source` meta value. */
	public static function source( string $source ): string {
		$map = [
			'manual'    => __( 'ručni unos', 'sidrena-cijena-za-woocommerce' ),
			'snapshot'  => __( 'snapshot', 'sidrena-cijena-za-woocommerce' ),
			'import'    => __( 'CSV uvoz', 'sidrena-cijena-za-woocommerce' ),
			'auto'      => __( 'automatski snapshot', 'sidrena-cijena-za-woocommerce' ),
			'inherited' => __( 'naslijeđeno', 'sidrena-cijena-za-woocommerce' ),
		];
		return $map[ $source ] ?? $source;
	}
}
