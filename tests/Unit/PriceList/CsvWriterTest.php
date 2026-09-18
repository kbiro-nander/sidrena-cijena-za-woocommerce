<?php
declare(strict_types=1);

namespace SidrenaCijena\Tests\Unit\PriceList;

use Brain\Monkey\Filters;
use DateTimeImmutable;
use DateTimeZone;
use SidrenaCijena\PriceList\CsvWriter;
use SidrenaCijena\PriceList\Item;
use SidrenaCijena\PriceList\Outlet;
use SidrenaCijena\PriceList\ServiceItem;
use SidrenaCijena\PriceList\Writer;
use SidrenaCijena\Reference\ReferencePriceType;
use SidrenaCijena\Tests\TestCase;

final class CsvWriterTest extends TestCase {
	private const BOM = "\xEF\xBB\xBF";
	private string $path;

	protected function setUp(): void {
		parent::setUp();
		$this->path = sys_get_temp_dir() . '/scwc-csv-' . uniqid() . '.csv';
	}

	protected function tearDown(): void {
		@unlink( $this->path );
		parent::tearDown();
	}

	/** @return ReferencePriceType[] */
	private function types( bool $withBase = false ): array {
		$types = [ new ReferencePriceType( 'anchor', 'Cijena na dan', '2026-09-10', [], true, 'SidrenaCijena', 'sidrena_cijena' ) ];
		if ( $withBase ) {
			$types[] = new ReferencePriceType( 'base', 'Bazna', '2026-11-17', [], true, 'BaznaCijena', 'bazna_cijena' );
		}
		return $types;
	}

	private function item( string $name = 'Proizvod', bool $sale = false ): Item {
		return new Item( $name, 'SKU-1', 'Marka', 'kg', 25.98, 12.99, $sale, $sale ? 'Sniženje' : '', [ 'anchor' => [ 'amount' => 14.99, 'date' => '2026-09-10' ] ], $sale ? 11.5 : null, '385000123', true, 'https://example.hr/p/1' );
	}

	/**
	 * @param iterable<Item|ServiceItem> $items
	 * @param ReferencePriceType[]|null $types
	 * @return array{0:\SidrenaCijena\PriceList\WriteStats,1:string}
	 */
	private function write( iterable $items, ?CsvWriter $writer = null, ?array $types = null ): array {
		$writer = $writer ?? new CsvWriter();
		self::assertInstanceOf( Writer::class, $writer );
		$outlet = new Outlet( 'webshop', 'Ulica 1', 'WEB1', '1', 'Trgovina', 'https://example.hr/' );
		$stats  = $writer->write( $items, $outlet, new DateTimeImmutable( '2026-10-01 06:00:12', new DateTimeZone( 'Europe/Zagreb' ) ), $this->path, $types ?? $this->types() );
		return [ $stats, (string) file_get_contents( $this->path ) ];
	}

	public function test_header_order_bom_and_product_row(): void {
		[ $stats, $raw ] = $this->write( [ $this->item() ] );
		self::assertStringStartsWith( self::BOM, $raw );
		$lines = explode( "\n", rtrim( substr( $raw, 3 ), "\n" ) );
		self::assertSame( 'vrsta;naziv;šifra;marka;jedinica_mjere;cijena_za_jedinicu_mjere;maloprodajna_cijena;posebni_oblik_prodaje;naziv_posebnog_oblika_prodaje;sidrena_cijena;sidrena_cijena_datum;najniža_cijena_30_dana;barkod;dostupnost;url', $lines[0] );
		self::assertSame( 'proizvod;Proizvod;SKU-1;Marka;kg;25.98;12.99;ne;;14.99;2026-09-10;;385000123;dostupno;https://example.hr/p/1', $lines[1] );
		self::assertCount( 2, $lines );
		self::assertStringNotContainsString( "\r", $raw );
		self::assertSame( 1, $stats->products );
		self::assertSame( 0, $stats->services );
	}

