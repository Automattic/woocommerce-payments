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

/**
 * Returns the MultiCurrency singleton.
 *
 * This function is declared unconditionally, on purpose: PHP hoists top-level function declarations
 * at compile time, so the early `return` below never prevented it from existing, and callers across
 * the plugin and in third-party code — including `MultiCurrency::instance()`, which delegates here —
 * rely on it being available whether or not the feature is enabled. When the module is disabled
 * (see `MultiCurrency::is_enabled()`), the instance is inert: it registers no hooks, and
 * `MultiCurrency::init()` refuses to initialize, so its getters return empty collections and the
 * store currency.
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

		if ( WCPay\MultiCurrency\MultiCurrency::is_enabled() ) {
			$instance->init_hooks();
		}
	}

	return $instance;
}

if ( ! WCPay\MultiCurrency\MultiCurrency::is_enabled() ) {
	return;
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
