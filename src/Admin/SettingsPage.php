<?php
/**
 * WooCommerce › Sidrena cijena settings page (tabbed, Settings API).
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

namespace SidrenaCijena\Admin;

use SidrenaCijena\Display\BadgeContext;
use SidrenaCijena\Display\BadgeData;
use SidrenaCijena\Display\OmnibusView;
use SidrenaCijena\Display\PriceBadge;
use SidrenaCijena\Display\ReferenceView;
use SidrenaCijena\Reference\ReferencePriceRegistry;
use SidrenaCijena\Settings\Defaults;
use SidrenaCijena\Settings\Sanitizer;
use SidrenaCijena\Settings\Settings;
use SidrenaCijena\Support\DateFormat;
use SidrenaCijena\Support\Slugifier;

/**
 * @phpstan-import-type FieldDef from Fields
 */
class SettingsPage {

	public const SLUG       = 'scwc-settings';
	public const GROUP      = 'scwc_settings_group';
	public const CAPABILITY = 'manage_woocommerce';

	private const ACTIONS = [ 'scwc_generate_now', 'scwc_run_sweep', 'scwc_reschedule' ];

	/** @var callable():array<int,array{label:string,value:string}> */
	private $status;

	/** @var callable():array<int,array{id:int,name:string}> */
	private $categories;

	private string $templateDir;

	/**
	 * @param callable():array<int,array{label:string,value:string}>|null $statusProvider   Rows for the "Cjenik" status box.
	 * @param callable():array<int,array{id:int,name:string}>|null        $categoryProvider Product categories for the overrides repeater.
	 * @param string|null                                                  $templateDir      Plugin templates directory.
	 */
	public function __construct(
		private readonly Settings $settings,
		private readonly ReferencePriceRegistry $registry,
		private readonly Sanitizer $sanitizer,
		private readonly PriceBadge $badge,
		?callable $statusProvider = null,
		?callable $categoryProvider = null,
		?string $templateDir = null
	) {
		$this->status      = $statusProvider ?? static fn(): array => [];
		$this->categories  = $categoryProvider ?? [ self::class, 'productCategories' ];
		$this->templateDir = $templateDir ?? SCWC_PLUGIN_DIR . 'templates';
	}

