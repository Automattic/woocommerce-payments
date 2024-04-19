<?php
/**
 * Class WC_Payments_Blocks_Payment_Method
 *
 * @package WooCommerce\Payments
 */

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use WCPay\Fraud_Prevention\Fraud_Prevention_Service;
use WCPay\WC_Payments_Checkout;
use WCPay\WooPay\WooPay_Utilities;

/**
 * The payment method, which allows the gateway to work with WooCommerce Blocks.
 */
class WC_Payments_Blocks_Payment_Method extends AbstractPaymentMethodType {
	/**
	 * The gateway instance.
	 *
	 * @var WC_Payment_Gateway_WCPay
	 */
	private $gateway;

	/**
	 * WC Payments Checkout
	 *
	 * @var WC_Payments_Checkout
	 */
	private $wc_payments_checkout;

	/**
	 * Initializes the class.
	 */
	public function initialize() {
		$this->name                 = WC_Payment_Gateway_WCPay::GATEWAY_ID;
		$this->gateway              = WC_Payments::get_gateway();
		$this->wc_payments_checkout = WC_Payments::get_wc_payments_checkout();
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

		if ( ( is_cart() || is_checkout() || is_product() || has_block( 'woocommerce/checkout' ) || has_block( 'woocommerce/cart' ) || is_admin() ) ) {
			WC_Payments_Utils::enqueue_style(
				'wc-blocks-checkout-style',
				plugins_url( 'dist/blocks-checkout.css', WCPAY_PLUGIN_FILE ),
				[],
				'1.0',
				'all'
			);
		}

		wp_register_script(
			'stripe',
			'https://js.stripe.com/v3/',
			[],
			'3.0',
			true
		);

		WC_Payments::register_script_with_dependencies( 'WCPAY_BLOCKS_CHECKOUT', 'dist/blocks-checkout', [ 'stripe' ] );
		wp_set_script_translations( 'WCPAY_BLOCKS_CHECKOUT', 'woocommerce-payments' );

		if ( WC()->cart ) {
			wp_add_inline_script(
				'WCPAY_BLOCKS_CHECKOUT',
				'var wcBlocksCheckoutData = ' . wp_json_encode(
					[
						'amount'         => WC()->cart->get_total( '' ),
						'currency'       => get_woocommerce_currency(),
						'storeCountry'   => WC()->countries->get_base_country(),
						'billingCountry' => WC()->customer->get_billing_country(),
					]
				) . ';',
				'before'
			);
		}

		Fraud_Prevention_Service::maybe_append_fraud_prevention_token();

		return [ 'WCPAY_BLOCKS_CHECKOUT' ];
	}

	/**
	 * Loads the data about the gateway, which will be exposed in JavaScript.
	 *
	 * @return array An associative array, containing all necessary values.
	 */
	public function get_payment_method_data() {
		$is_woopay_eligible = WC_Payments_Features::is_woopay_eligible(); // Feature flag.
		$is_woopay_enabled  = 'yes' === $this->gateway->get_option( 'platform_checkout', 'no' );
		$woopay_config      = [];

		if ( $is_woopay_eligible && $is_woopay_enabled ) {
			$woopay_config = [
				'woopayHost' => WooPay_Utilities::get_woopay_url(),
			];
		}

		return array_merge(
			[
				'title'       => $this->gateway->get_option( 'title', '' ),
				'description' => $this->gateway->get_option( 'description', '' ),
				'is_admin'    => is_admin(), // Used to display payment method preview in wp-admin.
			],
			$woopay_config,
			$this->wc_payments_checkout->get_payment_fields_js_config()
		);
	}
}
