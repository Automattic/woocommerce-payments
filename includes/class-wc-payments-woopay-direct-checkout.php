<?php
/**
 * Class WC_Payments_Payment_Request_Button_Handler
 * Adds support for WooPay direct checkout feature.
 *
 * @package WooCommerce\Payments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WC_Payments_WooPay_Direct_Checkout.
 */
class WC_Payments_WooPay_Direct_Checkout {
	/**
	 * Initialize the hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'wp_enqueue_scripts', [ $this, 'scripts' ] );
		add_filter( 'woocommerce_create_order', [ $this, 'maybe_use_store_api_draft_order_id' ] );
	}

	/**
	 * This filter is used to ensure the session's store_api_draft_order is used, if it exists.
	 * This prevents a bug where the store_api_draft_order is not used and instead, a new
	 * order_awaiting_payment is created during the checkout request.
	 *
	 * @param int $order_id The order ID being used.
	 * @return int|mixed The new order ID to use.
	 */
	public function maybe_use_store_api_draft_order_id( $order_id ) {
		// Only apply this filter during the checkout request.
		$is_checkout = defined( 'WOOCOMMERCE_CHECKOUT' ) && WOOCOMMERCE_CHECKOUT;
		// Only apply this filter if the order ID is not already defined.
		$is_already_defined_order_id = ! empty( $order_id );
		// Only apply this filter if the session doesn't already have an order_awaiting_payment.
		$is_order_awaiting_payment = isset( WC()->session->order_awaiting_payment );
		if ( ! $is_checkout || $is_already_defined_order_id || $is_order_awaiting_payment ) {
			return $order_id;
		}

		return absint( WC()->session->get( 'store_api_draft_order', $order_id ) );
	}

	/**
	 * Enqueue scripts.
	 *
	 * @return void
	 */
	public function scripts() {
		// Only enqueue the script on the cart page, for now.
		if ( ! $this->is_cart_page() ) {
			return;
		}

		WC_Payments::register_script_with_dependencies( 'WCPAY_WOOPAY_DIRECT_CHECKOUT', 'dist/woopay-direct-checkout' );

		$direct_checkout_settings = [
			'params' => [
				'is_product_page' => $this->is_product_page(),
			],
		];
		wp_localize_script(
			'WCPAY_WOOPAY_DIRECT_CHECKOUT',
			'wcpayWooPayDirectCheckout',
			$direct_checkout_settings
		);

		wp_enqueue_script( 'WCPAY_WOOPAY_DIRECT_CHECKOUT' );
	}

	/**
	 * Check if the current page is the cart page.
	 *
	 * @return bool True if the current page is the cart page, false otherwise.
	 */
	public function is_cart_page(): bool {
		return is_cart() || has_block( 'woocommerce/cart' );
	}

	/**
	 * Check if the current page is the product page.
	 *
	 * @return bool True if the current page is the product page, false otherwise.
	 */
	public function is_product_page() {
		return is_product() || wc_post_content_has_shortcode( 'product_page' );
	}
}
