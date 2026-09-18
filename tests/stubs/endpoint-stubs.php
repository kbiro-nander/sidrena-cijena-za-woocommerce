<?php
declare(strict_types=1);

if ( ! function_exists( 'add_rewrite_rule' ) ) {
	function add_rewrite_rule( $regex, $query, $after = 'bottom' ) { $GLOBALS['scwc_test_rewrite'][ $regex ] = $query; }
}
if ( ! function_exists( 'status_header' ) ) {
	function status_header( $code, $description = '' ) { $GLOBALS['scwc_test_status'] = $code; }
}
