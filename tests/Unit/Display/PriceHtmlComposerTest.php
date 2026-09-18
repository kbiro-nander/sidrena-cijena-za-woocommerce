<?php
declare(strict_types=1);

namespace SidrenaCijena\Tests\Unit\Display;

use SidrenaCijena\Display\BadgeContext;
use SidrenaCijena\Display\BadgeData;
use SidrenaCijena\Display\OmnibusView;
use SidrenaCijena\Display\PriceBadge;
use SidrenaCijena\Display\PriceHtmlComposer;
use SidrenaCijena\Display\ReferenceView;
use SidrenaCijena\Settings\Defaults;
use SidrenaCijena\Settings\Settings;
use SidrenaCijena\Tests\TestCase;

final class PriceHtmlComposerTest extends TestCase {
	private function composer( ?Settings $settings = null ): PriceHtmlComposer {
		$settings = $settings ?? new Settings( Defaults::all() );
		return new PriceHtmlComposer( $settings, new PriceBadge( $settings, dirname( __DIR__, 3 ) . '/templates' ) );
	}

	private function anchor(): ReferenceView {
		return new ReferenceView( 'anchor', 'Cijena na dan', '10. 9. 2026.', '2026-09-10', 14.99, '14,99 €', null, null, false );
	}

	public function test_badge_appended_after_price_by_default(): void {
		$p    = $this->product( [ 'id' => 1 ] );
		$data = new BadgeData( 1, [ $this->anchor() ], null, false, 10.0, null );
		$html = $this->composer()->compose( '<span class="amount">10,00 €</span>', $p, $data, BadgeContext::LOOP );
		self::assertStringStartsWith( '<span class="amount">10,00 €</span> <span class="scwc-badge', $html );
	}

	public function test_badge_before_price_when_configured(): void {
		$p    = $this->product( [ 'id' => 1 ] );
		$data = new BadgeData( 1, [ $this->anchor() ], null, false, 10.0, null );
		$html = $this->composer( ( new Settings( Defaults::all() ) )->with( 'display.position', 'before' ) )->compose( '<span class="amount">10,00 €</span>', $p, $data, BadgeContext::LOOP );
		self::assertStringStartsWith( '<span class="scwc-badge', $html );
		self::assertStringEndsWith( '<span class="amount">10,00 €</span>', $html );
	}

	public function test_del_is_rebuilt_with_lowest_30_when_lower_than_regular(): void {
		$p    = $this->product( [ 'id' => 1, 'regular_price' => '20', 'sale_price' => '10', 'price_suffix' => ' <small>s PDV</small>' ] );
		$data = new BadgeData( 1, [ $this->anchor() ], new OmnibusView( 15.0, '15,00 €', 33, 'history' ), true, 10.0, 20.0 );
		$wc   = '<del aria-hidden="true"><span class="amount">20,00 €</span></del> <ins><span class="amount">10,00 €</span></ins>';
		$html = $this->composer()->compose( $wc, $p, $data, BadgeContext::SINGLE );
		self::assertStringStartsWith( '<del aria-hidden="true"><span class="woocommerce-Price-amount amount">15,00&nbsp;€</span></del> <ins><span class="woocommerce-Price-amount amount">10,00&nbsp;€</span></ins> <small>s PDV</small>', $html );
		self::assertStringContainsString( 'scwc-lowest30', $html );
	}

	public function test_del_kept_when_lowest_equals_or_exceeds_regular_or_setting_off(): void {
		$p    = $this->product( [ 'id' => 1, 'regular_price' => '20', 'sale_price' => '10' ] );
		$wc   = '<del>20</del> <ins>10</ins>';
		$data = new BadgeData( 1, [], new OmnibusView( 20.0, '20,00 €', 50, 'history' ), true, 10.0, 20.0 );
		self::assertStringStartsWith( '<del>20</del> <ins>10</ins>', $this->composer()->compose( $wc, $p, $data, BadgeContext::SINGLE ) );
		$data2 = new BadgeData( 1, [], new OmnibusView( 15.0, '15,00 €', 33, 'history' ), true, 10.0, 20.0 );
		$off   = ( new Settings( Defaults::all() ) )->with( 'display.omnibus_replace_del', false );
		self::assertStringStartsWith( '<del>20</del> <ins>10</ins>', $this->composer( $off )->compose( $wc, $p, $data2, BadgeContext::SINGLE ) );
	}

	public function test_del_never_rebuilt_for_variable_parents(): void {
		$p    = $this->product( [ 'id' => 1, 'type' => 'variable' ] );
		$data = new BadgeData( 1, [ $this->anchor() ], new OmnibusView( 15.0, '15,00 €', 33, 'history' ), true, 10.0, 20.0 );
		self::assertStringStartsWith( '<span class="price">range</span>', $this->composer()->compose( '<span class="price">range</span>', $p, $data, BadgeContext::LOOP ) );
	}
}
