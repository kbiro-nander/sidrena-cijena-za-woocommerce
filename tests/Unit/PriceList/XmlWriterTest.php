<?php
declare(strict_types=1);

namespace SidrenaCijena\Tests\Unit\PriceList;

use DateTimeImmutable;
use DateTimeZone;
use SidrenaCijena\PriceList\Item;
use SidrenaCijena\PriceList\Outlet;
use SidrenaCijena\PriceList\ServiceItem;
use SidrenaCijena\PriceList\Writer;
use SidrenaCijena\PriceList\WriteStats;
use SidrenaCijena\PriceList\XmlWriter;
use SidrenaCijena\Reference\ReferencePriceType;
use SidrenaCijena\Tests\TestCase;

final class XmlWriterTest extends TestCase {
	private string $path;

	protected function setUp(): void {
		parent::setUp();
		$this->path = sys_get_temp_dir() . '/scwc-xml-' . uniqid() . '.xml';
	}

	protected function tearDown(): void {
		@unlink( $this->path );
		parent::tearDown();
	}

	private function outlet(): Outlet {
		return new Outlet( 'webshop', 'Ulica 1, 10000 Zagreb', 'WEB1', '1', 'Trgovina "A" & B', 'https://example.hr/' );
	}

	private function when(): DateTimeImmutable {
		return new DateTimeImmutable( '2026-10-01 06:00:12', new DateTimeZone( 'Europe/Zagreb' ) );
	}

	/** @return ReferencePriceType[] */
	private function types( bool $withBase = false ): array {
		$types = [ new ReferencePriceType( 'anchor', 'Cijena na dan', '2026-09-10', [], true, 'SidrenaCijena', 'sidrena_cijena' ) ];
		if ( $withBase ) {
			$types[] = new ReferencePriceType( 'base', 'Bazna', '2026-11-17', [], true, 'BaznaCijena', 'bazna_cijena' );
		}
		return $types;
	}

	/**
	 * @param array<string,mixed> $o
	 */
	private function item( array $o = [] ): Item {
		$d = array_merge(
			[
				'name'          => 'Proizvod',
				'sku'           => 'SKU-1',
				'brand'         => 'Marka',
				'unit'          => 'kom',
				'unitPrice'     => 12.99,
				'price'         => 12.99,
				'isSpecialSale' => false,
				'saleName'      => '',
				'references'    => [ 'anchor' => [ 'amount' => 14.99, 'date' => '2026-09-10' ] ],
				'lowest30'      => null,
				'barcode'       => '385000123',
				'available'     => true,
				'url'           => 'https://example.hr/p/1',
			],
			$o
		);
		return new Item( $d['name'], $d['sku'], $d['brand'], $d['unit'], $d['unitPrice'], $d['price'], $d['isSpecialSale'], $d['saleName'], $d['references'], $d['lowest30'], $d['barcode'], $d['available'], $d['url'] );
	}

	/**
	 * @param iterable<Item|ServiceItem> $items
	 * @param ReferencePriceType[]      $types
	 */
	private function write( iterable $items, ?array $types = null ): \SimpleXMLElement {
		$writer = new XmlWriter();
		self::assertInstanceOf( Writer::class, $writer );
		$this->stats = $writer->write( $items, $this->outlet(), $this->when(), $this->path, $types ?? $this->types() );
		$xml         = simplexml_load_file( $this->path );
		self::assertNotFalse( $xml );
		return $xml;
	}

	private WriteStats $stats;

