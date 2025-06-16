<?php
/**
 * Class WC_Payments_Express_Checkout_Ajax_Handler
 *
 * @package WooCommerce\Payments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WCPay\Constants\Country_Code;
use WCPay\Exceptions\Invalid_Price_Exception;
use WCPay\Logger;

/**
 * WC_Payments_Express_Checkout_Ajax_Handler class.
 */
class WC_Payments_Express_Checkout_Ajax_Handler {
	/**
	 * WC_Payments_Express_Checkout_Button_Helper instance.
	 *
	 * @var WC_Payments_Express_Checkout_Button_Helper
	 */
	private $express_checkout_button_helper;

	/**
	 * Constructor.
	 *
	 * @param WC_Payments_Express_Checkout_Button_Helper $express_checkout_button_helper Express checkout button helper.
	 */
	public function __construct( WC_Payments_Express_Checkout_Button_Helper $express_checkout_button_helper ) {
		$this->express_checkout_button_helper = $express_checkout_button_helper;
	}

	/**
	 * Initialize hooks.
	 *
	 * @return  void
	 */
	public function init() {
		if ( function_exists( 'woocommerce_store_api_register_update_callback' ) ) {
			woocommerce_store_api_register_update_callback(
				[
					'namespace' => 'woopayments/express-checkout/refresh-ui',
					// do nothing, this callback is needed just to refresh the UI.
					'callback'  => '__return_null',
				]
			);
		}

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
		add_filter( 'woocommerce_get_country_locale', [ $this, 'modify_country_locale_for_express_checkout' ], 20 );
	}

