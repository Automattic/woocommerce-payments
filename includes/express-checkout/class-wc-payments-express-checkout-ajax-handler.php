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

	private $express_checkout_button_helper;

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

		$quantity = $this->get_quantity();

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
			WC()->cart->add_to_cart( $product->get_id(), $quantity );
		}

		WC()->cart->calculate_totals();

		if ( 'booking' === $product_type ) {
			$booking_id = $this->get_booking_id_from_cart();
		}

		$data           = [];
		$data          += $this->build_display_items();
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