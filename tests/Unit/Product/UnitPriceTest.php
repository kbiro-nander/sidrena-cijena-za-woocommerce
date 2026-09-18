<?php
declare(strict_types=1);

namespace SidrenaCijena\Tests\Unit\Product;

use SidrenaCijena\Product\UnitPrice;
use SidrenaCijena\Tests\TestCase;

final class UnitPriceTest extends TestCase {
	public function test_divides_price_by_quantity(): void {
		self::assertSame( 3.98, UnitPrice::compute( 1.99, 0.5 ) );
		self::assertSame( 12.99, UnitPrice::compute( 12.99, 1.0 ) );
	}

	public function test_null_when_quantity_missing_or_not_positive(): void {
		self::assertNull( UnitPrice::compute( 1.99, null ) );
		self::assertNull( UnitPrice::compute( 1.99, 0.0 ) );
		self::assertNull( UnitPrice::compute( 1.99, -1.0 ) );
	}
}
