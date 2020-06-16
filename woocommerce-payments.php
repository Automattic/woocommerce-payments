<?php
/**
 * Plugin Name: WooCommerce Payments
 * Plugin URI: https://woocommerce.com/payments/
 * Description: Accept payments via credit card. Manage transactions within WordPress.
 * Author: Automattic
 * Author URI: https://woocommerce.com/
 * Text Domain: woocommerce-payments
 * Domain Path: /languages
 * WC requires at least: 4.0
 * WC tested up to: 4.1
 * Requires WP: 5.3
 * Version: 1.0.1
 *
 * @package WooCommerce\Payments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

define( 'WCPAY_PLUGIN_FILE', __FILE__ );
define( 'WCPAY_ABSPATH', dirname( WCPAY_PLUGIN_FILE ) . '/' );
define( 'WCPAY_MIN_WC_ADMIN_VERSION', '0.23.2' );

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
