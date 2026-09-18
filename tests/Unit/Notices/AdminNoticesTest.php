<?php
declare(strict_types=1);

namespace SidrenaCijena\Tests\Unit\Notices;

use Brain\Monkey\Actions;
use SidrenaCijena\Notices\AdminNotices;
use SidrenaCijena\Notices\Environment;
use SidrenaCijena\Settings\Defaults;
use SidrenaCijena\Settings\Settings;
use SidrenaCijena\Tests\Support\FixedClock;
use SidrenaCijena\Tests\TestCase;

final class AdminNoticesTest extends TestCase {
	private function env( array $over = [] ): Environment {
		return new Environment( ...array_merge( [
			'nextGeneration'   => strtotime( '2026-10-02 04:00:00 UTC' ),
			'lastGeneration'   => [ 'at' => '2026-10-01 04:00:12', 'error' => '', 'files' => [ 'a.xml' ] ],
			'missingAnchors'   => 0,
			'blockCart'        => false,
			'prettyPermalinks' => true,
			'wcVersion'        => '9.9.0',
			'screenId'         => 'woocommerce_page_scwc-settings',
			'dismissed'        => [],
		], $over ) );
	}

	private function notices( ?Settings $settings = null, array $env = [] ): AdminNotices {
		return new AdminNotices( $settings ?? new Settings( ( new \SidrenaCijena\Settings\Sanitizer() )->sanitize( [ 'outlet' => [ 'address' => 'Ilica 1', 'label' => 'WEB1' ] ] ) ), fn() => $this->env( $env ), new FixedClock( '2026-10-01 10:00:00' ) );
	}

	public function test_healthy_setup_has_no_notices(): void {
		self::assertSame( [], $this->notices()->collect() );
	}

	public function test_incomplete_outlet_is_a_non_dismissible_error_everywhere(): void {
		$n = ( new AdminNotices( new Settings( Defaults::all() ), fn() => $this->env( [ 'screenId' => 'dashboard' ] ), new FixedClock() ) )->collect();
		self::assertCount( 1, $n );
		self::assertSame( 'outlet', $n[0]['id'] );
		self::assertSame( 'error', $n[0]['type'] );
		self::assertFalse( $n[0]['dismissible'] );
	}

	public function test_missing_schedule_and_stale_generation_are_warnings(): void {
		$ids = array_column( $this->notices( null, [ 'nextGeneration' => null, 'lastGeneration' => [ 'at' => '2026-09-29 04:00:00', 'error' => '' ] ] )->collect(), 'id' );
		self::assertSame( [ 'schedule', 'stale' ], $ids );
		$ids = array_column( $this->notices( null, [ 'lastGeneration' => [ 'at' => '2026-10-01 04:00:00', 'error' => 'Boom' ] ] )->collect(), 'id' );
		self::assertSame( [ 'generation_error' ], $ids );
		$ids = array_column( $this->notices( null, [ 'lastGeneration' => null ] )->collect(), 'id' );
		self::assertSame( [ 'never_generated' ], $ids );
	}

	public function test_informational_notices_only_on_woocommerce_screens_and_respect_dismissal(): void {
		$env = [ 'missingAnchors' => 12, 'blockCart' => true, 'prettyPermalinks' => false, 'wcVersion' => '9.1.0' ];
		$ids = array_column( $this->notices( null, $env )->collect(), 'id' );
		self::assertSame( [ 'permalinks', 'missing_anchors', 'block_cart', 'wc_gtin', 'wc_brands' ], $ids );
		$ids = array_column( $this->notices( null, $env + [ 'screenId' => 'dashboard' ] )->collect(), 'id' );
		self::assertSame( [ 'permalinks' ], $ids, 'only warnings outside WooCommerce screens' );
		$ids = array_column( $this->notices( null, $env + [ 'dismissed' => [ 'block_cart', 'missing_anchors' ] ] )->collect(), 'id' );
		self::assertSame( [ 'permalinks', 'wc_gtin', 'wc_brands' ], $ids );
	}

	public function test_disabled_price_list_skips_generation_notices(): void {
		$settings = ( new Settings( Defaults::all() ) )->with( 'price_list.enabled', false );
		$n        = ( new AdminNotices( $settings, fn() => $this->env( [ 'nextGeneration' => null, 'lastGeneration' => null ] ), new FixedClock() ) )->collect();
		self::assertSame( [], $n );
	}

	public function test_render_outputs_notice_markup_and_registers_hooks(): void {
		Actions\expectAdded( 'admin_notices' )->once();
		Actions\expectAdded( 'admin_post_scwc_dismiss_notice' )->once();
		$n = $this->notices( null, [ 'missingAnchors' => 3 ] );
		$n->register();
		ob_start();
		$n->render();
		$html = (string) ob_get_clean();
		self::assertStringContainsString( 'notice notice-info', $html );
		self::assertStringContainsString( '3', $html );
		self::assertStringContainsString( 'scwc_dismiss_notice', $html );
	}

	public function test_generation_scheduled_after_the_deadline_is_a_warning(): void {
		$late = ( new Settings( ( new \SidrenaCijena\Settings\Sanitizer() )->sanitize( [ 'outlet' => [ 'address' => 'Ilica 1', 'label' => 'WEB1' ], 'price_list' => [ 'enabled' => '1', 'generate_time' => '09:00' ] ] ) ) );
		$ids  = array_column( ( new AdminNotices( $late, fn() => $this->env(), new FixedClock( '2026-10-01 10:00:00' ) ) )->collect(), 'id' );
		self::assertSame( [ 'generate_after_deadline' ], $ids );
		$ok   = ( new Settings( ( new \SidrenaCijena\Settings\Sanitizer() )->sanitize( [ 'outlet' => [ 'address' => 'Ilica 1', 'label' => 'WEB1' ], 'price_list' => [ 'enabled' => '1', 'generate_time' => '07:30' ] ] ) ) );
		self::assertSame( [], ( new AdminNotices( $ok, fn() => $this->env(), new FixedClock( '2026-10-01 10:00:00' ) ) )->collect() );
	}
}
