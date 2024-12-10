<?php
/**
 * Class WC_Payments_Payment_Request_Button_Handler
 * Adds support for Apple Pay, Google Pay and Payment Request API buttons.
 * Utilizes the Stripe Payment Request Button to support checkout from the product detail and cart pages.
 *
 * Adapted from WooCommerce Stripe Gateway extension.
 *
 * @deprecated We'll delete this class as part of https://github.com/Automattic/woocommerce-payments/issues/9722 .
 * @package WooCommerce\Payments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WCPay\Constants\Country_Code;
use WCPay\Exceptions\Invalid_Price_Exception;
use WCPay\Fraud_Prevention\Fraud_Prevention_Service;
use WCPay\Logger;

/**
 * WC_Payments_Payment_Request_Button_Handler class.
 *
 * @deprecated We'll delete this class as part of https://github.com/Automattic/woocommerce-payments/issues/9722 .
 */
class WC_Payments_Payment_Request_Button_Handler {
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
	 * Express Checkout Helper instance.
	 *
	 * @var WC_Payments_Express_Checkout_Button_Helper
	 */
	private $express_checkout_helper;

	/**
	 * Initialize class actions.
	 *
	 * @param WC_Payments_Account                        $account Account information.
	 * @param WC_Payment_Gateway_WCPay                   $gateway WCPay gateway.
	 * @param WC_Payments_Express_Checkout_Button_Helper $express_checkout_helper Express checkout helper.
	 */
	public function __construct( WC_Payments_Account $account, WC_Payment_Gateway_WCPay $gateway, WC_Payments_Express_Checkout_Button_Helper $express_checkout_helper ) {
		$this->account                 = $account;
		$this->gateway                 = $gateway;
		$this->express_checkout_helper = $express_checkout_helper;
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

		if ( ! WC_Payments_Features::is_tokenized_cart_ece_enabled() ) {
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
		add_action( 'template_redirect', [ $this, 'handle_payment_request_redirect' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'scripts' ] );

		add_filter( 'woocommerce_gateway_title', [ $this, 'filter_gateway_title' ], 10, 2 );
		add_action( 'woocommerce_checkout_order_processed', [ $this, 'add_order_meta' ], 10, 2 );
		add_filter( 'woocommerce_login_redirect', [ $this, 'get_login_redirect_url' ], 10, 3 );
		add_filter( 'woocommerce_registration_redirect', [ $this, 'get_login_redirect_url' ], 10, 3 );
		add_filter( 'woocommerce_cart_needs_shipping_address', [ $this, 'filter_cart_needs_shipping_address' ], 11, 1 );

		// Add a filter for the value of `wcpay_is_apple_pay_enabled`.
		// This option does not get stored in the database at all, and this function
		// will be used to calculate it whenever the option value is retrieved instead.
		// It's used for displaying inbox notifications.
		add_filter( 'pre_option_wcpay_is_apple_pay_enabled', [ $this, 'get_option_is_apple_pay_enabled' ], 10, 1 );
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
	 * Sets the WC customer session if one is not set.
	 * This is needed so nonces can be verified by AJAX Request.
	 *
	 * @return void
	 */
	public function set_session() {
		// Don't set session cookies on product pages to allow for caching when payment request
		// buttons are disabled. But keep cookies if there is already an active WC session in place.
		if (
			! ( $this->express_checkout_helper->is_product() && $this->should_show_payment_request_button() )
			|| ( isset( WC()->session ) && WC()->session->has_session() )
		) {
			return;
		}

		WC()->session->set_customer_session_cookie( true );
	}

	/**
	 * Handles payment request redirect when the redirect dialog "Continue" button is clicked.
	 */
	public function handle_payment_request_redirect() {
		if (
			! empty( $_GET['wcpay_payment_request_redirect_url'] )
			&& ! empty( $_GET['_wpnonce'] )
			&& wp_verify_nonce( $_GET['_wpnonce'], 'wcpay-set-redirect-url' ) // @codingStandardsIgnoreLine
		) {
			$url = rawurldecode( esc_url_raw( wp_unslash( $_GET['wcpay_payment_request_redirect_url'] ) ) );
			// Sets a redirect URL cookie for 10 minutes, which we will redirect to after authentication.
			// Users will have a 10 minute timeout to login/create account, otherwise redirect URL expires.
			wc_setcookie( 'wcpay_payment_request_redirect_url', $url, time() + MINUTE_IN_SECONDS * 10 );
			// Redirects to "my-account" page.
			wp_safe_redirect( get_permalink( get_option( 'woocommerce_myaccount_page_id' ) ) );
		}
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
	 * Gets the product total price.
	 *
	 * @param object $product WC_Product_* object.
	 * @param bool   $is_deposit Whether customer is paying a deposit.
	 * @param int    $deposit_plan_id The ID of the deposit plan.
	 *
	 * @return mixed Total price.
	 *
	 * @throws Invalid_Price_Exception Whenever a product has no price.
	 */
	public function get_product_price( $product, ?bool $is_deposit = null, int $deposit_plan_id = 0 ) {
		// If prices should include tax, using tax inclusive price.
		if ( $this->express_checkout_helper->cart_prices_include_tax() ) {
			$base_price = wc_get_price_including_tax( $product );
		} else {
			$base_price = wc_get_price_excluding_tax( $product );
		}

		// If WooCommerce Deposits is active, we need to get the correct price for the product.
		if ( class_exists( 'WC_Deposits_Product_Manager' ) && class_exists( 'WC_Deposits_Plans_Manager' ) && WC_Deposits_Product_Manager::deposits_enabled( $product->get_id() ) ) {
			// If is_deposit is null, we use the default deposit type for the product.
			if ( is_null( $is_deposit ) ) {
				$is_deposit = 'deposit' === WC_Deposits_Product_Manager::get_deposit_selected_type( $product->get_id() );
			}
			if ( $is_deposit ) {
				$deposit_type       = WC_Deposits_Product_Manager::get_deposit_type( $product->get_id() );
				$available_plan_ids = WC_Deposits_Plans_Manager::get_plan_ids_for_product( $product->get_id() );
				// Default to first (default) plan if no plan is specified.
				if ( 'plan' === $deposit_type && 0 === $deposit_plan_id && ! empty( $available_plan_ids ) ) {
					$deposit_plan_id = $available_plan_ids[0];
				}

				// Ensure the selected plan is available for the product.
				if ( 0 === $deposit_plan_id || in_array( $deposit_plan_id, $available_plan_ids, true ) ) {
					$base_price = WC_Deposits_Product_Manager::get_deposit_amount( $product, $deposit_plan_id, 'display', $base_price );
				}
			}
		}

		// Add subscription sign-up fees to product price.
		$sign_up_fee        = 0;
		$subscription_types = [
			'subscription',
			'subscription_variation',
		];
		if ( in_array( $product->get_type(), $subscription_types, true ) && class_exists( 'WC_Subscriptions_Product' ) ) {
			// When there is no sign-up fee, `get_sign_up_fee` falls back to an int 0.
			$sign_up_fee = WC_Subscriptions_Product::get_sign_up_fee( $product );
		}

		if ( ! is_numeric( $base_price ) || ! is_numeric( $sign_up_fee ) ) {
			$error_message = sprintf(
			// Translators: %d is the numeric ID of the product without a price.
				__( 'Express checkout does not support products without prices! Please add a price to product #%d', 'woocommerce-payments' ),
				(int) $product->get_id()
			);
			throw new Invalid_Price_Exception(
				esc_html( $error_message )
			);
		}

		return $base_price + $sign_up_fee;
	}

	/**
	 * Gets the product data for the currently viewed page.
	 *
	 * @return mixed Returns false if not on a product page, the product information otherwise.
	 */
	public function get_product_data() {
		if ( ! $this->express_checkout_helper->is_product() ) {
			return false;
		}

		/** @var WC_Product_Variable $product */ // phpcs:ignore
		$product  = $this->express_checkout_helper->get_product();
		$currency = get_woocommerce_currency();

		if ( 'variable' === $product->get_type() || 'variable-subscription' === $product->get_type() ) {
			$variation_attributes = $product->get_variation_attributes();
			$attributes           = [];

			foreach ( $variation_attributes as $attribute_name => $attribute_values ) {
				$attribute_key = 'attribute_' . sanitize_title( $attribute_name );

				// Passed value via GET takes precedence. Otherwise get the default value for given attribute.
				$attributes[ $attribute_key ] = isset( $_GET[ $attribute_key ] ) // phpcs:ignore WordPress.Security.NonceVerification
					? wc_clean( wp_unslash( $_GET[ $attribute_key ] ) ) // phpcs:ignore WordPress.Security.NonceVerification
					: $product->get_variation_default_attribute( $attribute_name );
			}

			$data_store   = WC_Data_Store::load( 'product' );
			$variation_id = $data_store->find_matching_product_variation( $product, $attributes );

			if ( ! empty( $variation_id ) ) {
				$product = wc_get_product( $variation_id );
			}
		}

		try {
			$price = $this->get_product_price( $product );
		} catch ( Invalid_Price_Exception $e ) {
			Logger::log( $e->getMessage() );

			return false;
		}

		$data  = [];
		$items = [];

		$items[] = [
			'label'  => $product->get_name(),
			'amount' => WC_Payments_Utils::prepare_amount( $price, $currency ),
		];

		$total_tax = 0;
		foreach ( $this->get_taxes_like_cart( $product, $price ) as $tax ) {
			$total_tax += $tax;

			$items[] = [
				'label'   => __( 'Tax', 'woocommerce-payments' ),
				'amount'  => WC_Payments_Utils::prepare_amount( $tax, $currency ),
				'pending' => 0 === $tax,
			];
		}

		if ( wc_shipping_enabled() && 0 !== wc_get_shipping_method_count( true ) && $product->needs_shipping() ) {
			$items[] = [
				'label'   => __( 'Shipping', 'woocommerce-payments' ),
				'amount'  => 0,
				'pending' => true,
			];

			$data['shippingOptions'] = [
				'id'     => 'pending',
				'label'  => __( 'Pending', 'woocommerce-payments' ),
				'detail' => '',
				'amount' => 0,
			];
		}

		$data['displayItems'] = $items;
		$data['total']        = [
			'label'   => apply_filters( 'wcpay_payment_request_total_label', $this->express_checkout_helper->get_total_label() ),
			'amount'  => WC_Payments_Utils::prepare_amount( $price + $total_tax, $currency ),
			'pending' => true,
		];

		$data['needs_shipping'] = ( wc_shipping_enabled() && 0 !== wc_get_shipping_method_count( true ) && $product->needs_shipping() );
		$data['currency']       = strtolower( $currency );
		$data['country_code']   = substr( get_option( 'woocommerce_default_country' ), 0, 2 );

		return apply_filters( 'wcpay_payment_request_product_data', $data, $product );
	}

	/**
	 * Filters the gateway title to reflect Payment Request type
	 *
	 * @param string $title Gateway title.
	 * @param string $id Gateway ID.
	 */
	public function filter_gateway_title( $title, $id ) {
		if ( 'woocommerce_payments' !== $id || ! is_admin() ) {
			return $title;
		}

		$order        = $this->get_current_order();
		$method_title = is_object( $order ) ? $order->get_payment_method_title() : '';

		if ( ! empty( $method_title ) ) {
			if (
				strpos( $method_title, 'Apple Pay' ) === 0
				|| strpos( $method_title, 'Google Pay' ) === 0
				|| strpos( $method_title, 'Payment Request' ) === 0
			) {
				return $method_title;
			}
		}

		return $title;
	}

	/**
	 * Used to get the order in admin edit page.
	 *
	 * @return WC_Order|WC_Order_Refund|bool
	 */
	private function get_current_order() {
		global $theorder;
		global $post;

		if ( is_object( $theorder ) ) {
			return $theorder;
		}

		if ( is_object( $post ) ) {
			return wc_get_order( $post->ID );
		}

		return false;
	}

	/**
	 * Normalizes postal code in case of redacted data from Apple Pay.
	 *
	 * @param string $postcode Postal code.
	 * @param string $country Country.
	 */
	public function get_normalized_postal_code( $postcode, $country ) {
		/**
		 * Currently, Apple Pay truncates the UK and Canadian postal codes to the first 4 and 3 characters respectively
		 * when passing it back from the shippingcontactselected object. This causes WC to invalidate
		 * the postal code and not calculate shipping zones correctly.
		 */
		if ( Country_Code::UNITED_KINGDOM === $country ) {
			// Replaces a redacted string with something like N1C0000.
			return str_pad( preg_replace( '/\s+/', '', $postcode ), 7, '0' );
		}
		if ( Country_Code::CANADA === $country ) {
			// Replaces a redacted string with something like H3B000.
			return str_pad( preg_replace( '/\s+/', '', $postcode ), 6, '0' );
		}

		return $postcode;
	}

	/**
	 * Add needed order meta
	 *
	 * @param integer $order_id The order ID.
	 *
	 * @return  void
	 */
	public function add_order_meta( $order_id ) {
		if ( empty( $_POST['payment_request_type'] ) || ! isset( $_POST['payment_method'] ) || 'woocommerce_payments' !== $_POST['payment_method'] ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}

		$order = wc_get_order( $order_id );

		$payment_request_type = wc_clean( wp_unslash( $_POST['payment_request_type'] ) ); // phpcs:ignore WordPress.Security.NonceVerification

		$payment_method_titles = [
			'apple_pay'  => 'Apple Pay',
			'google_pay' => 'Google Pay',
		];

		$suffix = apply_filters( 'wcpay_payment_request_payment_method_title_suffix', 'WooPayments' );
		if ( ! empty( $suffix ) ) {
			$suffix = " ($suffix)";
		}

		$payment_method_title = isset( $payment_method_titles[ $payment_request_type ] ) ? $payment_method_titles[ $payment_request_type ] : 'Payment Request';
		$order->set_payment_method_title( $payment_method_title . $suffix );
		$order->save();
	}

	/**
	 * Checks whether Payment Request Button should be available on this page.
	 *
	 * @return bool
	 */
	public function should_show_payment_request_button() {
		// If account is not connected, then bail.
		if ( ! $this->account->is_stripe_connected() ) {
			return false;
		}

		// If no SSL, bail.
		if ( ! WC_Payments::mode()->is_test() && ! is_ssl() ) {
			Logger::log( 'Stripe Payment Request live mode requires SSL.' );

			return false;
		}

		// Page not supported.
		if ( ! $this->express_checkout_helper->is_product() && ! $this->express_checkout_helper->is_cart() && ! $this->express_checkout_helper->is_checkout() ) {
			return false;
		}

		// Product page, but not available in settings.
		if ( $this->express_checkout_helper->is_product() && ! $this->express_checkout_helper->is_available_at( 'product', self::BUTTON_LOCATIONS ) ) {
			return false;
		}

		// Checkout page, but not available in settings.
		if ( $this->express_checkout_helper->is_checkout() && ! $this->express_checkout_helper->is_available_at( 'checkout', self::BUTTON_LOCATIONS ) ) {
			return false;
		}

		// Cart page, but not available in settings.
		if ( $this->express_checkout_helper->is_cart() && ! $this->express_checkout_helper->is_available_at( 'cart', self::BUTTON_LOCATIONS ) ) {
			return false;
		}

		// Product page, but has unsupported product type.
		if ( $this->express_checkout_helper->is_product() && ! apply_filters( 'wcpay_payment_request_is_product_supported', $this->is_product_supported(), $this->express_checkout_helper->get_product() ) ) {
			Logger::log( 'Product page has unsupported product type ( Payment Request button disabled )' );

			return false;
		}

		// Cart has unsupported product type.
		if ( ( $this->express_checkout_helper->is_checkout() || $this->express_checkout_helper->is_cart() ) && ! $this->has_allowed_items_in_cart() ) {
			Logger::log( 'Items in the cart have unsupported product type ( Payment Request button disabled )' );

			return false;
		}

		// Order total doesn't matter for Pay for Order page. Thus, this page should always display payment buttons.
		if ( $this->express_checkout_helper->is_pay_for_order_page() ) {
			return true;
		}

		// Cart total is 0 or is on product page and product price is 0.
		// Exclude pay-for-order pages from this check.
		if (
			( ! $this->express_checkout_helper->is_product() && ! $this->express_checkout_helper->is_pay_for_order_page() && 0.0 === (float) WC()->cart->get_total( 'edit' ) ) ||
			( $this->express_checkout_helper->is_product() && 0.0 === (float) $this->express_checkout_helper->get_product()->get_price() )

		) {
			Logger::log( 'Order price is 0 ( Payment Request button disabled )' );

			return false;
		}

		return true;
	}

	/**
	 * Checks to make sure product type is supported.
	 *
	 * @return  array
	 */
	public function supported_product_types() {
		return apply_filters(
			'wcpay_payment_request_supported_types',
			[
				'simple',
				'variable',
				'variation',
				'subscription',
				'variable-subscription',
				'subscription_variation',
				'booking',
				'bundle',
				'composite',
				'mix-and-match',
			]
		);
	}

	/**
	 * Checks the cart to see if all items are allowed to be used.
	 *
	 * @return boolean
	 */
	public function has_allowed_items_in_cart() {
		// Pre Orders compatbility where we don't support charge upon release.
		if ( class_exists( 'WC_Pre_Orders_Cart' ) && WC_Pre_Orders_Cart::cart_contains_pre_order() && class_exists( 'WC_Pre_Orders_Product' ) && WC_Pre_Orders_Product::product_is_charged_upon_release( WC_Pre_Orders_Cart::get_pre_order_product() ) ) {
			return false;
		}

		foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
			$_product = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );

			if ( ! in_array( $_product->get_type(), $this->supported_product_types(), true ) ) {
				return false;
			}

			/**
			 * Filter whether product supports Payment Request Button on cart page.
			 *
			 * @param boolean $is_supported Whether product supports Payment Request Button on cart page.
			 * @param object $_product Product object.
			 *
			 * @since 6.9.0
			 */
			if ( ! apply_filters( 'wcpay_payment_request_is_cart_supported', true, $_product ) ) {
				return false;
			}

			// Trial subscriptions with shipping are not supported.
			if ( class_exists( 'WC_Subscriptions_Product' ) && WC_Subscriptions_Product::is_subscription( $_product ) && $_product->needs_shipping() && WC_Subscriptions_Product::get_trial_length( $_product ) > 0 ) {
				return false;
			}
		}

		// We don't support multiple packages with Payment Request Buttons because we can't offer a good UX.
		$packages = WC()->cart->get_shipping_packages();
		if ( 1 < ( is_countable( $packages ) ? count( $packages ) : 0 ) ) {
			return false;
		}

		return true;
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
	 * Returns the login redirect URL.
	 *
	 * @param string $redirect Default redirect URL.
	 *
	 * @return string Redirect URL.
	 */
	public function get_login_redirect_url( $redirect ) {
		$url = esc_url_raw( wp_unslash( $_COOKIE['wcpay_payment_request_redirect_url'] ?? '' ) );

		if ( empty( $url ) ) {
			return $redirect;
		}
		wc_setcookie( 'wcpay_payment_request_redirect_url', '' );

		return $url;
	}

	/**
	 * Load public scripts and styles.
	 */
	public function scripts() {
		// Don't load scripts if page is not supported.
		if ( ! $this->should_show_payment_request_button() ) {
			return;
		}

		$payment_request_params = [
			'ajax_url'           => admin_url( 'admin-ajax.php' ),
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
				'tokenized_cart_nonce'         => wp_create_nonce( 'woopayments_tokenized_cart_nonce' ),
				'tokenized_cart_session_nonce' => wp_create_nonce( 'woopayments_tokenized_cart_session_nonce' ),
				'store_api_nonce'              => wp_create_nonce( 'wc_store_api' ),
			],
			'checkout'           => [
				'currency_code'     => strtolower( get_woocommerce_currency() ),
				'currency_decimals' => WC_Payments::get_localization_service()->get_currency_format( get_woocommerce_currency() )['num_decimals'],
				'country_code'      => substr( get_option( 'woocommerce_default_country' ), 0, 2 ),
				'needs_shipping'    => WC()->cart->needs_shipping(),
				// Defaults to 'required' to match how core initializes this option.
				'needs_payer_phone' => 'required' === get_option( 'woocommerce_checkout_phone_field', 'required' ),
			],
			'button'             => $this->get_button_settings(),
			'login_confirmation' => $this->get_login_confirmation_settings(),
			'has_block'          => has_block( 'woocommerce/cart' ) || has_block( 'woocommerce/checkout' ),
			'product'            => $this->get_product_data(),
			'total_label'        => $this->express_checkout_helper->get_total_label(),
			'button_context'     => $this->express_checkout_helper->get_button_context(),
			'is_product_page'    => $this->express_checkout_helper->is_product(),
			'is_pay_for_order'   => $this->express_checkout_helper->is_pay_for_order_page(),
			'is_checkout_page'   => $this->express_checkout_helper->is_checkout(),
		];

		if ( WC_Payments_Features::is_tokenized_cart_ece_enabled() ) {
			WC_Payments::register_script_with_dependencies(
				'WCPAY_PAYMENT_REQUEST',
				'dist/tokenized-payment-request',
				[
					'jquery',
					'stripe',
				]
			);
			WC_Payments_Utils::enqueue_style(
				'WCPAY_PAYMENT_REQUEST',
				plugins_url( 'dist/tokenized-payment-request.css', WCPAY_PLUGIN_FILE ),
				[],
				WC_Payments::get_file_version( 'dist/tokenized-payment-request.css' )
			);
		}

		wp_localize_script( 'WCPAY_PAYMENT_REQUEST', 'wcpayPaymentRequestParams', $payment_request_params );

		wp_set_script_translations( 'WCPAY_PAYMENT_REQUEST', 'woocommerce-payments' );

		wp_enqueue_script( 'WCPAY_PAYMENT_REQUEST' );

		Fraud_Prevention_Service::maybe_append_fraud_prevention_token();

		$gateways = WC()->payment_gateways->get_available_payment_gateways();
		if ( isset( $gateways['woocommerce_payments'] ) ) {
			WC_Payments::get_wc_payments_checkout()->register_scripts();
		}
	}

	/**
	 * Display the payment request button.
	 */
	public function display_payment_request_button_html() {
		if ( ! $this->should_show_payment_request_button() ) {
			return;
		}
		?>
		<div id="wcpay-payment-request-button">
			<!-- A Stripe Element will be inserted here. -->
		</div>
		<?php
	}

	/**
	 * Whether product page has a supported product.
	 *
	 * @return boolean
	 */
	private function is_product_supported() {
		$product = $this->express_checkout_helper->get_product();
		if ( is_null( $product ) ) {
			return false;
		}

		if ( ! is_object( $product ) ) {
			return false;
		}

		if ( ! in_array( $product->get_type(), $this->supported_product_types(), true ) ) {
			return false;
		}

		// Trial subscriptions with shipping are not supported.
		if ( class_exists( 'WC_Subscriptions_Product' ) && $product->needs_shipping() && WC_Subscriptions_Product::get_trial_length( $product ) > 0 ) {
			return false;
		}

		// Pre Orders charge upon release not supported.
		if ( class_exists( 'WC_Pre_Orders_Product' ) && WC_Pre_Orders_Product::product_is_charged_upon_release( $product ) ) {
			return false;
		}

		// Composite products are not supported on the product page.
		if ( class_exists( 'WC_Composite_Products' ) && $product->is_type( 'composite' ) ) {
			return false;
		}

		// Mix and match products are not supported on the product page.
		if ( class_exists( 'WC_Mix_and_Match' ) && $product->is_type( 'mix-and-match' ) ) {
			return false;
		}

		if ( class_exists( 'WC_Product_Addons_Helper' ) ) {
			// File upload addon not supported.
			$product_addons = WC_Product_Addons_Helper::get_product_addons( $product->get_id() );
			foreach ( $product_addons as $addon ) {
				if ( 'file_upload' === $addon['type'] ) {
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Determine wether to filter the cart needs shipping address.
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
	 * Calculates whether Apple Pay is enabled for this store.
	 * The option value is not stored in the database, and is calculated
	 * using this function instead, and the values is returned by using the pre_option filter.
	 *
	 * The option value is retrieved for inbox notifications.
	 *
	 * @param mixed $value The value of the option.
	 */
	public function get_option_is_apple_pay_enabled( $value ) {
		// Return a random value (1 or 2) if the account is live and payment request buttons are enabled.
		if (
			$this->gateway->is_enabled()
			&& 'yes' === $this->gateway->get_option( 'payment_request' )
			&& ! WC_Payments::mode()->is_dev()
			&& $this->account->get_is_live()
		) {
			$value = wp_rand( 1, 2 );
		}

		return $value;
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
	 * Calculates taxes as displayed on cart, based on a product and a particular price.
	 *
	 * @param WC_Product $product The product, for retrieval of tax classes.
	 * @param float      $price The price, which to calculate taxes for.
	 *
	 * @return array              An array of final taxes.
	 */
	private function get_taxes_like_cart( $product, $price ) {
		if ( ! wc_tax_enabled() || $this->express_checkout_helper->cart_prices_include_tax() ) {
			// Only proceed when taxes are enabled, but not included.
			return [];
		}

		// Follows the way `WC_Cart_Totals::get_item_tax_rates()` works.
		$tax_class = $product->get_tax_class();
		$rates     = WC_Tax::get_rates( $tax_class );
		// No cart item, `woocommerce_cart_totals_get_item_tax_rates` can't be applied here.

		// Normally there should be a single tax, but `calc_tax` returns an array, let's use it.
		return WC_Tax::calc_tax( $price, $rates, false );
	}
}
