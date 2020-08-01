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
	echo "Could not find $_tests_dir/includes/functions.php, have you run bin/install-wp-tests.sh ?" . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit( 1 );
}

// Give access to tests_add_filter() function.
require_once $_tests_dir . '/includes/functions.php';


/**
 * Manually load the plugin being tested.
 */
function _manually_load_plugin() {
	// NOTE: this will skip the dependency check so the plugin can load. The test environment
	// needs to still make sure that all dependencies exist for it to successfully run.
	define( 'WCPAY_TEST_ENV', true );

	// Load the WooCommerce plugin so we can use its classes in our WooCommerce Payments plugin.
	require_once ABSPATH . '/wp-content/plugins/woocommerce/woocommerce.php';

	require dirname( dirname( __FILE__ ) ) . '/woocommerce-payments.php';

	require_once dirname( __FILE__ ) . '/../includes/class-wc-payments-db.php';
	require_once dirname( __FILE__ ) . '/../includes/wc-payment-api/models/class-wc-payments-api-charge.php';
	require_once dirname( __FILE__ ) . '/../includes/wc-payment-api/models/class-wc-payments-api-intention.php';
	require_once dirname( __FILE__ ) . '/../includes/wc-payment-api/class-wc-payments-api-client.php';
	require_once dirname( __FILE__ ) . '/../includes/wc-payment-api/class-wc-payments-http.php';

	require_once dirname( __FILE__ ) . '/../includes/exceptions/class-wc-payments-rest-request-exception.php';
	require_once dirname( __FILE__ ) . '/../includes/admin/class-wc-payments-rest-controller.php';
	require_once dirname( __FILE__ ) . '/../includes/admin/class-wc-rest-payments-webhook-controller.php';
	require_once dirname( __FILE__ ) . '/../includes/admin/tracks/class-tracker.php';
}

tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

// Start up the WP testing environment.
require $_tests_dir . '/includes/bootstrap.php';
