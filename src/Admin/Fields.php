<?php
/**
 * Declarative definitions of every setting shown on the settings page, grouped by tab.
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

namespace SidrenaCijena\Admin;

use SidrenaCijena\Settings\Defaults;
use SidrenaCijena\Settings\Sanitizer;
use SidrenaCijena\Settings\Settings;

/**
 * @phpstan-type FieldDef array{path:string,type:string,label:string,description?:string,options?:array<string,string>,min?:int,placeholder?:string,heading?:string}
 */
final class Fields {

	public const TABS = [
		'outlet'     => 'Prodajni objekt',
		'reference'  => 'Referentne cijene',
		'display'    => 'Prikaz',
		'price_list' => 'Cjenik',
		'history'    => 'Povijest cijena',
		'advanced'   => 'Napredno',
	];

	private const TAB_SECTIONS = [
		'outlet'     => 'outlet',
		'reference'  => 'reference_prices',
		'display'    => 'display',
		'price_list' => 'price_list',
		'history'    => 'history',
		'advanced'   => 'advanced',
	];

	public static function sectionForTab( string $tab ): string {
		return self::TAB_SECTIONS[ $tab ] ?? '';
	}

	/** Translated tab label. */
	public static function tabLabel( string $tab ): string {
		$td = 'sidrena-cijena-za-woocommerce';
		return match ( $tab ) {
			'outlet'     => __( 'Prodajni objekt', $td ),
			'reference'  => __( 'Referentne cijene', $td ),
			'display'    => __( 'Prikaz', $td ),
			'price_list' => __( 'Cjenik', $td ),
			'history'    => __( 'Povijest cijena', $td ),
			'advanced'   => __( 'Napredno', $td ),
			default      => $tab,
		};
	}

	/** "a.b.c" → "scwc_settings[a][b][c]". */
	public static function inputName( string $path ): string {
		return Settings::OPTION . '[' . implode( '][', explode( '.', $path ) ) . ']';
	}

	/** "a.b.c" → "scwc_settings_a_b_c". */
	public static function inputId( string $path ): string {
		return Settings::OPTION . '_' . str_replace( '.', '_', $path );
	}

	/**
	 * @return array<int,FieldDef>
	 */
	public static function forTab( string $tab ): array {
		$section = self::sectionForTab( $tab );
		if ( '' === $section ) {
			return [];
		}
		return array_values( array_filter( self::all(), static fn( array $d ) => str_starts_with( $d['path'], $section . '.' ) ) );
	}

