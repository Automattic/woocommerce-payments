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
 * Declared unconditionally, on purpose: PHP hoists top-level function declarations at compile time,
 * so the early `return` below never prevented this function from existing, and callers here and in
 * third-party code rely on it whether or not the module is active.
 *
 * `init_hooks()` stays unconditional too. Everything that changes what the store does is registered
 * from `MultiCurrency::init()`, which refuses to run when `MultiCurrency::is_active()` is false;
 * what is left here is the admin screens and REST routes an unconfigured store still needs to
 * switch the feature on.
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
		$instance->init_hooks();
	}

	return $instance;
}

// Skip the bootstrap when the merchant saved the setting as off. This is not what keeps the module
// inert — PHP hoists the function above, so callers construct it whether or not this file returns
// here. `MultiCurrency::is_active()` is what decides.
if ( false === WC_Payments_Features::get_saved_customer_multi_currency_preference() && ! wcpay_multi_currency_onboarding_check() ) {
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
