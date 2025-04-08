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

use Automattic\WooCommerce\Blocks\Package;
use Automattic\WooCommerce\Blocks\Assets\AssetDataRegistry;
use WCPay\Fraud_Prevention\Fraud_Prevention_Service;

/**
 * WC_Payments_Express_Checkout_Button_Handler class.
 */
class WC_Payments_Express_Checkout_Button_Handler {
	const BUTTON_LOCATIONS            = 'payment_request_button_locations';
	const DEFAULT_BORDER_RADIUS_IN_PX = 4;

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

		// Checks if Payment Request is enabled.
		if ( 'yes' !== $this->gateway->get_option( 'payment_request' ) ) {
			return;
		}

		// Don't load for change payment method page.
		if ( isset( $_GET['change_payment_method'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}

		add_action( 'template_redirect', [ $this, 'set_session' ] );
		add_action( 'template_redirect', [ $this, 'handle_express_checkout_redirect' ] );
		add_filter( 'woocommerce_login_redirect', [ $this, 'get_login_redirect_url' ], 10, 3 );
		add_filter( 'woocommerce_registration_redirect', [ $this, 'get_login_redirect_url' ], 10, 3 );
		add_filter( 'woocommerce_cart_needs_shipping_address', [ $this, 'filter_cart_needs_shipping_address' ], 11, 1 );
		add_action( 'wp_enqueue_scripts', [ $this, 'scripts' ] );
		add_action( 'before_woocommerce_pay_form', [ $this, 'display_pay_for_order_page_html' ], 1 );
		add_filter( 'woocommerce_gateway_title', [ $this, 'filter_gateway_title' ], 10, 2 );
		add_action( 'woocommerce_checkout_order_processed', [ $this->express_checkout_helper, 'add_order_payment_method_title' ], 10, 2 );

		$this->express_checkout_ajax_handler->init();

		if ( is_admin() && current_user_can( 'manage_woocommerce' ) ) {
			$this->register_ece_data_for_block_editor();
		}
	}

	/**
	 * The settings for the `button` attribute - they depend on the "grouped settings" flag value.
	 *
	 * @return array
	 */
	public function get_button_settings() {
		$button_type                      = $this->gateway->get_option( 'payment_request_button_type' );
		$common_settings                  = $this->express_checkout_helper->get_common_button_settings();
		$express_checkout_button_settings = [
			// Default format is en_US.
			'locale'       => apply_filters( 'wcpay_payment_request_button_locale', substr( get_locale(), 0, 2 ) ),
			'branded_type' => 'default' === $button_type ? 'short' : 'long',
		];

		return array_merge( $common_settings, $express_checkout_button_settings );
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
				'_wpnonce'                            => wp_create_nonce( 'wcpay-set-redirect-url' ),
				'wcpay_express_checkout_redirect_url' => rawurlencode( home_url( add_query_arg( [] ) ) ),
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
		if ( ! class_exists( 'WC_Subscriptions_Product' ) || ! class_exists( 'WC_Subscriptions_Cart' ) ) {
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

		// If automatically generate username/password are disabled, the Express Checkout API
		// can't include any of those fields, so account creation is not possible.
		return (
			$is_signup_from_checkout_allowed &&
			'yes' === get_option( 'woocommerce_registration_generate_username', 'yes' ) &&
			'yes' === get_option( 'woocommerce_registration_generate_password', 'yes' )
		);
	}

	/**
	 * Gets the parameters needed for Express Checkout functionality.
	 *
	 * @return array Parameters for Express Checkout.
	 */
	public function get_express_checkout_params() {
		return [
			'ajax_url'           => admin_url( 'admin-ajax.php' ),
			'wc_ajax_url'        => WC_AJAX::get_endpoint( '%%endpoint%%' ),
			'stripe'             => [
				'publishableKey' => $this->account->get_publishable_key( WC_Payments::mode()->is_test() ),
				'accountId'      => $this->account->get_stripe_account_id(),
				'locale'         => WC_Payments_Utils::convert_to_stripe_locale( get_locale() ),
			],
			'nonce'              => [
				'get_cart_details'             => wp_create_nonce( 'wcpay-get-cart-details' ),
				'shipping'                     => wp_create_nonce( 'wcpay-payment-request-shipping' ),
				'update_shipping'              => wp_create_nonce( 'wcpay-update-shipping-method' ),
				'checkout'                     => wp_create_nonce( 'woocommerce-process_checkout' ),
				'add_to_cart'                  => wp_create_nonce( 'wcpay-add-to-cart' ),
				'empty_cart'                   => wp_create_nonce( 'wcpay-empty-cart' ),
				'get_selected_product_data'    => wp_create_nonce( 'wcpay-get-selected-product-data' ),
				'platform_tracker'             => wp_create_nonce( 'platform_tracks_nonce' ),
				'pay_for_order'                => wp_create_nonce( 'pay_for_order' ),
				// needed to communicate via the Store API.
				'tokenized_cart_nonce'         => wp_create_nonce( 'woopayments_tokenized_cart_nonce' ),
				'tokenized_cart_session_nonce' => wp_create_nonce( 'woopayments_tokenized_cart_session_nonce' ),
				'store_api_nonce'              => wp_create_nonce( 'wc_store_api' ),
			],
			'checkout'           => [
				'currency_code'              => strtolower( get_woocommerce_currency() ),
				'currency_decimals'          => WC_Payments::get_localization_service()->get_currency_format( get_woocommerce_currency() )['num_decimals'],
				'country_code'               => substr( get_option( 'woocommerce_default_country' ), 0, 2 ),
				'needs_shipping'             => WC()->cart->needs_shipping(),
				// Defaults to 'required' to match how core initializes this option.
				'needs_payer_phone'          => 'required' === get_option( 'woocommerce_checkout_phone_field', 'required' ),
				'allowed_shipping_countries' => array_keys( WC()->countries->get_shipping_countries() ?? [] ),
				'display_prices_with_tax'    => 'incl' === get_option( 'woocommerce_tax_display_cart' ),
			],
			'button'             => $this->get_button_settings(),
			'login_confirmation' => $this->get_login_confirmation_settings(),
			'button_context'     => $this->express_checkout_helper->get_button_context(),
			'has_block'          => has_block( 'woocommerce/cart' ) || has_block( 'woocommerce/checkout' ),
			'product'            => $this->express_checkout_helper->get_product_data(),
			'total_label'        => $this->express_checkout_helper->get_total_label(),
			'store_name'         => get_bloginfo( 'name' ),
		];
	}

	/**
	 * Load public scripts and styles.
	 */
	public function scripts() {
		// Don't load scripts if page is not supported.
		if ( ! $this->express_checkout_helper->should_show_express_checkout_button() ) {
			return;
		}

		$express_checkout_params = $this->get_express_checkout_params();

		if ( WC_Payments_Features::is_tokenized_cart_ece_enabled() ) {
			WC_Payments::register_script_with_dependencies(
				'WCPAY_EXPRESS_CHECKOUT_ECE',
				'dist/tokenized-express-checkout',
				[
					'jquery',
					'stripe',
				]
			);

			WC_Payments_Utils::enqueue_style(
				'WCPAY_EXPRESS_CHECKOUT_ECE',
				plugins_url( 'dist/tokenized-express-checkout.css', WCPAY_PLUGIN_FILE ),
				[],
				WC_Payments::get_file_version( 'dist/tokenized-express-checkout.css' )
			);
		} else {
			WC_Payments::register_script_with_dependencies(
				'WCPAY_EXPRESS_CHECKOUT_ECE',
				'dist/express-checkout',
				[
					'jquery',
					'stripe',
				]
			);

			WC_Payments_Utils::enqueue_style(
				'WCPAY_EXPRESS_CHECKOUT_ECE',
				plugins_url( 'dist/express-checkout.css', WCPAY_PLUGIN_FILE ),
				[],
				WC_Payments::get_file_version( 'dist/express-checkout.css' )
			);
		}

		wp_localize_script( 'WCPAY_EXPRESS_CHECKOUT_ECE', 'wcpayExpressCheckoutParams', $express_checkout_params );
		wp_localize_script( 'WCPAY_BLOCKS_CHECKOUT', 'wcpayExpressCheckoutParams', $express_checkout_params );

		wp_set_script_translations( 'WCPAY_EXPRESS_CHECKOUT_ECE', 'woocommerce-payments' );

		wp_enqueue_script( 'WCPAY_EXPRESS_CHECKOUT_ECE' );

		Fraud_Prevention_Service::maybe_append_fraud_prevention_token();

		$gateways = WC()->payment_gateways->get_available_payment_gateways();
		if ( isset( $gateways['woocommerce_payments'] ) ) {
			WC_Payments::get_wc_payments_checkout()->register_scripts();
		}
	}

	/**
	 * Display the express checkout button.
	 */
	public function display_express_checkout_button_html() {
		if ( ! $this->express_checkout_helper->should_show_express_checkout_button() ) {
			return;
		}
		?>
		<div id="wcpay-express-checkout-element"></div>
		<?php
	}

	/**
	 * Displays the necessary HTML for the Pay for Order page.
	 *
	 * @param WC_Order $order The order that needs payment.
	 */
	public function display_pay_for_order_page_html( $order ) {
		$currency = get_woocommerce_currency();

		$data  = [];
		$items = [];

		foreach ( $order->get_items() as $item ) {
			if ( method_exists( $item, 'get_total' ) ) {
				$items[] = [
					'label'  => $item->get_name(),
					'amount' => WC_Payments_Utils::prepare_amount( $item->get_total(), $currency ),
				];
			}
		}

		if ( $order->get_total_tax() ) {
			$items[] = [
				'label'  => __( 'Tax', 'woocommerce-payments' ),
				'amount' => WC_Payments_Utils::prepare_amount( $order->get_total_tax(), $currency ),
			];
		}

		if ( $order->get_shipping_total() ) {
			$shipping_label = sprintf(
			// Translators: %s is the name of the shipping method.
				__( 'Shipping (%s)', 'woocommerce-payments' ),
				$order->get_shipping_method()
			);

			$items[] = [
				'label'  => $shipping_label,
				'amount' => WC_Payments_Utils::prepare_amount( $order->get_shipping_total(), $currency ),
			];
		}

		foreach ( $order->get_fees() as $fee ) {
			$items[] = [
				'label'  => $fee->get_name(),
				'amount' => WC_Payments_Utils::prepare_amount( $fee->get_amount(), $currency ),
			];
		}

		$data['order']          = $order->get_id();
		$data['displayItems']   = $items;
		$data['needs_shipping'] = false; // This should be already entered/prepared.
		$data['total']          = [
			'label'   => apply_filters( 'wcpay_payment_request_total_label', $this->express_checkout_helper->get_total_label() ),
			'amount'  => WC_Payments_Utils::prepare_amount( $order->get_total(), $currency ),
			'pending' => true,
		];

		wp_localize_script( 'WCPAY_EXPRESS_CHECKOUT_ECE', 'wcpayECEPayForOrderParams', $data );
	}

	/**
	 * Sets the WC customer session if one is not set.
	 * This is needed so nonces can be verified by AJAX Request.
	 *
	 * @return void
	 */
	public function set_session() {
		// Don't set session cookies on product pages to allow for caching when express checkout
		// buttons are disabled. But keep cookies if there is already an active WC session in place.
		if (
			! ( $this->express_checkout_helper->is_product() && $this->express_checkout_helper->should_show_express_checkout_button() )
			|| ( isset( WC()->session ) && WC()->session->has_session() )
		) {
			return;
		}

		WC()->session->set_customer_session_cookie( true );
	}

	/**
	 * Handles express checkout redirect when the redirect dialog "Continue" button is clicked.
	 */
	public function handle_express_checkout_redirect() {
		if (
			! empty( $_GET['wcpay_express_checkout_redirect_url'] )
			&& ! empty( $_GET['_wpnonce'] )
			&& wp_verify_nonce( $_GET['_wpnonce'], 'wcpay-set-redirect-url' ) // @codingStandardsIgnoreLine
		) {
			$url = rawurldecode( esc_url_raw( wp_unslash( $_GET['wcpay_express_checkout_redirect_url'] ) ) );
			// Sets a redirect URL cookie for 10 minutes, which we will redirect to after authentication.
			// Users will have a 10 minute timeout to login/create account, otherwise redirect URL expires.
			wc_setcookie( 'wcpay_express_checkout_redirect_url', $url, time() + MINUTE_IN_SECONDS * 10 );
			// Redirects to "my-account" page.
			wp_safe_redirect( get_permalink( get_option( 'woocommerce_myaccount_page_id' ) ) );
		}
	}

	/**
	 * Returns the login redirect URL.
	 *
	 * @param string $redirect Default redirect URL.
	 *
	 * @return string Redirect URL.
	 */
	public function get_login_redirect_url( $redirect ) {
		$url = esc_url_raw( wp_unslash( $_COOKIE['wcpay_express_checkout_redirect_url'] ?? '' ) );

		if ( empty( $url ) ) {
			return $redirect;
		}
		wc_setcookie( 'wcpay_express_checkout_redirect_url', '' );

		return $url;
	}


	/**
	 * Determine whether to filter the cart needs shipping address.
	 *
	 * @param boolean $needs_shipping_address Whether the cart needs a shipping address.
	 */
	public function filter_cart_needs_shipping_address( $needs_shipping_address ) {
		if ( $this->has_subscription_product() && wc_get_shipping_method_count( true, true ) === 0 ) {
			return false;
		}

		return $needs_shipping_address;
	}

	/**
	 * Filters the gateway title to reflect the button type used.
	 *
	 * @param string $title Gateway title.
	 * @param string $id Gateway ID.
	 */
	public function filter_gateway_title( $title, $id ) {
		if ( 'woocommerce_payments' !== $id || ! is_admin() ) {
			return $title;
		}

		$order        = $this->express_checkout_helper->get_current_order();
		$method_title = is_object( $order ) ? $order->get_payment_method_title() : '';

		if ( ! empty( $method_title ) ) {
			if (
				strpos( $method_title, 'Apple Pay' ) === 0
				|| strpos( $method_title, 'Google Pay' ) === 0
				|| strpos( $method_title, 'Payment Request' ) === 0 // Legacy PRB title.
			) {
				return $method_title;
			}
		}

		return $title;
	}

	/**
	 * Add ECE data to `wcSettings` to allow it to be accessed by the front-end JS script in the Block editor.
	 *
	 * @return void
	 */
	private function register_ece_data_for_block_editor() {
		$data_registry = Package::container()->get( AssetDataRegistry::class );

		if ( $data_registry->exists( 'ece_data' ) ) {
			return;
		}

		$data_registry->add(
			'ece_data',
			[
				'button' => $this->get_button_settings(),
			]
		);
	}
}
