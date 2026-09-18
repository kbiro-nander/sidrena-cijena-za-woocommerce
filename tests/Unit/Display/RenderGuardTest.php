<?php
declare(strict_types=1);

namespace SidrenaCijena\Tests\Unit\Display;

use Brain\Monkey\Filters;
use SidrenaCijena\Display\BadgeContext;
use SidrenaCijena\Display\RenderGuard;
use SidrenaCijena\Display\RequestContext;
use SidrenaCijena\Settings\Defaults;
use SidrenaCijena\Settings\Settings;
use SidrenaCijena\Tests\TestCase;

final class RenderGuardTest extends TestCase {
	private function guard( ?Settings $settings = null, ?RequestContext $ctx = null ): RenderGuard {
		return new RenderGuard( $settings ?? new Settings( Defaults::all() ), fn() => $ctx ?? new RequestContext( null, false, false ) );
	}

	public function test_contexts_follow_display_settings(): void {
		$p = $this->product( [ 'id' => 1 ] );
		self::assertTrue( $this->guard()->shouldRender( $p, BadgeContext::LOOP ) );
		$off = ( new Settings( Defaults::all() ) )->with( 'display.loop', false )->with( 'display.checkout', 'none' );
		self::assertFalse( $this->guard( $off )->shouldRender( $p, BadgeContext::LOOP ) );
		self::assertTrue( $this->guard( $off )->shouldRender( $p, BadgeContext::SINGLE ) );
		self::assertFalse( $this->guard( $off )->shouldRender( $p, BadgeContext::CHECKOUT ) );
		self::assertTrue( $this->guard()->shouldRender( $p, BadgeContext::MINI_CART ), 'mini cart on by default' );
		self::assertFalse( $this->guard( ( new Settings( Defaults::all() ) )->with( 'display.mini_cart', false ) )->shouldRender( $p, BadgeContext::MINI_CART ) );
	}

	public function test_skips_admin_screens_wc_rest_and_emails(): void {
		$p = $this->product( [ 'id' => 1 ] );
		self::assertFalse( $this->guard( null, new RequestContext( null, true, false ) )->shouldRender( $p, BadgeContext::LOOP ) );
		self::assertFalse( $this->guard( null, new RequestContext( '/wc/v3/products', false, false ) )->shouldRender( $p, BadgeContext::LOOP ) );
		self::assertTrue( $this->guard( null, new RequestContext( '/wc/store/v1/products', false, false ) )->shouldRender( $p, BadgeContext::LOOP ), 'Store API keeps the badge in price_html' );
		do_action( 'woocommerce_email_header' );
		self::assertFalse( $this->guard()->shouldRender( $p, BadgeContext::LOOP ) );
	}

	public function test_grouped_products_never_render_and_filter_has_last_word(): void {
		self::assertFalse( $this->guard()->shouldRender( $this->product( [ 'id' => 1, 'type' => 'grouped' ] ), BadgeContext::SINGLE ) );
		Filters\expectApplied( 'scwc_should_render_badge' )->once()->andReturn( false );
		self::assertFalse( $this->guard()->shouldRender( $this->product( [ 'id' => 2 ] ), BadgeContext::SINGLE ) );
	}
}
