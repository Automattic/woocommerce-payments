<?php
/**
 * Class WC_Payments_Blocks_Payment_Method
 *
 * @package WooCommerce\Payments
 */

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use WCPay\Payment_Methods\CC_Payment_Gateway;

/**
 * The payment method, which allows the gateway to work with WooCommerce Blocks.
 */
class WC_Payments_Blocks_Payment_Method extends AbstractPaymentMethodType {
	/**
	 * Initializes the class.
	 */
	public function initialize() {
		$this->name    = CC_Payment_Gateway::GATEWAY_ID;
		$this->gateway = WC_Payments::get_gateway();
	}

	/**
	 * Checks whether the gateway is active.
	 *
	 * @return boolean True when active.
	 */
	public function is_active() {
		return $this->gateway->is_available();
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
		wp_set_script_translations( 'wc-payment-method-wcpay', 'woocommerce-payments' );

		return [ 'wc-payment-method-wcpay' ];
	}

	/**
	 * Loads the data about the gateway, which will be exposed in JavaScript.
	 *
	 * @return array An associative array, containing all necessary values.
	 */
	public function get_payment_method_data() {
		return array_merge(
			[
				'title'       => $this->gateway->get_option( 'title', '' ),
				'description' => $this->gateway->get_option( 'description', '' ),
				'is_admin'    => is_admin(), // Used to display payment method preview in wp-admin.
			],
			$this->gateway->get_payment_fields_js_config()
		);
	}
}