	public function register(): void {
		add_action( 'admin_menu', [ $this, 'addMenu' ] );
		add_action( 'admin_init', [ $this, 'registerSetting' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
	}

	public function addMenu(): void {
		add_submenu_page(
			'woocommerce',
			__( 'Sidrena cijena', 'sidrena-cijena-za-woocommerce' ),
			__( 'Sidrena cijena', 'sidrena-cijena-za-woocommerce' ),
			self::CAPABILITY,
			self::SLUG,
			[ $this, 'render' ]
		);
	}

	public function registerSetting(): void {
		register_setting(
			self::GROUP,
			Settings::OPTION,
			[
				'type'              => 'array',
				'sanitize_callback' => [ $this->sanitizer, 'sanitize' ],
				'default'           => Defaults::all(),
			]
		);
	}

	public function enqueue( string $hookSuffix ): void {
		if ( ! str_contains( $hookSuffix, self::SLUG ) ) {
			return;
		}
		wp_enqueue_style( 'scwc-admin', SCWC_PLUGIN_URL . 'assets/css/admin.css', [], SCWC_VERSION );
		wp_enqueue_script( 'scwc-admin-settings', SCWC_PLUGIN_URL . 'assets/js/admin-settings.js', [], SCWC_VERSION, true );
	}

	/** @return array<string,string> Tab key => label. */
	public static function tabs(): array {
		$out = [];
		foreach ( array_keys( Fields::TABS ) as $tab ) {
			$out[ $tab ] = Fields::tabLabel( $tab );
		}
		return $out;
	}

	public static function sectionForTab( string $tab ): string {
		return Fields::sectionForTab( $tab );
	}

	/**
	 * @param array<string,mixed> $get Query parameters.
	 */
	public function currentTab( array $get ): string {
		$tab = $get['tab'] ?? '';
		return is_string( $tab ) && isset( Fields::TABS[ $tab ] ) ? $tab : 'outlet';
	}

	public function render(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Nemate ovlasti za pristup ovoj stranici.', 'sidrena-cijena-za-woocommerce' ) );
		}
		// Read-only page routing; the form itself is protected by settings_fields() + options.php.
		$tab = $this->currentTab( $_GET ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<div class="wrap scwc-settings">';
		echo '<h1>' . esc_html__( 'Sidrena cijena za WooCommerce', 'sidrena-cijena-za-woocommerce' ) . '</h1>';
		settings_errors();
		echo $this->navTabs( $tab ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
		echo '<form method="post" action="' . esc_url( admin_url( 'options.php' ) ) . '">';
		settings_fields( self::GROUP );
		echo '<input type="hidden" name="' . esc_attr( Settings::OPTION ) . '[_tab]" value="' . esc_attr( $tab ) . '" />';
		echo $this->intro( $tab ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<table class="form-table" role="presentation"><tbody>';
		echo $this->rows( $tab ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</tbody></table>';
		echo $this->extras( $tab ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $this->hiddenInputsForOtherTabs( $tab ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		submit_button();
		echo '</form></div>';
	}

	/**
	 * Hidden inputs carrying every setting that is not on the given tab, so a partial form post keeps the rest intact.
	 */
	public function hiddenInputsForOtherTabs( string $tab ): string {
		$section = self::sectionForTab( $tab );
		$html    = '';
		foreach ( Fields::all() as $def ) {
			if ( str_starts_with( $def['path'], $section . '.' ) ) {
				continue;
			}
			$html .= $this->hiddenTree( Fields::inputName( $def['path'] ), $this->settings->get( $def['path'] ) );
		}
		return $html;
	}

	/** "{form}_{address}_{label}_{storage}_YYYYMMDD_HHMMSS.xml" from current outlet settings. */
	public function filenamePreview(): string {
		$parts = [];
		foreach ( [ 'form', 'address', 'label', 'storage_number' ] as $key ) {
			$slug    = Slugifier::filenamePart( (string) $this->settings->get( 'outlet.' . $key, '' ) );
			$parts[] = '' === $slug ? 'na' : $slug;
		}
		return implode( '_', $parts ) . '_YYYYMMDD_HHMMSS.xml';
	}

	/**
	 * Default category provider: all product categories.
	 *
	 * @return array<int,array{id:int,name:string}>
	 */
	public static function productCategories(): array {
		$terms = get_terms(
			[
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
			]
		);
		if ( ! is_array( $terms ) ) {
			return [];
		}
		$out = [];
		foreach ( $terms as $term ) {
			if ( $term instanceof \WP_Term ) {
				$out[] = [
					'id'   => (int) $term->term_id,
					'name' => (string) $term->name,
				];
			}
		}
		return $out;
	}

	private function navTabs( string $current ): string {
		$html = '<nav class="nav-tab-wrapper woo-nav-tab-wrapper">';
		foreach ( self::tabs() as $tab => $label ) {
			$url   = add_query_arg(
				[
					'page' => self::SLUG,
					'tab'  => $tab,
				],
				admin_url( 'admin.php' )
			);
			$class = 'nav-tab' . ( $tab === $current ? ' nav-tab-active' : '' );
			$html .= '<a href="' . esc_url( $url ) . '" class="' . esc_attr( $class ) . '">' . esc_html( $label ) . '</a>';
		}
		return $html . '</nav>';
	}

	private function intro( string $tab ): string {
		$text = match ( $tab ) {
			'outlet'     => __( 'Podaci o prodajnom objektu ulaze u naziv datoteke i zaglavlje cjenika (NN 101/2026).', 'sidrena-cijena-za-woocommerce' ),
			'reference'  => __( 'Sidrena cijena je redovna cijena koja je vrijedila na referentni dan; akcijske cijene se ne uzimaju u obzir.', 'sidrena-cijena-za-woocommerce' ),
			'display'    => __( 'Oznaka se prikazuje uz svaku cijenu: jasno, vidljivo i čitljivo, s referentnim datumom.', 'sidrena-cijena-za-woocommerce' ),
			'price_list' => __( 'Cjenik robe objavljuje se svaki dan prije 08:00, cjenik usluga pri svakoj promjeni; verzije se čuvaju najmanje 30 dana.', 'sidrena-cijena-za-woocommerce' ),
			'history'    => __( 'Povijest cijena služi za izračun najniže cijene u 30 dana prije sniženja (čl. 19 ZZP).', 'sidrena-cijena-za-woocommerce' ),
			default      => '',
		};
		return '' === $text ? '' : '<p class="scwc-intro">' . esc_html( $text ) . '</p>';
	}

	private function rows( string $tab ): string {
		$renderer = new FieldRenderer( $this->templateDir, 'reference' === $tab ? ( $this->categories )() : [] );
		$html     = '';
		foreach ( Fields::forTab( $tab ) as $def ) {
			if ( isset( $def['heading'] ) ) {
				$html .= $renderer->heading( $def['heading'] );
			}
			$html .= $renderer->render( $def, $this->settings->get( $def['path'] ) );
		}
		return $html;
	}

	private function extras( string $tab ): string {
		return match ( $tab ) {
			'outlet'     => $this->outletExtras(),
			'display'    => $this->displayPreview(),
			'price_list' => $this->priceListExtras(),
			default      => '',
		};
	}

	private function outletExtras(): string {
		$slug  = (string) $this->settings->get( 'price_list.slug', 'cjenik' );
		$base  = (string) home_url( '/' . $slug . '/' );
		$html  = '<div class="scwc-box scwc-filename-preview"><h3>' . esc_html__( 'Naziv datoteke cjenika', 'sidrena-cijena-za-woocommerce' ) . '</h3>';
		$html .= '<p><code>' . esc_html( $this->filenamePreview() ) . '</code></p>';
		$html .= '<p class="description">' . esc_html__( 'Datum i vrijeme dodaju se pri svakom generiranju. Isti naziv s nastavkom .csv za CSV.', 'sidrena-cijena-za-woocommerce' ) . '</p>';
		$html .= '<h3>' . esc_html__( 'Javne adrese', 'sidrena-cijena-za-woocommerce' ) . '</h3><ul>';
		foreach ( [ $base, $base . 'latest.xml', $base . 'latest.csv' ] as $url ) {
			$html .= '<li><a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html( $url ) . '</a></li>';
		}
		return $html . '</ul></div>';
	}

	private function displayPreview(): string {
		$type = $this->registry->primary();
		$iso  = (string) ( $type->defaultDate ?? Defaults::ANCHOR_DATE );
		$date = DateFormat::parseIso( $iso );
		$fmt  = (string) $this->settings->get( 'display.date_format', DateFormat::CROATIAN );
		$ref  = new ReferenceView( $type->key, $type->label, null === $date ? '' : DateFormat::croatian( $date, $fmt ), $iso, 14.99, wc_price( 14.99 ), null, null, false );
		$omni = (bool) $this->settings->get( 'display.omnibus', true ) ? new OmnibusView( 12.49, wc_price( 12.49 ), 23, 'history' ) : null;
		$data = new BadgeData( 0, [ $ref ], $omni, true, 12.99, 16.99 );

		$html  = '<div class="scwc-box scwc-preview"><h3>' . esc_html__( 'Pregled oznake (primjer)', 'sidrena-cijena-za-woocommerce' ) . '</h3>';
		$html .= '<p class="description">' . esc_html__( 'Proizvod na sniženju: trenutna cijena 12,99 €, sidrena 14,99 €, najniža u 30 dana 12,49 €. Pregled se osvježava nakon spremanja.', 'sidrena-cijena-za-woocommerce' ) . '</p>';
		$html .= '<div class="scwc-preview__price"><del>' . wp_kses_post( wc_price( 16.99 ) ) . '</del> <ins>' . wp_kses_post( wc_price( 12.99 ) ) . '</ins> ';
		$html .= wp_kses_post( $this->badge->render( $data, BadgeContext::SINGLE ) );
		return $html . '</div></div>';
	}

	private function priceListExtras(): string {
		$rows = ( $this->status )();
		$html = '<div class="scwc-box scwc-status"><h3>' . esc_html__( 'Stanje cjenika', 'sidrena-cijena-za-woocommerce' ) . '</h3>';
		if ( [] === $rows ) {
			$html .= '<p class="description">' . esc_html__( 'Nema podataka o generiranju.', 'sidrena-cijena-za-woocommerce' ) . '</p>';
		} else {
			$html .= '<table class="widefat striped scwc-status__table"><tbody>';
			foreach ( $rows as $row ) {
				$html .= '<tr><th>' . esc_html( (string) ( $row['label'] ?? '' ) ) . '</th><td>' . wp_kses_post( (string) ( $row['value'] ?? '' ) ) . '</td></tr>';
			}
			$html .= '</tbody></table>';
		}
		$labels = [
			'scwc_generate_now' => __( 'Generiraj sada', 'sidrena-cijena-za-woocommerce' ),
			'scwc_run_sweep'    => __( 'Provjeri cijene sada', 'sidrena-cijena-za-woocommerce' ),
			'scwc_reschedule'   => __( 'Ponovno zakaži zadatke', 'sidrena-cijena-za-woocommerce' ),
		];
		$html  .= '<p class="scwc-status__actions">';
		foreach ( self::ACTIONS as $action ) {
			$url   = wp_nonce_url( admin_url( 'admin-post.php?action=' . $action ), $action );
			$html .= '<a class="button" href="' . esc_url( $url ) . '">' . esc_html( $labels[ $action ] ) . '</a> ';
		}
		return $html . '</p></div>';
	}

	/**
	 * @param mixed $value Scalar, list, or nested array.
	 */
	private function hiddenTree( string $name, $value ): string {
		if ( is_array( $value ) ) {
			$html = '';
			$list = array_is_list( $value );
			foreach ( $value as $key => $item ) {
				$child = $list && is_scalar( $item ) ? $name . '[]' : $name . '[' . $key . ']';
				$html .= $this->hiddenTree( $child, $item );
			}
			return $html;
		}
		if ( is_bool( $value ) ) {
			$value = $value ? '1' : '0';
		}
		$value = is_scalar( $value ) ? (string) $value : '';
		return '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" />';
	}
}