	public function test_document_structure_and_header(): void {
		$xml = $this->write( [ $this->item(), new ServiceItem( 'Montaža', 40.0, false, '', [ 'anchor' => [ 'amount' => 50.0, 'date' => '2026-09-10' ] ] ) ] );
		self::assertSame( 'Cjenik', $xml->getName() );
		self::assertSame( '1.0', (string) $xml['verzija'] );
		self::assertSame( '2026-10-01T06:00:12+02:00', (string) $xml['generirano'] );
		self::assertSame( 'sidrena-cijena-za-woocommerce/' . SCWC_VERSION, (string) $xml['izvor'] );
		self::assertSame( 'webshop', (string) $xml->ProdajniObjekt->Oblik );
		self::assertSame( 'Ulica 1, 10000 Zagreb', (string) $xml->ProdajniObjekt->Adresa );
		self::assertSame( 'WEB1', (string) $xml->ProdajniObjekt->Oznaka );
		self::assertSame( '1', (string) $xml->ProdajniObjekt->BrojPohrane );
		self::assertSame( 'Trgovina "A" & B', (string) $xml->ProdajniObjekt->Naziv );
		self::assertSame( 'https://example.hr/', (string) $xml->ProdajniObjekt->Url );
		self::assertCount( 1, $xml->Proizvodi->Proizvod );
		self::assertCount( 1, $xml->Usluge->Usluga );
		self::assertSame( 1, $this->stats->products );
		self::assertSame( 1, $this->stats->services );

		$head = file_get_contents( $this->path );
		self::assertStringStartsWith( '<?xml version="1.0" encoding="UTF-8"?>', $head );
		self::assertStringContainsString( "\n  <ProdajniObjekt>", $head );
	}

	public function test_product_fields_in_order(): void {
		$xml = $this->write( [ $this->item() ] );
		$p   = $xml->Proizvodi->Proizvod[0];
		$names = [];
		foreach ( $p->children() as $child ) {
			$names[] = $child->getName();
		}
		self::assertSame(
			[ 'Naziv', 'Sifra', 'Marka', 'JedinicaMjere', 'CijenaZaJedinicuMjere', 'MaloprodajnaCijena', 'PosebniOblikProdaje', 'NazivPosebnogOblikaProdaje', 'SidrenaCijena', 'Barkod', 'Dostupnost', 'Url' ],
			$names
		);
		self::assertSame( 'Proizvod', (string) $p->Naziv );
		self::assertSame( 'SKU-1', (string) $p->Sifra );
		self::assertSame( 'Marka', (string) $p->Marka );
		self::assertSame( 'kom', (string) $p->JedinicaMjere );
		self::assertSame( '12.99', (string) $p->CijenaZaJedinicuMjere );
		self::assertSame( '12.99', (string) $p->MaloprodajnaCijena );
		self::assertSame( 'ne', (string) $p->PosebniOblikProdaje );
		self::assertSame( '', (string) $p->NazivPosebnogOblikaProdaje );
		self::assertSame( '14.99', (string) $p->SidrenaCijena );
		self::assertSame( '2026-09-10', (string) $p->SidrenaCijena['datum'] );
		self::assertSame( '385000123', (string) $p->Barkod );
		self::assertSame( 'dostupno', (string) $p->Dostupnost );
		self::assertSame( 'https://example.hr/p/1', (string) $p->Url );
	}

	public function test_sale_lowest30_and_unavailable(): void {
		$xml = $this->write( [ $this->item( [ 'isSpecialSale' => true, 'saleName' => 'Sniženje', 'price' => 9.9, 'lowest30' => 11.5, 'available' => false, 'unitPrice' => null ] ) ] );
		$p   = $xml->Proizvodi->Proizvod[0];
		self::assertSame( 'da', (string) $p->PosebniOblikProdaje );
		self::assertSame( 'Sniženje', (string) $p->NazivPosebnogOblikaProdaje );
		self::assertSame( '9.90', (string) $p->MaloprodajnaCijena );
		self::assertSame( '11.50', (string) $p->NajnizaCijena30Dana );
		self::assertSame( 'nedostupno', (string) $p->Dostupnost );
		self::assertSame( '', (string) $p->CijenaZaJedinicuMjere );
		self::assertCount( 1, $p->NajnizaCijena30Dana );
	}

