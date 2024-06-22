<?php
/**
 * Class WC_Payments_Express_Checkout_Button_Handler
 * Adds support for Apple Pay, Google Pay and ECE API buttons.
 * Utilizes the Stripe Express Checkout Element to support checkout from the product detail and cart pages.
 *
 * @package WooCommerce\Payments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WCPay\Fraud_Prevention\Fraud_Prevention_Service;

/**
 * WC_Payments_Express_Checkout_Button_Handler class.
 */
class WC_Payments_Express_Checkout_Button_Handler {
	const BUTTON_LOCATIONS = 'payment_request_button_locations';

	/**
	 * WC_Payments_Account instance to get information about the account
	 *
	 * @var WC_Payments_Account
	 */
	private $account;

	/**
	 * WC_Payment_Gateway_WCPay instance.
	 *
	 * @var WC_Payment_Gateway_WCPay
	 */
	private $gateway;

	/**
	 * Express Checkout Ajax Handle instance.
	 *
	 * @var WC_Payments_Express_Checkout_Button_Helper
	 */
	private $express_checkout_helper;

	/**
	 * Express Checkout Helper instance.
	 *
	 * @var WC_Payments_Express_Checkout_Ajax_Handler
	 */
	private $express_checkout_ajax_handler;

	/**
	 * Initialize class actions.
	 *
	 * @param WC_Payments_Account                        $account Account information.
	 * @param WC_Payment_Gateway_WCPay                   $gateway WCPay gateway.
	 * @param WC_Payments_Express_Checkout_Button_Helper $express_checkout_helper Express checkout helper.
	 * @param WC_Payments_Express_Checkout_Ajax_Handler  $express_checkout_ajax_handler Express checkout ajax handler.
	 */
	public function __construct( WC_Payments_Account $account, WC_Payment_Gateway_WCPay $gateway, WC_Payments_Express_Checkout_Button_Helper $express_checkout_helper, WC_Payments_Express_Checkout_Ajax_Handler $express_checkout_ajax_handler ) {
		$this->account                       = $account;
		$this->gateway                       = $gateway;
		$this->express_checkout_helper       = $express_checkout_helper;
		$this->express_checkout_ajax_handler = $express_checkout_ajax_handler;
	}

