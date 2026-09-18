<?php
declare(strict_types=1);

namespace SidrenaCijena\Tests\Unit\Settings;

use Brain\Monkey\Functions;
use SidrenaCijena\Settings\Defaults;
use SidrenaCijena\Settings\Settings;
use SidrenaCijena\Tests\TestCase;

final class SettingsTest extends TestCase {
	public function test_defaults_expose_anchor_type_with_legal_reference_date(): void {
		$s = new Settings( Defaults::all() );
		self::assertSame( '2026-09-10', $s->get( 'reference_prices.anchor.date' ) );
		self::assertTrue( $s->get( 'reference_prices.anchor.enabled' ) );
		self::assertFalse( $s->get( 'reference_prices.base.enabled' ) );
		self::assertSame( 'cjenik', $s->get( 'price_list.slug' ) );
		self::assertSame( '06:00', $s->get( 'price_list.generate_time' ) );
		self::assertSame( 35, $s->get( 'price_list.retention_days' ) );
	}

	public function test_get_returns_default_for_unknown_path(): void {
		$s = new Settings( [] );
		self::assertSame( 'x', $s->get( 'nope.deeper', 'x' ) );
		self::assertNull( $s->get( 'nope' ) );
	}

	public function test_from_option_merges_stored_values_over_defaults(): void {
		Functions\expect( 'get_option' )->once()->with( Settings::OPTION, [] )->andReturn( [
			'price_list' => [ 'slug' => 'cijene' ],
			'outlet'     => [ 'address' => 'Ilica 1' ],
		] );
		$s = Settings::fromOption();
		self::assertSame( 'cijene', $s->get( 'price_list.slug' ) );
		self::assertSame( '06:00', $s->get( 'price_list.generate_time' ) );
		self::assertSame( 'Ilica 1', $s->get( 'outlet.address' ) );
		self::assertSame( 'webshop', $s->get( 'outlet.form' ) );
	}

	public function test_section_returns_array(): void {
		$s = new Settings( Defaults::all() );
		self::assertArrayHasKey( 'form', $s->section( 'outlet' ) );
		self::assertSame( [], $s->section( 'missing' ) );
	}

	public function test_with_returns_new_instance_with_value_set(): void {
		$s  = new Settings( Defaults::all() );
		$s2 = $s->with( 'price_list.slug', 'cijene' );
		self::assertSame( 'cjenik', $s->get( 'price_list.slug' ) );
		self::assertSame( 'cijene', $s2->get( 'price_list.slug' ) );
	}
}
