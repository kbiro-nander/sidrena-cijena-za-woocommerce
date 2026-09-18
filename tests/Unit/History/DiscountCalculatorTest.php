<?php
declare(strict_types=1);

namespace SidrenaCijena\Tests\Unit\History;

use SidrenaCijena\History\DiscountCalculator;
use SidrenaCijena\Tests\TestCase;

final class DiscountCalculatorTest extends TestCase {
	public function test_percent_is_floored_so_it_never_overstates(): void {
		self::assertSame( 23, DiscountCalculator::percent( 12.99, 10.0 ) ); // 23.01%
		self::assertSame( 33, DiscountCalculator::percent( 30.0, 20.0 ) );  // 33.33%
		self::assertSame( 50, DiscountCalculator::percent( 40.0, 20.0 ) );
	}

	public function test_null_when_no_real_discount(): void {
		self::assertNull( DiscountCalculator::percent( 10.0, 10.0 ) );
		self::assertNull( DiscountCalculator::percent( 10.0, 12.0 ) );
		self::assertNull( DiscountCalculator::percent( 0.0, 5.0 ) );
		self::assertNull( DiscountCalculator::percent( 10.0, 9.95 ), 'less than 1% floors to 0 → null' );
	}
}
