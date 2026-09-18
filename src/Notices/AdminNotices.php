<?php
/**
 * Admin notices about compliance-relevant problems.
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

namespace SidrenaCijena\Notices;

use DateTimeImmutable;
use DateTimeZone;
use SidrenaCijena\Settings\Settings;
use SidrenaCijena\Support\Clock;

final class AdminNotices {

	public const STALE_HOURS    = 26;
	public const DISMISS_ACTION = 'scwc_dismiss_notice';
	public const USER_META      = 'scwc_dismissed_notices';

	/** @var callable():Environment */
	private $environment;

	/**
	 * @param callable():Environment $environment Lazily gathers the facts.
	 */
	public function __construct( private readonly Settings $settings, callable $environment, private readonly Clock $clock ) {
		$this->environment = $environment;
	}

	public function register(): void {
		add_action( 'admin_notices', [ $this, 'render' ] );
		add_action( 'admin_post_' . self::DISMISS_ACTION, [ $this, 'dismiss' ] );
	}

	/**
	 * @return array<int,array{id:string,type:string,message:string,dismissible:bool}>
	 */
	public function collect(): array {
		$env      = ( $this->environment )();
		$notices  = [];
		$outlet   = $this->settings->section( 'outlet' );
		$listOn   = (bool) $this->settings->get( 'price_list.enabled', true );
		$toolsUrl = admin_url( 'admin.php?page=scwc-tools' );
		$setUrl   = admin_url( 'admin.php?page=scwc-settings' );

		if ( $listOn && ( '' === trim( (string) ( $outlet['address'] ?? '' ) ) || '' === trim( (string) ( $outlet['label'] ?? '' ) ) ) ) {
			$notices[] = $this->notice(
				'outlet',
				'error',
				sprintf(
				/* translators: %s: settings URL */
					__( 'Sidrena cijena: cjenik se ne može generirati dok ne unesete adresu i oznaku prodajnog objekta. <a href="%s">Otvorite postavke</a>.', 'sidrena-cijena-za-woocommerce' ),
					esc_url( $setUrl )
				),
				false
			);
		}

		if ( $listOn ) {
			if ( null === $env->nextGeneration ) {
				$notices[] = $this->notice( 'schedule', 'warning', __( 'Sidrena cijena: dnevno generiranje cjenika nije zakazano. Otvorite postavke cjenika i kliknite „Ponovno zakaži zadatke”.', 'sidrena-cijena-za-woocommerce' ), false );
			}
			$last = $env->lastGeneration;
			if ( null === $last || empty( $last['at'] ) ) {
				$notices[] = $this->notice(
					'never_generated',
					'warning',
					sprintf(
					/* translators: %s: settings URL */
						__( 'Sidrena cijena: cjenik još nije generiran. <a href="%s">Generirajte ga sada</a> i provjerite javni URL.', 'sidrena-cijena-za-woocommerce' ),
						esc_url( $setUrl . '&tab=price_list' )
					),
					false
				);
			} elseif ( ! empty( $last['error'] ) ) {
				$notices[] = $this->notice(
					'generation_error',
					'warning',
					sprintf(
					/* translators: %s: error message */
						__( 'Sidrena cijena: zadnje generiranje cjenika nije uspjelo: %s', 'sidrena-cijena-za-woocommerce' ),
						esc_html( (string) $last['error'] )
					),
					false
				);
			} else {
				$at = new DateTimeImmutable( (string) $last['at'], new DateTimeZone( 'UTC' ) );
				if ( $this->clock->now()->getTimestamp() - $at->getTimestamp() > self::STALE_HOURS * HOUR_IN_SECONDS ) {
					$notices[] = $this->notice(
						'stale',
						'warning',
						sprintf(
						/* translators: %s: date/time */
							__( 'Sidrena cijena: cjenik nije osvježen od %s (obveza: svaki dan do 08:00). Provjerite WP-Cron / Action Scheduler.', 'sidrena-cijena-za-woocommerce' ),
							esc_html( $at->setTimezone( $this->clock->timezone() )->format( 'j. n. Y. H:i' ) )
						),
						false
					);
				}
			}
			$time = (string) $this->settings->get( 'price_list.generate_time', '06:00' );
			if ( strcmp( $time, '07:30' ) > 0 ) {
				$notices[] = $this->notice(
					'generate_after_deadline',
					'warning',
					sprintf(
					/* translators: 1: configured time, 2: settings URL */
						__( 'Sidrena cijena: dnevno generiranje cjenika zakazano je u %1$s, a cjenik robe mora biti objavljen svaki dan do 08:00 (NN 101/2026). <a href="%2$s">Pomaknite vrijeme</a> na najkasnije 07:30.', 'sidrena-cijena-za-woocommerce' ),
						esc_html( $time ),
						esc_url( $setUrl . '&tab=price_list' )
					),
					false
				);
			}
			if ( ! $env->prettyPermalinks ) {
				$notices[] = $this->notice( 'permalinks', 'warning', __( 'Sidrena cijena: trajne veze su „obične” pa je cjenik dostupan samo na adresi s parametrima (?scwc_cjenik=latest&scwc_format=xml). Preporučujemo uključiti lijepe trajne veze.', 'sidrena-cijena-za-woocommerce' ), false );
			}
		}

		if ( $env->isWooCommerceScreen() ) {
			if ( $listOn && ( null === $env->lastGeneration || empty( $env->lastGeneration['at'] ) ) ) {
				$slug      = trim( (string) $this->settings->get( 'price_list.slug', 'cjenik' ), '/' );
				$notices[] = $this->notice(
					'public_url',
					'info',
					sprintf(
					/* translators: 1: public URL, 2: slug */
						__( 'Sidrena cijena: javna adresa cjenika bit će <strong>%1$s</strong> (npr. %1$slatest.xml). Adresa je /%2$s/, a ne /cijene.', 'sidrena-cijena-za-woocommerce' ),
						esc_url( home_url( '/' . $slug . '/' ) ),
						esc_html( $slug )
					),
					true
				);
			}
			if ( $env->missingAnchors > 0 ) {
				$notices[] = $this->notice(
					'missing_anchors',
					'info',
					sprintf(
					/* translators: 1: count, 2: tools URL */
						__( 'Sidrena cijena: %1$d proizvoda/varijacija nema sidrenu cijenu ni oznaku „nema referentne cijene”. <a href="%2$s">Snimite ili uvezite cijene</a>.', 'sidrena-cijena-za-woocommerce' ),
						$env->missingAnchors,
						esc_url( $toolsUrl )
					),
					true
				);
			}
			if ( $env->blockCart ) {
				$notices[] = $this->notice( 'block_cart', 'info', __( 'Sidrena cijena: koristite blokovsku košaricu/blagajnu. Sidrena cijena tamo se prikazuje kao dodatni redak podataka stavke; puni prikaz oznake u blokovima stiže u sljedećoj verziji.', 'sidrena-cijena-za-woocommerce' ), true );
			}
			if ( version_compare( $env->wcVersion, '9.2', '<' ) ) {
				$notices[] = $this->notice( 'wc_gtin', 'info', __( 'Sidrena cijena: WooCommerce 9.2+ ima ugrađeno polje GTIN/EAN (barkod). Nadogradite WooCommerce kako bi cjenik sadržavao barkodove.', 'sidrena-cijena-za-woocommerce' ), true );
			}
			if ( version_compare( $env->wcVersion, '9.6', '<' ) ) {
				$notices[] = $this->notice( 'wc_brands', 'info', __( 'Sidrena cijena: WooCommerce 9.6+ ima ugrađene marke (Brands). Nadogradite WooCommerce kako bi cjenik sadržavao marku.', 'sidrena-cijena-za-woocommerce' ), true );
			}
		}

		return array_values( array_filter( $notices, static fn( array $n ) => ! $n['dismissible'] || ! in_array( $n['id'], $env->dismissed, true ) ) );
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		foreach ( $this->collect() as $n ) {
			echo '<div class="notice notice-' . esc_attr( $n['type'] ) . ' scwc-notice">';
			echo '<p>' . wp_kses_post( $n['message'] );
			if ( $n['dismissible'] ) {
				$url = add_query_arg(
					[
						'action'   => self::DISMISS_ACTION,
						'notice'   => $n['id'],
						'_wpnonce' => wp_create_nonce( self::DISMISS_ACTION ),
					],
					admin_url( 'admin-post.php' )
				);
				echo ' <a href="' . esc_url( $url ) . '">' . esc_html__( 'Sakrij', 'sidrena-cijena-za-woocommerce' ) . '</a>';
			}
			echo '</p></div>';
		}
	}

	public function dismiss(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! wp_verify_nonce( (string) ( $_GET['_wpnonce'] ?? '' ), self::DISMISS_ACTION ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			wp_die( esc_html__( 'Nedozvoljen zahtjev.', 'sidrena-cijena-za-woocommerce' ) );
		}
		$id        = sanitize_key( (string) ( $_GET['notice'] ?? '' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$dismissed = self::dismissedFor( get_current_user_id() );
		if ( '' !== $id && ! in_array( $id, $dismissed, true ) ) {
			$dismissed[] = $id;
			update_user_meta( get_current_user_id(), self::USER_META, $dismissed );
		}
		wp_safe_redirect( wp_get_referer() ?: admin_url() );
		exit;
	}

	/**
	 * @return string[]
	 */
	public static function dismissedFor( int $userId ): array {
		$value = get_user_meta( $userId, self::USER_META, true );
		return is_array( $value ) ? array_map( 'strval', $value ) : [];
	}

	/**
	 * @return array{id:string,type:string,message:string,dismissible:bool}
	 */
	private function notice( string $id, string $type, string $message, bool $dismissible ): array {
		return [
			'id'          => $id,
			'type'        => $type,
			'message'     => $message,
			'dismissible' => $dismissible,
		];
	}
}
