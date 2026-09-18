<?php
declare(strict_types=1);

namespace SidrenaCijena\Tests\Unit\Admin;

use Brain\Monkey\Functions;
use SidrenaCijena\Admin\StatusProvider;
use SidrenaCijena\Scheduling\ActionSchedulerBackend;
use SidrenaCijena\Scheduling\Scheduler;
use SidrenaCijena\Settings\Defaults;
use SidrenaCijena\Settings\Settings;
use SidrenaCijena\Tests\Support\FixedClock;
use SidrenaCijena\Tests\TestCase;

final class StatusProviderTest extends TestCase {
	public function test_rows_include_last_generation_next_run_urls_and_backend(): void {
		scwc_test_schedule_reset();
		$settings  = ( new Settings( Defaults::all() ) )->with( 'price_list.external_cron_key', 'k' )->with( 'outlet.label', 'WEB1' )->with( 'outlet.address', 'Ilica 1' )
			->with( 'outlets.additional', [ [ 'form' => 'poslovnica', 'address' => 'V 5', 'label' => 'ZG-02', 'storage_number' => '1' ] ] );
		$scheduler = new Scheduler( new ActionSchedulerBackend(), $settings, new FixedClock( '2026-10-01 03:00:00' ) );
		$scheduler->ensureScheduled();
		Functions\when( 'get_option' )->alias( fn( $k, $d = false ) => 'scwc_last_generation' === $k ? [ 'at' => '2026-10-01 04:00:12', 'files' => [ 'a.xml', 'a.csv' ], 'products' => 10, 'services' => 2, 'error' => '' ] : $d );
		$rows   = ( new StatusProvider( $settings, $scheduler, new FixedClock( '2026-10-01 10:00:00' ) ) )();
		$labels = array_column( $rows, 'label' );
		$values = implode( "\n", array_column( $rows, 'value' ) );
		self::assertContains( 'Zadnje generiranje', $labels );
		self::assertStringContainsString( '1. 10. 2026. 06:00', $values );
		self::assertStringContainsString( '10 proizvoda, 2 usluge', $values );
		self::assertStringContainsString( 'https://example.hr/cjenik/latest.xml', $values );
		self::assertStringContainsString( 'https://example.hr/cjenik/zg-02/latest.xml', $values );
		self::assertStringContainsString( 'ZG-02', $values );
		self::assertStringContainsString( 'scwc_run=1&amp;key=k', $values );
		self::assertStringContainsString( 'Action Scheduler', $values );
		$next = array_values( array_filter( $rows, fn( $r ) => 'Sljedeće generiranje' === $r['label'] ) )[0]['value'];
		self::assertSame( '1. 10. 2026. 06:00', $next, 'next generation shown in local time' );
	}

	public function test_missing_generation_and_schedule_are_reported(): void {
		scwc_test_schedule_reset();
		$settings  = new Settings( Defaults::all() );
		$scheduler = new Scheduler( new ActionSchedulerBackend(), $settings, new FixedClock() );
		$values    = implode( "\n", array_column( ( new StatusProvider( $settings, $scheduler, new FixedClock() ) )(), 'value' ) );
		self::assertStringContainsString( 'nikad', $values );
		self::assertStringContainsString( 'nije zakazano', $values );
	}

	private function providerWith( $http ): StatusProvider {
		scwc_test_schedule_reset();
		$settings  = ( new Settings( Defaults::all() ) )->with( 'outlet.label', 'WEB1' )->with( 'outlet.address', 'Ilica 1' );
		$scheduler = new Scheduler( new ActionSchedulerBackend(), $settings, new FixedClock() );
		return new StatusProvider( $settings, $scheduler, new FixedClock(), $http );
	}

	public function test_public_address_row_is_first_and_unmistakable(): void {
		$rows = ( $this->providerWith( fn( string $url ) => [ 'code' => 200 ] ) )();
		self::assertSame( 'Javna adresa cjenika', $rows[0]['label'] );
		self::assertStringContainsString( '<strong>https://example.hr/cjenik/</strong>', $rows[0]['value'] );
		self::assertStringContainsString( '/cijene', $rows[0]['value'], 'explicitly says what the address is NOT' );
	}

	public function test_availability_check_reports_status_with_a_hint(): void {
		$ok = array_values( array_filter( ( $this->providerWith( fn( string $url ) => [ 'code' => 200 ] ) )(), fn( $r ) => 'Provjera dostupnosti' === $r['label'] ) )[0]['value'];
		self::assertStringContainsString( 'HTTP 200', $ok );
		self::assertStringContainsString( 'u redu', $ok );
		$nf = array_values( array_filter( ( $this->providerWith( fn( string $url ) => [ 'code' => 404 ] ) )(), fn( $r ) => 'Provjera dostupnosti' === $r['label'] ) )[0]['value'];
		self::assertStringContainsString( 'HTTP 404', $nf );
		self::assertStringContainsString( 'Trajne veze', $nf );
		$err = array_values( array_filter( ( $this->providerWith( fn( string $url ) => 'cURL error 28' ) )(), fn( $r ) => 'Provjera dostupnosti' === $r['label'] ) )[0]['value'];
		self::assertStringContainsString( 'cURL error 28', $err );
		$bot = array_values( array_filter( ( $this->providerWith( fn( string $url ) => [ 'code' => 403 ] ) )(), fn( $r ) => 'Provjera dostupnosti' === $r['label'] ) )[0]['value'];
		self::assertStringContainsString( 'robot', $bot );
	}
}