	/**
	 * Adds the current product to the cart. Used on product detail page.
	 */
	public function ajax_add_to_cart() {
		check_ajax_referer( 'wcpay-add-to-cart', 'security' );

		if ( ! defined( 'WOOCOMMERCE_CART' ) ) {
			define( 'WOOCOMMERCE_CART', true );
		}

		WC()->shipping->reset_shipping();

		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : false;
		$product    = wc_get_product( $product_id );

		if ( ! $product ) {
			wp_send_json(
				[
					'error' => [
						'code'    => 'invalid_product_id',
						'message' => __( 'Invalid product id', 'woocommerce-payments' ),
					],
				],
				404
			);
			return;
		}

		$quantity = $this->express_checkout_button_helper->get_quantity();

		$product_type = $product->get_type();

		$is_add_to_cart_valid = apply_filters( 'woocommerce_add_to_cart_validation', true, $product_id, $quantity );

		if ( ! $is_add_to_cart_valid ) {
			// Some extensions error messages needs to be
			// submitted to show error messages.
			wp_send_json(
				[
					'error'  => true,
					'submit' => true,
				],
				400
			);
			return;
		}

		// First empty the cart to prevent wrong calculation.
		WC()->cart->empty_cart();

		if ( ( 'variable' === $product_type || 'variable-subscription' === $product_type ) && isset( $_POST['attributes'] ) ) {
			$attributes = wc_clean( wp_unslash( $_POST['attributes'] ) );

			$data_store   = WC_Data_Store::load( 'product' );
			$variation_id = $data_store->find_matching_product_variation( $product, $attributes );

			WC()->cart->add_to_cart( $product->get_id(), $quantity, $variation_id, $attributes );
		}

		if ( in_array( $product_type, [ 'simple', 'variation', 'subscription', 'subscription_variation', 'booking', 'bundle', 'mix-and-match' ], true ) ) {
			$allowed_item_data = [
				// Teams for WooCommerce Memberships fields.
				'team_name',
				'team_owner_takes_seat',
			];
			$item_data         = [];

			foreach ( $allowed_item_data as $item ) {
				if ( isset( $_POST[ $item ] ) ) {
					$item_data[ $item ] = wc_clean( wp_unslash( $_POST[ $item ] ) );
				}
			}

			WC()->cart->add_to_cart( $product->get_id(), $quantity, 0, [], $item_data );
		}

		WC()->cart->calculate_totals();

		if ( 'booking' === $product_type ) {
			$booking_id = $this->express_checkout_button_helper->get_booking_id_from_cart();
		}

		$data           = [];
		$data          += $this->express_checkout_button_helper->build_display_items();
		$data['result'] = 'success';

		if ( ! empty( $booking_id ) ) {
			$data['bookingId'] = $booking_id;
		}

		wp_send_json( $data );
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
		if ( 'true' !== $request->get_header( 'X-WooPayments-Tokenized-Cart' ) ) {
			return $response;
		}

		// header added as additional layer of security.
		$nonce = $request->get_header( 'X-WooPayments-Tokenized-Cart-Nonce' );
		if ( ! wp_verify_nonce( $nonce, 'woopayments_tokenized_cart_nonce' ) ) {
			return $response;
		}

		// This route is used to get shipping rates.
		// Google Pay/Apple Pay might provide us with "trimmed" zip codes.
		// If that's the case, let's temporarily allow to skip the zip code validation, in order to get some shipping rates.
		$is_update_customer_route = $request->get_route() === '/wc/store/v1/cart/update-customer';
		if ( $is_update_customer_route ) {
			add_filter( 'woocommerce_validate_postcode', [ $this, 'maybe_skip_postcode_validation' ], 10, 3 );
		}

		if ( isset( $request['shipping_address'] ) && is_array( $request['shipping_address'] ) ) {
			$shipping_address = $request['shipping_address'];
			$shipping_address = $this->transform_ece_address_state_data( $shipping_address );
			// on the "update customer" route, Google Pay/Apple Pay might provide redacted postcode data.
			// we need to modify the zip code to ensure that shipping zone identification still works.
			if ( $is_update_customer_route ) {
				$shipping_address = $this->transform_ece_address_postcode_data( $shipping_address );
			}
			$request->set_param( 'shipping_address', $shipping_address );
		}
		if ( isset( $request['billing_address'] ) && is_array( $request['billing_address'] ) ) {
			$billing_address = $request['billing_address'];
			$billing_address = $this->transform_ece_address_state_data( $billing_address );
			// on the "update customer" route, Google Pay/Apple Pay might provide redacted postcode data.
			// we need to modify the zip code to ensure that shipping zone identification still works.
			if ( $is_update_customer_route ) {
				$billing_address = $this->transform_ece_address_postcode_data( $billing_address );
			}
			$request->set_param( 'billing_address', $billing_address );
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
	 * Transform a Google Pay/Apple Pay state address data fields into values that are valid for WooCommerce.
	 *
	 * @param array $address The address to normalize from the Google Pay/Apple Pay request.
	 *
	 * @return array
	 */
	private function transform_ece_address_state_data( $address ) {
		$country = $address['country'] ?? '';
		if ( empty( $country ) ) {
			return $address;
		}

		// Due to a bug in Apple Pay, the "Region" part of a Hong Kong address is delivered in
		// `shipping_postcode`, so we need some special case handling for that. According to
		// our sources at Apple Pay people will sometimes use the district or even sub-district
		// for this value. As such we check against all regions, districts, and sub-districts
		// with both English and Mandarin spelling.
		//
		// @reykjalin: The check here is quite elaborate in an attempt to make sure this doesn't break once
		// Apple Pay fixes the bug that causes address values to be in the wrong place. Because of that the
		// algorithm becomes:
		// 1. Use the supplied state if it's valid (in case Apple Pay bug is fixed)
		// 2. Use the value supplied in the postcode if it's a valid HK region (equivalent to a WC state).
		// 3. Fall back to the value supplied in the state. This will likely cause a validation error, in
		// which case a merchant can reach out to us so we can either: 1) add whatever the customer used
		// as a state to our list of valid states; or 2) let them know the customer must spell the state
		// in some way that matches our list of valid states.
		//
		// @reykjalin: This HK specific sanitazation *should be removed* once Apple Pay fix
		// the address bug. More info on that in pc4etw-bY-p2.
		if ( Country_Code::HONG_KONG === $country ) {
			include_once WCPAY_ABSPATH . 'includes/constants/class-express-checkout-hong-kong-states.php';

			$state = $address['state'] ?? '';
			if ( ! \WCPay\Constants\Express_Checkout_Hong_Kong_States::is_valid_state( strtolower( $state ) ) ) {
				$postcode = $address['postcode'] ?? '';
				if ( strtolower( $postcode ) === 'hongkong' ) {
					$postcode = 'hong kong';
				}
				if ( \WCPay\Constants\Express_Checkout_Hong_Kong_States::is_valid_state( strtolower( $postcode ) ) ) {
					$address['state'] = $postcode;
				}
			}
		}

		// States from Apple Pay or Google Pay are in long format, we need their short format.
		$state = $address['state'] ?? '';
		if ( ! empty( $state ) ) {
			$address['state'] = $this->get_normalized_state( $state, $country );
		}

		return $address;
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
	private function get_normalized_state( $state, $country ) {
		// If it's empty or already normalized, skip.
		if ( ! $state || $this->is_normalized_state( $state, $country ) ) {
			return $state;
		}

		// Try to match state from the Express Checkout API list of states.
		$state = $this->get_normalized_state_from_ece_states( $state, $country );

		// If it's normalized, return.
		if ( $this->is_normalized_state( $state, $country ) ) {
			return $state;
		}

		// If the above doesn't work, fallback to matching against the list of translated
		// states from WooCommerce.
		return $this->get_normalized_state_from_wc_states( $state, $country );
	}

	/**
	 * Checks if given state is normalized.
	 *
	 * @param string $state State.
	 * @param string $country Two-letter country code.
	 *
	 * @return bool Whether state is normalized or not.
	 */
	private function is_normalized_state( $state, $country ) {
		$wc_states = WC()->countries->get_states( $country );
		return is_array( $wc_states ) && array_key_exists( $state, $wc_states );
	}

	/**
	 * Get normalized state from Express Checkout API dropdown list of states.
	 *
	 * @param string $state Full state name or state code.
	 * @param string $country Two-letter country code.
	 *
	 * @return string Normalized state or original state input value.
	 */
	private function get_normalized_state_from_ece_states( $state, $country ) {
		// Include Express Checkout Element API State list for compatibility with WC countries/states.
		include_once WCPAY_ABSPATH . 'includes/constants/class-express-checkout-element-states.php';
		$pr_states = \WCPay\Constants\Express_Checkout_Element_States::STATES;

		if ( ! isset( $pr_states[ $country ] ) ) {
			return $state;
		}

		foreach ( $pr_states[ $country ] as $wc_state_abbr => $pr_state ) {
			$sanitized_state_string = $this->express_checkout_button_helper->sanitize_string( $state );
			// Checks if input state matches with Express Checkout state code (0), name (1) or localName (2).
			if (
				( ! empty( $pr_state[0] ) && $sanitized_state_string === $this->express_checkout_button_helper->sanitize_string( $pr_state[0] ) ) ||
				( ! empty( $pr_state[1] ) && $sanitized_state_string === $this->express_checkout_button_helper->sanitize_string( $pr_state[1] ) ) ||
				( ! empty( $pr_state[2] ) && $sanitized_state_string === $this->express_checkout_button_helper->sanitize_string( $pr_state[2] ) )
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
	private function get_normalized_state_from_wc_states( $state, $country ) {
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
	 * Transform a Google Pay/Apple Pay postcode address data fields into values that are valid for WooCommerce.
	 *
	 * @param array $address The address to normalize from the Google Pay/Apple Pay request.
	 *
	 * @return array
	 */
	private function transform_ece_address_postcode_data( $address ) {
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
	 * Normalizes postal code in case of redacted data from Apple Pay.
	 *
	 * @param string $postcode Postal code.
	 * @param string $country Country.
	 */
	private function get_normalized_postal_code( $postcode, $country ) {
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
	 * Modify country locale settings to handle express checkout address requirements.
	 *
	 * @param array $locales Array of country locale settings.
	 * @return array Modified locales array.
	 */
	public function modify_country_locale_for_express_checkout( $locales ) {
		// Only modify locale settings if this is an express checkout AJAX request.
		if ( ! $this->is_express_checkout_context() ) {
			return $locales;
		}

		include_once WCPAY_ABSPATH . 'includes/constants/class-express-checkout-element-states.php';

		// For countries that don't have state fields, make the state field optional.
		foreach ( \WCPay\Constants\Express_Checkout_Element_States::COUNTRIES_WITHOUT_STATES as $country_code ) {
			$locales[ $country_code ]['state']['required'] = false;
		}

		return $locales;
	}

	/**
	 * Check if we're in an express checkout context.
	 *
	 * @return bool True if we're in an express checkout context, false otherwise.
	 */
	private function is_express_checkout_context() {
		// Only proceed if this is a Store API request.
		if ( ! WC_Payments_Utils::is_store_api_request() ) {
			return false;
		}

		// Check for the 'X-WooPayments-Tokenized-Cart' header using superglobals.
		if ( 'true' !== sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_WOOPAYMENTS_TOKENIZED_CART'] ?? '' ) ) ) {
			return false;
		}

		// Verify the nonce from the 'X-WooPayments-Tokenized-Cart-Nonce' header using superglobals.
		$nonce = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_WOOPAYMENTS_TOKENIZED_CART_NONCE'] ?? '' ) );
		if ( ! wp_verify_nonce( $nonce, 'woopayments_tokenized_cart_nonce' ) ) {
			return false;
		}

		return true;
	}
}
