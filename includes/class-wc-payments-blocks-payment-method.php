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

		$handles = [ 'WCPAY_BLOCKS_CHECKOUT' ];

		// The express checkout methods (Apple Pay, Google Pay, Amazon Pay) register with the
		// WC Blocks registry, and the checkout block only reads the registry as it mounts.
		// Returning the script among the payment method handles makes WC Blocks print it
		// before the checkout block's frontend script; enqueueing it on `wp_enqueue_scripts`
		// alone would print it after the block has already mounted, and the express methods
		// would never appear.
		if ( $this->should_load_express_checkout_script() ) {
			WC_Payments::register_script_with_dependencies(
				'WCPAY_BLOCKS_EXPRESS_CHECKOUT',
				'dist/blocks-express-checkout',
				[
					'WCPAY_BLOCKS_CHECKOUT',
				]
			);
			wp_set_script_translations( 'WCPAY_BLOCKS_EXPRESS_CHECKOUT', 'woocommerce-payments' );

			$handles[] = 'WCPAY_BLOCKS_EXPRESS_CHECKOUT';
		}

		return $handles;
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

	/**
	 * Whether the blocks express checkout script (which registers Apple Pay, Google Pay,
	 * and Amazon Pay with the WC Blocks registry) should load alongside the checkout script.
	 *
	 * @return bool
	 */
	private function should_load_express_checkout_script() {
		// In the block editor, the script provides the express methods' previews.
		if ( is_admin() ) {
			return true;
		}

		return WC_Payments::get_express_checkout_helper()->should_show_express_checkout_button()
			|| $this->gateway->is_express_checkout_in_payment_methods_enabled();
	}
}
