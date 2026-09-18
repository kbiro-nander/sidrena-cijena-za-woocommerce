<?php
declare(strict_types=1);

namespace SidrenaCijena\Tests\Unit\PriceList;

use DateTimeImmutable;
use DateTimeZone;
use SidrenaCijena\PriceList\FilenameBuilder;
use SidrenaCijena\PriceList\Outlet;
use SidrenaCijena\Tests\TestCase;

final class FilenameBuilderTest extends TestCase {
	private function time(): DateTimeImmutable {
		return new DateTimeImmutable( '2026-10-01 06:00:12', new DateTimeZone( 'Europe/Zagreb' ) );
	}

	public function test_builds_parts_in_order_with_local_timestamp(): void {
		$outlet = new Outlet( 'webshop', 'Ulica 1, 10000 Zagreb', 'WEB1', '1', 'Trgovina', 'https://example.hr/' );
		$name   = ( new FilenameBuilder() )->build( $outlet, $this->time(), 'xml' );
		self::assertSame( 'webshop_ulica-1-10000-zagreb_web1_1_20261001_060012.xml', $name );
		self::assertTrue( FilenameBuilder::isValid( $name ) );
	}

	public function test_transliterates_diacritics(): void {
		$outlet = new Outlet( 'webshop', 'Trg Ivana Pavla II 3, Šibenik', 'Đuro & Sons', '1', '', '' );
		$name   = ( new FilenameBuilder() )->build( $outlet, $this->time(), 'csv' );
		self::assertSame( 'webshop_trg-ivana-pavla-ii-3-sibenik_duro-sons_1_20261001_060012.csv', $name );
	}

	public function test_empty_parts_become_na(): void {
		$outlet = new Outlet( '', 'Ulica 1', '', '', '', '' );
		self::assertSame( 'na_ulica-1_na_na_20261001_060012.xml', ( new FilenameBuilder() )->build( $outlet, $this->time(), 'xml' ) );
	}

	public function test_pattern_validation(): void {
		self::assertTrue( FilenameBuilder::isValid( 'a_b_20261001_060012.csv' ) );
		self::assertFalse( FilenameBuilder::isValid( '../etc/passwd.xml' ) );
		self::assertFalse( FilenameBuilder::isValid( 'manifest.json' ) );
		self::assertFalse( FilenameBuilder::isValid( 'Upper.xml' ) );
		self::assertFalse( FilenameBuilder::isValid( '.hidden.xml' ) );
		self::assertSame( 1, preg_match( FilenameBuilder::PATTERN, 'webshop_x_y_1_20261001_060012.xml' ) );
	}
}
