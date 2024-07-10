<?php
/**
 * Class WC_Payments_Express_Checkout_Ajax_Handler
 *
 * @package WooCommerce\Payments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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

		if ( ! defined( 'WCPAY_ECE_CHECKOUT' ) ) {
			define( 'WCPAY_ECE_CHECKOUT', true );
		}

		// In case the state is required, but is missing, add a more descriptive error notice.
		$this->express_checkout_button_helper->validate_state();

		$this->express_checkout_button_helper->normalize_state();

		WC()->checkout()->process_checkout();

		die( 0 );
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

	/**
	 * Add needed order meta
	 *
	 * @param integer $order_id The order ID.
	 *
	 * @return  void
	 */
	public function add_order_meta( $order_id ) {
		if ( empty( $_POST['express_payment_type'] ) || ! isset( $_POST['payment_method'] ) || 'woocommerce_payments' !== $_POST['payment_method'] ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}

		$order = wc_get_order( $order_id );

		$express_payment_type = wc_clean( wp_unslash( $_POST['express_payment_type'] ) ); // phpcs:ignore WordPress.Security.NonceVerification

		$express_payment_titles = [
			'apple_pay'  => 'Apple Pay',
			'google_pay' => 'Google Pay',
		];

		$suffix = apply_filters( 'wcpay_payment_request_payment_method_title_suffix', 'WooPayments' );
		if ( ! empty( $suffix ) ) {
			$suffix = " ($suffix)";
		}

		$payment_method_title = isset( $express_payment_titles[ $express_payment_type ] ) ? $express_payment_titles[ $express_payment_type ] : 'Express Payment';
		$order->set_payment_method_title( $payment_method_title . $suffix );
		$order->save();
	}
}
