<?php
declare(strict_types=1);

namespace SidrenaCijena\Tests\Unit\Reference;

use Brain\Monkey\Filters;
use SidrenaCijena\Reference\ReferencePriceRegistry;
use SidrenaCijena\Reference\ReferencePriceType;
use SidrenaCijena\Settings\Defaults;
use SidrenaCijena\Settings\Settings;
use SidrenaCijena\Tests\TestCase;

final class ReferencePriceRegistryTest extends TestCase {
	public function test_anchor_is_always_registered_and_base_only_when_enabled(): void {
		$registry = ReferencePriceRegistry::fromSettings( new Settings( Defaults::all() ) );
		self::assertSame( [ 'anchor' ], array_keys( $registry->enabled() ) );
		self::assertSame( [ 'anchor', 'base' ], array_keys( $registry->all() ) );

		$settings = ( new Settings( Defaults::all() ) )->with( 'reference_prices.base.enabled', true )->with( 'reference_prices.base.date', '2026-11-17' );
		$registry = ReferencePriceRegistry::fromSettings( $settings );
		self::assertSame( [ 'anchor', 'base' ], array_keys( $registry->enabled() ) );
	}

	public function test_type_carries_label_date_overrides_and_meta_keys(): void {
		$settings = ( new Settings( Defaults::all() ) )->with( 'reference_prices.anchor.category_overrides', [ [ 'term_ids' => [ 3 ], 'date' => '2025-05-02' ] ] );
		$anchor   = ReferencePriceRegistry::fromSettings( $settings )->get( 'anchor' );
		self::assertInstanceOf( ReferencePriceType::class, $anchor );
		self::assertSame( 'Cijena na dan', $anchor->label );
		self::assertSame( '2026-09-10', $anchor->defaultDate );
		self::assertSame( [ [ 'term_ids' => [ 3 ], 'date' => '2025-05-02' ] ], $anchor->categoryOverrides );
		self::assertSame( '_scwc_ref_anchor_price', $anchor->metaKey( 'price' ) );
		self::assertSame( 'SidrenaCijena', $anchor->xmlElement );
		self::assertSame( 'sidrena_cijena', $anchor->csvColumn );
		self::assertSame( 'BaznaCijena', ReferencePriceRegistry::fromSettings( $settings )->get( 'base' )->xmlElement );
	}

	public function test_filter_can_add_custom_type(): void {
		Filters\expectApplied( 'scwc_reference_price_types' )->once()->andReturnUsing( function ( array $types ) {
			$types['promo2027'] = new ReferencePriceType( 'promo2027', 'Cijena 1. 1. 2027.', '2027-01-01', [], true, 'Cijena2027', 'cijena_2027' );
			return $types;
		} );
		$registry = ReferencePriceRegistry::fromSettings( new Settings( Defaults::all() ) );
		self::assertSame( [ 'anchor', 'promo2027' ], array_keys( $registry->enabled() ) );
		self::assertNull( $registry->get( 'missing' ) );
	}
}
