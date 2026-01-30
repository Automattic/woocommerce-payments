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
	 * Constructor.
	 *
	 * @param WC_Payment_Gateway_WCPay|null $gateway Optional. The gateway to use. Defaults to main card gateway.
	 */
	public function __construct( $gateway = null ) {
		// Set the gateway and name immediately in constructor because get_name()
		// is called during register() before initialize() runs.
		$this->gateway = $gateway ?? WC_Payments::get_gateway();
		$this->name    = $this->gateway ? $this->gateway->id : WC_Payment_Gateway_WCPay::GATEWAY_ID;
	}

	/**
	 * Initializes the class.
	 */
	public function initialize() {
		$this->wc_payments_checkout = WC_Payments::get_wc_payments_checkout();
	}

	/**
	 * Checks whether the gateway is active.
	 *
	 * This uses is_enabled() instead of is_available() because is_available()
	 * includes runtime checks (HTTPS, currency, account status) that can fail
	 * in the block editor context, causing WooPayments to incorrectly appear
	 * as "incompatible with block-based checkout". The is_enabled() method
	 * simply checks if the gateway is enabled in settings, which aligns with
	 * how WooCommerce core payment methods implement this check.
	 *
	 * @return boolean True when active.
	 */
	public function is_active() {
		return $this->gateway && $this->gateway->is_enabled();
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
				WC_Payments::get_file_version( 'dist/checkout.css' ),
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

		Fraud_Prevention_Service::maybe_append_fraud_prevention_token();

		return [ 'WCPAY_BLOCKS_CHECKOUT' ];
	}

	/**
	 * Loads the data about the gateway, which will be exposed in JavaScript.
	 *
	 * @return array An associative array, containing all necessary values.
	 */
	public function get_payment_method_data() {
		// Return minimal data if gateway is not available.
		if ( ! $this->gateway ) {
			return [
				'title'       => '',
				'description' => '',
				'is_admin'    => is_admin(),
			];
		}

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
