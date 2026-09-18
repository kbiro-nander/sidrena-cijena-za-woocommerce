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
		'outlet'     => 'Prodajni objekti',
		'reference'  => 'Referentne cijene',
		'display'    => 'Prikaz',
		'price_list' => 'Cjenik',
		'history'    => 'Povijest cijena',
		'advanced'   => 'Napredno',
	];

	private const TAB_SECTIONS = [
		'outlet'     => 'outlet|outlets',
		'reference'  => 'reference_prices',
		'display'    => 'display',
		'price_list' => 'price_list',
		'history'    => 'history',
		'advanced'   => 'advanced',
	];

	/** First settings section of a tab (legacy helper). */
	public static function sectionForTab( string $tab ): string {
		return self::sectionsForTab( $tab )[0] ?? '';
	}

	/**
	 * All settings sections shown on a tab.
	 *
	 * @return string[]
	 */
	public static function sectionsForTab( string $tab ): array {
		$sections = self::TAB_SECTIONS[ $tab ] ?? '';
		return '' === $sections ? [] : explode( '|', $sections );
	}

	/** Whether a settings path belongs to a tab. */
	public static function pathOnTab( string $path, string $tab ): bool {
		foreach ( self::sectionsForTab( $tab ) as $section ) {
			if ( str_starts_with( $path, $section . '.' ) ) {
				return true;
			}
		}
		return false;
	}

	/** Translated tab label. */
	public static function tabLabel( string $tab ): string {
		return match ( $tab ) {
			'outlet'     => __( 'Prodajni objekti', 'sidrena-cijena-za-woocommerce' ),
			'reference'  => __( 'Referentne cijene', 'sidrena-cijena-za-woocommerce' ),
			'display'    => __( 'Prikaz', 'sidrena-cijena-za-woocommerce' ),
			'price_list' => __( 'Cjenik', 'sidrena-cijena-za-woocommerce' ),
			'history'    => __( 'Povijest cijena', 'sidrena-cijena-za-woocommerce' ),
			'advanced'   => __( 'Napredno', 'sidrena-cijena-za-woocommerce' ),
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
		if ( [] === self::sectionsForTab( $tab ) ) {
			return [];
		}
		return array_values( array_filter( self::all(), static fn( array $d ) => self::pathOnTab( $d['path'], $tab ) ) );
	}

	/**
	 * @return array<int,FieldDef>
	 */
	public static function all(): array {
		return array_merge(
			[
				[
					'path'        => 'outlet.form',
					'type'        => 'text',
					'label'       => __( 'Oblik prodajnog objekta', 'sidrena-cijena-za-woocommerce' ),
					'description' => __( 'Npr. webshop, prodavaonica, kiosk. Prvi dio naziva datoteke cjenika.', 'sidrena-cijena-za-woocommerce' ),
					'placeholder' => 'webshop',
				],
				[
					'path'        => 'outlet.address',
					'type'        => 'text',
					'label'       => __( 'Adresa prodajnog objekta', 'sidrena-cijena-za-woocommerce' ),
					'description' => __( 'Za web trgovinu: adresa sjedišta trgovca. Obvezan podatak u cjeniku.', 'sidrena-cijena-za-woocommerce' ),
					'placeholder' => 'Ilica 1, Zagreb',
				],
				[
					'path'        => 'outlet.label',
					'type'        => 'text',
					'label'       => __( 'Oznaka prodajnog objekta', 'sidrena-cijena-za-woocommerce' ),
					'description' => __( 'Interna oznaka objekta (npr. naziv web trgovine). Obvezan dio naziva datoteke.', 'sidrena-cijena-za-woocommerce' ),
				],
				[
					'path'        => 'outlet.storage_number',
					'type'        => 'text',
					'label'       => __( 'Broj pohrane', 'sidrena-cijena-za-woocommerce' ),
					'description' => __( 'Zadano 1 ako nemate više skladišta.', 'sidrena-cijena-za-woocommerce' ),
				],
				[
					'path'        => 'outlet.merchant_name',
					'type'        => 'text',
					'label'       => __( 'Naziv trgovca', 'sidrena-cijena-za-woocommerce' ),
					'description' => __( 'Prazno = naziv web stranice.', 'sidrena-cijena-za-woocommerce' ),
				],
				[
					'path'  => 'outlet.key',
					'type'  => 'hidden',
					'label' => 'key',
				],
				[
					'path'        => 'outlets.additional',
					'type'        => 'outlets',
					'heading'     => __( 'Dodatni prodajni objekti (poslovnice)', 'sidrena-cijena-za-woocommerce' ),
					'label'       => __( 'Poslovnice', 'sidrena-cijena-za-woocommerce' ),
					'description' => __( 'Svaki prodajni objekt mora imati vlastitu datoteku cjenika s vlastitim nazivom i arhivom od 30 dana (NN 101/2026, t. VI.), i kad su cijene jednake. Ovdje dodajte fizičke poslovnice; cijene se preuzimaju iz WooCommercea.', 'sidrena-cijena-za-woocommerce' ),
				],
			],
			self::referenceType( 'anchor', __( 'Sidrena (dodatna) cijena', 'sidrena-cijena-za-woocommerce' ), true ),
			self::referenceType( 'base', __( 'Bazna cijena (od 17. 11. 2026.)', 'sidrena-cijena-za-woocommerce' ), false ),
			[
				[
					'path'        => 'reference_prices.variation_inherit_parent',
					'type'        => 'checkbox',
					'label'       => __( 'Varijacije nasljeđuju referentnu cijenu roditelja', 'sidrena-cijena-za-woocommerce' ),
					'description' => __( 'Kada varijacija nema vlastitu referentnu cijenu, koristi se cijena varijabilnog proizvoda.', 'sidrena-cijena-za-woocommerce' ),
					'heading'     => __( 'Ostalo', 'sidrena-cijena-za-woocommerce' ),
				],
				[
					'path'        => 'reference_prices.auto_na_after_date',
					'type'        => 'checkbox',
					'label'       => __( 'Automatski "nema referentne cijene" za nove proizvode', 'sidrena-cijena-za-woocommerce' ),
					'description' => __( 'Proizvodi kreirani nakon referentnog datuma nemaju sidrenu cijenu pa se oznaka ne prikazuje.', 'sidrena-cijena-za-woocommerce' ),
				],

				[
					'path'    => 'display.position',
					'type'    => 'select',
					'label'   => __( 'Položaj oznake', 'sidrena-cijena-za-woocommerce' ),
					'options' => [
						'after'  => __( 'Iza cijene (isti redak)', 'sidrena-cijena-za-woocommerce' ),
						'below'  => __( 'Ispod cijene', 'sidrena-cijena-za-woocommerce' ),
						'before' => __( 'Ispred cijene', 'sidrena-cijena-za-woocommerce' ),
					],
				],
				[
					'path'        => 'display.format',
					'type'        => 'textarea',
					'label'       => __( 'Format oznake', 'sidrena-cijena-za-woocommerce' ),
					'description' => __( 'Dostupne oznake: {label}, {date}, {price}, {key}. Sidrena cijena isključuje akcijske cijene.', 'sidrena-cijena-za-woocommerce' ),
				],
				[
					'path'        => 'display.format_compact',
					'type'        => 'textarea',
					'label'       => __( 'Sažeti format (košarica, blagajna)', 'sidrena-cijena-za-woocommerce' ),
					'description' => __( 'Iste oznake kao gore; koristi se gdje je malo prostora.', 'sidrena-cijena-za-woocommerce' ),
				],
				[
					'path'        => 'display.date_format',
					'type'        => 'text',
					'label'       => __( 'Format datuma', 'sidrena-cijena-za-woocommerce' ),
					'description' => __( 'PHP format datuma; zadano "j. n. Y." daje 10. 9. 2026.', 'sidrena-cijena-za-woocommerce' ),
					'placeholder' => 'j. n. Y.',
				],
				[
					'path'        => 'display.loop',
					'type'        => 'checkbox',
					'label'       => __( 'Prikaži u popisu proizvoda', 'sidrena-cijena-za-woocommerce' ),
					'description' => __( 'Zakon traži oznaku svugdje gdje se prikazuje cijena.', 'sidrena-cijena-za-woocommerce' ),
				],
				[
					'path'  => 'display.single',
					'type'  => 'checkbox',
					'label' => __( 'Prikaži na stranici proizvoda', 'sidrena-cijena-za-woocommerce' ),
				],
				[
					'path'  => 'display.cart',
					'type'  => 'checkbox',
					'label' => __( 'Prikaži u košarici', 'sidrena-cijena-za-woocommerce' ),
				],
				[
					'path'    => 'display.checkout',
					'type'    => 'select',
					'label'   => __( 'Prikaz na blagajni', 'sidrena-cijena-za-woocommerce' ),
					'options' => [
						'unit'  => __( 'Jedinična cijena', 'sidrena-cijena-za-woocommerce' ),
						'total' => __( 'Ukupno za količinu', 'sidrena-cijena-za-woocommerce' ),
						'none'  => __( 'Ne prikazuj', 'sidrena-cijena-za-woocommerce' ),
					],
				],
				[
					'path'  => 'display.mini_cart',
					'type'  => 'checkbox',
					'label' => __( 'Prikaži u mini košarici', 'sidrena-cijena-za-woocommerce' ),
				],
				[
					'path'        => 'display.item_data',
					'type'        => 'checkbox',
					'label'       => __( 'Dodaj redak u podatke stavke (blok košarica/blagajna)', 'sidrena-cijena-za-woocommerce' ),
					'description' => __( 'Blok košarica ne renderira oznaku; ovako se podatak ipak prikazuje kao redak stavke.', 'sidrena-cijena-za-woocommerce' ),
				],
				[
					'path'    => 'display.variable_loop',
					'type'    => 'select',
					'label'   => __( 'Varijabilni proizvodi u popisu', 'sidrena-cijena-za-woocommerce' ),
					'options' => [
						'range' => __( 'Raspon (min – max)', 'sidrena-cijena-za-woocommerce' ),
						'none'  => __( 'Ne prikazuj', 'sidrena-cijena-za-woocommerce' ),
					],
				],
				[
					'path'        => 'display.omnibus',
					'type'        => 'checkbox',
					'label'       => __( 'Prikaži najnižu cijenu u 30 dana prije sniženja', 'sidrena-cijena-za-woocommerce' ),
					'description' => __( 'Obveza prema čl. 19 Zakona o zaštiti potrošača za svako sniženje.', 'sidrena-cijena-za-woocommerce' ),
				],
				[
					'path'  => 'display.omnibus_label',
					'type'  => 'text',
					'label' => __( 'Naziv najniže cijene', 'sidrena-cijena-za-woocommerce' ),
				],
				[
					'path'        => 'display.omnibus_replace_del',
					'type'        => 'checkbox',
					'label'       => __( 'Zamijeni precrtanu cijenu najnižom u 30 dana', 'sidrena-cijena-za-woocommerce' ),
					'description' => __( 'Kada je najniža cijena niža od redovne, precrtava se ona (postotak se računa od nje).', 'sidrena-cijena-za-woocommerce' ),
				],
				[
					'path'  => 'display.show_percent',
					'type'  => 'checkbox',
					'label' => __( 'Prikaži postotak sniženja', 'sidrena-cijena-za-woocommerce' ),
				],
				[
					'path'        => 'display.load_css',
					'type'        => 'checkbox',
					'label'       => __( 'Učitaj CSS dodatka', 'sidrena-cijena-za-woocommerce' ),
					'description' => __( 'Isključite ako stilove definirate u temi.', 'sidrena-cijena-za-woocommerce' ),
				],

				[
					'path'        => 'price_list.enabled',
					'type'        => 'checkbox',
					'label'       => __( 'Objavi strojno čitljiv cjenik', 'sidrena-cijena-za-woocommerce' ),
					'description' => __( 'Obveza za sve trgovce s web stranicom od 1. 10. 2026.', 'sidrena-cijena-za-woocommerce' ),
				],
				[
					'path'    => 'price_list.formats',
					'type'    => 'multicheck',
					'label'   => __( 'Formati', 'sidrena-cijena-za-woocommerce' ),
					'options' => [
						'xml' => 'XML',
						'csv' => 'CSV',
					],
				],
				[
					'path'        => 'price_list.slug',
					'type'        => 'text',
					'label'       => __( 'Putanja (slug)', 'sidrena-cijena-za-woocommerce' ),
					'description' => __( 'Javna adresa cjenika: /{slug}/latest.xml', 'sidrena-cijena-za-woocommerce' ),
					'placeholder' => 'cjenik',
				],
				[
					'path'        => 'price_list.generate_time',
					'type'        => 'time',
					'label'       => __( 'Vrijeme dnevnog generiranja', 'sidrena-cijena-za-woocommerce' ),
					'description' => __( 'Cjenik robe mora biti objavljen svaki dan prije 08:00.', 'sidrena-cijena-za-woocommerce' ),
				],
				[
					'path'        => 'price_list.regenerate_on_change',
					'type'        => 'select',
					'label'       => __( 'Regeneriraj pri promjeni cijene', 'sidrena-cijena-za-woocommerce' ),
					'description' => __( 'Cjenik usluga mora se objaviti pri svakoj promjeni cijene. „Svi proizvodi” (zadano) osvježava cjenik i pri promjeni cijene robe, što omogućuje dohvat cijena u gotovo stvarnom vremenu (t. VII.).', 'sidrena-cijena-za-woocommerce' ),
					'options'     => [
						'services' => __( 'Samo usluge', 'sidrena-cijena-za-woocommerce' ),
						'all'      => __( 'Svi proizvodi', 'sidrena-cijena-za-woocommerce' ),
						'never'    => __( 'Nikad (samo dnevno)', 'sidrena-cijena-za-woocommerce' ),
					],
				],
				[
					'path'        => 'price_list.debounce_seconds',
					'type'        => 'number',
					'label'       => __( 'Odgoda regeneriranja (s)', 'sidrena-cijena-za-woocommerce' ),
					'description' => __( 'Više promjena u ovom razdoblju spaja se u jedno generiranje.', 'sidrena-cijena-za-woocommerce' ),
					'min'         => 0,
				],
				[
					'path'        => 'price_list.retention_days',
					'type'        => 'number',
					'label'       => __( 'Čuvanje starih cjenika (dana)', 'sidrena-cijena-za-woocommerce' ),
					'description' => __( 'Zakonski minimum 30 dana.', 'sidrena-cijena-za-woocommerce' ),
					'min'         => Sanitizer::MIN_RETENTION_DAYS,
				],
				[
					'path'  => 'price_list.include_out_of_stock',
					'type'  => 'checkbox',
					'label' => __( 'Uključi proizvode kojih nema na zalihi', 'sidrena-cijena-za-woocommerce' ),
				],
				[
					'path'  => 'price_list.include_hidden',
					'type'  => 'checkbox',
					'label' => __( 'Uključi skrivene proizvode', 'sidrena-cijena-za-woocommerce' ),
				],
				[
					'path'    => 'price_list.service_rule',
					'type'    => 'select',
					'label'   => __( 'Što je usluga', 'sidrena-cijena-za-woocommerce' ),
					'options' => [
						'virtual_or_flag' => __( 'Virtualni proizvod ili oznaka "Ovo je usluga"', 'sidrena-cijena-za-woocommerce' ),
						'virtual'         => __( 'Samo virtualni proizvodi', 'sidrena-cijena-za-woocommerce' ),
						'flag'            => __( 'Samo oznaka "Ovo je usluga"', 'sidrena-cijena-za-woocommerce' ),
						'all'             => __( 'Svi proizvodi su usluge', 'sidrena-cijena-za-woocommerce' ),
						'none'            => __( 'Nema usluga', 'sidrena-cijena-za-woocommerce' ),
					],
				],
				[
					'path'        => 'price_list.sale_name_default',
					'type'        => 'text',
					'label'       => __( 'Zadani naziv posebnog oblika prodaje', 'sidrena-cijena-za-woocommerce' ),
					'placeholder' => 'Sniženje',
				],
				[
					'path'    => 'price_list.csv_delimiter',
					'type'    => 'select',
					'label'   => __( 'CSV razdjelnik', 'sidrena-cijena-za-woocommerce' ),
					'options' => [
						';'  => __( 'Točka-zarez (;)', 'sidrena-cijena-za-woocommerce' ),
						','  => __( 'Zarez (,)', 'sidrena-cijena-za-woocommerce' ),
						"\t" => __( 'Tabulator', 'sidrena-cijena-za-woocommerce' ),
					],
				],
				[
					'path'  => 'price_list.csv_bom',
					'type'  => 'checkbox',
					'label' => __( 'CSV s UTF-8 BOM (za Excel)', 'sidrena-cijena-za-woocommerce' ),
				],
				[
					'path'    => 'price_list.tax_mode',
					'type'    => 'select',
					'label'   => __( 'Cijene u cjeniku', 'sidrena-cijena-za-woocommerce' ),
					'options' => [
						'incl' => __( 'S PDV-om (maloprodajne)', 'sidrena-cijena-za-woocommerce' ),
						'excl' => __( 'Bez PDV-a', 'sidrena-cijena-za-woocommerce' ),
					],
				],
				[
					'path'        => 'price_list.external_cron_key',
					'type'        => 'text',
					'label'       => __( 'Ključ za vanjski cron', 'sidrena-cijena-za-woocommerce' ),
					'description' => __( 'Za pokretanje generiranja izvana: /cjenik/?scwc_run=1&key=KLJUČ (npr. iz sistemskog crona).', 'sidrena-cijena-za-woocommerce' ),
				],

				[
					'path'        => 'history.enabled',
					'type'        => 'checkbox',
					'label'       => __( 'Bilježi povijest cijena', 'sidrena-cijena-za-woocommerce' ),
					'description' => __( 'Potrebno za izračun najniže cijene u 30 dana prije sniženja.', 'sidrena-cijena-za-woocommerce' ),
				],
				[
					'path'        => 'history.retention_days',
					'type'        => 'number',
					'label'       => __( 'Čuvanje povijesti (dana)', 'sidrena-cijena-za-woocommerce' ),
					'description' => __( 'Najmanje 31 dan; preporučeno 400 radi naknadnih provjera.', 'sidrena-cijena-za-woocommerce' ),
					'min'         => 31,
				],
				[
					'path'        => 'history.sweep_time',
					'type'        => 'time',
					'label'       => __( 'Vrijeme dnevne provjere cijena', 'sidrena-cijena-za-woocommerce' ),
					'description' => __( 'Hvata promjene cijena izvan WooCommerce sučelja (ERP, SQL).', 'sidrena-cijena-za-woocommerce' ),
				],
				[
					'path'        => 'history.omnibus_fallback',
					'type'        => 'select',
					'label'       => __( 'Kad nema dovoljno povijesti', 'sidrena-cijena-za-woocommerce' ),
					'description' => __( 'Za sniženja bez 30 dana zabilježene povijesti.', 'sidrena-cijena-za-woocommerce' ),
					'options'     => [
						'regular' => __( 'Koristi redovnu cijenu', 'sidrena-cijena-za-woocommerce' ),
						'hide'    => __( 'Ne prikazuj najnižu cijenu', 'sidrena-cijena-za-woocommerce' ),
					],
				],

				[
					'path'        => 'advanced.remove_data_on_uninstall',
					'type'        => 'checkbox',
					'label'       => __( 'Obriši sve podatke pri deinstalaciji', 'sidrena-cijena-za-woocommerce' ),
					'description' => __( 'Postavke, meta podatke proizvoda, povijest cijena i generirane cjenike.', 'sidrena-cijena-za-woocommerce' ),
				],
				[
					'path'  => 'advanced.debug_log',
					'type'  => 'checkbox',
					'label' => __( 'Zapisuj dijagnostiku u WooCommerce log', 'sidrena-cijena-za-woocommerce' ),
				],
			]
		);
	}

	/**
	 * @return array<int,FieldDef>
	 */
	private static function referenceType( string $key, string $heading, bool $always ): array {
		$p    = "reference_prices.{$key}.";
		$defs = [];
		if ( $always ) {
			$defs[] = [
				'path'    => $p . 'enabled',
				'type'    => 'hidden',
				'label'   => __( 'Omogućeno', 'sidrena-cijena-za-woocommerce' ),
				'heading' => $heading,
			];
		} else {
			$defs[] = [
				'path'        => $p . 'enabled',
				'type'        => 'checkbox',
				'label'       => __( 'Omogući', 'sidrena-cijena-za-woocommerce' ),
				'description' => __( 'Uključite kada pravilnik odredi referentni dan za baznu cijenu.', 'sidrena-cijena-za-woocommerce' ),
				'heading'     => $heading,
			];
		}
		$dateDescription = 'anchor' === $key
			? sprintf(
				/* translators: 1: default anchor date, 2: FMCG date */
				__( 'Zadano %1$s (NN 101/2026). Datum za FMCG kategorije koje ste već označavali: %2$s – dodajte ih kao iznimku ispod.', 'sidrena-cijena-za-woocommerce' ),
				'10. 9. 2026.',
				'2. 5. 2025.'
			)
			: __( 'Referentni dan određuje pravilnik; ostavite prazno dok nije objavljen.', 'sidrena-cijena-za-woocommerce' );
		return array_merge(
			$defs,
			[
				[
					'path'        => $p . 'label',
					'type'        => 'text',
					'label'       => __( 'Naziv na stranici', 'sidrena-cijena-za-woocommerce' ),
					'description' => __( 'Npr. "Cijena na dan" – prikazuje se uz datum i iznos.', 'sidrena-cijena-za-woocommerce' ),
				],
				[
					'path'        => $p . 'date',
					'type'        => 'date',
					'label'       => __( 'Referentni datum', 'sidrena-cijena-za-woocommerce' ),
					'description' => $dateDescription,
				],
				[
					'path'        => $p . 'category_overrides',
					'type'        => 'category_overrides',
					'label'       => __( 'Iznimke po kategorijama', 'sidrena-cijena-za-woocommerce' ),
					'description' => __( 'Kategorije (uključujući podkategorije) s drugim referentnim datumom.', 'sidrena-cijena-za-woocommerce' ),
				],
				[
					'path'        => $p . 'auto_snapshot_at',
					'type'        => 'datetime-local',
					'label'       => __( 'Automatski snapshot cijena', 'sidrena-cijena-za-woocommerce' ),
					'description' => __( 'U zadano vrijeme redovne cijene svih proizvoda kopiraju se kao referentne (zakazani zadatak). Prazno = bez automatskog snapshota.', 'sidrena-cijena-za-woocommerce' ),
				],
				[
					'path'  => $p . 'auto_snapshot_done',
					'type'  => 'readonly',
					'label' => __( 'Snapshot obavljen', 'sidrena-cijena-za-woocommerce' ),
				],
			]
		);
	}
}
