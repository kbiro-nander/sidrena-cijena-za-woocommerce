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
use SidrenaCijena\Scheduling\Scheduler;
use SidrenaCijena\Settings\Settings;
use SidrenaCijena\Support\Clock;

final class StatusProvider {

	public function __construct(
		private readonly Settings $settings,
		private readonly Scheduler $scheduler,
		private readonly Clock $clock,
	) {}

	/**
	 * @return array<int,array{label:string,value:string}>
	 */
	public function __invoke(): array {
		$td   = 'sidrena-cijena-za-woocommerce';
		$rows = [];
		$last = get_option( Generator::OPTION_LAST, [] );
		$last = is_array( $last ) ? $last : [];

		if ( empty( $last['at'] ) ) {
			$rows[] = [ 'label' => __( 'Zadnje generiranje', $td ), 'value' => __( 'nikad', $td ) ];
		} else {
			$value = esc_html( $this->local( (string) $last['at'] ) );
			if ( ! empty( $last['error'] ) ) {
				$value .= ' – <span style="color:#d63638">' . esc_html( (string) $last['error'] ) . '</span>';
			} else {
				$value .= sprintf(
					/* translators: 1: products, 2: services */
					' (' . __( '%1$d proizvoda, %2$d usluge', $td ) . ')',
					(int) ( $last['products'] ?? 0 ),
					(int) ( $last['services'] ?? 0 )
				);
				$value .= '<br><code>' . esc_html( implode( ', ', (array) ( $last['files'] ?? [] ) ) ) . '</code>';
			}
			$rows[] = [ 'label' => __( 'Zadnje generiranje', $td ), 'value' => $value ];
		}

		$status = $this->scheduler->status();
		$rows[] = [ 'label' => __( 'Sljedeće generiranje', $td ), 'value' => $this->when( $status[ Scheduler::HOOK_GENERATE ] ?? null ) ];
		$rows[] = [ 'label' => __( 'Sljedeća provjera cijena', $td ), 'value' => $this->when( $status[ Scheduler::HOOK_SWEEP ] ?? null ) ];
		if ( ! empty( $status[ Scheduler::HOOK_AUTO_SNAPSHOT ] ) ) {
			$rows[] = [ 'label' => __( 'Zakazano automatsko snimanje', $td ), 'value' => $this->when( $status[ Scheduler::HOOK_AUTO_SNAPSHOT ] ) ];
		}
		$rows[] = [ 'label' => __( 'Mehanizam zakazivanja', $td ), 'value' => 'action_scheduler' === $this->scheduler->backend()->name() ? 'Action Scheduler' : 'WP-Cron' ];

		$base   = home_url( '/' . trim( (string) $this->settings->get( 'price_list.slug', 'cjenik' ), '/' ) . '/' );
		$links  = [];
		foreach ( [ '' => __( 'Popis', $td ), 'latest.xml' => 'latest.xml', 'latest.csv' => 'latest.csv', 'index.json' => 'index.json' ] as $path => $label ) {
			$links[] = '<a href="' . esc_url( $base . $path ) . '" target="_blank" rel="noopener">' . esc_html( (string) $label ) . '</a>';
		}
		$rows[] = [ 'label' => __( 'Javni URL-ovi', $td ), 'value' => implode( ' · ', $links ) ];
		$key    = (string) $this->settings->get( 'price_list.external_cron_key', '' );
		if ( '' !== $key ) {
			$url    = $base . '?scwc_run=1&key=' . rawurlencode( $key );
			$rows[] = [ 'label' => __( 'Vanjski cron (GET)', $td ), 'value' => '<code>' . esc_html( $url ) . '</code>' ];
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
