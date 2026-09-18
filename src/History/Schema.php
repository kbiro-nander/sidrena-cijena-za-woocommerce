<?php
/**
 * Price-history table schema (dbDelta).
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

namespace SidrenaCijena\History;

final class Schema {

	public const DB_VERSION = '1';
	public const OPTION     = 'scwc_db_version';

	/**
	 * @param \wpdb $wpdb WordPress database.
	 */
	public static function tableName( $wpdb ): string {
		return $wpdb->prefix . 'scwc_price_history';
	}

	public static function sql( string $table, string $charsetCollate ): string {
		return "CREATE TABLE {$table} (
  id bigint(20) unsigned NOT NULL auto_increment,
  product_id bigint(20) unsigned NOT NULL,
  parent_id bigint(20) unsigned NOT NULL default 0,
  regular_price decimal(19,4) NULL,
  sale_price decimal(19,4) NULL,
  active_price decimal(19,4) NOT NULL,
  is_on_sale tinyint(1) NOT NULL default 0,
  source varchar(20) NOT NULL default 'save',
  recorded_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY product_recorded (product_id,recorded_at),
  KEY recorded_at (recorded_at)
) {$charsetCollate};";
	}

	/** Create/upgrade the table via dbDelta and store the schema version. */
	public static function install(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( self::sql( self::tableName( $wpdb ), $wpdb->get_charset_collate() ) );
		update_option( self::OPTION, self::DB_VERSION );
	}

	public static function needsUpgrade(): bool {
		return (string) get_option( self::OPTION, '' ) !== self::DB_VERSION;
	}
}
