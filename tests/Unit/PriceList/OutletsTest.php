<?php
declare(strict_types=1);

namespace SidrenaCijena\Tests\Unit\PriceList;

use SidrenaCijena\PriceList\Outlet;
use SidrenaCijena\PriceList\Outlets;
use SidrenaCijena\Settings\Defaults;
use SidrenaCijena\Settings\Settings;
use SidrenaCijena\Tests\TestCase;

final class OutletsTest extends TestCase {
	private function settings( array $additional = [], string $label = 'WEB1' ): Settings {
		return ( new Settings( Defaults::all() ) )
			->with( 'outlet.address', 'Ilica 1' )
			->with( 'outlet.label', $label )
			->with( 'outlets.additional', $additional );
	}

	public function test_primary_outlet_comes_first_with_key_from_label(): void {
		$outlets = Outlets::fromSettings( $this->settings() );
		self::assertCount( 1, $outlets );
		self::assertSame( 'web1', $outlets[0]->key );
		self::assertTrue( $outlets[0]->isPrimary() );
		self::assertSame( 'webshop', $outlets[0]->form );
	}

	public function test_primary_without_label_gets_webshop_key(): void {
		self::assertSame( 'webshop', Outlets::fromSettings( $this->settings( [], '' ) )[0]->key );
	}

	public function test_additional_outlets_follow_with_unique_keys(): void {
		$outlets = Outlets::fromSettings( $this->settings( [
			[ 'form' => 'poslovnica', 'address' => 'Vukovarska 5', 'label' => 'ZG-02', 'storage_number' => '3' ],
			[ 'form' => 'poslovnica', 'address' => 'Ilica 1', 'label' => 'WEB1', 'storage_number' => '2' ],
			[ 'form' => 'kiosk', 'address' => 'Trg 1', 'label' => 'Web 1', 'storage_number' => '5' ],
		] ) );
		self::assertSame( [ 'web1', 'zg-02', 'web1-2', 'web-1' ], array_map( fn( Outlet $o ) => $o->key, $outlets ) );
		self::assertFalse( $outlets[1]->isPrimary() );
		self::assertSame( 'Vukovarska 5', $outlets[1]->address );
		self::assertSame( '3', $outlets[1]->storageNumber );
		self::assertSame( $outlets[0]->merchantName, $outlets[1]->merchantName, 'merchant name and URL are shared' );
		self::assertSame( $outlets[0]->url, $outlets[2]->url );
	}

	public function test_by_key(): void {
		$outlets = Outlets::fromSettings( $this->settings( [ [ 'form' => 'poslovnica', 'address' => 'V 5', 'label' => 'ZG-02', 'storage_number' => '1' ] ] ) );
		self::assertSame( 'ZG-02', Outlets::byKey( $outlets, 'zg-02' )?->label );
		self::assertNull( Outlets::byKey( $outlets, 'nope' ) );
	}

	public function test_outlet_constructor_derives_key_when_omitted(): void {
		$o = new Outlet( 'webshop', 'Ilica 1', 'Web Shop 1', '1', 'T', 'https://x/' );
		self::assertSame( 'web-shop-1', $o->key );
		self::assertSame( 'custom', ( new Outlet( 'webshop', 'Ilica 1', 'W', '1', 'T', 'https://x/', 'custom' ) )->key );
	}
}
