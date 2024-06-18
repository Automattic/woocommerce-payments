<?php
/**
 * Class WC_Payments_Payment_Request_Button_Handler
 * Adds support for Apple Pay, Google Pay and Payment Request API buttons.
 * Utilizes the Stripe Payment Request Button to support checkout from the product detail and cart pages.
 *
 * Adapted from WooCommerce Stripe Gateway extension.
 *
 * @package WooCommerce\Payments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WCPay\Constants\Country_Code;
use WCPay\Exceptions\Invalid_Price_Exception;
use WCPay\Fraud_Prevention\Fraud_Prevention_Service;
use WCPay\Logger;
use WCPay\Payment_Information;

/**
 * WC_Payments_Payment_Request_Button_Handler class.
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

		if ( WC_Payments_Features::is_stripe_ece_enabled() ) {
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

		add_action( 'before_woocommerce_pay_form', [ $this, 'display_pay_for_order_page_html' ], 1 );

		add_action( 'wc_ajax_wcpay_get_cart_details', [ $this, 'ajax_get_cart_details' ] );
		add_action( 'wc_ajax_wcpay_get_shipping_options', [ $this, 'ajax_get_shipping_options' ] );
		add_action( 'wc_ajax_wcpay_update_shipping_method', [ $this, 'ajax_update_shipping_method' ] );
		add_action( 'wc_ajax_wcpay_create_order', [ $this, 'ajax_create_order' ] );
		add_action( 'wc_ajax_wcpay_get_selected_product_data', [ $this, 'ajax_get_selected_product_data' ] );
		add_action( 'wc_ajax_wcpay_pay_for_order', [ $this, 'ajax_pay_for_order' ] );

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

		if ( WC_Payments_Features::is_tokenized_cart_prb_enabled() ) {
			add_action(
				'woocommerce_store_api_checkout_update_order_from_request',
				[
					$this,
					'tokenized_cart_set_payment_method_type',
				],
				10,
				2
			);
			add_filter( 'rest_pre_dispatch', [ $this, 'tokenized_cart_store_api_address_normalization' ], 10, 3 );
			add_filter( 'rest_pre_dispatch', [ $this, 'tokenized_cart_store_api_nonce_overwrite' ], 10, 3 );
			add_filter(
				'rest_post_dispatch',
				[ $this, 'tokenized_cart_store_api_nonce_headers' ],
				10,
				3
			);
		}
	}

	/**
	 * Updates the checkout order based on the request, to set the Apple Pay/Google Pay payment method title.
	 *
	 * @param \WC_Order        $order The order to be updated.
	 * @param \WP_REST_Request $request Store API request to update the order.
	 */
	public function tokenized_cart_set_payment_method_type( \WC_Order $order, \WP_REST_Request $request ) {
		if ( ! isset( $request['payment_method'] ) || 'woocommerce_payments' !== $request['payment_method'] ) {
			return;
		}

		if ( empty( $request['payment_data'] ) ) {
			return;
		}

		$payment_data = [];
		foreach ( $request['payment_data'] as $data ) {
			$payment_data[ sanitize_key( $data['key'] ) ] = wc_clean( $data['value'] );
		}

		if ( empty( $payment_data['payment_request_type'] ) ) {
			return;
		}

		$payment_request_type = wc_clean( wp_unslash( $payment_data['payment_request_type'] ) );

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
	}

	/**
	 * The nonce supplied by the frontend can be overwritten in this middleware:
	 * https://github.com/woocommerce/woocommerce/blob/trunk/plugins/woocommerce-blocks/assets/js/middleware/store-api-nonce.js
	 *
	 * This is a workaround to use instead a different nonce key, when supplied.
	 *
	 * @param mixed            $response Response to replace the requested version with.
	 * @param \WP_REST_Server  $server Server instance.
	 * @param \WP_REST_Request $request Request used to generate the response.
	 *
	 * @return mixed
	 */
	public function tokenized_cart_store_api_nonce_overwrite( $response, $server, $request ) {
		$nonce = $request->get_header( 'X-WooPayments-Store-Api-Nonce' );
		if ( empty( $nonce ) ) {
			return $response;
		}

		$request->set_header( 'Nonce', $nonce );

		return $response;
	}

	/**
	 * Google Pay/Apple Pay parameters for address data might need some massaging for some of the countries.
	 * Ensuring that the Store API doesn't throw a `rest_invalid_param` error message for some of those scenarios.
	 *
	 * @param mixed            $response Response to replace the requested version with.
	 * @param \WP_REST_Server  $server Server instance.
	 * @param \WP_REST_Request $request Request used to generate the response.
	 *
	 * @return mixed
	 */
	public function tokenized_cart_store_api_address_normalization( $response, $server, $request ) {
		if ( 'true' !== $request->get_header( 'X-WooPayments-Express-Payment-Request' ) ) {
			return $response;
		}

		// header added as additional layer of security.
		$nonce = $request->get_header( 'X-WooPayments-Express-Payment-Request-Nonce' );
		if ( ! wp_verify_nonce( $nonce, 'woopayments_tokenized_cart_nonce' ) ) {
			return $response;
		}

		// This route is used to get shipping rates.
		// GooglePay/ApplePay might provide us with "trimmed" zip codes.
		// If that's the case, let's temporarily allow to skip the zip code validation, in order to get some shipping rates.
		$is_update_customer_route = $request->get_route() === '/wc/store/v1/cart/update-customer';
		if ( $is_update_customer_route ) {
			add_filter( 'woocommerce_validate_postcode', [ $this, 'maybe_skip_postcode_validation' ], 10, 3 );
		}

		$request_data = $request->get_json_params();
		if ( isset( $request_data['shipping_address'] ) ) {
			$request->set_param( 'shipping_address', $this->transform_prb_address_state_data( $request_data['shipping_address'] ) );
			// on the "update customer" route, GooglePay/Apple pay might provide redacted postcode data.
			// we need to modify the zip code to ensure that shipping zone identification still works.
			if ( $is_update_customer_route ) {
				$request->set_param( 'shipping_address', $this->transform_prb_address_postcode_data( $request_data['shipping_address'] ) );
			}
		}
		if ( isset( $request_data['billing_address'] ) ) {
			$request->set_param( 'billing_address', $this->transform_prb_address_state_data( $request_data['billing_address'] ) );
			// on the "update customer" route, GooglePay/Apple pay might provide redacted postcode data.
			// we need to modify the zip code to ensure that shipping zone identification still works.
			if ( $is_update_customer_route ) {
				$request->set_param( 'billing_address', $this->transform_prb_address_postcode_data( $request_data['billing_address'] ) );
			}
		}

		return $response;
	}

	/**
	 * In order to create an additional layer of security, we're adding a custom nonce to the Store API REST responses.
	 * This nonce is added as a response header on the Store API, because nonces are tied to user sessions,
	 * and anonymous carts count as separate user sessions.
	 *
	 * @param \WP_HTTP_Response $response Response to replace the requested version with.
	 * @param \WP_REST_Server   $server Server instance.
	 * @param \WP_REST_Request  $request Request used to generate the response.
	 *
	 * @return \WP_HTTP_Response
	 */
	public function tokenized_cart_store_api_nonce_headers( $response, $server, $request ) {
		if ( $request->get_route() === '/wc/store/v1/cart' ) {
			$response->header( 'X-WooPayments-Express-Payment-Request-Nonce', wp_create_nonce( 'woopayments_tokenized_cart_nonce' ) );
		}

		return $response;
	}

	/**
	 * Allows certain "redacted" postcodes for some countries to bypass WC core validation.
	 *
	 * @param bool   $valid Whether the postcode is valid.
	 * @param string $postcode The postcode in question.
	 * @param string $country The country for the postcode.
	 *
	 * @return bool
	 */
	public function maybe_skip_postcode_validation( $valid, $postcode, $country ) {
		if ( ! in_array( $country, [ Country_Code::UNITED_KINGDOM, Country_Code::CANADA ], true ) ) {
			return $valid;
		}

		// We padded the string with `0` in the `get_normalized_postal_code` method.
		// It's a flimsy check, but better than nothing.
		// Plus, this check is only made for the scenarios outlined in the `tokenized_cart_store_api_address_normalization` method.
		if ( substr( $postcode, - 1 ) === '0' ) {
			return true;
		}

		return $valid;
	}

	/**
	 * Transform a GooglePay/ApplePay state address data fields into values that are valid for WooCommerce.
	 *
	 * @param array $address The address to normalize from the GooglePay/ApplePay request.
	 *
	 * @return array
	 */
	private function transform_prb_address_state_data( $address ) {
		$country = $address['country'] ?? '';
		if ( empty( $country ) ) {
			return $address;
		}

		// States from Apple Pay or Google Pay are in long format, we need their short format..
		$state = $address['state'] ?? '';
		if ( ! empty( $state ) ) {
			$address['state'] = $this->get_normalized_state( $state, $country );
		}

		return $address;
	}

	/**
	 * Transform a GooglePay/ApplePay postcode address data fields into values that are valid for WooCommerce.
	 *
	 * @param array $address The address to normalize from the GooglePay/ApplePay request.
	 *
	 * @return array
	 */
	private function transform_prb_address_postcode_data( $address ) {
		$country = $address['country'] ?? '';
		if ( empty( $country ) ) {
			return $address;
		}

		// Normalizes postal code in case of redacted data from Apple Pay or Google Pay.
		$postcode = $address['postcode'] ?? '';
		if ( ! empty( $postcode ) ) {
			$address['postcode'] = $this->get_normalized_postal_code( $postcode, $country );
		}

		return $address;
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

		wp_localize_script( 'WCPAY_PAYMENT_REQUEST', 'wcpayPaymentRequestPayForOrderParams', $data );
	}

	/**
	 * Get cart data.
	 *
	 * @return mixed Returns false if on a product page, the product information otherwise.
	 */
	public function get_cart_data() {
		if ( $this->express_checkout_helper->is_product() ) {
			return false;
		}

		return $this->express_checkout_helper->build_display_items();
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
				'tokenized_cart_nonce'      => wp_create_nonce( 'woopayments_tokenized_cart_nonce' ),
				'tokenized_order_nonce'     => wp_create_nonce( 'wc_store_api' ),
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
			'has_block'          => has_block( 'woocommerce/cart' ) || has_block( 'woocommerce/checkout' ),
			'product'            => $this->get_product_data(),
			'total_label'        => $this->express_checkout_helper->get_total_label(),
			'button_context'     => $this->express_checkout_helper->get_button_context(),
			'is_product_page'    => $this->express_checkout_helper->is_product(),
			'is_pay_for_order'   => $this->express_checkout_helper->is_pay_for_order_page(),
			'is_checkout_page'   => $this->express_checkout_helper->is_checkout(),
		];

		if ( WC_Payments_Features::is_tokenized_cart_prb_enabled() && ( $this->express_checkout_helper->is_product() || $this->express_checkout_helper->is_pay_for_order_page() ) ) {
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
		} else {
			WC_Payments::register_script_with_dependencies(
				'WCPAY_PAYMENT_REQUEST',
				'dist/payment-request',
				[
					'jquery',
					'stripe',
				]
			);
			WC_Payments_Utils::enqueue_style(
				'WCPAY_PAYMENT_REQUEST',
				plugins_url( 'dist/payment-request.css', WCPAY_PLUGIN_FILE ),
				[],
				WC_Payments::get_file_version( 'dist/payment-request.css' )
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
	 * Get cart details.
	 */
	public function ajax_get_cart_details() {
		check_ajax_referer( 'wcpay-get-cart-details', 'security' );

		if ( ! defined( 'WOOCOMMERCE_CART' ) ) {
			define( 'WOOCOMMERCE_CART', true );
		}

		if ( ! defined( 'WOOCOMMERCE_CHECKOUT' ) ) {
			define( 'WOOCOMMERCE_CHECKOUT', true );
		}

		WC()->cart->calculate_totals();

		wp_send_json( array_merge( $this->express_checkout_helper->build_display_items(), [ 'needs_shipping' => WC()->cart->needs_shipping() ] ) );
	}

	/**
	 * Get shipping options.
	 *
	 * @see WC_Cart::get_shipping_packages().
	 * @see WC_Shipping::calculate_shipping().
	 * @see WC_Shipping::get_packages().
	 */
	public function ajax_get_shipping_options() {
		check_ajax_referer( 'wcpay-payment-request-shipping', 'security' );

		$shipping_address          = filter_input_array(
			INPUT_POST,
			[
				'country'   => FILTER_SANITIZE_SPECIAL_CHARS,
				'state'     => FILTER_SANITIZE_SPECIAL_CHARS,
				'postcode'  => FILTER_SANITIZE_SPECIAL_CHARS,
				'city'      => FILTER_SANITIZE_SPECIAL_CHARS,
				'address_1' => FILTER_SANITIZE_SPECIAL_CHARS,
				'address_2' => FILTER_SANITIZE_SPECIAL_CHARS,
			]
		);
		$product_view_options      = filter_input_array( INPUT_POST, [ 'is_product_page' => FILTER_SANITIZE_SPECIAL_CHARS ] );
		$should_show_itemized_view = ! isset( $product_view_options['is_product_page'] ) ? true : filter_var( $product_view_options['is_product_page'], FILTER_VALIDATE_BOOLEAN );

		$data = $this->get_shipping_options( $shipping_address, $should_show_itemized_view );
		wp_send_json( $data );
	}

	/**
	 * Gets shipping options available for specified shipping address
	 *
	 * @param array   $shipping_address Shipping address.
	 * @param boolean $itemized_display_items Indicates whether to show subtotals or itemized views.
	 *
	 * @return array Shipping options data.
	 *
	 * phpcs:ignore Squiz.Commenting.FunctionCommentThrowTag
	 */
	public function get_shipping_options( $shipping_address, $itemized_display_items = false ) {
		try {
			// Set the shipping options.
			$data = [];

			// Remember current shipping method before resetting.
			$chosen_shipping_methods = WC()->session->get( 'chosen_shipping_methods', [] );
			$this->calculate_shipping( apply_filters( 'wcpay_payment_request_shipping_posted_values', $shipping_address ) );

			$packages = WC()->shipping->get_packages();

			if ( ! empty( $packages ) && WC()->customer->has_calculated_shipping() ) {
				foreach ( $packages as $package ) {
					if ( empty( $package['rates'] ) ) {
						throw new Exception( __( 'Unable to find shipping method for address.', 'woocommerce-payments' ) );
					}

					foreach ( $package['rates'] as $rate ) {
						$data['shipping_options'][] = [
							'id'     => $rate->id,
							'label'  => $rate->label,
							'detail' => '',
							'amount' => WC_Payments_Utils::prepare_amount( $rate->cost, get_woocommerce_currency() ),
						];
					}
				}
			} else {
				throw new Exception( __( 'Unable to find shipping method for address.', 'woocommerce-payments' ) );
			}

			// The first shipping option is automatically applied on the client.
			// Keep chosen shipping method by sorting shipping options if the method still available for new address.
			// Fallback to the first available shipping method.
			if ( isset( $data['shipping_options'][0] ) ) {
				if ( isset( $chosen_shipping_methods[0] ) ) {
					$chosen_method_id         = $chosen_shipping_methods[0];
					$compare_shipping_options = function ( $a, $b ) use ( $chosen_method_id ) {
						if ( $a['id'] === $chosen_method_id ) {
							return - 1;
						}

						if ( $b['id'] === $chosen_method_id ) {
							return 1;
						}

						return 0;
					};
					usort( $data['shipping_options'], $compare_shipping_options );
				}

				$first_shipping_method_id = $data['shipping_options'][0]['id'];
				$this->update_shipping_method( [ $first_shipping_method_id ] );
			}

			WC()->cart->calculate_totals();

			$this->maybe_restore_recurring_chosen_shipping_methods( $chosen_shipping_methods );

			$data          += $this->express_checkout_helper->build_display_items( $itemized_display_items );
			$data['result'] = 'success';
		} catch ( Exception $e ) {
			$data          += $this->express_checkout_helper->build_display_items( $itemized_display_items );
			$data['result'] = 'invalid_shipping_address';
		}

		return $data;
	}

	/**
	 * Update shipping method.
	 */
	public function ajax_update_shipping_method() {
		check_ajax_referer( 'wcpay-update-shipping-method', 'security' );

		if ( ! defined( 'WOOCOMMERCE_CART' ) ) {
			define( 'WOOCOMMERCE_CART', true );
		}

		$shipping_methods = filter_input( INPUT_POST, 'shipping_method', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY );
		$this->update_shipping_method( $shipping_methods );

		WC()->cart->calculate_totals();

		$product_view_options      = filter_input_array( INPUT_POST, [ 'is_product_page' => FILTER_SANITIZE_SPECIAL_CHARS ] );
		$should_show_itemized_view = ! isset( $product_view_options['is_product_page'] ) ? true : filter_var( $product_view_options['is_product_page'], FILTER_VALIDATE_BOOLEAN );

		$data           = [];
		$data          += $this->express_checkout_helper->build_display_items( $should_show_itemized_view );
		$data['result'] = 'success';

		wp_send_json( $data );
	}

	/**
	 * Updates shipping method in WC session
	 *
	 * @param array $shipping_methods Array of selected shipping methods ids.
	 */
	public function update_shipping_method( $shipping_methods ) {
		$chosen_shipping_methods = (array) WC()->session->get( 'chosen_shipping_methods' );

		if ( is_array( $shipping_methods ) ) {
			foreach ( $shipping_methods as $i => $value ) {
				$chosen_shipping_methods[ $i ] = wc_clean( $value );
			}
		}

		WC()->session->set( 'chosen_shipping_methods', $chosen_shipping_methods );
	}

	/**
	 * Gets the selected product data.
	 *
	 * @throws Exception If product or stock is unavailable - caught inside function.
	 */
	public function ajax_get_selected_product_data() {
		check_ajax_referer( 'wcpay-get-selected-product-data', 'security' );

		try {
			$product_id      = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : false;
			$qty             = ! isset( $_POST['qty'] ) ? 1 : apply_filters( 'woocommerce_add_to_cart_quantity', absint( $_POST['qty'] ), $product_id );
			$addon_value     = isset( $_POST['addon_value'] ) ? max( (float) $_POST['addon_value'], 0 ) : 0;
			$product         = wc_get_product( $product_id );
			$variation_id    = null;
			$currency        = get_woocommerce_currency();
			$is_deposit      = isset( $_POST['wc_deposit_option'] ) ? 'yes' === sanitize_text_field( wp_unslash( $_POST['wc_deposit_option'] ) ) : null;
			$deposit_plan_id = isset( $_POST['wc_deposit_payment_plan'] ) ? absint( $_POST['wc_deposit_payment_plan'] ) : 0;

			if ( ! is_a( $product, 'WC_Product' ) ) {
				/* translators: product ID */
				throw new Exception( sprintf( __( 'Product with the ID (%d) cannot be found.', 'woocommerce-payments' ), $product_id ) );
			}

			if ( ( 'variable' === $product->get_type() || 'variable-subscription' === $product->get_type() ) && isset( $_POST['attributes'] ) ) {
				$attributes = wc_clean( wp_unslash( $_POST['attributes'] ) );

				$data_store   = WC_Data_Store::load( 'product' );
				$variation_id = $data_store->find_matching_product_variation( $product, $attributes );

				if ( ! empty( $variation_id ) ) {
					$product = wc_get_product( $variation_id );
				}
			}

			// Force quantity to 1 if sold individually and check for existing item in cart.
			if ( $product->is_sold_individually() ) {
				$qty = apply_filters( 'wcpay_payment_request_add_to_cart_sold_individually_quantity', 1, $qty, $product_id, $variation_id );
			}

			if ( ! $product->has_enough_stock( $qty ) ) {
				/* translators: 1: product name 2: quantity in stock */
				throw new Exception( sprintf( __( 'You cannot add that amount of "%1$s"; to the cart because there is not enough stock (%2$s remaining).', 'woocommerce-payments' ), $product->get_name(), wc_format_stock_quantity_for_display( $product->get_stock_quantity(), $product ) ) );
			}

			$price = $this->get_product_price( $product, $is_deposit, $deposit_plan_id );
			$total = $qty * $price + $addon_value;

			$quantity_label = 1 < $qty ? ' (x' . $qty . ')' : '';

			$data  = [];
			$items = [];

			$items[] = [
				'label'  => $product->get_name() . $quantity_label,
				'amount' => WC_Payments_Utils::prepare_amount( $total, $currency ),
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

			if ( wc_shipping_enabled() && $product->needs_shipping() ) {
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
				'label'   => $this->express_checkout_helper->get_total_label(),
				'amount'  => WC_Payments_Utils::prepare_amount( $total + $total_tax, $currency ),
				'pending' => true,
			];

			$data['needs_shipping'] = ( wc_shipping_enabled() && $product->needs_shipping() );
			$data['currency']       = strtolower( get_woocommerce_currency() );
			$data['country_code']   = substr( get_option( 'woocommerce_default_country' ), 0, 2 );

			wp_send_json( $data );
		} catch ( Exception $e ) {
			if ( is_a( $e, Invalid_Price_Exception::class ) ) {
				Logger::log( $e->getMessage() );
			}
			wp_send_json( [ 'error' => wp_strip_all_tags( $e->getMessage() ) ], 500 );
		}
	}

	/**
	 * Handles payment requests on the Pay for Order page.
	 *
	 * @throws Exception All exceptions are handled within the method.
	 */
	public function ajax_pay_for_order() {
		check_ajax_referer( 'pay_for_order' );

		if (
			! isset( $_POST['payment_method'] ) || 'woocommerce_payments' !== $_POST['payment_method']
			|| ! isset( $_POST['order'] ) || ! intval( $_POST['order'] )
			|| ! isset( $_POST['wcpay-payment-method'] ) || empty( $_POST['wcpay-payment-method'] )
		) {
			// Incomplete request.
			$response = [
				'result'   => 'error',
				'messages' => __( 'Invalid request', 'woocommerce-payments' ),
			];
			wp_send_json( $response, 400 );

			return;
		}

		try {
			// Set up an environment, similar to core checkout.
			wc_maybe_define_constant( 'WOOCOMMERCE_CHECKOUT', true );
			wc_set_time_limit( 0 );

			// Load the order.
			$order_id = intval( $_POST['order'] );
			$order    = wc_get_order( $order_id );

			if ( ! is_a( $order, WC_Order::class ) ) {
				throw new Exception( __( 'Invalid order!', 'woocommerce-payments' ) );
			}

			if ( ! $order->needs_payment() ) {
				throw new Exception( __( 'This order does not require payment!', 'woocommerce-payments' ) );
			}

			$this->add_order_meta( $order_id );

			// Load the gateway.
			$all_gateways = WC()->payment_gateways->get_available_payment_gateways();
			$gateway      = $all_gateways['woocommerce_payments'];
			$result       = $gateway->process_payment( $order_id );

			// process_payment() should only return `success` or throw an exception.
			if ( ! is_array( $result ) || ! isset( $result['result'] ) || 'success' !== $result['result'] || ! isset( $result['redirect'] ) ) {
				throw new Exception( __( 'Unable to determine payment success.', 'woocommerce-payments' ) );
			}

			// Include the order ID in the result.
			$result['order_id'] = $order_id;

			$result = apply_filters( 'woocommerce_payment_successful_result', $result, $order_id );
		} catch ( Exception $e ) {
			$result = [
				'result'   => 'error',
				'messages' => $e->getMessage(),
			];
		}

		wp_send_json( $result );
	}

	/**
	 * Normalizes billing and shipping state fields.
	 */
	public function normalize_state() {
		check_ajax_referer( 'woocommerce-process_checkout', '_wpnonce' );

		$billing_country  = ! empty( $_POST['billing_country'] ) ? wc_clean( wp_unslash( $_POST['billing_country'] ) ) : '';
		$shipping_country = ! empty( $_POST['shipping_country'] ) ? wc_clean( wp_unslash( $_POST['shipping_country'] ) ) : '';
		$billing_state    = ! empty( $_POST['billing_state'] ) ? wc_clean( wp_unslash( $_POST['billing_state'] ) ) : '';
		$shipping_state   = ! empty( $_POST['shipping_state'] ) ? wc_clean( wp_unslash( $_POST['shipping_state'] ) ) : '';

		if ( $billing_state && $billing_country ) {
			$_POST['billing_state'] = $this->get_normalized_state( $billing_state, $billing_country );
		}

		if ( $shipping_state && $shipping_country ) {
			$_POST['shipping_state'] = $this->get_normalized_state( $shipping_state, $shipping_country );
		}
	}

	/**
	 * Checks if given state is normalized.
	 *
	 * @param string $state State.
	 * @param string $country Two-letter country code.
	 *
	 * @return bool Whether state is normalized or not.
	 */
	public function is_normalized_state( $state, $country ) {
		$wc_states = WC()->countries->get_states( $country );

		return is_array( $wc_states ) && array_key_exists( $state, $wc_states );
	}

	/**
	 * Sanitize string for comparison.
	 *
	 * @param string $string String to be sanitized.
	 *
	 * @return string The sanitized string.
	 */
	public function sanitize_string( $string ) {
		return trim( wc_strtolower( remove_accents( $string ) ) );
	}

	/**
	 * Get normalized state from Payment Request API dropdown list of states.
	 *
	 * @param string $state Full state name or state code.
	 * @param string $country Two-letter country code.
	 *
	 * @return string Normalized state or original state input value.
	 */
	public function get_normalized_state_from_pr_states( $state, $country ) {
		// Include Payment Request API State list for compatibility with WC countries/states.
		include_once WCPAY_ABSPATH . 'includes/constants/class-payment-request-button-states.php';
		$pr_states = \WCPay\Constants\Payment_Request_Button_States::STATES;

		if ( ! isset( $pr_states[ $country ] ) ) {
			return $state;
		}

		foreach ( $pr_states[ $country ] as $wc_state_abbr => $pr_state ) {
			$sanitized_state_string = $this->sanitize_string( $state );
			// Checks if input state matches with Payment Request state code (0), name (1) or localName (2).
			if (
				( ! empty( $pr_state[0] ) && $sanitized_state_string === $this->sanitize_string( $pr_state[0] ) ) ||
				( ! empty( $pr_state[1] ) && $sanitized_state_string === $this->sanitize_string( $pr_state[1] ) ) ||
				( ! empty( $pr_state[2] ) && $sanitized_state_string === $this->sanitize_string( $pr_state[2] ) )
			) {
				return $wc_state_abbr;
			}
		}

		return $state;
	}

	/**
	 * Get normalized state from WooCommerce list of translated states.
	 *
	 * @param string $state Full state name or state code.
	 * @param string $country Two-letter country code.
	 *
	 * @return string Normalized state or original state input value.
	 */
	public function get_normalized_state_from_wc_states( $state, $country ) {
		$wc_states = WC()->countries->get_states( $country );

		if ( is_array( $wc_states ) ) {
			foreach ( $wc_states as $wc_state_abbr => $wc_state_value ) {
				if ( preg_match( '/' . preg_quote( $wc_state_value, '/' ) . '/i', $state ) ) {
					return $wc_state_abbr;
				}
			}
		}

		return $state;
	}

	/**
	 * Gets the normalized state/county field because in some
	 * cases, the state/county field is formatted differently from
	 * what WC is expecting and throws an error. An example
	 * for Ireland, the county dropdown in Chrome shows "Co. Clare" format.
	 *
	 * @param string $state Full state name or an already normalized abbreviation.
	 * @param string $country Two-letter country code.
	 *
	 * @return string Normalized state abbreviation.
	 */
	public function get_normalized_state( $state, $country ) {
		// If it's empty or already normalized, skip.
		if ( ! $state || $this->is_normalized_state( $state, $country ) ) {
			return $state;
		}

		// Try to match state from the Payment Request API list of states.
		$state = $this->get_normalized_state_from_pr_states( $state, $country );

		// If it's normalized, return.
		if ( $this->is_normalized_state( $state, $country ) ) {
			return $state;
		}

		// If the above doesn't work, fallback to matching against the list of translated
		// states from WooCommerce.
		return $this->get_normalized_state_from_wc_states( $state, $country );
	}

	/**
	 * The Payment Request API provides its own validation for the address form.
	 * For some countries, it might not provide a state field, so we need to return a more descriptive
	 * error message, indicating that the Payment Request button is not supported for that country.
	 */
	public function validate_state() {
		$wc_checkout     = WC_Checkout::instance();
		$posted_data     = $wc_checkout->get_posted_data();
		$checkout_fields = $wc_checkout->get_checkout_fields();
		$countries       = WC()->countries->get_countries();

		$is_supported = true;
		// Checks if billing state is missing and is required.
		if ( ! empty( $checkout_fields['billing']['billing_state']['required'] ) && '' === $posted_data['billing_state'] ) {
			$is_supported = false;
		}

		// Checks if shipping state is missing and is required.
		if ( WC()->cart->needs_shipping_address() && ! empty( $checkout_fields['shipping']['shipping_state']['required'] ) && '' === $posted_data['shipping_state'] ) {
			$is_supported = false;
		}

		if ( ! $is_supported ) {
			wc_add_notice(
				sprintf(
				/* translators: %s: country. */
					__( 'The payment request button is not supported in %s because some required fields couldn\'t be verified. Please proceed to the checkout page and try again.', 'woocommerce-payments' ),
					$countries[ $posted_data['billing_country'] ] ?? $posted_data['billing_country']
				),
				'error'
			);
		}
	}

	/**
	 * Create order. Security is handled by WC.
	 */
	public function ajax_create_order() {
		if ( WC()->cart->is_empty() ) {
			wp_send_json_error( __( 'Empty cart', 'woocommerce-payments' ), 400 );
		}

		if ( ! defined( 'WOOCOMMERCE_CHECKOUT' ) ) {
			define( 'WOOCOMMERCE_CHECKOUT', true );
		}

		if ( ! defined( 'WCPAY_PAYMENT_REQUEST_CHECKOUT' ) ) {
			define( 'WCPAY_PAYMENT_REQUEST_CHECKOUT', true );
		}

		// In case the state is required, but is missing, add a more descriptive error notice.
		$this->validate_state();

		$this->normalize_state();

		WC()->checkout()->process_checkout();

		die( 0 );
	}

	/**
	 * Calculate and set shipping method.
	 *
	 * @param array $address Shipping address.
	 */
	protected function calculate_shipping( $address = [] ) {
		$country   = $address['country'];
		$state     = $address['state'];
		$postcode  = $address['postcode'];
		$city      = $address['city'];
		$address_1 = $address['address_1'];
		$address_2 = $address['address_2'];

		// Normalizes state to calculate shipping zones.
		$state = $this->get_normalized_state( $state, $country );

		// Normalizes postal code in case of redacted data from Apple Pay.
		$postcode = $this->get_normalized_postal_code( $postcode, $country );

		WC()->shipping->reset_shipping();

		if ( $postcode && WC_Validation::is_postcode( $postcode, $country ) ) {
			$postcode = wc_format_postcode( $postcode, $country );
		}

		if ( $country ) {
			WC()->customer->set_location( $country, $state, $postcode, $city );
			WC()->customer->set_shipping_location( $country, $state, $postcode, $city );
		} else {
			WC()->customer->set_billing_address_to_base();
			WC()->customer->set_shipping_address_to_base();
		}

		WC()->customer->set_calculated_shipping( true );
		WC()->customer->save();

		$packages = [];

		$packages[0]['contents']                 = WC()->cart->get_cart();
		$packages[0]['contents_cost']            = 0;
		$packages[0]['applied_coupons']          = WC()->cart->applied_coupons;
		$packages[0]['user']['ID']               = get_current_user_id();
		$packages[0]['destination']['country']   = $country;
		$packages[0]['destination']['state']     = $state;
		$packages[0]['destination']['postcode']  = $postcode;
		$packages[0]['destination']['city']      = $city;
		$packages[0]['destination']['address']   = $address_1;
		$packages[0]['destination']['address_2'] = $address_2;

		foreach ( WC()->cart->get_cart() as $item ) {
			if ( $item['data']->needs_shipping() ) {
				if ( isset( $item['line_total'] ) ) {
					$packages[0]['contents_cost'] += $item['line_total'];
				}
			}
		}

		$packages = apply_filters( 'woocommerce_cart_shipping_packages', $packages );

		WC()->shipping->calculate_shipping( $packages );
	}

	/**
	 * Builds the shipping methods to pass to Payment Request
	 *
	 * @param array $shipping_methods Shipping methods.
	 */
	protected function build_shipping_methods( $shipping_methods ) {
		if ( empty( $shipping_methods ) ) {
			return [];
		}

		$shipping = [];

		foreach ( $shipping_methods as $method ) {
			$shipping[] = [
				'id'     => $method['id'],
				'label'  => $method['label'],
				'detail' => '',
				'amount' => WC_Payments_Utils::prepare_amount( $method['amount']['value'], get_woocommerce_currency() ),
			];
		}

		return $shipping;
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

	/**
	 * Restores the shipping methods previously chosen for each recurring cart after shipping was reset and recalculated
	 * during the Payment Request get_shipping_options flow.
	 *
	 * When the cart contains multiple subscriptions with different billing periods, customers are able to select different shipping
	 * methods for each subscription, however, this is not supported when purchasing with Apple Pay and Google Pay as it's
	 * only concerned about handling the initial purchase.
	 *
	 * In order to avoid Woo Subscriptions's `WC_Subscriptions_Cart::validate_recurring_shipping_methods` throwing an error, we need to restore
	 * the previously chosen shipping methods for each recurring cart.
	 *
	 * This function needs to be called after `WC()->cart->calculate_totals()` is run, otherwise `WC()->cart->recurring_carts` won't exist yet.
	 *
	 * @param array $previous_chosen_methods The previously chosen shipping methods.
	 */
	private function maybe_restore_recurring_chosen_shipping_methods( $previous_chosen_methods = [] ) {
		if ( empty( WC()->cart->recurring_carts ) || ! method_exists( 'WC_Subscriptions_Cart', 'get_recurring_shipping_package_key' ) ) {
			return;
		}

		$chosen_shipping_methods = WC()->session->get( 'chosen_shipping_methods', [] );

		foreach ( WC()->cart->recurring_carts as $recurring_cart_key => $recurring_cart ) {
			foreach ( $recurring_cart->get_shipping_packages() as $recurring_cart_package_index => $recurring_cart_package ) {
				$package_key = WC_Subscriptions_Cart::get_recurring_shipping_package_key( $recurring_cart_key, $recurring_cart_package_index );

				// If the recurring cart package key is found in the previous chosen methods, but not in the current chosen methods, restore it.
				if ( isset( $previous_chosen_methods[ $package_key ] ) && ! isset( $chosen_shipping_methods[ $package_key ] ) ) {
					$chosen_shipping_methods[ $package_key ] = $previous_chosen_methods[ $package_key ];
				}
			}
		}

		WC()->session->set( 'chosen_shipping_methods', $chosen_shipping_methods );
	}
}
