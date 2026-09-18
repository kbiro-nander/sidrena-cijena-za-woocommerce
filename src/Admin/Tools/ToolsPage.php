<?php
/**
 * WooCommerce › Sidrena cijena – Alati admin page (snapshot, CSV import, CSV export).
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

namespace SidrenaCijena\Admin\Tools;

use SidrenaCijena\Reference\ReferencePriceRegistry;
use SidrenaCijena\Settings\Settings;

class ToolsPage {

	public const SLUG   = 'scwc-tools';
	public const HANDLE = 'scwc-admin-tools';

	public function __construct(
		private readonly Settings $settings,
		private readonly ReferencePriceRegistry $registry,
		private readonly string $templateDir = SCWC_PLUGIN_DIR . 'templates',
	) {}

	public function register(): void {
		add_action( 'admin_menu', [ $this, 'addMenu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
	}

	public function addMenu(): void {
		add_submenu_page(
			'woocommerce',
			__( 'Sidrena cijena – Alati', 'sidrena-cijena-za-woocommerce' ),
			__( 'Sidrena cijena – Alati', 'sidrena-cijena-za-woocommerce' ),
			ToolsAjax::CAPABILITY,
			self::SLUG,
			[ $this, 'render' ]
		);
	}

	public function enqueue( string $hookSuffix = '' ): void {
		if ( ! str_contains( $hookSuffix, self::SLUG ) ) {
			return;
		}
		wp_enqueue_style( 'scwc-admin', SCWC_PLUGIN_URL . 'assets/css/admin.css', [], SCWC_VERSION );
		wp_enqueue_script( self::HANDLE, SCWC_PLUGIN_URL . 'assets/js/admin-tools.js', [ 'jquery' ], SCWC_VERSION, true );
		wp_localize_script(
			self::HANDLE,
			'scwcTools',
			[
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( ToolsAjax::NONCE ),
				'action'  => ToolsAjax::ACTION,
				'i18n'    => [
					'starting'        => __( 'Pokrećem…', 'sidrena-cijena-za-woocommerce' ),
					'done'            => __( 'Gotovo.', 'sidrena-cijena-za-woocommerce' ),
					'error'           => __( 'Greška:', 'sidrena-cijena-za-woocommerce' ),
					'networkError'    => __( 'Greška u komunikaciji s poslužiteljem.', 'sidrena-cijena-za-woocommerce' ),
					'confirmSnapshot' => __( 'Zapisati referentne cijene za sve proizvode? Ova radnja mijenja podatke proizvoda.', 'sidrena-cijena-za-woocommerce' ),
					'confirmImport'   => __( 'Primijeniti uvoz? Referentne cijene navedenih proizvoda bit će prepisane.', 'sidrena-cijena-za-woocommerce' ),
					'chooseFile'      => __( 'Odaberite CSV datoteku.', 'sidrena-cijena-za-woocommerce' ),
					'rowsValid'       => __( 'ispravnih redaka', 'sidrena-cijena-za-woocommerce' ),
					'rowsInvalid'     => __( 'neispravnih', 'sidrena-cijena-za-woocommerce' ),
					'rowsUnmatched'   => __( 'bez proizvoda', 'sidrena-cijena-za-woocommerce' ),
					/* translators: %d: number */
					'imported'        => __( 'Uvoz završen: ažurirano %1$d, označeno bez cijene %2$d, preskočeno %3$d.', 'sidrena-cijena-za-woocommerce' ),
					/* translators: %d: number */
					'exported'        => __( 'Izvezeno redaka: %d', 'sidrena-cijena-za-woocommerce' ),
					'actions'         => [
						'written'          => __( 'zapisano', 'sidrena-cijena-za-woocommerce' ),
						'skipped_existing' => __( 'preskočeno – već postoji', 'sidrena-cijena-za-woocommerce' ),
						'skipped_on_sale'  => __( 'preskočeno – na akciji', 'sidrena-cijena-za-woocommerce' ),
						'skipped_no_price' => __( 'preskočeno – bez cijene', 'sidrena-cijena-za-woocommerce' ),
						'marked_na'        => __( 'označeno – bez referentne cijene', 'sidrena-cijena-za-woocommerce' ),
					],
				],
			]
		);
	}

	public function render(): void {
		if ( ! current_user_can( ToolsAjax::CAPABILITY ) ) {
			wp_die( esc_html__( 'Nemate dopuštenje za pristup ovoj stranici.', 'sidrena-cijena-za-woocommerce' ) );
		}
		$types    = $this->registry->enabled();
		$settings = $this->settings;
		$template = CsvImporter::template( $this->registry );
		include $this->templateDir . '/admin/tools.php';
	}
}
