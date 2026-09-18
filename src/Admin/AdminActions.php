<?php
/**
 * admin-post.php handlers for the settings page buttons (generate now, sweep, reschedule).
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

namespace SidrenaCijena\Admin;

final class AdminActions {

	public const ACTIONS = [ 'scwc_generate_now', 'scwc_run_sweep', 'scwc_reschedule' ];

	/** @var callable():?string */
	private $generate;
	/** @var callable():void */
	private $sweep;
	/** @var callable():void */
	private $reschedule;
	/** @var callable():array<string,mixed> */
	private $input;
	/** @var callable():void */
	private $exit;

	/**
	 * @param callable():?string             $generate   Runs generation; returns an error message or null.
	 * @param callable():void                $sweep      Runs a full sweep (first page + chain).
	 * @param callable():void                $reschedule Reschedules all jobs.
	 * @param callable():array<string,mixed> $input      Request input (default $_GET).
	 * @param callable():void|null           $exit       Terminates the request after the redirect (tests inject a no-op).
	 */
	public function __construct( callable $generate, callable $sweep, callable $reschedule, ?callable $input = null, ?callable $exit = null ) {
		$this->generate   = $generate;
		$this->sweep      = $sweep;
		$this->reschedule = $reschedule;
		$this->input      = $input ?? static fn(): array => $_GET; // phpcs:ignore WordPress.Security.NonceVerification
		$this->exit       = $exit ?? static function (): void {
			exit;
		};
	}

	public function register(): void {
		foreach ( self::ACTIONS as $action ) {
			add_action( 'admin_post_' . $action, fn() => $this->handle( $action ) );
		}
		add_action( 'admin_notices', [ $this, 'notice' ] );
	}

	public function handle( string $action ): void {
		$input = ( $this->input )();
		if ( ! current_user_can( 'manage_woocommerce' ) || ! wp_verify_nonce( (string) ( $input['_wpnonce'] ?? '' ), $action ) ) {
			wp_die( esc_html__( 'Nedozvoljen zahtjev.', 'sidrena-cijena-za-woocommerce' ) );
		}
		$result = '';
		$error  = '';
		switch ( $action ) {
			case 'scwc_generate_now':
				$error  = (string) ( ( $this->generate )() ?? '' );
				$result = '' === $error ? 'generated' : 'error';
				break;
			case 'scwc_run_sweep':
				( $this->sweep )();
				$result = 'swept';
				break;
			case 'scwc_reschedule':
				( $this->reschedule )();
				update_option( 'scwc_flush_rewrite', 1 ); // Also re-registers the /cjenik/ routes on the next request.
				$result = 'rescheduled';
				break;
		}
		$args = [
			'page'        => 'scwc-settings',
			'tab'         => 'price_list',
			'scwc_result' => $result,
		];
		if ( '' !== $error ) {
			$args['scwc_error'] = $error;
		}
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		( $this->exit )();
	}

	public function notice(): void {
		$input  = ( $this->input )();
		$result = (string) ( $input['scwc_result'] ?? '' );
		if ( '' === $result ) {
			return;
		}
		$messages = [
			'generated'   => __( 'Cjenik je generiran.', 'sidrena-cijena-za-woocommerce' ),
			'swept'       => __( 'Provjera cijena je pokrenuta.', 'sidrena-cijena-za-woocommerce' ),
			'rescheduled' => __( 'Zadaci su ponovno zakazani.', 'sidrena-cijena-za-woocommerce' ),
		];
		if ( 'error' === $result ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html(
				sprintf(
				/* translators: %s: error message */
					__( 'Generiranje cjenika nije uspjelo: %s', 'sidrena-cijena-za-woocommerce' ),
					sanitize_text_field( wp_unslash( (string) ( $input['scwc_error'] ?? '' ) ) )
				)
			) . '</p></div>';
			return;
		}
		if ( isset( $messages[ $result ] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $messages[ $result ] ) . '</p></div>';
		}
	}
}
