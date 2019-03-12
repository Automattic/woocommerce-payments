<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WC_Payments {

	public static function init( $plugin_name ) {
		// TODO: (#7) bail if WooCommerce / WordPress / Gutenberg / WC-Admin versions required are not present

		include_once dirname( __FILE__ ) . '/class-wc-payment-gateway-wcpay.php';
		add_filter( 'plugin_action_links_' . $plugin_name, array( __CLASS__, 'add_plugin_links' ) );
		add_filter( 'woocommerce_payment_gateways', array( __CLASS__, 'register_gateway' ) );
	}

	public static function add_plugin_links( $links ) {
		$plugin_links = array(
			'<a href="' . esc_attr( WC_Payment_Gateway_WCPay::get_settings_url() ) . '">' . esc_html__( 'Settings', 'woocommerce-payments' ) . '</a>',
		);

		return array_merge( $plugin_links, $links );
	}

	public static function register_gateway( $gateways ) {
		$gateways[] = 'WC_Payment_Gateway_WCPay';
		return $gateways;
	}

}
