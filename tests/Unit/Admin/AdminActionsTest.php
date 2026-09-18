<?php
declare(strict_types=1);

namespace SidrenaCijena\Tests\Unit\Admin;

use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use SidrenaCijena\Admin\AdminActions;
use SidrenaCijena\Tests\TestCase;

final class AdminActionsTest extends TestCase {
	private array $calls = [];

	private function actions(): AdminActions {
		return new AdminActions(
			function () { $this->calls[] = 'generate'; return null; },
			function () { $this->calls[] = 'sweep'; },
			function () { $this->calls[] = 'reschedule'; },
			fn() => [ 'action' => $GLOBALS['scwc_test_action'] ?? '', '_wpnonce' => 'nonce', 'scwc_result' => $GLOBALS['scwc_test_result'] ?? '' ],
			function () {},
		);
	}

	public function test_registers_admin_post_hooks_and_notice(): void {
		Actions\expectAdded( 'admin_post_scwc_generate_now' )->once();
		Actions\expectAdded( 'admin_post_scwc_run_sweep' )->once();
		Actions\expectAdded( 'admin_post_scwc_reschedule' )->once();
		Actions\expectAdded( 'admin_notices' )->once();
		$this->actions()->register();
	}

	public function test_generate_now_runs_and_redirects_with_result(): void {
		$GLOBALS['scwc_test_action'] = 'scwc_generate_now';
		$this->actions()->handle( 'scwc_generate_now' );
		self::assertSame( [ 'generate' ], $this->calls );
		self::assertStringContainsString( 'scwc_result=generated', $GLOBALS['scwc_test_redirect'] );
	}

	public function test_generation_error_is_carried_in_redirect(): void {
		$a = new AdminActions( fn() => 'Nema adrese', fn() => null, fn() => null, fn() => [ '_wpnonce' => 'nonce' ], function () {} );
		$a->handle( 'scwc_generate_now' );
		self::assertStringContainsString( 'scwc_result=error', $GLOBALS['scwc_test_redirect'] );
		self::assertStringContainsString( 'scwc_error=Nema', $GLOBALS['scwc_test_redirect'] );
	}

	public function test_invalid_nonce_dies(): void {
		Functions\when( 'wp_verify_nonce' )->justReturn( false );
		$this->expectException( \RuntimeException::class );
		$this->actions()->handle( 'scwc_run_sweep' );
	}

	public function test_result_notice_rendered_from_query(): void {
		$GLOBALS['scwc_test_result'] = 'rescheduled';
		ob_start();
		$this->actions()->notice();
		$html = (string) ob_get_clean();
		self::assertStringContainsString( 'notice-success', $html );
		unset( $GLOBALS['scwc_test_result'] );
		ob_start();
		$this->actions()->notice();
		self::assertSame( '', (string) ob_get_clean() );
	}
}