	public function test_sale_row_fills_lowest30_and_sale_name(): void {
		[ , $raw ] = $this->write( [ $this->item( 'X', true ) ] );
		$lines     = explode( "\n", trim( substr( $raw, 3 ) ) );
		self::assertSame( 'proizvod;X;SKU-1;Marka;kg;25.98;12.99;da;Sniženje;14.99;2026-09-10;11.50;385000123;dostupno;https://example.hr/p/1', $lines[1] );
	}

	public function test_service_row_shape(): void {
		[ $stats, $raw ] = $this->write( [ new ServiceItem( 'Montaža', 40.0, true, 'Akcija', [ 'anchor' => [ 'amount' => null, 'date' => '2026-09-10' ] ] ) ] );
		$lines           = explode( "\n", trim( substr( $raw, 3 ) ) );
		self::assertSame( 'usluga;Montaža;;;;;40.00;da;Akcija;;2026-09-10;;;;', $lines[1] );
		self::assertSame( 0, $stats->products );
		self::assertSame( 1, $stats->services );
	}

	public function test_bom_can_be_disabled_and_delimiter_changed(): void {
		[ , $raw ] = $this->write( [ $this->item() ], new CsvWriter( ',', false ) );
		self::assertStringStartsNotWith( self::BOM, $raw );
		self::assertStringStartsWith( 'vrsta,naziv,šifra,', $raw );
		self::assertStringContainsString( "\nproizvod,Proizvod,SKU-1,", $raw );
	}

	public function test_quotes_fields_with_delimiter_quote_and_newline(): void {
		[ , $raw ] = $this->write( [ $this->item( "Kabel; 2m \"crni\"\ndrugi red" ) ] );
		self::assertStringContainsString( "proizvod;\"Kabel; 2m \"\"crni\"\"\ndrugi red\";SKU-1;", $raw );
		$fh = fopen( $this->path, 'r' );
		fseek( $fh, 3 );
		fgetcsv( $fh, 0, ';', '"', '' );
		$row = fgetcsv( $fh, 0, ';', '"', '' );
		fclose( $fh );
		self::assertSame( "Kabel; 2m \"crni\"\ndrugi red", $row[1] );
	}

	public function test_second_type_adds_two_columns_after_first_type(): void {
		$refs = [ 'anchor' => [ 'amount' => 14.99, 'date' => '2026-09-10' ], 'base' => [ 'amount' => 13.0, 'date' => null ] ];
		$item = new Item( 'P', '', '', '', null, 1.0, false, '', $refs, null, '', false, '' );
		[ , $raw ] = $this->write( [ $item ], null, $this->types( true ) );
		$lines     = explode( "\n", trim( substr( $raw, 3 ) ) );
		self::assertStringContainsString( ';sidrena_cijena;sidrena_cijena_datum;bazna_cijena;bazna_cijena_datum;najniža_cijena_30_dana;', $lines[0] );
		self::assertSame( 'proizvod;P;;;;;1.00;ne;;14.99;2026-09-10;13.00;;;;nedostupno;', $lines[1] );
	}

	public function test_header_and_row_filters(): void {
		Filters\expectApplied( 'scwc_price_list_csv_header' )->once()->andReturnUsing( fn( array $h ) => array_merge( $h, [ 'extra' ] ) );
		Filters\expectApplied( 'scwc_price_list_csv_row' )->once()->with( \Mockery::type( 'array' ), \Mockery::type( Item::class ) )->andReturnUsing( fn( array $r ) => array_merge( $r, [ 'X' ] ) );
		[ , $raw ] = $this->write( [ $this->item() ] );
		$lines     = explode( "\n", trim( substr( $raw, 3 ) ) );
		self::assertStringEndsWith( ';url;extra', $lines[0] );
		self::assertStringEndsWith( ';https://example.hr/p/1;X', $lines[1] );
	}
}
