<?php
/**
 * Plugin Name: WooCommerce Payments
 * Plugin URI: https://github.com/Automattic/woocommerce-payments
 * Description: Feature plugin for accepting payments via a WooCommerce-branded payment gateway.
 * Author: Automattic
 * Author URI: https://woocommerce.com/
 * Text Domain: woocommerce-payments
 * Domain Path: /languages
 * Version: 0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Initialize the extension. Note that this gets called on the "plugins_loaded" filter,
 * so WooCommerce classes are guaranteed to exist at this point (if WooCommerce is enabled).
 */
function wcpay_init() {
	include_once dirname( __FILE__ ) . '/includes/class-wc-payments.php';
	WC_Payments::init( plugin_basename( __FILE__ ) );
}

add_action( 'plugins_loaded', 'wcpay_init' );
