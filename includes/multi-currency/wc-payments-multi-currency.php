<?php
/**
 * Main functions to start Multi_Currency class.
 *
 * @package WooCommerce\Payments
 */

defined( 'ABSPATH' ) || exit;

// Include the main Multi_Currency class.
if ( ! class_exists( 'WCPay\Multi_Currency\Multi_Currency', false ) ) {
	include_once WCPAY_ABSPATH . 'includes/multi-currency/class-multi-currency.php';
}



/**
 * Returns the main instance of Multi_Currency.
 *
 * @return Multi_Currency
 */
function WC_Payments_Multi_Currency() { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid
	return WCPay\Multi_Currency\Multi_Currency::instance();
}

add_action( 'plugins_loaded', 'WC_Payments_Multi_Currency', 12 );
