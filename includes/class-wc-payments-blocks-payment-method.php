<?php
/**
 * Class WC_Payments_Blocks_Payment_Method
 *
 * @package WooCommerce\Payments
 */

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use Automattic\WooCommerce\Blocks\Payments\PaymentResult;
use Automattic\WooCommerce\Blocks\Payments\PaymentContext;

/**
 * The payment method, which allows the gateway to work with WooCommerce Blocks.
 */
class WC_Payments_Blocks_Payment_Method extends AbstractPaymentMethodType {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'woocommerce_rest_checkout_process_payment_with_context', [ $this, 'add_payment_request_order_meta' ], 8, 2 );
	}

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
		wp_set_script_translations( 'wc-payment-method-wcpay', 'woocommerce-payments' );

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

	/**
	 * Add payment request data to the order meta as hooked on the
	 * woocommerce_rest_checkout_process_payment_with_context action.
	 *
	 * @param PaymentContext $context Holds context for the payment.
	 * @param PaymentResult  $result  Result object for the payment.
	 */
	public function add_payment_request_order_meta( PaymentContext $context, PaymentResult &$result ) {
		$data = $context->payment_data;
		if ( ! empty( $data['payment_request_type'] ) && 'woocommerce_payments' === $context->payment_method ) {
			$this->add_order_meta( $context->order, $data['payment_request_type'] );
		}

		// - TODO: Add error handling.
	}

	/**
	 * Handles adding information about the payment request type used to the order meta.
	 *
	 * @param \WC_Order $order The order being processed.
	 * @param string    $payment_request_type The payment request type used for payment.
	 */
	private function add_order_meta( \WC_Order $order, $payment_request_type ) {
		if ( 'apple_pay' === $payment_request_type ) {
			$order->set_payment_method_title( 'Apple Pay (WooCommerce Payments)' );
			$order->save();
		}

		if ( 'payment_request_api' === $payment_request_type ) {
			$order->set_payment_method_title( 'Payment Request (WooCommerce Payments)' );
			$order->save();
		}
	}
}
