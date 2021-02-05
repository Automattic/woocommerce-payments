<?php
/**
 * Class WC_Payments_Blocks_Payment_Method
 *
 * @package WooCommerce\Payments
 */

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * The payment method, which allows the gateway to work with WooCommerce Blocks.
 */
class WC_Payments_Blocks_Payment_Method extends AbstractPaymentMethodType {
	/**
	 * Initializes the class.
	 */
	public function initialize() {
		$this->name     = WC_Payment_Gateway_WCPay::GATEWAY_ID;
		$this->settings = get_option( 'woocommerce_woocommerce_payments_settings', [] );
	}

	/**
	 * Checks whether the gateway is active.
	 *
	 * @return boolean True when active.
	 */
	public function is_active() {
		return WC_Payments::get_gateway()->is_available();
	}

	/**
	 * Defines all scripts, necessary for the payment method.
	 *
	 * @return string[] A list of script handles.
	 */
	public function get_payment_method_script_handles() {
		wp_register_script(
			'stripe',
			'https://js.stripe.com/v3/',
			[],
			'3.0',
			true
		);

		wp_register_script(
			'wc-payment-method-wcpay',
			plugins_url( 'dist/blocks-checkout.js', WCPAY_PLUGIN_FILE ),
			[ 'stripe' ],
			'1.0.1',
			true
		);

		return [ 'wc-payment-method-wcpay' ];
	}

	/**
	 * Loads the data about the gateway, which will be exposed in JavaScript.
	 *
	 * @return array An associative array, containing all cecessary values.
	 */
	public function get_payment_method_data() {
		return array_merge(
			[
				'title'       => isset( $this->settings['title'] ) ? $this->settings['title'] : '',
				'description' => isset( $this->settings['description'] ) ? $this->settings['description'] : '',
			],
			WC_Payments::get_gateway()->get_payment_fields_js_config()
		);
	}
}
