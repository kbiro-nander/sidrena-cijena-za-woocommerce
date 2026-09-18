<?php
declare(strict_types=1);

namespace SidrenaCijena\Tests\Unit\Lifecycle;

use SidrenaCijena\Lifecycle\Requirements;
use SidrenaCijena\Tests\TestCase;

final class RequirementsTest extends TestCase {
	public function test_passes_with_supported_php_and_woocommerce(): void {
		self::assertSame( [], Requirements::problems( '8.1.0', '9.3.0' ) );
	}

	public function test_reports_old_php(): void {
		$problems = Requirements::problems( '8.0.30', '9.3.0' );
		self::assertCount( 1, $problems );
		self::assertStringContainsString( '8.1', $problems[0] );
	}

	public function test_reports_missing_and_old_woocommerce(): void {
		self::assertCount( 1, Requirements::problems( '8.2.0', null ) );
		self::assertCount( 1, Requirements::problems( '8.2.0', '8.9.1' ) );
	}
}