	public function test_missing_reference_is_empty_element_without_datum_when_unknown(): void {
		$xml = $this->write( [ $this->item( [ 'references' => [ 'anchor' => [ 'amount' => null, 'date' => null ] ] ] ) ] );
		$p   = $xml->Proizvodi->Proizvod[0];
		self::assertCount( 1, $p->SidrenaCijena );
		self::assertSame( '', (string) $p->SidrenaCijena );
		self::assertNull( $p->SidrenaCijena['datum'] );
		self::assertCount( 0, $p->NajnizaCijena30Dana );
		self::assertStringContainsString( '<SidrenaCijena/>', (string) file_get_contents( $this->path ) );
	}

	public function test_missing_amount_keeps_known_date(): void {
		$xml = $this->write( [ $this->item( [ 'references' => [ 'anchor' => [ 'amount' => null, 'date' => '2026-09-10' ] ] ] ) ] );
		self::assertSame( '2026-09-10', (string) $xml->Proizvodi->Proizvod[0]->SidrenaCijena['datum'] );
		self::assertSame( '', (string) $xml->Proizvodi->Proizvod[0]->SidrenaCijena );
	}

	public function test_second_reference_type_element(): void {
		$refs = [ 'anchor' => [ 'amount' => 14.99, 'date' => '2026-09-10' ], 'base' => [ 'amount' => 13.0, 'date' => '2026-11-17' ] ];
		$xml  = $this->write( [ $this->item( [ 'references' => $refs ] ), new ServiceItem( 'U', 1.0, false, '', $refs ) ], $this->types( true ) );
		$p    = $xml->Proizvodi->Proizvod[0];
		self::assertSame( '13.00', (string) $p->BaznaCijena );
		self::assertSame( '2026-11-17', (string) $p->BaznaCijena['datum'] );
		self::assertSame( '13.00', (string) $xml->Usluge->Usluga[0]->BaznaCijena );
		$raw = (string) file_get_contents( $this->path );
		self::assertLessThan( strpos( $raw, '<BaznaCijena' ), strpos( $raw, '<SidrenaCijena' ) );
	}

	public function test_escapes_special_characters(): void {
		$xml = $this->write( [ $this->item( [ 'name' => 'A & B <"C">', 'brand' => "O'Neil" ] ) ] );
		self::assertSame( 'A & B <"C">', (string) $xml->Proizvodi->Proizvod[0]->Naziv );
		self::assertSame( "O'Neil", (string) $xml->Proizvodi->Proizvod[0]->Marka );
		self::assertStringContainsString( 'A &amp; B &lt;', (string) file_get_contents( $this->path ) );
	}

	public function test_service_fields(): void {
		$xml = $this->write( [ new ServiceItem( 'Montaža', 40.0, true, 'Akcija', [ 'anchor' => [ 'amount' => 50.0, 'date' => '2026-09-10' ] ] ) ] );
		$u   = $xml->Usluge->Usluga[0];
		$names = [];
		foreach ( $u->children() as $child ) {
			$names[] = $child->getName();
		}
		self::assertSame( [ 'NazivUsluge', 'MaloprodajnaCijena', 'PosebniOblikProdaje', 'NazivPosebnogOblikaProdaje', 'SidrenaCijena' ], $names );
		self::assertSame( 'Montaža', (string) $u->NazivUsluge );
		self::assertSame( '40.00', (string) $u->MaloprodajnaCijena );
		self::assertSame( 'da', (string) $u->PosebniOblikProdaje );
		self::assertSame( 'Akcija', (string) $u->NazivPosebnogOblikaProdaje );
		self::assertSame( '50.00', (string) $u->SidrenaCijena );
		self::assertSame( 0, $this->stats->products );
		self::assertSame( 1, $this->stats->services );
	}

	public function test_empty_list_still_yields_valid_document(): void {
		$xml = $this->write( [] );
		self::assertCount( 0, $xml->Proizvodi->children() );
		self::assertCount( 0, $xml->Usluge->children() );
		self::assertSame( 0, $this->stats->products + $this->stats->services );
	}
}
