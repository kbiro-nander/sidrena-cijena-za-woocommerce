<?php
declare(strict_types=1);

namespace SidrenaCijena\Tests\Unit\Display;

use SidrenaCijena\Display\RequestContext;
use SidrenaCijena\Tests\TestCase;

final class RequestContextTest extends TestCase {
	public function test_wc_rest_vs_store_api_detection(): void {
		$rest = new RequestContext( '/wc/v3/products', false, false );
		self::assertTrue( $rest->isRest() );
		self::assertTrue( $rest->isWcRest() );
		self::assertFalse( $rest->isStoreApi() );

		$store = new RequestContext( '/wc/store/v1/cart', false, false );
		self::assertTrue( $store->isStoreApi() );
		self::assertFalse( $store->isWcRest() );

		$front = new RequestContext( null, false, false );
		self::assertFalse( $front->isRest() );
	}

	public function test_admin_screen_excludes_ajax(): void {
		self::assertTrue( ( new RequestContext( null, true, false ) )->isAdminScreen() );
		self::assertFalse( ( new RequestContext( null, true, true ) )->isAdminScreen() );
	}

	public function test_email_is_detected_between_header_and_footer_actions(): void {
		$ctx = new RequestContext( null, false, false );
		self::assertFalse( $ctx->isEmail() );
		do_action( 'woocommerce_email_header' );
		self::assertTrue( $ctx->isEmail() );
		do_action( 'woocommerce_email_footer' );
		self::assertFalse( $ctx->isEmail() );
	}

	public function test_from_globals_reads_rest_route_from_request_uri(): void {
		$_SERVER['REQUEST_URI'] = '/wp-json/wc/store/v1/products?x=1';
		self::assertTrue( RequestContext::fromGlobals()->isStoreApi() );
		$_SERVER['REQUEST_URI'] = '/?rest_route=/wc/v3/orders';
		self::assertTrue( RequestContext::fromGlobals()->isWcRest() );
		$_SERVER['REQUEST_URI'] = '/shop/';
		self::assertFalse( RequestContext::fromGlobals()->isRest() );
		unset( $_SERVER['REQUEST_URI'] );
	}
}
