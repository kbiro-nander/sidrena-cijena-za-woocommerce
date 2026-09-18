<?php
/**
 * Rows for the "Cjenik" status box on the settings page.
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

namespace SidrenaCijena\Admin;

use DateTimeImmutable;
use DateTimeZone;
use SidrenaCijena\PriceList\Generator;
use SidrenaCijena\PriceList\Outlets;
use SidrenaCijena\Scheduling\Scheduler;
use SidrenaCijena\Settings\Settings;
use SidrenaCijena\Support\Clock;

final class StatusProvider {

	/** @var callable(string):(array{code:int}|string) */
	private $http;

	/**
	 * @param callable(string):(array{code:int}|string)|null $http HEAD request: returns ['code' => int] or an error message.
	 */
	public function __construct(
		private readonly Settings $settings,
		private readonly Scheduler $scheduler,
		private readonly Clock $clock,
		?callable $http = null,
	) {
		$this->http = $http ?? static function ( string $url ) {
			$response = wp_remote_head(
				$url,
				[
					'timeout'     => 5,
					'redirection' => 2,
					'sslverify'   => false,
				]
			);
			if ( is_wp_error( $response ) ) {
				return $response->get_error_message();
			}
			return [ 'code' => (int) wp_remote_retrieve_response_code( $response ) ];
		};
	}

	/**
	 * @return array<int,array{label:string,value:string}>
	 */
	private function publicAddressRows(): array {
		$slug   = trim( (string) $this->settings->get( 'price_list.slug', 'cjenik' ), '/' );
		$base   = (string) home_url( '/' . $slug . '/' );
		$rows   = [];
		$rows[] = [
			'label' => __( 'Javna adresa cjenika', 'sidrena-cijena-za-woocommerce' ),
			'value' => '<strong>' . esc_html( $base ) . '</strong><br><span class="description">' . sprintf(
				/* translators: 1: slug */
				esc_html__( 'Adresa je /%1$s/ (i /%1$s/latest.xml, /%1$s/latest.csv), a ne /cijene ni /%1$s.xml. Slug mijenjate u kartici Cjenik.', 'sidrena-cijena-za-woocommerce' ),
				esc_html( $slug )
			) . '</span>',
		];
		$result = ( $this->http )( $base . 'latest.xml' );
		if ( is_array( $result ) ) {
			$code = (int) ( $result['code'] ?? 0 );
			$hint = match ( true ) {
				200 === $code               => __( 'u redu – cjenik je javno dostupan', 'sidrena-cijena-za-woocommerce' ),
				404 === $code               => __( 'nije pronađen – spremite Postavke → Trajne veze (bez promjena) ili kliknite „Ponovno zakaži zadatke”, pa provjerite je li cjenik generiran', 'sidrena-cijena-za-woocommerce' ),
				in_array( $code, [ 401, 403, 429, 503 ], true ) => __( 'blokirano – zaštita od robota, CDN ili lozinka na stranici; datoteke moraju biti dostupne bez prijave i bez zaštite od robota', 'sidrena-cijena-za-woocommerce' ),
				default                     => __( 'neočekivan odgovor – provjerite adresu u pregledniku', 'sidrena-cijena-za-woocommerce' ),
			};
			$value = sprintf( 'HTTP %d – %s', $code, esc_html( $hint ) );
		} else {
			$value = esc_html( (string) $result ) . ' – ' . esc_html__( 'poslužitelj ne može dohvatiti vlastitu adresu (loopback); provjerite u pregledniku', 'sidrena-cijena-za-woocommerce' );
		}
		$rows[] = [
			'label' => __( 'Provjera dostupnosti', 'sidrena-cijena-za-woocommerce' ),
			'value' => $value,
		];
		return $rows;
	}

	/**
	 * @return array<int,array{label:string,value:string}>
	 */
	public function __invoke(): array {
		$rows = $this->publicAddressRows();
		$last = get_option( Generator::OPTION_LAST, [] );
		$last = is_array( $last ) ? $last : [];

		if ( empty( $last['at'] ) ) {
			$rows[] = [
				'label' => __( 'Zadnje generiranje', 'sidrena-cijena-za-woocommerce' ),
				'value' => __( 'nikad', 'sidrena-cijena-za-woocommerce' ),
			];
		} else {
			$value = esc_html( $this->local( (string) $last['at'] ) );
			if ( ! empty( $last['error'] ) ) {
				$value .= ' – <span style="color:#d63638">' . esc_html( (string) $last['error'] ) . '</span>';
			} else {
				$value .= sprintf(
					/* translators: 1: products, 2: services */
					' (' . __( '%1$d proizvoda, %2$d usluge', 'sidrena-cijena-za-woocommerce' ) . ')',
					(int) ( $last['products'] ?? 0 ),
					(int) ( $last['services'] ?? 0 )
				);
				$value .= '<br><code>' . esc_html( implode( ', ', (array) ( $last['files'] ?? [] ) ) ) . '</code>';
			}
			$rows[] = [
				'label' => __( 'Zadnje generiranje', 'sidrena-cijena-za-woocommerce' ),
				'value' => $value,
			];
		}

		$status = $this->scheduler->status();
		$rows[] = [
			'label' => __( 'Sljedeće generiranje', 'sidrena-cijena-za-woocommerce' ),
			'value' => $this->when( $status[ Scheduler::HOOK_GENERATE ] ?? null ),
		];
		$rows[] = [
			'label' => __( 'Sljedeća provjera cijena', 'sidrena-cijena-za-woocommerce' ),
			'value' => $this->when( $status[ Scheduler::HOOK_SWEEP ] ?? null ),
		];
		if ( ! empty( $status[ Scheduler::HOOK_AUTO_SNAPSHOT ] ) ) {
			$rows[] = [
				'label' => __( 'Zakazano automatsko snimanje', 'sidrena-cijena-za-woocommerce' ),
				'value' => $this->when( $status[ Scheduler::HOOK_AUTO_SNAPSHOT ] ),
			];
		}
		$rows[] = [
			'label' => __( 'Mehanizam zakazivanja', 'sidrena-cijena-za-woocommerce' ),
			'value' => 'action_scheduler' === $this->scheduler->backend()->name() ? 'Action Scheduler' : 'WP-Cron',
		];

		$base  = home_url( '/' . trim( (string) $this->settings->get( 'price_list.slug', 'cjenik' ), '/' ) . '/' );
		$links = [];
		foreach ( [
			''           => __( 'Popis', 'sidrena-cijena-za-woocommerce' ),
			'latest.xml' => 'latest.xml',
			'latest.csv' => 'latest.csv',
			'index.json' => 'index.json',
		] as $path => $label ) {
			$links[] = '<a href="' . esc_url( $base . $path ) . '" target="_blank" rel="noopener">' . esc_html( (string) $label ) . '</a>';
		}
		$rows[]  = [
			'label' => __( 'Javni URL-ovi', 'sidrena-cijena-za-woocommerce' ),
			'value' => implode( ' · ', $links ),
		];
		$outlets = Outlets::fromSettings( $this->settings );
		if ( count( $outlets ) > 1 ) {
			$perOutlet = [];
			foreach ( $outlets as $outlet ) {
				$perOutlet[] = esc_html( $outlet->label ) . ': <a href="' . esc_url( $base . $outlet->key . '/latest.xml' ) . '" target="_blank" rel="noopener">latest.xml</a> · <a href="' . esc_url( $base . $outlet->key . '/latest.csv' ) . '" target="_blank" rel="noopener">latest.csv</a>';
			}
			$rows[] = [
				'label' => __( 'Po prodajnom objektu', 'sidrena-cijena-za-woocommerce' ),
				'value' => implode( '<br>', $perOutlet ),
			];
		}
		$key = (string) $this->settings->get( 'price_list.external_cron_key', '' );
		if ( '' !== $key ) {
			$url    = $base . '?scwc_run=1&key=' . rawurlencode( $key );
			$rows[] = [
				'label' => __( 'Vanjski cron (GET)', 'sidrena-cijena-za-woocommerce' ),
				'value' => '<code>' . esc_html( $url ) . '</code>',
			];
		}
		return $rows;
	}

	private function when( ?int $ts ): string {
		if ( null === $ts ) {
			return esc_html__( 'nije zakazano', 'sidrena-cijena-za-woocommerce' );
		}
		return esc_html( ( new DateTimeImmutable( '@' . $ts ) )->setTimezone( $this->clock->timezone() )->format( 'j. n. Y. H:i' ) );
	}

	private function local( string $utc ): string {
		return ( new DateTimeImmutable( $utc, new DateTimeZone( 'UTC' ) ) )->setTimezone( $this->clock->timezone() )->format( 'j. n. Y. H:i' );
	}
}
