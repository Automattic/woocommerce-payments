<?php
/**
 * Main functions to start WC_Payments_Multi_Currency class.
 *
 * @package WooCommerce\Payments
 */

defined( 'ABSPATH' ) || exit;

// Include the main WC_Payments_Multi_Currency class.
if ( ! class_exists( 'WC_Payments_Multi_Currency', false ) ) {
	include_once WCPAY_ABSPATH . 'includes/multi-currency/class-wc-payments-multi-currency.php';
}

/**
 * Returns the main instance of WC_Payments_Multi_Currency.
 *
 * @return WC_Payments_Multi_Currency
 */
function WC_Payments_Multi_Currency() { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid
	return WC_Payments_Multi_Currency::instance();
}

add_action( 'plugins_loaded', 'WC_Payments_Multi_Currency', 12 );
