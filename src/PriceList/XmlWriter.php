<?php
/**
 * Streams the price list as XML (ext/xmlwriter).
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

namespace SidrenaCijena\PriceList;

use DateTimeImmutable;
use RuntimeException;
use SidrenaCijena\Reference\ReferencePriceType;
use SidrenaCijena\Support\Money;

class XmlWriter implements Writer {

	public const VERSION = '1.0';

	/**
	 * {@inheritDoc}
	 */
	public function write( iterable $items, Outlet $outlet, DateTimeImmutable $generatedAtLocal, string $path, array $types ): WriteStats {
		$w = new \XMLWriter();
		if ( ! $w->openUri( $path ) ) {
			throw new RuntimeException( esc_html( sprintf( /* translators: %s: file path */ __( 'Nije moguće pisati u datoteku %s.', 'sidrena-cijena-za-woocommerce' ), $path ) ) );
		}
		$w->setIndent( true );
		$w->setIndentString( '  ' );
		$w->startDocument( '1.0', 'UTF-8' );

		$w->startElement( 'Cjenik' );
		$w->writeAttribute( 'verzija', self::VERSION );
		$w->writeAttribute( 'generirano', $generatedAtLocal->format( DATE_ATOM ) );
		$w->writeAttribute( 'izvor', 'sidrena-cijena-za-woocommerce/' . SCWC_VERSION );

		$w->startElement( 'ProdajniObjekt' );
		$w->writeElement( 'Oblik', $outlet->form );
		$w->writeElement( 'Adresa', $outlet->address );
		$w->writeElement( 'Oznaka', $outlet->label );
		$w->writeElement( 'BrojPohrane', $outlet->storageNumber );
		$w->writeElement( 'Naziv', $outlet->merchantName );
		$w->writeElement( 'Url', $outlet->url );
		$w->endElement();

		// Products and services land in separate sections, so services are buffered until products are done.
		$products = 0;
		$services = [];
		$w->startElement( 'Proizvodi' );
		foreach ( $items as $item ) {
			if ( $item instanceof ServiceItem ) {
				$services[] = $item;
				continue;
			}
			if ( ! $item instanceof Item ) {
				continue;
			}
			$this->product( $w, $item, $types );
			++$products;
			$w->flush();
		}
		$w->endElement();

		$w->startElement( 'Usluge' );
		foreach ( $services as $service ) {
			$this->service( $w, $service, $types );
		}
		$w->endElement();

		$w->endElement();
		$w->endDocument();
		$w->flush();

		return new WriteStats( $products, count( $services ) );
	}

	/**
	 * @param ReferencePriceType[] $types Types in order.
	 */
	private function product( \XMLWriter $w, Item $item, array $types ): void {
		$w->startElement( 'Proizvod' );
		$w->writeElement( 'Naziv', $item->name );
		$w->writeElement( 'Sifra', $item->sku );
		$w->writeElement( 'Marka', $item->brand );
		$w->writeElement( 'JedinicaMjere', $item->unit );
		$w->writeElement( 'CijenaZaJedinicuMjere', $this->money( $item->unitPrice ) );
		$w->writeElement( 'MaloprodajnaCijena', $this->money( $item->price ) );
		$w->writeElement( 'PosebniOblikProdaje', $item->isSpecialSale ? 'da' : 'ne' );
		$w->writeElement( 'NazivPosebnogOblikaProdaje', $item->saleName );
		$this->references( $w, $item->references, $types );
		if ( null !== $item->lowest30 ) {
			$w->writeElement( 'NajnizaCijena30Dana', $this->money( $item->lowest30 ) );
		}
		$w->writeElement( 'Barkod', $item->barcode );
		$w->writeElement( 'Dostupnost', $item->available ? 'dostupno' : 'nedostupno' );
		$w->writeElement( 'Url', $item->url );
		$w->endElement();
	}

	/**
	 * @param ReferencePriceType[] $types Types in order.
	 */
	private function service( \XMLWriter $w, ServiceItem $item, array $types ): void {
		$w->startElement( 'Usluga' );
		$w->writeElement( 'NazivUsluge', $item->name );
		$w->writeElement( 'MaloprodajnaCijena', $this->money( $item->price ) );
		$w->writeElement( 'PosebniOblikProdaje', $item->isSpecialSale ? 'da' : 'ne' );
		$w->writeElement( 'NazivPosebnogOblikaProdaje', $item->saleName );
		$this->references( $w, $item->references, $types );
		$w->endElement();
	}

	/**
	 * @param array<string,array{amount:?float,date:?string}> $references Row references.
	 * @param ReferencePriceType[]                             $types      Types in order.
	 */
	private function references( \XMLWriter $w, array $references, array $types ): void {
		foreach ( $types as $type ) {
			$ref = $references[ $type->key ] ?? [
				'amount' => null,
				'date'   => null,
			];
			$w->startElement( $type->xmlElement );
			if ( ! empty( $ref['date'] ) ) {
				$w->writeAttribute( 'datum', (string) $ref['date'] );
			}
			if ( null !== $ref['amount'] ) {
				$w->text( $this->money( $ref['amount'] ) );
			}
			$w->endElement();
		}
	}

	private function money( ?float $amount ): string {
		return null === $amount ? '' : Money::toDecimal( $amount );
	}
}
