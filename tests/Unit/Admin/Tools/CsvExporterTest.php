<?php
declare(strict_types=1);

namespace SidrenaCijena\Tests\Unit\Admin\Tools;

use SidrenaCijena\Admin\Tools\CsvExporter;
use SidrenaCijena\Product\BarcodeResolver;
use SidrenaCijena\Product\BrandResolver;
use SidrenaCijena\Product\ProductAdapter;
use SidrenaCijena\Reference\CategoryOverrideResolver;
use SidrenaCijena\Reference\ReferenceDateResolver;
use SidrenaCijena\Reference\ReferencePriceRegistry;
use SidrenaCijena\Reference\ReferencePriceRepository;
use SidrenaCijena\Reference\ReferencePriceType;
use SidrenaCijena\Tests\TestCase;

final class CsvExporterTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		$this->product( [ 'id' => 1, 'sku' => 'A-1', 'name' => 'Prvi', 'regular_price' => '10', 'meta' => [ '_scwc_ref_anchor_price' => '9.5', '_scwc_ref_anchor_source' => 'manual', '_scwc_ref_anchor_date' => '2026-09-01' ] ] );
		$this->product( [ 'id' => 2, 'sku' => 'B-2', 'name' => 'Drugi; "citat"', 'regular_price' => '20' ] );
		$this->product( [ 'id' => 3, 'sku' => 'C-3', 'name' => 'Treći', 'regular_price' => '30', 'meta' => [ '_scwc_ref_anchor_na' => '1' ] ] );
	}

	/**
	 * @param array<int,int[]> $pages
	 */
	private function exporter( array $pages, bool $withBase = true ): CsvExporter {
		$types = [ 'anchor' => new ReferencePriceType( 'anchor', 'Cijena na dan', '2026-09-10', [], true, 'SidrenaCijena', 'sidrena_cijena' ) ];
		if ( $withBase ) {
			$types['base'] = new ReferencePriceType( 'base', 'Bazna', '2026-01-01', [], true, 'BaznaCijena', 'bazna_cijena' );
		}
		$types['off'] = new ReferencePriceType( 'off', 'Off', null, [], false, 'Off', 'off' );
		return new CsvExporter(
			new ReferencePriceRegistry( $types ),
			new ReferencePriceRepository( new ReferenceDateResolver( new CategoryOverrideResolver( fn() => [] ) ) ),
			new ProductAdapter( new BrandResolver(), new BarcodeResolver() ),
			fn( int $page, int $perPage ) => $pages[ $page ] ?? [],
			fn( int $id ) => \wc_get_product( $id ) ?: null,
		);
	}

	public function test_yields_header_then_one_row_per_product_per_enabled_type(): void {
		$rows = iterator_to_array( $this->exporter( [ 1 => [ 1, 2, 3 ] ] )->rows(), false );
		self::assertSame( CsvExporter::HEADER, $rows[0] );
		self::assertSame( [ 'sku', 'naziv', 'vrsta_proizvoda', 'redovna_cijena', 'tip', 'cijena', 'datum', 'izvor', 'na' ], $rows[0] );
		self::assertCount( 7, $rows );
		self::assertSame( [ 'A-1', 'Prvi', 'simple', '10', 'anchor', '9.5', '2026-09-01', 'manual', '' ], $rows[1] );
		self::assertSame( [ 'A-1', 'Prvi', 'simple', '10', 'base', '', '', '', '' ], $rows[2] );
		self::assertSame( [ 'C-3', 'Treći', 'simple', '30', 'anchor', '', '', '', '1' ], $rows[5] );
	}

	public function test_only_missing_filters_present_and_na_rows(): void {
		$rows = iterator_to_array( $this->exporter( [ 1 => [ 1, 2, 3 ] ], false )->rows( true ), false );
		self::assertCount( 2, $rows );
		self::assertSame( 'B-2', $rows[1][0] );
	}

	public function test_later_pages_omit_header_and_generator_returns_has_more(): void {
		$gen  = $this->exporter( [ 1 => [ 1, 2 ], 2 => [ 3 ] ], false )->rows( false, 2, 2 );
		$rows = iterator_to_array( $gen, false );
		self::assertCount( 1, $rows );
		self::assertSame( 'C-3', $rows[0][0] );
		self::assertFalse( $gen->getReturn() );

		$gen1 = $this->exporter( [ 1 => [ 1, 2 ], 2 => [ 3 ] ], false )->rows( false, 1, 2 );
		iterator_to_array( $gen1, false );
		self::assertTrue( $gen1->getReturn() );
	}

	public function test_to_csv_string_adds_bom_and_quotes_fields(): void {
		$csv = CsvExporter::toCsvString( [ [ 'sku', 'naziv' ], [ 'B-2', 'Drugi; "citat"' ] ] );
		self::assertStringStartsWith( "\xEF\xBB\xBF" . "sku;naziv\n", $csv );
		self::assertStringContainsString( 'B-2;"Drugi; ""citat"""' . "\n", $csv );

		$comma = CsvExporter::toCsvString( [ [ 'a', 'b' ] ], ',' );
		self::assertSame( "\xEF\xBB\xBF" . "a,b\n", $comma );
	}
}
