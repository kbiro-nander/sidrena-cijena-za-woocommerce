<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
// Patchwork must be loaded BEFORE any stub file so Brain Monkey can redefine those functions.
require_once __DIR__ . '/../vendor/antecedent/patchwork/Patchwork.php';

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/stubs/' );
}
if ( ! defined( 'SCWC_VERSION' ) ) {
	define( 'SCWC_VERSION', '0.0.0-test' );
}
if ( ! defined( 'SCWC_PLUGIN_FILE' ) ) {
	define( 'SCWC_PLUGIN_FILE', dirname( __DIR__ ) . '/sidrena-cijena-za-woocommerce.php' );
}
if ( ! defined( 'SCWC_PLUGIN_DIR' ) ) {
	define( 'SCWC_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
}
if ( ! defined( 'WC_VERSION' ) ) {
	define( 'WC_VERSION', '9.9.0' );
}
if ( ! defined( 'ARRAY_A' ) ) {
	define( 'ARRAY_A', 'ARRAY_A' );
	define( 'OBJECT', 'OBJECT' );
}
if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'MINUTE_IN_SECONDS', 60 );
	define( 'HOUR_IN_SECONDS', 3600 );
	define( 'DAY_IN_SECONDS', 86400 );
	define( 'WEEK_IN_SECONDS', 604800 );
}

require_once __DIR__ . '/stubs/wc-classes.php';
require_once __DIR__ . '/stubs/wp-functions.php';
require_once __DIR__ . '/stubs/wc-functions.php';
// Additional per-area stub files (alphabetical); each function must be guarded with function_exists().
foreach ( glob( __DIR__ . '/stubs/*-stubs.php' ) ?: [] as $scwc_stub_file ) {
	require_once $scwc_stub_file;
}

// A fake $wpdb so services that touch the database can be constructed in tests.
$GLOBALS['wpdb'] = new SidrenaCijena\Tests\Support\FakeWpdb();
