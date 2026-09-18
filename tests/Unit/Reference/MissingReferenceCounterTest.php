<?php
declare(strict_types=1);

namespace SidrenaCijena\Tests\Unit\Reference;

use SidrenaCijena\Reference\MissingReferenceCounter;
use SidrenaCijena\Reference\ReferencePriceType;
use SidrenaCijena\Tests\Support\FakeWpdb;
use SidrenaCijena\Tests\TestCase;

final class MissingReferenceCounterTest extends TestCase {
	public function test_counts_published_priced_products_without_reference_or_na_flag(): void {
		$wpdb = new FakeWpdb();
		$wpdb->posts = 'wp_posts';
		$wpdb->postmeta = 'wp_postmeta';
		$wpdb->varResults[] = '7';
		$type = new ReferencePriceType( 'anchor', 'Cijena na dan', '2026-09-10', [], true, 'SidrenaCijena', 'sidrena_cijena' );
		$count = ( new MissingReferenceCounter( $wpdb ) )->count( $type );
		self::assertSame( 7, $count );
		$sql = $wpdb->queries[0];
		self::assertStringContainsString( "post_type IN ('product','product_variation')", $sql );
		self::assertStringContainsString( "post_status = 'publish'", $sql );
		self::assertStringContainsString( "meta_key = '_scwc_ref_anchor_price'", $sql );
		self::assertStringContainsString( "meta_key = '_scwc_ref_anchor_na'", $sql );
		self::assertStringContainsString( "meta_key = '_regular_price'", $sql );
		self::assertStringContainsString( "IN ('variable','grouped')", $sql );
		self::assertStringStartsWith( 'SELECT COUNT(DISTINCT p.ID)', $sql, 'category joins must not multiply rows' );
		self::assertStringContainsString( 'NOT EXISTS', $sql );
	}

	public function test_result_is_cached_in_transient(): void {
		$wpdb = new FakeWpdb();
		$wpdb->posts = 'wp_posts';
		$wpdb->postmeta = 'wp_postmeta';
		$wpdb->varResults[] = '3';
		$type = new ReferencePriceType( 'anchor', 'x', null, [], true, 'S', 's' );
		unset( $GLOBALS['scwc_test_transients']['scwc_missing_ref_anchor'] );
		$c = new MissingReferenceCounter( $wpdb );
		self::assertSame( 3, $c->cached( $type ) );
		self::assertSame( 3, $c->cached( $type ) );
		self::assertCount( 1, $wpdb->queries );
		$c->flush( $type );
		$wpdb->varResults[] = '0';
		self::assertSame( 0, $c->cached( $type ) );
	}
}
