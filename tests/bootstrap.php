<?php
/**
 * PHPUnit bootstrap file
 *
 * @package WooCommerce\Payments
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	echo "Could not find $_tests_dir/includes/functions.php, have you run bin/install-wp-tests.sh ?" . PHP_EOL; // WPCS: XSS ok.
	exit( 1 );
}

// Give access to tests_add_filter() function.
require_once $_tests_dir . '/includes/functions.php';

/**
 * Returns WooCommerce main directory.
 *
 * @return string
 */
function wc_dir() {
	return dirname( dirname( dirname( __FILE__ ) ) ) . '/woocommerce';
}

/**
 * Manually load the plugin being tested.
 */
function _manually_load_plugin() {
	// Load the WooCommerce plugin so we can use its classes in our WooCommerce Payments plugin.
	require_once wc_dir() . '/woocommerce.php';

	require dirname( dirname( __FILE__ ) ) . '/woocommerce-payments.php';

	require_once dirname( __FILE__ ) . '/../includes/wc-payment-api/models/class-wc-payments-api-charge.php';
	require_once dirname( __FILE__ ) . '/../includes/wc-payment-api/models/class-wc-payments-api-intention.php';
	require_once dirname( __FILE__ ) . '/../includes/wc-payment-api/class-wc-payments-api-client.php';
	require_once dirname( __FILE__ ) . '/../includes/wc-payment-api/class-wc-payments-http.php';
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

// Start up the WP testing environment.
require $_tests_dir . '/includes/bootstrap.php';
