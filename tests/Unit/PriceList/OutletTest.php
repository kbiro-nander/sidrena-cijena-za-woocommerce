<?php
declare(strict_types=1);

namespace SidrenaCijena\Tests\Unit\PriceList;

use SidrenaCijena\PriceList\Outlet;
use SidrenaCijena\Settings\Defaults;
use SidrenaCijena\Settings\Settings;
use SidrenaCijena\Tests\TestCase;

final class OutletTest extends TestCase {
	public function test_from_settings_reads_outlet_section_and_site_data(): void {
		$settings = ( new Settings( Defaults::all() ) )
			->with( 'outlet.address', 'Ulica 1, 10000 Zagreb' )
			->with( 'outlet.label', 'WEB1' )
			->with( 'outlet.storage_number', '2' )
			->with( 'outlet.merchant_name', 'Trgovina d.o.o.' );
		$outlet = Outlet::fromSettings( $settings );
		self::assertSame( 'webshop', $outlet->form );
		self::assertSame( 'Ulica 1, 10000 Zagreb', $outlet->address );
		self::assertSame( 'WEB1', $outlet->label );
		self::assertSame( '2', $outlet->storageNumber );
		self::assertSame( 'Trgovina d.o.o.', $outlet->merchantName );
		self::assertSame( 'https://example.hr/', $outlet->url );
		self::assertTrue( $outlet->isComplete() );
	}

	public function test_merchant_name_falls_back_to_blog_name(): void {
		$outlet = Outlet::fromSettings( new Settings( Defaults::all() ) );
		self::assertSame( 'Test trgovina', $outlet->merchantName );
	}

	public function test_incomplete_without_address_or_label(): void {
		self::assertFalse( Outlet::fromSettings( new Settings( Defaults::all() ) )->isComplete() );
		self::assertFalse( ( new Outlet( 'webshop', 'Ulica 1', '', '1', 'X', 'https://example.hr/' ) )->isComplete() );
		self::assertFalse( ( new Outlet( 'webshop', '', 'WEB1', '1', 'X', 'https://example.hr/' ) )->isComplete() );
		self::assertTrue( ( new Outlet( '', 'Ulica 1', 'WEB1', '', 'X', 'https://example.hr/' ) )->isComplete() );
	}
}
