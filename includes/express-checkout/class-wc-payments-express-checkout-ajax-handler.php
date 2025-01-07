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
		// GooglePay/ApplePay might provide us with "trimmed" zip codes.
		// If that's the case, let's temporarily allow to skip the zip code validation, in order to get some shipping rates.
		$is_update_customer_route = $request->get_route() === '/wc/store/v1/cart/update-customer';
		if ( $is_update_customer_route ) {
			add_filter( 'woocommerce_validate_postcode', [ $this, 'maybe_skip_postcode_validation' ], 10, 3 );
		}

		$request_data = $request->get_json_params();
		if ( isset( $request_data['shipping_address'] ) ) {
			$request->set_param( 'shipping_address', $this->transform_ece_address_state_data( $request_data['shipping_address'] ) );
			// on the "update customer" route, GooglePay/Apple pay might provide redacted postcode data.
			// we need to modify the zip code to ensure that shipping zone identification still works.
			if ( $is_update_customer_route ) {
				$request->set_param( 'shipping_address', $this->transform_ece_address_postcode_data( $request_data['shipping_address'] ) );
			}
		}
		if ( isset( $request_data['billing_address'] ) ) {
			$request->set_param( 'billing_address', $this->transform_ece_address_state_data( $request_data['billing_address'] ) );
			// on the "update customer" route, GooglePay/Apple pay might provide redacted postcode data.
			// we need to modify the zip code to ensure that shipping zone identification still works.
			if ( $is_update_customer_route ) {
				$request->set_param( 'billing_address', $this->transform_ece_address_postcode_data( $request_data['billing_address'] ) );
			}
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
	private function transform_ece_address_state_data( $address ) {
		$country = $address['country'] ?? '';
		if ( empty( $country ) ) {
			return $address;
		}

		// States from Apple Pay or Google Pay are in long format, we need their short format..
		$state = $address['state'] ?? '';
		if ( ! empty( $state ) ) {
			$address['state'] = $this->express_checkout_button_helper->get_normalized_state( $state, $country );
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
	private function transform_ece_address_postcode_data( $address ) {
		$country = $address['country'] ?? '';
		if ( empty( $country ) ) {
			return $address;
		}

		// Normalizes postal code in case of redacted data from Apple Pay or Google Pay.
		$postcode = $address['postcode'] ?? '';
		if ( ! empty( $postcode ) ) {
			$address['postcode'] = $this->express_checkout_button_helper->get_normalized_postal_code( $postcode, $country );
		}

		return $address;
	}
}
