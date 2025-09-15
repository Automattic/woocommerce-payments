<?php
/**
 * Class WC_Gateway_Apple_Pay
 *
 * Adds Apple Pay as a payment gateway.
 *
 * @package WooCommerce\Payments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Apple Pay Payment Gateway.
 */
class WC_Gateway_Apple_Pay extends WC_Payment_Gateway_CC {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id           = 'woocommerce_payments_applePay';
		$this->method_title = __( 'Apple Pay', 'woocommerce-payments' );
		$this->title        = __( 'Apple Pay', 'woocommerce-payments' );
		$this->has_fields   = false;
		$this->enabled      = 'yes';
		$this->description  = '';
		$this->supports     = [
			'products',
			'refunds',
		];

		// Initialize express checkout parameters for dynamic place order button functionality.
		$this->init_express_checkout_params();
	}

	/**
	 * Enqueue express checkout parameters for Apple Pay payment method.
	 *
	 * @return void
	 */
	public function enqueue_express_checkout_params() {
		// Only enqueue on checkout/cart pages where the payment method might be used.
		if ( ! is_checkout() && ! is_cart() ) {
			return;
		}

		// Ensure the blocks checkout script is registered and enqueued.
		if ( ! wp_script_is( 'WCPAY_BLOCKS_CHECKOUT', 'registered' ) ) {
			// Register the script if it's not already registered.
			WC_Payments::register_script_with_dependencies( 'WCPAY_BLOCKS_CHECKOUT', 'dist/blocks-checkout', [ 'stripe' ] );
		}

		// Get the express checkout parameters without initializing button functionality.
		$express_checkout_params = $this->get_express_checkout_params_for_payment_method();

		// Localize the parameters to the blocks checkout script.
		wp_localize_script( 'WCPAY_BLOCKS_CHECKOUT', 'wcpayExpressCheckoutParams', $express_checkout_params );
	}

	/**
	 * Make this gateway always available.
	 *
	 * @return bool
	 */
	public function is_available() {
		return true;
	}

	/**
	 * Initialize express checkout parameters to ensure they're available.
	 * when Apple Pay is used as a payment method (dynamic place order button).
	 *
	 * @return void
	 */
	private function init_express_checkout_params() {
		// Only initialize if we're on the frontend and the dynamic place order button feature is enabled.
		if ( is_admin() || ! WC_Payments_Features::is_dynamic_checkout_place_order_button_enabled() ) {
			return;
		}

		// Hook into wp_enqueue_scripts to localize the express checkout parameters.
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_express_checkout_params' ], 5 );
	}

	/**
	 * Get express checkout parameters for payment method without initializing button functionality.
	 *
	 * @return array Express checkout parameters.
	 */
	private function get_express_checkout_params_for_payment_method() {
		$account                 = WC_Payments::get_account_service();
		$gateway                 = WC_Payments::get_gateway();
		$express_checkout_helper = WC_Payments::get_express_checkout_helper();

		// Create a minimal button handler instance just to get the parameters.
		// We don't call init() on it to avoid initializing button functionality.
		$express_checkout_ajax_handler = new WC_Payments_Express_Checkout_Ajax_Handler( $express_checkout_helper );
		$button_handler                = new WC_Payments_Express_Checkout_Button_Handler(
			$account,
			$gateway,
			$express_checkout_helper,
			$express_checkout_ajax_handler
		);

		return $button_handler->get_express_checkout_params();
	}
}
