<?php
declare(strict_types=1);

namespace SidrenaCijena\Tests\Unit\Core;

use SidrenaCijena\Container;
use SidrenaCijena\Tests\TestCase;

final class ContainerTest extends TestCase {
	public function test_factories_are_lazy_and_shared(): void {
		$c     = new Container();
		$calls = 0;
		$c->set( 'thing', function () use ( &$calls ) { $calls++; return new \stdClass(); } );
		self::assertSame( 0, $calls );
		self::assertSame( $c->get( 'thing' ), $c->get( 'thing' ) );
		self::assertSame( 1, $calls );
		self::assertTrue( $c->has( 'thing' ) );
		self::assertFalse( $c->has( 'nope' ) );
	}

	public function test_unknown_id_throws(): void {
		$this->expectException( \InvalidArgumentException::class );
		( new Container() )->get( 'nope' );
	}

	public function test_factory_receives_container(): void {
		$c = new Container();
		$c->set( 'a', fn() => 'A' );
		$c->set( 'b', fn( Container $c ) => $c->get( 'a' ) . 'B' );
		self::assertSame( 'AB', $c->get( 'b' ) );
	}
}
