<?php
declare(strict_types=1);

namespace SidrenaCijena\Tests\Unit\History;

use SidrenaCijena\History\Schema;
use SidrenaCijena\Tests\TestCase;

final class SchemaTest extends TestCase {
	public function test_table_name_uses_wpdb_prefix(): void {
		$wpdb = \Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';
		self::assertSame( 'wp_scwc_price_history', Schema::tableName( $wpdb ) );
	}

	public function test_sql_follows_dbdelta_conventions(): void {
		$sql = Schema::sql( 'wp_scwc_price_history', 'DEFAULT CHARSET=utf8mb4' );
		self::assertStringContainsString( 'CREATE TABLE wp_scwc_price_history (', $sql );
		self::assertStringContainsString( 'PRIMARY KEY  (id)', $sql, 'dbDelta requires two spaces after PRIMARY KEY' );
		self::assertStringContainsString( 'KEY product_recorded (product_id,recorded_at)', $sql );
		self::assertStringContainsString( 'active_price decimal(19,4) NOT NULL', $sql );
		self::assertStringNotContainsString( '`', $sql );
		self::assertStringNotContainsString( 'IF NOT EXISTS', $sql );
		self::assertStringEndsWith( 'DEFAULT CHARSET=utf8mb4;', trim( $sql ) );
	}
}
