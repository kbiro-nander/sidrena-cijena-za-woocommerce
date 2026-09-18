<?php
declare(strict_types=1);

namespace SidrenaCijena\Tests\Unit;

use SidrenaCijena\Tests\TestCase;

final class BootstrapTest extends TestCase {
	public function test_product_stub_reports_sale_from_prices(): void {
		$p = $this->product( [ 'id' => 5, 'regular_price' => '10', 'sale_price' => '8' ] );
		self::assertTrue( $p->is_on_sale() );
		self::assertSame( '8', $p->get_price() );
		self::assertSame( 5, \wc_get_product( 5 )->get_id() );
	}
}
