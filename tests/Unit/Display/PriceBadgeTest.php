<?php
declare(strict_types=1);

namespace SidrenaCijena\Tests\Unit\Display;

use Brain\Monkey\Filters;
use SidrenaCijena\Display\BadgeContext;
use SidrenaCijena\Display\BadgeData;
use SidrenaCijena\Display\OmnibusView;
use SidrenaCijena\Display\PriceBadge;
use SidrenaCijena\Display\ReferenceView;
use SidrenaCijena\Settings\Defaults;
use SidrenaCijena\Settings\Settings;
use SidrenaCijena\Tests\TestCase;

final class PriceBadgeTest extends TestCase {
	private function data( array $refs = [], ?OmnibusView $omnibus = null, bool $onSale = false ): BadgeData {
		return new BadgeData( 1, $refs, $omnibus, $onSale, 10.0, $onSale ? 20.0 : null );
	}

	private function anchor( float $amount = 14.99, string $html = '14,99 €' ): ReferenceView {
		return new ReferenceView( 'anchor', 'Cijena na dan', '10. 9. 2026.', '2026-09-10', $amount, $html, null, null, false );
	}

	private function badge( ?Settings $settings = null ): PriceBadge {
		return new PriceBadge( $settings ?? new Settings( Defaults::all() ), dirname( __DIR__, 3 ) . '/templates' );
	}

	public function test_renders_anchor_with_label_date_and_amount(): void {
		$html = $this->badge()->render( $this->data( [ $this->anchor() ] ), BadgeContext::LOOP );
		self::assertStringContainsString( 'class="scwc-badge scwc-badge--loop scwc-badge--after"', $html );
		self::assertStringContainsString( 'data-scwc-ref="anchor"', $html );
		self::assertStringContainsString( 'Cijena na dan 10. 9. 2026.: <span class="scwc-ref-price__amount">14,99 €</span>', $html );
		self::assertStringNotContainsString( 'scwc-lowest30', $html );
	}

	public function test_renders_omnibus_line_and_percent_when_on_sale(): void {
		$html = $this->badge()->render( $this->data( [ $this->anchor() ], new OmnibusView( 15.0, '15,00 €', 33, 'history' ), true ), BadgeContext::SINGLE );
		self::assertStringContainsString( 'Najniža cijena u 30 dana prije sniženja: <span class="scwc-lowest30__amount">15,00 €</span>', $html );
		self::assertStringContainsString( '<span class="scwc-discount">&minus;33&nbsp;%</span>', $html );
		self::assertLessThan( strpos( $html, 'scwc-ref-price' ), strpos( $html, 'scwc-lowest30' ), 'omnibus line precedes anchor' );
	}

	public function test_percent_hidden_when_setting_off_or_null(): void {
		$off = ( new Settings( Defaults::all() ) )->with( 'display.show_percent', false );
		self::assertStringNotContainsString( 'scwc-discount', $this->badge( $off )->render( $this->data( [], new OmnibusView( 15.0, '15,00 €', 33, 'history' ), true ), BadgeContext::SINGLE ) );
		self::assertStringNotContainsString( 'scwc-discount', $this->badge()->render( $this->data( [], new OmnibusView( 15.0, '15,00 €', null, 'history' ), true ), BadgeContext::SINGLE ) );
	}

	public function test_compact_format_used_in_cart_contexts(): void {
		$html = $this->badge()->render( $this->data( [ $this->anchor() ] ), BadgeContext::CART );
		self::assertStringContainsString( '10. 9. 2026.: <span class="scwc-ref-price__amount">14,99 €</span>', $html );
		self::assertStringNotContainsString( 'Cijena na dan', $html );
	}

	public function test_custom_format_tokens_and_escaping(): void {
		$settings = ( new Settings( Defaults::all() ) )->with( 'display.format', '{price} ({label} <b>{date}</b>)' );
		$html     = $this->badge( $settings )->render( $this->data( [ $this->anchor() ] ), BadgeContext::LOOP );
		self::assertStringContainsString( '<span class="scwc-ref-price__amount">14,99 €</span> (Cijena na dan &lt;b&gt;10. 9. 2026.&lt;/b&gt;)', $html );
	}

	public function test_position_class_reflects_setting(): void {
		$settings = ( new Settings( Defaults::all() ) )->with( 'display.position', 'below' );
		self::assertStringContainsString( 'scwc-badge--below', $this->badge( $settings )->render( $this->data( [ $this->anchor() ] ), BadgeContext::LOOP ) );
	}

	public function test_empty_data_renders_nothing(): void {
		self::assertSame( '', $this->badge()->render( $this->data(), BadgeContext::LOOP ) );
	}

	public function test_html_filter_is_applied(): void {
		Filters\expectApplied( 'scwc_price_badge_html' )->once()->andReturn( '<i>x</i>' );
		self::assertSame( '<i>x</i>', $this->badge()->render( $this->data( [ $this->anchor() ] ), BadgeContext::LOOP ) );
	}

	public function test_theme_template_override_is_used_when_present(): void {
		$dir = sys_get_temp_dir() . '/scwc-tpl-' . uniqid();
		mkdir( $dir );
		file_put_contents( $dir . '/price-badge.php', '<?php echo "OVERRIDE:" . count( $badge->references );' );
		$badge = new PriceBadge( new Settings( Defaults::all() ), dirname( __DIR__, 3 ) . '/templates', fn( string $file ) => $dir . '/' . $file );
		self::assertSame( 'OVERRIDE:1', $badge->render( $this->data( [ $this->anchor() ] ), BadgeContext::LOOP ) );
		unlink( $dir . '/price-badge.php' );
		rmdir( $dir );
	}
}
