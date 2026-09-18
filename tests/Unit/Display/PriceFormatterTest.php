<?php
declare(strict_types=1);

namespace SidrenaCijena\Tests\Unit\Display;

use Brain\Monkey\Functions;
use SidrenaCijena\Display\BadgeContext;
use SidrenaCijena\Display\PriceFormatter;
use SidrenaCijena\Tests\TestCase;

final class PriceFormatterTest extends TestCase {
	public function test_catalog_contexts_use_shop_display_conversion(): void {
		$p = $this->product( [ 'id' => 1 ] );
		Functions\expect( 'wc_get_price_to_display' )->once()->with( $p, [ 'price' => '10', 'qty' => 1 ] )->andReturn( 12.5 );
		$f = new PriceFormatter();
		self::assertSame( 12.5, $f->display( $p, '10', BadgeContext::LOOP ) );
	}

	public function test_cart_contexts_follow_cart_tax_display_mode(): void {
		$p = $this->product( [ 'id' => 1 ] );
		WC()->cart->incl = true;
		Functions\expect( 'wc_get_price_including_tax' )->once()->with( $p, [ 'price' => '10', 'qty' => 2 ] )->andReturn( 25.0 );
		self::assertSame( 25.0, ( new PriceFormatter() )->display( $p, '10', BadgeContext::CART, 2 ) );
		WC()->cart->incl = false;
		Functions\expect( 'wc_get_price_excluding_tax' )->once()->andReturn( 20.0 );
		self::assertSame( 20.0, ( new PriceFormatter() )->display( $p, '10', BadgeContext::CHECKOUT, 2 ) );
		WC()->cart->incl = true;
	}

	public function test_html_uses_wc_price(): void {
		self::assertStringContainsString( '14,99&nbsp;€', ( new PriceFormatter() )->html( 14.99 ) );
	}
}
