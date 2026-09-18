<?php
declare(strict_types=1);

namespace SidrenaCijena\Tests\Unit\Admin\Tools;

use Brain\Monkey\Functions;
use SidrenaCijena\Admin\Tools\CsvImporter;
use SidrenaCijena\Admin\Tools\ImportPreview;
use SidrenaCijena\Reference\CategoryOverrideResolver;
use SidrenaCijena\Reference\ReferenceDateResolver;
use SidrenaCijena\Reference\ReferencePriceRegistry;
use SidrenaCijena\Reference\ReferencePriceRepository;
use SidrenaCijena\Reference\ReferencePriceType;
use SidrenaCijena\Tests\TestCase;

final class CsvImporterTest extends TestCase {
	private ReferencePriceRegistry $registry;

	protected function setUp(): void {
		parent::setUp();
		$this->registry = new ReferencePriceRegistry(
			[
				'anchor' => new ReferencePriceType( 'anchor', 'Cijena na dan', '2026-09-10', [], true, 'SidrenaCijena', 'sidrena_cijena' ),
				'base'   => new ReferencePriceType( 'base', 'Bazna cijena', null, [], false, 'BaznaCijena', 'bazna_cijena' ),
			]
		);
		$this->product( [ 'id' => 1, 'sku' => 'A-1', 'regular_price' => '10' ] );
		$this->product( [ 'id' => 2, 'sku' => 'B-2', 'regular_price' => '20' ] );
	}

	private function importer(): CsvImporter {
		return new CsvImporter(
			$this->registry,
			new ReferencePriceRepository( new ReferenceDateResolver( new CategoryOverrideResolver( fn() => [] ) ) ),
			fn( string $sku ) => (int) \wc_get_product_id_by_sku( $sku ),
			fn( int $id ) => \wc_get_product( $id ) ?: null,
		);
	}

	public function test_strips_bom_and_maps_croatian_headers_with_decimal_comma(): void {
		$csv     = "\xEF\xBB\xBF" . "Šifra;Cijena;Datum;Tip\r\nA-1;1.234,50;2026-09-10;anchor\r\nB-2;19,99;;\r\n";
		$preview = $this->importer()->parse( $csv );

		self::assertSame( ';', $preview->delimiter );
		self::assertSame( [], $preview->errors() );
		self::assertSame( 2, $preview->valid );
		self::assertSame( 0, $preview->invalid );
		self::assertSame( 1234.5, $preview->rows[0]->amount );
		self::assertSame( '2026-09-10', $preview->rows[0]->date );
		self::assertSame( 1, $preview->rows[0]->productId );
		self::assertSame( 2, $preview->rows[0]->line );
		self::assertSame( 19.99, $preview->rows[1]->amount );
		self::assertNull( $preview->rows[1]->date );
		self::assertSame( 'anchor', $preview->rows[1]->typeKey );
		self::assertSame( 2, $preview->rows[1]->productId );
	}

	public function test_detects_comma_and_tab_delimiters(): void {
		$comma = $this->importer()->parse( "sku,price\nA-1,\"1,234.50\"\n" );
		self::assertSame( ',', $comma->delimiter );
		self::assertSame( 1234.5, $comma->rows[0]->amount );

		$tab = $this->importer()->parse( "sku\tprice\tdate\nB-2\t5\t2026-01-01\n" );
		self::assertSame( "\t", $tab->delimiter );
		self::assertSame( 5.0, $tab->rows[0]->amount );
		self::assertSame( 2, $tab->rows[0]->productId );
	}

	public function test_explicit_delimiter_wins_over_detection(): void {
		$preview = $this->importer()->parse( "sku;price\nA-1;12,5\n", 'anchor', ';' );
		self::assertSame( ';', $preview->delimiter );
		self::assertSame( 12.5, $preview->rows[0]->amount );
	}

	public function test_invalid_date_row_is_reported_without_aborting(): void {
		$preview = $this->importer()->parse( "sku;cijena;datum\nA-1;10;10.09.2026\nB-2;20;2026-09-10\n" );
		self::assertSame( 1, $preview->valid );
		self::assertSame( 1, $preview->invalid );
		self::assertStringContainsString( 'Neispravan datum', (string) $preview->rows[0]->error );
		self::assertSame( [ 'Redak 2: Neispravan datum' ], $preview->errors() );
		self::assertNull( $preview->rows[1]->error );
	}

