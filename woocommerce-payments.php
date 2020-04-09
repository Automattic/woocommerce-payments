<?php
/**
 * Plugin Name: WooCommerce Payments
 * Plugin URI: https://github.com/Automattic/woocommerce-payments
 * Description: Feature plugin for accepting payments via a WooCommerce-branded payment gateway.
 * Author: Automattic
 * Author URI: https://woocommerce.com/
 * Text Domain: woocommerce-payments
 * Domain Path: /languages
 * WC requires at least: 3.9
 * WC tested up to: 4.0
 * Requires WP: 5.3
 * Version: 0.8.3-dev
 *
 * @package WooCommerce\Payments
 *
 * Woo: 5278104:8ed5c1451e548223478370a6b0652bd4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

define( 'WCPAY_PLUGIN_FILE', __FILE__ );
define( 'WCPAY_ABSPATH', dirname( WCPAY_PLUGIN_FILE ) . '/' );
define( 'WCPAY_MIN_WC_ADMIN_VERSION', '0.23.2' );

require_once WCPAY_ABSPATH . 'vendor/autoload_packages.php';

/**
 * Initialize the Jetpack connection functionality.
 */
function wcpay_jetpack_init() {
	$jetpack_config = new Automattic\Jetpack\Config();
	$jetpack_config->ensure( 'connection' );
}

// The Jetpack initialization needs to be run with higher priority.
add_action( 'plugins_loaded', 'wcpay_jetpack_init', 1 );

/**
 * Initialize the extension. Note that this gets called on the "plugins_loaded" filter,
 * so WooCommerce classes are guaranteed to exist at this point (if WooCommerce is enabled).
 */
function wcpay_init() {
	require_once WCPAY_ABSPATH . '/includes/class-wc-payments.php';
	WC_Payments::init();
}

// Make sure this is run *after* WooCommerce has a chance to initialize its packages (wc-admin, etc). That is run with priority 10.
add_action( 'plugins_loaded', 'wcpay_init', 11 );
