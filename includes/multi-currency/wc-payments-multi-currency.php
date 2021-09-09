<?php
/**
 * Main functions to start MultiCurrency class.
 *
 * @package WooCommerce\Payments
 */

defined( 'ABSPATH' ) || exit;

/**
 * Returns the main instance of MultiCurrency.
 *
 * @return WCPay\MultiCurrency\MultiCurrency
 */
function WC_Payments_Multi_Currency() { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid
	return WCPay\MultiCurrency\MultiCurrency::instance();
}

add_action( 'plugins_loaded', 'WC_Payments_Multi_Currency', 12 );

register_deactivation_hook( WCPAY_PLUGIN_FILE, 'wcpay_multi_currency_deactivated' );

/**
 * Plugin deactivation hook.
 */
function wcpay_multi_currency_deactivated() {
	WCPay\MultiCurrency\MultiCurrency::remove_woo_admin_notes();
}

if ( ! function_exists( 'wc_get_currency_switcher_markup' ) ) {
	/**
	 * Gets the switcher widget markup.
	 *
	 * @param array $instance The widget's instance settings.
	 * @param array $args     The widget's arguments.
	 *
	 * @return string The widget markup.
	 */
	function wc_get_currency_switcher_markup( array $instance = [], array $args = [] ): string {
		return WC_Payments_Multi_Currency()->get_switcher_widget_markup( $instance, $args );
	}
}
