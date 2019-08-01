<?php
/**
 * PHPUnit bootstrap file
 *
 * @package WooCommerce\Payments
 */

define( 'TEST_PLUGIN_DIR', dirname( dirname( dirname( __FILE__ ) ) ) . '/' );

// Load the Composer autoloader.
require_once TEST_PLUGIN_DIR . 'vendor/autoload.php';

// Bootstrap WP Mock.
WP_Mock::bootstrap();