	public function test_unmatched_sku_and_missing_sku_are_errors(): void {
		$preview = $this->importer()->parse( "sku;cijena\nZZZ;10\n;10\n" );
		self::assertSame( 0, $preview->valid );
		self::assertSame( 2, $preview->invalid );
		self::assertSame( 1, $preview->unmatched );
		self::assertNull( $preview->rows[0]->productId );
		self::assertStringContainsString( 'Proizvod s tom šifrom ne postoji', (string) $preview->rows[0]->error );
		self::assertNotNull( $preview->rows[1]->error );
	}

	public function test_na_rows_need_no_amount_and_non_positive_amounts_fail(): void {
		$preview = $this->importer()->parse( "sku;cijena;na\nA-1;;da\nB-2;0;\nA-1;-5;0\n" );
		self::assertTrue( $preview->rows[0]->na );
		self::assertNull( $preview->rows[0]->error );
		self::assertSame( 1, $preview->valid );
		self::assertStringContainsString( 'Neispravan iznos', (string) $preview->rows[1]->error );
		self::assertStringContainsString( 'Neispravan iznos', (string) $preview->rows[2]->error );
	}

	public function test_unknown_type_key_is_an_error_but_disabled_type_is_accepted(): void {
		$preview = $this->importer()->parse( "sku;cijena;tip\nA-1;10;base\nB-2;10;nope\n" );
		self::assertSame( 'base', $preview->rows[0]->typeKey );
		self::assertNull( $preview->rows[0]->error );
		self::assertStringContainsString( 'Nepoznata vrsta', (string) $preview->rows[1]->error );
	}

	public function test_default_type_key_applies_when_column_absent(): void {
		$preview = $this->importer()->parse( "sku;cijena\nA-1;10\n", 'base' );
		self::assertSame( 'base', $preview->rows[0]->typeKey );
	}

	public function test_missing_required_column_is_fatal(): void {
		$preview = $this->importer()->parse( "naziv;cijena\nA-1;10\n" );
		self::assertSame( [], $preview->rows );
		self::assertCount( 1, $preview->errors() );
		self::assertStringContainsString( 'sku', $preview->errors()[0] );
		self::assertTrue( $preview->isFatal() );
		self::assertSame( 0, $preview->valid );
	}

	public function test_blank_lines_are_ignored_and_preview_round_trips_through_arrays(): void {
		$preview = $this->importer()->parse( "sku;cijena\n\nA-1;10\n\n" );
		self::assertCount( 1, $preview->rows );
		$restored = ImportPreview::fromArray( $preview->toArray() );
		self::assertEquals( $preview, $restored );
	}

	public function test_apply_sets_prices_marks_na_and_skips_invalid_rows(): void {
		$preview = $this->importer()->parse( "sku;cijena;datum;na\nA-1;12,50;2026-09-01;\nB-2;;;1\nZZZ;10;;\n" );
		Functions\expect( 'update_post_meta' )->once()->with( 1, '_scwc_ref_anchor_price', '12.5' );
		Functions\expect( 'update_post_meta' )->once()->with( 1, '_scwc_ref_anchor_source', 'import' );
		Functions\expect( 'update_post_meta' )->once()->with( 1, '_scwc_ref_anchor_date', '2026-09-01' );
		Functions\expect( 'update_post_meta' )->once()->with( 2, '_scwc_ref_anchor_na', '1' );
		Functions\expect( 'update_post_meta' )->once()->with( 2, '_scwc_ref_anchor_source', 'import' );

		$result = $this->importer()->apply( $preview );

		self::assertSame( 1, $result->updated );
		self::assertSame( 1, $result->markedNa );
		self::assertSame( 1, $result->skipped );
		self::assertCount( 1, $result->errors );
		self::assertStringStartsWith( 'Redak 4:', $result->errors[0] );
		self::assertSame( 1, $result->toArray()['updated'] );
	}

	public function test_apply_on_fatal_preview_does_nothing(): void {
		Functions\expect( 'update_post_meta' )->never();
		$result = $this->importer()->apply( $this->importer()->parse( "x;y\n1;2\n" ) );
		self::assertSame( 0, $result->updated );
		self::assertCount( 1, $result->errors );
	}

	public function test_template_is_importable(): void {
		$template = CsvImporter::template( $this->registry );
		self::assertStringStartsWith( 'sku;cijena;datum;tip', $template );
		$preview = $this->importer()->parse( $template );
		self::assertCount( 2, $preview->rows );
		self::assertSame( ';', $preview->delimiter );
		self::assertSame( 'anchor', $preview->rows[0]->typeKey );
		self::assertGreaterThan( 0, $preview->rows[0]->amount );
	}
}