	/**
	 * @return array<int,FieldDef>
	 */
	public static function all(): array {
		$td = 'sidrena-cijena-za-woocommerce';
		return array_merge(
			[
				[ 'path' => 'outlet.form', 'type' => 'text', 'label' => __( 'Oblik prodajnog objekta', $td ), 'description' => __( 'Npr. webshop, prodavaonica, kiosk. Prvi dio naziva datoteke cjenika.', $td ), 'placeholder' => 'webshop' ],
				[ 'path' => 'outlet.address', 'type' => 'text', 'label' => __( 'Adresa prodajnog objekta', $td ), 'description' => __( 'Za web trgovinu: adresa sjedišta trgovca. Obvezan podatak u cjeniku.', $td ), 'placeholder' => 'Ilica 1, Zagreb' ],
				[ 'path' => 'outlet.label', 'type' => 'text', 'label' => __( 'Oznaka prodajnog objekta', $td ), 'description' => __( 'Interna oznaka objekta (npr. naziv web trgovine). Obvezan dio naziva datoteke.', $td ) ],
				[ 'path' => 'outlet.storage_number', 'type' => 'text', 'label' => __( 'Broj skladišta', $td ), 'description' => __( 'Zadano 1 ako nemate više skladišta.', $td ) ],
				[ 'path' => 'outlet.merchant_name', 'type' => 'text', 'label' => __( 'Naziv trgovca', $td ), 'description' => __( 'Prazno = naziv web stranice.', $td ) ],
			],
			self::referenceType( 'anchor', __( 'Sidrena (dodatna) cijena', $td ), true ),
			self::referenceType( 'base', __( 'Bazna cijena (od 17. 11. 2026.)', $td ), false ),
			[
				[ 'path' => 'reference_prices.variation_inherit_parent', 'type' => 'checkbox', 'label' => __( 'Varijacije nasljeđuju referentnu cijenu roditelja', $td ), 'description' => __( 'Kada varijacija nema vlastitu referentnu cijenu, koristi se cijena varijabilnog proizvoda.', $td ), 'heading' => __( 'Ostalo', $td ) ],
				[ 'path' => 'reference_prices.auto_na_after_date', 'type' => 'checkbox', 'label' => __( 'Automatski "nema referentne cijene" za nove proizvode', $td ), 'description' => __( 'Proizvodi kreirani nakon referentnog datuma nemaju sidrenu cijenu pa se oznaka ne prikazuje.', $td ) ],

				[ 'path' => 'display.position', 'type' => 'select', 'label' => __( 'Položaj oznake', $td ), 'options' => [ 'after' => __( 'Iza cijene (isti redak)', $td ), 'below' => __( 'Ispod cijene', $td ), 'before' => __( 'Ispred cijene', $td ) ] ],
				[ 'path' => 'display.format', 'type' => 'textarea', 'label' => __( 'Format oznake', $td ), 'description' => __( 'Dostupne oznake: {label}, {date}, {price}, {key}. Sidrena cijena isključuje akcijske cijene.', $td ) ],
				[ 'path' => 'display.format_compact', 'type' => 'textarea', 'label' => __( 'Sažeti format (košarica, blagajna)', $td ), 'description' => __( 'Iste oznake kao gore; koristi se gdje je malo prostora.', $td ) ],
				[ 'path' => 'display.date_format', 'type' => 'text', 'label' => __( 'Format datuma', $td ), 'description' => __( 'PHP format datuma; zadano "j. n. Y." daje 10. 9. 2026.', $td ), 'placeholder' => 'j. n. Y.' ],
				[ 'path' => 'display.loop', 'type' => 'checkbox', 'label' => __( 'Prikaži u popisu proizvoda', $td ), 'description' => __( 'Zakon traži oznaku svugdje gdje se prikazuje cijena.', $td ) ],
				[ 'path' => 'display.single', 'type' => 'checkbox', 'label' => __( 'Prikaži na stranici proizvoda', $td ) ],
				[ 'path' => 'display.cart', 'type' => 'checkbox', 'label' => __( 'Prikaži u košarici', $td ) ],
				[ 'path' => 'display.checkout', 'type' => 'select', 'label' => __( 'Prikaz na blagajni', $td ), 'options' => [ 'unit' => __( 'Jedinična cijena', $td ), 'total' => __( 'Ukupno za količinu', $td ), 'none' => __( 'Ne prikazuj', $td ) ] ],
				[ 'path' => 'display.mini_cart', 'type' => 'checkbox', 'label' => __( 'Prikaži u mini košarici', $td ) ],
				[ 'path' => 'display.item_data', 'type' => 'checkbox', 'label' => __( 'Dodaj redak u podatke stavke (blok košarica/blagajna)', $td ), 'description' => __( 'Blok košarica ne renderira oznaku; ovako se podatak ipak prikazuje kao redak stavke.', $td ) ],
				[ 'path' => 'display.variable_loop', 'type' => 'select', 'label' => __( 'Varijabilni proizvodi u popisu', $td ), 'options' => [ 'range' => __( 'Raspon (min – max)', $td ), 'none' => __( 'Ne prikazuj', $td ) ] ],
				[ 'path' => 'display.omnibus', 'type' => 'checkbox', 'label' => __( 'Prikaži najnižu cijenu u 30 dana prije sniženja', $td ), 'description' => __( 'Obveza prema čl. 19 Zakona o zaštiti potrošača za svako sniženje.', $td ) ],
				[ 'path' => 'display.omnibus_label', 'type' => 'text', 'label' => __( 'Naziv najniže cijene', $td ) ],
				[ 'path' => 'display.omnibus_replace_del', 'type' => 'checkbox', 'label' => __( 'Zamijeni precrtanu cijenu najnižom u 30 dana', $td ), 'description' => __( 'Kada je najniža cijena niža od redovne, precrtava se ona (postotak se računa od nje).', $td ) ],
				[ 'path' => 'display.show_percent', 'type' => 'checkbox', 'label' => __( 'Prikaži postotak sniženja', $td ) ],
				[ 'path' => 'display.load_css', 'type' => 'checkbox', 'label' => __( 'Učitaj CSS dodatka', $td ), 'description' => __( 'Isključite ako stilove definirate u temi.', $td ) ],

				[ 'path' => 'price_list.enabled', 'type' => 'checkbox', 'label' => __( 'Objavi strojno čitljiv cjenik', $td ), 'description' => __( 'Obveza za sve trgovce s web stranicom od 1. 10. 2026.', $td ) ],
				[ 'path' => 'price_list.formats', 'type' => 'multicheck', 'label' => __( 'Formati', $td ), 'options' => [ 'xml' => 'XML', 'csv' => 'CSV' ] ],
				[ 'path' => 'price_list.slug', 'type' => 'text', 'label' => __( 'Putanja (slug)', $td ), 'description' => __( 'Javna adresa cjenika: /{slug}/latest.xml', $td ), 'placeholder' => 'cjenik' ],
				[ 'path' => 'price_list.generate_time', 'type' => 'time', 'label' => __( 'Vrijeme dnevnog generiranja', $td ), 'description' => __( 'Cjenik robe mora biti objavljen svaki dan prije 08:00.', $td ) ],
				[ 'path' => 'price_list.regenerate_on_change', 'type' => 'select', 'label' => __( 'Regeneriraj pri promjeni cijene', $td ), 'description' => __( 'Cjenik usluga mora se objaviti pri svakoj promjeni cijene.', $td ), 'options' => [ 'services' => __( 'Samo usluge', $td ), 'all' => __( 'Svi proizvodi', $td ), 'never' => __( 'Nikad (samo dnevno)', $td ) ] ],
				[ 'path' => 'price_list.debounce_seconds', 'type' => 'number', 'label' => __( 'Odgoda regeneriranja (s)', $td ), 'description' => __( 'Više promjena u ovom razdoblju spaja se u jedno generiranje.', $td ), 'min' => 0 ],
				[ 'path' => 'price_list.retention_days', 'type' => 'number', 'label' => __( 'Čuvanje starih cjenika (dana)', $td ), 'description' => __( 'Zakonski minimum 30 dana.', $td ), 'min' => Sanitizer::MIN_RETENTION_DAYS ],
				[ 'path' => 'price_list.include_out_of_stock', 'type' => 'checkbox', 'label' => __( 'Uključi proizvode kojih nema na zalihi', $td ) ],
				[ 'path' => 'price_list.include_hidden', 'type' => 'checkbox', 'label' => __( 'Uključi skrivene proizvode', $td ) ],
				[ 'path' => 'price_list.service_rule', 'type' => 'select', 'label' => __( 'Što je usluga', $td ), 'options' => [ 'virtual_or_flag' => __( 'Virtualni proizvod ili oznaka "Ovo je usluga"', $td ), 'virtual' => __( 'Samo virtualni proizvodi', $td ), 'flag' => __( 'Samo oznaka "Ovo je usluga"', $td ), 'all' => __( 'Svi proizvodi su usluge', $td ), 'none' => __( 'Nema usluga', $td ) ] ],
				[ 'path' => 'price_list.sale_name_default', 'type' => 'text', 'label' => __( 'Zadani naziv posebnog oblika prodaje', $td ), 'placeholder' => 'Sniženje' ],
				[ 'path' => 'price_list.csv_delimiter', 'type' => 'select', 'label' => __( 'CSV razdjelnik', $td ), 'options' => [ ';' => __( 'Točka-zarez (;)', $td ), ',' => __( 'Zarez (,)', $td ), "\t" => __( 'Tabulator', $td ) ] ],
				[ 'path' => 'price_list.csv_bom', 'type' => 'checkbox', 'label' => __( 'CSV s UTF-8 BOM (za Excel)', $td ) ],
				[ 'path' => 'price_list.tax_mode', 'type' => 'select', 'label' => __( 'Cijene u cjeniku', $td ), 'options' => [ 'incl' => __( 'S PDV-om (maloprodajne)', $td ), 'excl' => __( 'Bez PDV-a', $td ) ] ],
				[ 'path' => 'price_list.external_cron_key', 'type' => 'text', 'label' => __( 'Ključ za vanjski cron', $td ), 'description' => __( 'Za pokretanje generiranja izvana (?scwc_cron=KLJUČ). Prazno = generira se automatski.', $td ) ],

				[ 'path' => 'history.enabled', 'type' => 'checkbox', 'label' => __( 'Bilježi povijest cijena', $td ), 'description' => __( 'Potrebno za izračun najniže cijene u 30 dana prije sniženja.', $td ) ],
				[ 'path' => 'history.retention_days', 'type' => 'number', 'label' => __( 'Čuvanje povijesti (dana)', $td ), 'description' => __( 'Najmanje 31 dan; preporučeno 400 radi naknadnih provjera.', $td ), 'min' => 31 ],
				[ 'path' => 'history.sweep_time', 'type' => 'time', 'label' => __( 'Vrijeme dnevne provjere cijena', $td ), 'description' => __( 'Hvata promjene cijena izvan WooCommerce sučelja (ERP, SQL).', $td ) ],
				[ 'path' => 'history.omnibus_fallback', 'type' => 'select', 'label' => __( 'Kad nema dovoljno povijesti', $td ), 'description' => __( 'Za sniženja bez 30 dana zabilježene povijesti.', $td ), 'options' => [ 'regular' => __( 'Koristi redovnu cijenu', $td ), 'hide' => __( 'Ne prikazuj najnižu cijenu', $td ) ] ],

				[ 'path' => 'advanced.remove_data_on_uninstall', 'type' => 'checkbox', 'label' => __( 'Obriši sve podatke pri deinstalaciji', $td ), 'description' => __( 'Postavke, meta podatke proizvoda, povijest cijena i generirane cjenike.', $td ) ],
				[ 'path' => 'advanced.debug_log', 'type' => 'checkbox', 'label' => __( 'Zapisuj dijagnostiku u WooCommerce log', $td ) ],
			]
		);
	}

