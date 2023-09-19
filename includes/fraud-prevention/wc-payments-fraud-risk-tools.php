<?php
/**
 * Main functions to start Fraud & Risk tools class.
 *
 * @package WooCommerce\Payments
 */

defined( 'ABSPATH' ) || exit;

/**
 * Returns the main instance of Fraud and Risk tools.
 *
 * @return WCPay\Fraud_Prevention\Fraud_Risk_Tools
 */
function WC_Payments_Fraud_Risk_Tools() { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid
	return WCPay\Fraud_Prevention\Fraud_Risk_Tools::instance();
}

add_action( 'plugins_loaded', 'WC_Payments_Fraud_Risk_Tools', 12 );
