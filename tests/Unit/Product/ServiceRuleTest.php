<?php
declare(strict_types=1);

namespace SidrenaCijena\Tests\Unit\Product;

use Brain\Monkey\Filters;
use SidrenaCijena\Product\ProductSnapshot;
use SidrenaCijena\Product\ServiceRule;
use SidrenaCijena\Tests\TestCase;

final class ServiceRuleTest extends TestCase {
	private function snap( bool $virtual, ?bool $flag ): ProductSnapshot {
		return ProductSnapshot::fromArray( [ 'id' => 1, 'isVirtual' => $virtual, 'serviceFlag' => $flag ] );
	}

	public function test_virtual_or_flag_mode(): void {
		$rule = new ServiceRule( 'virtual_or_flag' );
		self::assertTrue( $rule->isService( $this->snap( true, null ) ) );
		self::assertTrue( $rule->isService( $this->snap( false, true ) ) );
		self::assertFalse( $rule->isService( $this->snap( false, null ) ) );
		self::assertFalse( $rule->isService( $this->snap( true, false ) ), 'explicit "no" flag overrides virtual' );
	}

	public function test_virtual_only_flag_only_all_none(): void {
		self::assertTrue( ( new ServiceRule( 'virtual' ) )->isService( $this->snap( true, null ) ) );
		self::assertFalse( ( new ServiceRule( 'virtual' ) )->isService( $this->snap( false, true ) ) );
		self::assertTrue( ( new ServiceRule( 'flag' ) )->isService( $this->snap( false, true ) ) );
		self::assertFalse( ( new ServiceRule( 'flag' ) )->isService( $this->snap( true, null ) ) );
		self::assertTrue( ( new ServiceRule( 'all' ) )->isService( $this->snap( false, null ) ) );
		self::assertFalse( ( new ServiceRule( 'none' ) )->isService( $this->snap( true, true ) ) );
	}

	public function test_filter_has_last_word(): void {
		Filters\expectApplied( 'scwc_is_service' )->once()->andReturn( true );
		self::assertTrue( ( new ServiceRule( 'none' ) )->isService( $this->snap( false, null ) ) );
	}
}
