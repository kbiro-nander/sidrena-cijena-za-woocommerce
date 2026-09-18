<?php
/**
 * Counts published, priced products/variations that have neither a reference price nor the "na" flag.
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

namespace SidrenaCijena\Reference;

final class MissingReferenceCounter {

	/**
	 * @param \wpdb|object $wpdb Database.
	 */
	public function __construct( private readonly object $wpdb ) {}

	public function count( ReferencePriceType $type ): int {
		$posts = $this->wpdb->posts;
		$meta  = $this->wpdb->postmeta;
		$sql   = $this->wpdb->prepare(
			"SELECT COUNT(*) FROM {$posts} p
INNER JOIN {$meta} rp ON rp.post_id = p.ID AND rp.meta_key = '_regular_price' AND rp.meta_value <> ''
LEFT JOIN {$meta} ref ON ref.post_id = p.ID AND ref.meta_key = %s AND ref.meta_value <> ''
LEFT JOIN {$meta} na ON na.post_id = p.ID AND na.meta_key = %s
LEFT JOIN {$this->wpdb->prefix}term_relationships tr ON tr.object_id = p.ID
LEFT JOIN {$this->wpdb->prefix}term_taxonomy tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'product_type'
LEFT JOIN {$this->wpdb->prefix}terms t ON t.term_id = tt.term_id
WHERE p.post_type IN ('product','product_variation') AND p.post_status = 'publish'
AND ref.meta_id IS NULL AND na.meta_id IS NULL
AND (t.name IS NULL OR t.name NOT IN ('variable','grouped'))",
			$type->metaKey( 'price' ),
			$type->metaKey( 'na' )
		);
		return (int) $this->wpdb->get_var( $sql );
	}

	public function cached( ReferencePriceType $type, int $ttl = 12 * HOUR_IN_SECONDS ): int {
		$key   = self::transientKey( $type );
		$value = get_transient( $key );
		if ( false !== $value && null !== $value ) {
			return (int) $value;
		}
		$count = $this->count( $type );
		set_transient( $key, $count, $ttl );
		return $count;
	}

	public function flush( ReferencePriceType $type ): void {
		delete_transient( self::transientKey( $type ) );
	}

	public static function transientKey( ReferencePriceType $type ): string {
		return 'scwc_missing_ref_' . $type->key;
	}
}
