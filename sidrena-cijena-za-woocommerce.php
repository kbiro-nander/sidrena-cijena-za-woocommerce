<?php
/**
 * Plugin Name:          Sidrena cijena za WooCommerce
 * Plugin URI:           https://github.com/kbiro-nander/sidrena-cijena-za-woocommerce
 * Description:          Sidrena (dodatna) cijena uz svaku cijenu, najniža cijena u 30 dana prije sniženja i strojno čitljiv cjenik (XML/CSV) prema NN 101/2026 i Zakonu o zaštiti potrošača.
 * Version:              1.1.3
 * Requires at least:    6.4
 * Requires PHP:         8.1
 * Requires Plugins:     woocommerce
 * WC requires at least: 9.0
 * WC tested up to:      9.9
 * Author:               Kristijan Biro
 * Author URI:           https://github.com/kbiro-nander
 * License:              MIT
 * License URI:          https://opensource.org/licenses/MIT
 * Text Domain:          sidrena-cijena-za-woocommerce
 * Domain Path:          /languages
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

define( 'SCWC_VERSION', '1.1.3' );
define( 'SCWC_PLUGIN_FILE', __FILE__ );
define( 'SCWC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SCWC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

$scwc_autoload = SCWC_PLUGIN_DIR . 'vendor/autoload.php';
if ( ! is_readable( $scwc_autoload ) ) {
	add_action(
		'admin_notices',
		static function (): void {
			echo '<div class="notice notice-error"><p>Sidrena cijena za WooCommerce: nedostaje <code>vendor/autoload.php</code>. Pokrenite <code>composer install --no-dev</code>.</p></div>';
		}
	);
	return;
}
require_once $scwc_autoload;

add_action(
	'before_woocommerce_init',
	static function (): void {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
		}
	}
);

register_activation_hook( __FILE__, [ \SidrenaCijena\Lifecycle\Activator::class, 'activate' ] );
register_deactivation_hook( __FILE__, [ \SidrenaCijena\Lifecycle\Deactivator::class, 'deactivate' ] );

add_action(
	'plugins_loaded',
	static function (): void {
		\SidrenaCijena\Plugin::instance()->boot();
	},
	20
);