	/**
	 * @return array<int,FieldDef>
	 */
	private static function referenceType( string $key, string $heading, bool $always ): array {
		$td   = 'sidrena-cijena-za-woocommerce';
		$p    = "reference_prices.{$key}.";
		$defs = [];
		if ( $always ) {
			$defs[] = [ 'path' => $p . 'enabled', 'type' => 'hidden', 'label' => __( 'Omogućeno', $td ), 'heading' => $heading ];
		} else {
			$defs[] = [ 'path' => $p . 'enabled', 'type' => 'checkbox', 'label' => __( 'Omogući', $td ), 'description' => __( 'Uključite kada pravilnik odredi referentni dan za baznu cijenu.', $td ), 'heading' => $heading ];
		}
		$dateDescription = 'anchor' === $key
			? sprintf(
				/* translators: 1: default anchor date, 2: FMCG date */
				__( 'Zadano %1$s (NN 101/2026). Datum za FMCG kategorije koje ste već označavali: %2$s – dodajte ih kao iznimku ispod.', $td ),
				'10. 9. 2026.',
				'2. 5. 2025.'
			)
			: __( 'Referentni dan određuje pravilnik; ostavite prazno dok nije objavljen.', $td );
		return array_merge( $defs, [
			[ 'path' => $p . 'label', 'type' => 'text', 'label' => __( 'Naziv na stranici', $td ), 'description' => __( 'Npr. "Cijena na dan" – prikazuje se uz datum i iznos.', $td ) ],
			[ 'path' => $p . 'date', 'type' => 'date', 'label' => __( 'Referentni datum', $td ), 'description' => $dateDescription ],
			[ 'path' => $p . 'category_overrides', 'type' => 'category_overrides', 'label' => __( 'Iznimke po kategorijama', $td ), 'description' => __( 'Kategorije (uključujući podkategorije) s drugim referentnim datumom.', $td ) ],
			[ 'path' => $p . 'auto_snapshot_at', 'type' => 'datetime-local', 'label' => __( 'Automatski snapshot cijena', $td ), 'description' => __( 'U zadano vrijeme redovne cijene svih proizvoda kopiraju se kao referentne (zakazani zadatak). Prazno = bez automatskog snapshota.', $td ) ],
			[ 'path' => $p . 'auto_snapshot_done', 'type' => 'readonly', 'label' => __( 'Snapshot obavljen', $td ) ],
		] );
	}
}
