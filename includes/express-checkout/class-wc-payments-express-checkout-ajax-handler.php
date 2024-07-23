<?php
/**
 * Class WC_Payments_Express_Checkout_Ajax_Handler
 *
 * @package WooCommerce\Payments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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
		add_action( 'wc_ajax_wcpay_create_order', [ $this, 'ajax_create_order' ] );
		add_action( 'wc_ajax_wcpay_pay_for_order', [ $this, 'ajax_pay_for_order' ] );
		add_action( 'wc_ajax_wcpay_get_shipping_options', [ $this, 'ajax_get_shipping_options' ] );
		add_action( 'wc_ajax_wcpay_get_cart_details', [ $this, 'ajax_get_cart_details' ] );
		add_action( 'wc_ajax_wcpay_update_shipping_method', [ $this, 'ajax_update_shipping_method' ] );
		add_action( 'wc_ajax_wcpay_get_selected_product_data', [ $this, 'ajax_get_selected_product_data' ] );
	}

	/**
	 * Create order. Security is handled by WC.
	 *
	 * @throws Exception If cart is empty. That is handled within the method.
	 */
	public function ajax_create_order() {
		try {
			if ( WC()->cart->is_empty() ) {
				throw new Exception( __( 'Empty cart', 'woocommerce-payments' ) );
			}

			if ( ! defined( 'WOOCOMMERCE_CHECKOUT' ) ) {
				define( 'WOOCOMMERCE_CHECKOUT', true );
			}

			if ( ! defined( 'WCPAY_ECE_CHECKOUT' ) ) {
				define( 'WCPAY_ECE_CHECKOUT', true );
			}

			// In case the state is required, but is missing, add a more descriptive error notice.
			$this->express_checkout_button_helper->validate_state();

			$this->express_checkout_button_helper->normalize_state();

			WC()->checkout()->process_checkout();
		} catch ( Exception $e ) {
			Logger::error( 'Failed to process express checkout payment: ' . $e );

			$response = [
				'result'   => 'error',
				'messages' => $e->getMessage(),
			];
			wp_send_json( $response, 400 );
		}

		die( 0 );
	}

	/**
	 * Handles payment requests on the Pay for Order page.
	 *
	 * @throws Exception All exceptions are handled within the method.
	 */
	public function ajax_pay_for_order() {
		check_ajax_referer( 'pay_for_order' );

		try {
			if (
				! isset( $_POST['payment_method'] ) || 'woocommerce_payments' !== $_POST['payment_method']
				|| ! isset( $_POST['order'] ) || ! intval( $_POST['order'] )
				|| ! isset( $_POST['wcpay-payment-method'] ) || empty( $_POST['wcpay-payment-method'] )
			) {
				// Incomplete request.
				throw new Exception( __( 'Invalid request', 'woocommerce-payments' ) );
			}

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

			$this->express_checkout_button_helper->add_order_payment_method_title( $order_id );

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

			wp_send_json( $result );
		} catch ( Exception $e ) {
			$order_message = isset( $order_id ) ? "order #$order_id" : 'invalid order';
			Logger::error( 'Failed to process express checkout payment for ' . $order_message . ': ' . $e );

			$result = [
				'result'   => 'error',
				'messages' => $e->getMessage(),
			];
			wp_send_json( $result, 400 );
		}
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

		$data = $this->express_checkout_button_helper->get_shipping_options( $shipping_address, $should_show_itemized_view );
		wp_send_json( $data );
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

		wp_send_json(
			array_merge(
				$this->express_checkout_button_helper->build_display_items(),
				[
					'needs_shipping' => WC()->cart->needs_shipping(),
				]
			)
		);
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
		$this->express_checkout_button_helper->update_shipping_method( $shipping_methods );

		WC()->cart->calculate_totals();

		$product_view_options      = filter_input_array( INPUT_POST, [ 'is_product_page' => FILTER_SANITIZE_SPECIAL_CHARS ] );
		$should_show_itemized_view = ! isset( $product_view_options['is_product_page'] ) ? true : filter_var( $product_view_options['is_product_page'], FILTER_VALIDATE_BOOLEAN );

		$data           = $this->express_checkout_button_helper->build_display_items( $should_show_itemized_view );
		$data['result'] = 'success';

		wp_send_json( $data );
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

			$price = $this->express_checkout_button_helper->get_product_price( $product, $is_deposit, $deposit_plan_id );
			$total = $qty * $price + $addon_value;

			$quantity_label = 1 < $qty ? ' (x' . $qty . ')' : '';

			$data  = [];
			$items = [];

			$items[] = [
				'label'  => $product->get_name() . $quantity_label,
				'amount' => WC_Payments_Utils::prepare_amount( $total, $currency ),
			];

			$total_tax = 0;
			foreach ( $this->express_checkout_button_helper->get_taxes_like_cart( $product, $price ) as $tax ) {
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
				'label'   => $this->express_checkout_button_helper->get_total_label(),
				'amount'  => WC_Payments_Utils::prepare_amount( $total + $total_tax, $currency ),
				'pending' => true,
			];

			$data['needs_shipping'] = wc_shipping_enabled() && 0 !== wc_get_shipping_method_count( true ) && $product->needs_shipping();
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
	 * Empties the cart via AJAX. Used on the product page.
	 */
	public function ajax_empty_cart() {
		check_ajax_referer( 'wcpay-empty-cart', 'security' );

		$booking_id = isset( $_POST['booking_id'] ) ? absint( $_POST['booking_id'] ) : null;

		WC()->cart->empty_cart();

		if ( $booking_id ) {
			// When a bookable product is added to the cart, a 'booking' is create with status 'in-cart'.
			// This status is used to prevent the booking from being booked by another customer
			// and should be removed when the cart is emptied for PRB purposes.
			do_action( 'wc-booking-remove-inactive-cart', $booking_id ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
		}

		wp_send_json( [ 'result' => 'success' ] );
	}
}
