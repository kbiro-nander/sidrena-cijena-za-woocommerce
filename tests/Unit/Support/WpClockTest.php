<?php
declare(strict_types=1);

namespace SidrenaCijena\Tests\Unit\Support;

use SidrenaCijena\Support\WpClock;
use SidrenaCijena\Tests\TestCase;

final class WpClockTest extends TestCase {
	public function test_now_is_utc_and_timezone_comes_from_wordpress(): void {
		$clock = new WpClock();
		self::assertSame( 'UTC', $clock->now()->getTimezone()->getName() );
		self::assertSame( 'Europe/Zagreb', $clock->timezone()->getName() );
		self::assertSame( 'Europe/Zagreb', $clock->nowLocal()->getTimezone()->getName() );
	}
}
