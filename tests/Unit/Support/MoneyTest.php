<?php
declare(strict_types=1);

namespace SidrenaCijena\Tests\Unit\Support;

use SidrenaCijena\Support\Money;
use SidrenaCijena\Tests\TestCase;

final class MoneyTest extends TestCase {
	public function test_parse_accepts_decimal_comma_and_thousand_separators(): void {
		self::assertSame( 12.99, Money::parse( '12,99' ) );
		self::assertSame( 1234.5, Money::parse( '1.234,50' ) );
		self::assertSame( 1234.5, Money::parse( '1,234.50' ) );
		self::assertSame( 12.99, Money::parse( ' 12.99 € ' ) );
	}

	public function test_parse_rejects_empty_or_garbage(): void {
		self::assertNull( Money::parse( '' ) );
		self::assertNull( Money::parse( 'abc' ) );
		self::assertNull( Money::parse( null ) );
	}

	public function test_to_decimal_uses_dot_and_fixed_places(): void {
		self::assertSame( '12.99', Money::toDecimal( 12.99 ) );
		self::assertSame( '12.00', Money::toDecimal( '12' ) );
		self::assertSame( '12.5000', Money::toDecimal( 12.5, 4 ) );
		self::assertSame( '0.10', Money::toDecimal( 0.1 ) );
	}

	public function test_is_positive_amount(): void {
		self::assertTrue( Money::isPositive( '0.01' ) );
		self::assertFalse( Money::isPositive( '0' ) );
		self::assertFalse( Money::isPositive( '' ) );
		self::assertFalse( Money::isPositive( null ) );
	}
}
