<?php
declare(strict_types=1);

namespace SidrenaCijena\Tests\Unit\Product;

use SidrenaCijena\Product\ProductSnapshot;
use SidrenaCijena\Tests\TestCase;

final class ProductSnapshotTest extends TestCase {
	public function test_from_array_fills_defaults_and_exposes_helpers(): void {
		$s = ProductSnapshot::fromArray( [ 'id' => 3, 'regular' => '10', 'sale' => '8', 'active' => '8', 'isOnSale' => true ] );
		self::assertSame( 3, $s->id );
		self::assertSame( 'simple', $s->type );
		self::assertSame( '', $s->meta( '_missing' ) );
		self::assertTrue( $s->isOnSale );
		self::assertTrue( $s->isVariation() === false );
		self::assertFalse( $s->isVariableParent() );
		self::assertSame( 'instock', $s->stockStatus );
	}

	public function test_type_helpers(): void {
		self::assertTrue( ProductSnapshot::fromArray( [ 'id' => 1, 'type' => 'variation', 'parentId' => 5 ] )->isVariation() );
		self::assertTrue( ProductSnapshot::fromArray( [ 'id' => 1, 'type' => 'variable' ] )->isVariableParent() );
		self::assertTrue( ProductSnapshot::fromArray( [ 'id' => 1, 'type' => 'grouped' ] )->isContainer() );
		self::assertFalse( ProductSnapshot::fromArray( [ 'id' => 1, 'type' => 'external' ] )->isContainer() );
	}
}
