<?php
declare(strict_types=1);

namespace SidrenaCijena\Tests\Unit\Admin\Tools;

use SidrenaCijena\Admin\Tools\SnapshotRequest;
use SidrenaCijena\Settings\Defaults;
use SidrenaCijena\Settings\Settings;
use SidrenaCijena\Tests\TestCase;

final class SnapshotRequestTest extends TestCase {
	public function test_from_array_reads_all_fields(): void {
		$settings = new Settings( Defaults::all() );
		$req      = SnapshotRequest::fromArray(
			[ 'type' => 'base', 'date' => '2026-09-10', 'mode' => 'overwrite', 'skip_on_sale' => '1', 'dry_run' => 'true' ],
			$settings
		);
		self::assertSame( 'base', $req->typeKey );
		self::assertSame( '2026-09-10', $req->date );
		self::assertSame( SnapshotRequest::MODE_OVERWRITE, $req->mode );
		self::assertTrue( $req->skipOnSale );
		self::assertTrue( $req->dryRun );
		self::assertTrue( $req->markNaAfterDate );
	}

	public function test_invalid_date_and_unknown_mode_fall_back(): void {
		$settings = ( new Settings( Defaults::all() ) )->with( 'reference_prices.auto_na_after_date', false );
		$req      = SnapshotRequest::fromArray( [ 'date' => '10.09.2026', 'mode' => 'bogus' ], $settings );
		self::assertSame( 'anchor', $req->typeKey );
		self::assertNull( $req->date );
		self::assertSame( SnapshotRequest::MODE_ONLY_MISSING, $req->mode );
		self::assertFalse( $req->skipOnSale );
		self::assertFalse( $req->dryRun );
		self::assertFalse( $req->markNaAfterDate );
	}

	public function test_empty_date_means_type_default_and_round_trips_to_array(): void {
		$req = SnapshotRequest::fromArray( [ 'type' => 'anchor', 'date' => '', 'skip_on_sale' => '0' ], new Settings( Defaults::all() ) );
		self::assertNull( $req->date );
		self::assertFalse( $req->skipOnSale );
		self::assertSame( 'anchor', $req->toArray()['type'] );
		self::assertSame( 'only_missing', $req->toArray()['mode'] );
	}
}
