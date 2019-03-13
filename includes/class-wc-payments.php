<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Main class for the WooCommerce Payments extension. Its responsibility is to initialize the extension.
 */
class WC_Payments {

	/**
	 * Entry point to the initialization logic.
	 * @param $plugin_name string The extension's plugin name. It will be "woocommerce-payments/woocommerce-payments.php" unless the user renamed the plugin directory.
	 */
	public static function init( $plugin_name ) {
		// TODO: (#7) bail if WooCommerce / WordPress / Gutenberg / WC-Admin versions required are not present

		include_once dirname( __FILE__ ) . '/class-wc-payment-gateway-wcpay.php';
		add_filter( 'plugin_action_links_' . $plugin_name, array( __CLASS__, 'add_plugin_links' ) );
		add_filter( 'woocommerce_payment_gateways', array( __CLASS__, 'register_gateway' ) );
	}

	/**
	 * Adds links to the plugin's row in the "Plugins" Wp-Admin page.
	 * @see https://codex.wordpress.org/Plugin_API/Filter_Reference/plugin_action_links_(plugin_file_name)
	 *
	 * @param $links array The existing list of links that will be rendered.
	 *
	 * @return array The list of links that will be rendered, after adding some links specific to this plugin.
	 */
	public static function add_plugin_links( $links ) {
		$plugin_links = array(
			'<a href="' . esc_attr( WC_Payment_Gateway_WCPay::get_settings_url() ) . '">' . esc_html__( 'Settings', 'woocommerce-payments' ) . '</a>',
		);

		return array_merge( $plugin_links, $links );
	}

	/**
	 * Adds the WooCommerce Payments' gateway class to the list of installed payment gateways.
	 * @param $gateways array Existing list of gateway classes that will be available for the merchant to configure.
	 *
	 * @return array The list of payment gateways that will be available, including WooCommerce Payments' Gateway class.
	 */
	public static function register_gateway( $gateways ) {
		$gateways[] = 'WC_Payment_Gateway_WCPay';
		return $gateways;
	}

}
