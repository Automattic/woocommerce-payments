<?php
/**
 * Main functions to start MultiCurrency class.
 *
 * @package WooCommerce\Payments
 */

defined( 'ABSPATH' ) || exit;

/**
 * Load customer multi-currency if feature is enabled or if it is the setup page.
 */
function wcpay_multi_currency_onboarding_check() {
	$is_setup_page = false;

	// Skip checking the HTTP referer if it is a cron job.
	if ( ! defined( 'DOING_CRON' ) ) {
		$http_referer = sanitize_text_field( wp_unslash( $_SERVER['HTTP_REFERER'] ?? '' ) );
		if ( ! empty( $http_referer ) ) {
			$is_setup_page = strpos( $http_referer, 'multi-currency-setup' ) !== false;
		}
	}

	return $is_setup_page;
}

if ( ! WC_Payments_Features::is_customer_multi_currency_enabled() && ! wcpay_multi_currency_onboarding_check() ) {
	return;
}

/**
 * Returns the MultiCurrency singleton.
 *
 * @return WCPay\MultiCurrency\MultiCurrency
 */
function WC_Payments_Multi_Currency() { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid
	static $instance = null;

	if ( is_null( $instance ) ) {
		$instance = new WCPay\MultiCurrency\MultiCurrency(
			WC_Payments::get_settings_service(),
			WC_Payments::get_payments_api_client(),
			WC_Payments::get_account_service(),
			WC_Payments::get_localization_service(),
			WC_Payments::get_database_cache()
		);

		// Exclude deprecated currencies from multi-currency.
		add_filter(
			'wcpay_multi_currency_available_currencies',
			function ( $available_currencies ) {
				// Remove BGN (Bulgarian Lev) - Bulgaria transitioned to EUR in 2025.
				// Only remove if not currently enabled in multi-currency.
				$enabled_currencies = get_option( 'wcpay_multi_currency_enabled_currencies', [] );
				// Handle edge case where option might be stored as string instead of array.
				if ( ! is_array( $enabled_currencies ) ) {
					return $available_currencies;
				}
				if ( ! in_array( 'BGN', $enabled_currencies, true ) ) {
					$available_currencies = array_diff( $available_currencies, [ 'BGN' ] );
				}
				return $available_currencies;
			}
		);

		$instance->init_hooks();
	}

	return $instance;
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
