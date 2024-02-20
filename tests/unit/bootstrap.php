<?php
/**
 * PHPUnit bootstrap file
 *
 * @package WooCommerce\Payments
 */

use WCPay\Container;
use WCPay\Internal\DependencyManagement\ExtendedContainer;

$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	echo "Could not find $_tests_dir/includes/functions.php, have you run bin/install-wp-tests.sh ?" . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit( 1 );
}

if ( PHP_VERSION_ID >= 80000 && file_exists( $_tests_dir . '/includes/phpunit7/MockObject' ) ) {
	// WP Core test library includes patches for PHPUnit 7 to make it compatible with PHP 8+.
	require_once $_tests_dir . '/includes/phpunit7/MockObject/Builder/NamespaceMatch.php';
	require_once $_tests_dir . '/includes/phpunit7/MockObject/Builder/ParametersMatch.php';
	require_once $_tests_dir . '/includes/phpunit7/MockObject/InvocationMocker.php';
	require_once $_tests_dir . '/includes/phpunit7/MockObject/MockMethod.php';
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

	// Load the WooCommerce plugin so we can use its classes in our WooPayments plugin.
	require_once WP_PLUGIN_DIR . '/woocommerce/woocommerce.php';

	// Set a default currency to be used for the multi-currency tests because the default
	// is not loaded even though it's set during the tests setup.
	update_option( 'woocommerce_currency', 'USD' );

	// Enable the WCPay Subscriptions feature flag in tests to ensure we can test
	// subscriptions funtionality.
	add_filter(
		'pre_option__wcpay_feature_subscriptions',
		function() {
			return '1';
		}
	);

	update_option( '_wcpay_feature_allow_subscription_migrations', '1' );

	$_plugin_dir = dirname( __FILE__ ) . '/../../';

	require $_plugin_dir . 'woocommerce-payments.php';

	require_once $_plugin_dir . 'includes/class-wc-payments-db.php';
	require_once $_plugin_dir . 'includes/wc-payment-api/models/class-wc-payments-api-charge.php';
	require_once $_plugin_dir . 'includes/wc-payment-api/models/class-wc-payments-api-abstract-intention.php';
	require_once $_plugin_dir . 'includes/wc-payment-api/models/class-wc-payments-api-payment-intention.php';
	require_once $_plugin_dir . 'includes/wc-payment-api/models/class-wc-payments-api-setup-intention.php';
	require_once $_plugin_dir . 'includes/wc-payment-api/class-wc-payments-api-client.php';
	require_once $_plugin_dir . 'includes/wc-payment-api/class-wc-payments-http-interface.php';
	require_once $_plugin_dir . 'includes/wc-payment-api/class-wc-payments-http.php';

	// Load the gateway files, so subscriptions can be tested.
	require_once $_plugin_dir . 'includes/compat/subscriptions/trait-wc-payments-subscriptions-utilities.php';
	require_once $_plugin_dir . 'includes/compat/subscriptions/trait-wc-payment-gateway-wcpay-subscriptions.php';
	require_once $_plugin_dir . 'includes/class-wc-payment-gateway-wcpay.php';

	require_once $_plugin_dir . 'includes/exceptions/class-rest-request-exception.php';
	require_once $_plugin_dir . 'includes/admin/class-wc-payments-admin.php';
	require_once $_plugin_dir . 'includes/admin/class-wc-payments-admin-settings.php';
	require_once $_plugin_dir . 'includes/admin/class-wc-payments-admin-sections-overwrite.php';
	require_once $_plugin_dir . 'includes/admin/class-wc-payments-rest-controller.php';
	require_once $_plugin_dir . 'includes/admin/class-wc-rest-payments-accounts-controller.php';
	require_once $_plugin_dir . 'includes/admin/class-wc-rest-payments-orders-controller.php';
	require_once $_plugin_dir . 'includes/admin/class-wc-rest-payments-fraud-outcomes-controller.php';
	require_once $_plugin_dir . 'includes/admin/class-wc-rest-payments-onboarding-controller.php';
	require_once $_plugin_dir . 'includes/admin/class-wc-rest-payments-webhook-controller.php';
	require_once $_plugin_dir . 'includes/admin/class-wc-rest-payments-terminal-locations-controller.php';
	require_once $_plugin_dir . 'includes/admin/class-wc-rest-payments-tos-controller.php';
	require_once $_plugin_dir . 'includes/admin/class-wc-rest-payments-settings-controller.php';
	require_once $_plugin_dir . 'includes/admin/tracks/class-tracker.php';
	require_once $_plugin_dir . 'includes/admin/class-wc-rest-payments-reader-controller.php';
	require_once $_plugin_dir . 'includes/admin/class-wc-rest-payments-files-controller.php';
	require_once $_plugin_dir . 'includes/reports/class-wc-rest-payments-reports-transactions-controller.php';
	require_once $_plugin_dir . 'includes/reports/class-wc-rest-payments-reports-authorizations-controller.php';
	require_once $_plugin_dir . 'includes/admin/class-wc-rest-payments-payment-intents-controller.php';
	require_once $_plugin_dir . 'includes/class-woopay-tracker.php';
	require_once $_plugin_dir . 'includes/admin/class-wc-rest-payments-customer-controller.php';
	require_once $_plugin_dir . 'includes/admin/class-wc-rest-payments-refunds-controller.php';

	// Load currency helper class early to ensure its implementation is used over the one resolved during further test initialization.
	require_once __DIR__ . '/helpers/class-wc-helper-site-currency.php';

	// Assist testing methods and classes with keyword `final`.
	// Woo Core uses the similar approach from this package, and implements it as class `CodeHacker`.
	DG\BypassFinals::enable( false, true );
	DG\BypassFinals::setWhitelist(
		[
			'*/AbstractSessionRateLimiter.php',
		]
	);
}

tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

// Need those polyfills to run tests in CI.
require_once dirname( __FILE__ ) . '/../../vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php';

// Start up the WP testing environment.
require $_tests_dir . '/includes/bootstrap.php';
require dirname( __FILE__ ) . '/../WCPAY_UnitTestCase.php';

// We use outdated PHPUnit version, which emits deprecation errors in PHP 7.4 (deprecated reflection APIs).
if ( defined( 'PHP_VERSION_ID' ) && PHP_VERSION_ID >= 70400 ) {
	error_reporting( error_reporting() ^ E_DEPRECATED ); // phpcs:ignore
}

/**
 * Don't init the subscriptions-core package when running WCPAY unit tests.
 *
 * Init'ing the subscriptions-core loads all subscriptions class and hooks, which breaks existing WCPAY unit tests.
 * WCPAY already mocks the WC Subscriptions classes/functions it needs so there's no need to load them anyway.
 *
 * This function should only be used to load any mocked Subscriptions Core classes that need to be loaded before the PHPUnit FileLoader.
 */
function wcpay_init_subscriptions_core() {
	require_once __DIR__ . '/helpers/class-wcs-helper-background-repairer.php';
}

// Placeholder for the test container.
$GLOBALS['wcpay_test_container'] = null;

/**
 * Extracts the internal ExtendedContainer instance of the WCPay container.
 *
 * This allows full access to the full ExtendedContainer functionality,
 * rather than only to the non-test `get` and `has` methods of the container.
 *
 * @throws Exception In case the container is not available.
 * @return ExtendedContainer The extended container.
 */
function wcpay_get_test_container() {
	if ( $GLOBALS['wcpay_test_container'] instanceof ExtendedContainer ) {
		return $GLOBALS['wcpay_test_container'];
	}

	$container = $GLOBALS['wcpay_container'] ?? null;
	if ( ! $container instanceof Container ) {
		if ( is_null( $container ) ) {
			$container = wcpay_get_container();
		} else {
			throw new Exception( 'Tests require the WCPay dependency container to be set up.' );
		}
	}

	// Load the property through reflection.
	$property = new ReflectionProperty( $container, 'container' );
	$property->setAccessible( true );
	$extended_container = $property->getValue( $container );

	return $extended_container;
}
