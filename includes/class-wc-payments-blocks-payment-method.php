<?php
/**
 * Class WC_Payments_Blocks_Payment_Method
 *
 * @package WooCommerce\Payments
 */

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use WCPay\Fraud_Prevention\Fraud_Prevention_Service;
use WCPay\WC_Payments_Checkout;

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

		add_filter( 'the_content', [ $this, 'maybe_add_card_testing_token' ] );
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
		wp_enqueue_style(
			'wc-blocks-checkout-style',
			plugins_url( 'dist/blocks-checkout.css', WCPAY_PLUGIN_FILE ),
			[],
			'1.0'
		);
		wp_register_script(
			'stripe',
			'https://js.stripe.com/v3/',
			[],
			'3.0',
			true
		);

		WC_Payments::register_script_with_dependencies( 'WCPAY_BLOCKS_CHECKOUT', 'dist/blocks-checkout', [ 'stripe' ] );
		wp_set_script_translations( 'WCPAY_BLOCKS_CHECKOUT', 'woocommerce-payments' );

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
				'woopayHost' => defined( 'PLATFORM_CHECKOUT_FRONTEND_HOST' ) ? PLATFORM_CHECKOUT_FRONTEND_HOST : 'https://pay.woo.com',
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

	/**
	 * Adds the hidden input containing the card testing prevention token to the blocks checkout page.
	 *
	 * @param   string $content  The content that's going to be flushed to the browser.
	 *
	 * @return  string
	 */
	public function maybe_add_card_testing_token( $content ) {
		if ( ! wp_script_is( 'WCPAY_BLOCKS_CHECKOUT' ) || ! WC()->session ) {
			return $content;
		}

		$fraud_prevention_service = Fraud_Prevention_Service::get_instance();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( $fraud_prevention_service->is_enabled() ) {
			$content .= '<input type="hidden" name="wcpay-fraud-prevention-token" id="wcpay-fraud-prevention-token" value="' . esc_attr( Fraud_Prevention_Service::get_instance()->get_token() ) . '">';
		}
		return $content;
	}
}