	/**
	 * Initialize hooks.
	 *
	 * @return  void
	 */
	public function init() {
		// Checks if WCPay is enabled.
		if ( ! $this->gateway->is_enabled() ) {
			return;
		}

		if ( ! WC_Payments_Features::is_stripe_ece_enabled() ) {
			return;
		}

		// Checks if Payment Request is enabled.
		if ( 'yes' !== $this->gateway->get_option( 'payment_request' ) ) {
			return;
		}

		// Don't load for change payment method page.
		if ( isset( $_GET['change_payment_method'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}

		add_action( 'wp_enqueue_scripts', [ $this, 'scripts' ] );

		$this->express_checkout_ajax_handler->init();
	}

	/**
	 * The settings for the `button` attribute - they depend on the "grouped settings" flag value.
	 *
	 * @return array
	 */
	public function get_button_settings() {
		$button_type                     = $this->gateway->get_option( 'payment_request_button_type' );
		$common_settings                 = $this->express_checkout_helper->get_common_button_settings();
		$payment_request_button_settings = [
			// Default format is en_US.
			'locale'       => apply_filters( 'wcpay_payment_request_button_locale', substr( get_locale(), 0, 2 ) ),
			'branded_type' => 'default' === $button_type ? 'short' : 'long',
		];

		return array_merge( $common_settings, $payment_request_button_settings );
	}

	/**
	 * Settings array for the user authentication dialog and redirection.
	 *
	 * @return array|false
	 */
	public function get_login_confirmation_settings() {
		if ( is_user_logged_in() || ! $this->is_authentication_required() ) {
			return false;
		}

		/* translators: The text encapsulated in `**` can be replaced with "Apple Pay" or "Google Pay". Please translate this text, but don't remove the `**`. */
		$message      = __( 'To complete your transaction with **the selected payment method**, you must log in or create an account with our site.', 'woocommerce-payments' );
		$redirect_url = add_query_arg(
			[
				'_wpnonce'                           => wp_create_nonce( 'wcpay-set-redirect-url' ),
				'wcpay_payment_request_redirect_url' => rawurlencode( home_url( add_query_arg( [] ) ) ),
				// Current URL to redirect to after login.
			],
			home_url()
		);

		return [ // nosemgrep: audit.php.wp.security.xss.query-arg -- home_url passed in to add_query_arg.
			'message'      => $message,
			'redirect_url' => $redirect_url,
		];
	}

	/**
	 * Checks whether authentication is required for checkout.
	 *
	 * @return bool
	 */
	public function is_authentication_required() {
		// If guest checkout is disabled and account creation is not possible, authentication is required.
		if ( 'no' === get_option( 'woocommerce_enable_guest_checkout', 'yes' ) && ! $this->is_account_creation_possible() ) {
			return true;
		}
		// If cart contains subscription and account creation is not posible, authentication is required.
		if ( $this->has_subscription_product() && ! $this->is_account_creation_possible() ) {
			return true;
		}

		return false;
	}

	/**
	 * Checks whether cart contains a subscription product or this is a subscription product page.
	 *
	 * @return boolean
	 */
	public function has_subscription_product() {
		if ( ! class_exists( 'WC_Subscriptions_Product' ) ) {
			return false;
		}

		if ( $this->express_checkout_helper->is_product() ) {
			$product = $this->express_checkout_helper->get_product();
			if ( WC_Subscriptions_Product::is_subscription( $product ) ) {
				return true;
			}
		}

		if ( $this->express_checkout_helper->is_checkout() || $this->express_checkout_helper->is_cart() ) {
			if ( WC_Subscriptions_Cart::cart_contains_subscription() ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Checks whether account creation is possible during checkout.
	 *
	 * @return bool
	 */
	public function is_account_creation_possible() {
		$is_signup_from_checkout_allowed = 'yes' === get_option( 'woocommerce_enable_signup_and_login_from_checkout', 'no' );

		// If a subscription is being purchased, check if account creation is allowed for subscriptions.
		if ( ! $is_signup_from_checkout_allowed && $this->has_subscription_product() ) {
			$is_signup_from_checkout_allowed = 'yes' === get_option( 'woocommerce_enable_signup_from_checkout_for_subscriptions', 'no' );
		}

		// If automatically generate username/password are disabled, the Payment Request API
		// can't include any of those fields, so account creation is not possible.
		return (
			$is_signup_from_checkout_allowed &&
			'yes' === get_option( 'woocommerce_registration_generate_username', 'yes' ) &&
			'yes' === get_option( 'woocommerce_registration_generate_password', 'yes' )
		);
	}

	/**
	 * Load public scripts and styles.
	 */
	public function scripts() {
		// Don't load scripts if page is not supported.
		if ( ! $this->express_checkout_helper->should_show_express_checkout_button() ) {
			return;
		}

		$payment_request_params = [
			'ajax_url'           => admin_url( 'admin-ajax.php' ),
			'wc_ajax_url'        => WC_AJAX::get_endpoint( '%%endpoint%%' ),
			'stripe'             => [
				'publishableKey' => $this->account->get_publishable_key( WC_Payments::mode()->is_test() ),
				'accountId'      => $this->account->get_stripe_account_id(),
				'locale'         => WC_Payments_Utils::convert_to_stripe_locale( get_locale() ),
			],
			'nonce'              => [
				'get_cart_details'          => wp_create_nonce( 'wcpay-get-cart-details' ),
				'shipping'                  => wp_create_nonce( 'wcpay-payment-request-shipping' ),
				'update_shipping'           => wp_create_nonce( 'wcpay-update-shipping-method' ),
				'checkout'                  => wp_create_nonce( 'woocommerce-process_checkout' ),
				'add_to_cart'               => wp_create_nonce( 'wcpay-add-to-cart' ),
				'empty_cart'                => wp_create_nonce( 'wcpay-empty-cart' ),
				'get_selected_product_data' => wp_create_nonce( 'wcpay-get-selected-product-data' ),
				'platform_tracker'          => wp_create_nonce( 'platform_tracks_nonce' ),
				'pay_for_order'             => wp_create_nonce( 'pay_for_order' ),
			],
			'checkout'           => [
				'currency_code'     => strtolower( get_woocommerce_currency() ),
				'country_code'      => substr( get_option( 'woocommerce_default_country' ), 0, 2 ),
				'needs_shipping'    => WC()->cart->needs_shipping(),
				// Defaults to 'required' to match how core initializes this option.
				'needs_payer_phone' => 'required' === get_option( 'woocommerce_checkout_phone_field', 'required' ),
			],
			'button'             => $this->get_button_settings(),
			'login_confirmation' => $this->get_login_confirmation_settings(),
			'is_product_page'    => $this->express_checkout_helper->is_product(),
			'button_context'     => $this->express_checkout_helper->get_button_context(),
			'is_pay_for_order'   => $this->express_checkout_helper->is_pay_for_order_page(),
			'has_block'          => has_block( 'woocommerce/cart' ) || has_block( 'woocommerce/checkout' ),
			'product'            => $this->express_checkout_helper->get_product_data(),
			'total_label'        => $this->express_checkout_helper->get_total_label(),
			'is_checkout_page'   => $this->express_checkout_helper->is_checkout(),
		];

		WC_Payments::register_script_with_dependencies( 'WCPAY_EXPRESS_CHECKOUT_ECE', 'dist/express-checkout', [ 'jquery', 'stripe' ] );

		WC_Payments_Utils::enqueue_style(
			'WCPAY_EXPRESS_CHECKOUT_ECE',
			plugins_url( 'dist/payment-request.css', WCPAY_PLUGIN_FILE ),
			[],
			WC_Payments::get_file_version( 'dist/payment-request.css' )
		);

		wp_localize_script( 'WCPAY_EXPRESS_CHECKOUT_ECE', 'wcpayExpressCheckoutParams', $payment_request_params );

		wp_set_script_translations( 'WCPAY_EXPRESS_CHECKOUT_ECE', 'woocommerce-payments' );

		wp_enqueue_script( 'WCPAY_EXPRESS_CHECKOUT_ECE' );

		Fraud_Prevention_Service::maybe_append_fraud_prevention_token();

		$gateways = WC()->payment_gateways->get_available_payment_gateways();
		if ( isset( $gateways['woocommerce_payments'] ) ) {
			WC_Payments::get_wc_payments_checkout()->register_scripts();
		}
	}

	/**
	 * Display the payment request button.
	 */
	public function display_express_checkout_button_html() {
		if ( ! $this->express_checkout_helper->should_show_express_checkout_button() ) {
			return;
		}
		?>
		<div id="wcpay-express-checkout-element"></div>
		<?php
	}
}