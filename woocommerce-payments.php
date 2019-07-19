<?php
/**
 * Plugin Name: WooCommerce Payments
 * Plugin URI: https://github.com/Automattic/woocommerce-payments
 * Description: Feature plugin for accepting payments via a WooCommerce-branded payment gateway.
 * Author: Automattic
 * Author URI: https://woocommerce.com/
 * Text Domain: woocommerce-payments
 * Domain Path: /languages
 * WC requires at least: 3.6
 * WC tested up to: 3.6.4
 * Version: 0.2.0
 *
 * @package WooCommerce\Payments
 */

define( 'WCPAY_WC_ADMIN_VERSION_REQUIRED', '0.15' );
// When a WooCommerce version that bundles WC-Admin is released, set it here if it satisfies our requirements.
define( 'WCPAY_WC_MIN_VERSION_WITH_WC_ADMIN', false );

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

define( 'WCPAY_PLUGIN_FILE', __FILE__ );
define( 'WCPAY_ABSPATH', dirname( WCPAY_PLUGIN_FILE ) . '/' );

/**
 * Initialize the extension. Note that this gets called on the "plugins_loaded" filter,
 * so WooCommerce classes are guaranteed to exist at this point (if WooCommerce is enabled).
 */
function wcpay_init() {
	include_once dirname( __FILE__ ) . '/includes/class-wc-payments.php';
	WC_Payments::init();
}

add_action( 'plugins_loaded', 'wcpay_init' );
