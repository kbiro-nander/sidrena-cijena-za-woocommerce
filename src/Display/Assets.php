<?php
/**
 * Front-end assets.
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

namespace SidrenaCijena\Display;

use SidrenaCijena\Settings\Settings;

final class Assets {

	public function __construct( private readonly Settings $settings ) {}

	public function register(): void {
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue' ] );
	}

	public function enqueue(): void {
		if ( ! (bool) $this->settings->get( 'display.load_css', true ) ) {
			return;
		}
		wp_enqueue_style( 'scwc-frontend', SCWC_PLUGIN_URL . 'assets/css/frontend.css', [], SCWC_VERSION );
	}
}
