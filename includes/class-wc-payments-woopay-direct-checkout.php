<?php
/**
 * Class WC_Payments_WooPay_Direct_Checkout
 * Adds support for WooPay direct checkout feature.
 *
 * @package WooCommerce\Payments
 */

use WCPay\WooPay\WooPay_Utilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WC_Payments_WooPay_Direct_Checkout.
 */
class WC_Payments_WooPay_Direct_Checkout {
	/**
	 * WooPay_Utilities instance.
	 *
	 * @var WooPay_Utilities
	 */
	private $woopay_utilities;

	/**
	 * Initialize class actions.
	 *
	 * @param WooPay_Utilities $woopay_utilities WooPay utilities.
	 */
	public function __construct( WooPay_Utilities $woopay_utilities ) {
		$this->woopay_utilities = $woopay_utilities;
	}

	/**
	 * Initialize the hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'wp_footer', [ $this, 'scripts' ] );
		add_filter( 'woocommerce_create_order', [ $this, 'maybe_use_store_api_draft_order_id' ] );
	}

	/**
	 * This filter is used to ensure the session's store_api_draft_order is used, if it exists.
	 * This prevents a bug where the store_api_draft_order is not used and instead, a new
	 * order_awaiting_payment is created during the checkout request. The bug being evident
	 * if a product had one remaining stock and the store_api_draft_order was reserving it,
	 * an order would fail to be placed since when order_awaiting_payment is created, it would
	 * not be able to reserve the one stock.
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
		// Only apply this filter if draft order ID exists.
		$has_draft_order = ! empty( WC()->session->get( 'store_api_draft_order' ) );
		if ( ! $is_checkout || $is_already_defined_order_id || $is_order_awaiting_payment || ! $has_draft_order ) {
			return $order_id;
		}

		$draft_order_id = absint( WC()->session->get( 'store_api_draft_order' ) );
		// Set the order status to "pending" payment, so that it can be resumed.
		$draft_order = wc_get_order( $draft_order_id );
		$draft_order->set_status( 'pending' );
		$draft_order->save();

		// Move $draft_order_id in session, from store_api_draft_order to order_awaiting_payment.
		WC()->session->set( 'store_api_draft_order', null );
		WC()->session->set( 'order_awaiting_payment', $draft_order_id );

		return $order_id;
	}

	/**
	 * Enqueue scripts.
	 *
	 * @return void
	 */
	public function scripts() {
		if ( ! $this->should_enqueue_scripts() ) {
			return;
		}

		if ( ! $this->woopay_utilities->should_enable_woopay_on_cart_or_checkout() ) {
			return;
		}

		// Enqueue the WCPay common config script only if it hasn't been enqueued yet.
		// This may happen when Direct Checkout is being enqueued on pages that are not the cart page,
		// such as the home and shop pages.
		if ( function_exists( 'did_filter' ) && did_filter( 'wcpay_payment_fields_js_config' ) === 0 ) {
			WC_Payments::enqueue_woopay_common_config_script();
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
	 * Check if the direct checkout scripts should be enqueued on the page.
	 *
	 * Scripts should be enqueued if:
	 * - The current page is the cart page.
	 * - The current page has a cart block.
	 * - The current page has the blocks mini cart widget, i.e 'woocommerce_blocks_cart_enqueue_data' has been fired.
	 * - The current page has the cart fragments script enqueued. which is enqueued by the shortcode mini cart widget.
	 *
	 * @return bool True if the scripts should be enqueued, false otherwise.
	 */
	private function should_enqueue_scripts(): bool {
		return $this->is_cart_page()
			|| did_action( 'woocommerce_blocks_cart_enqueue_data' ) > 0
			|| ( wp_script_is( 'wc-cart-fragments', 'enqueued' ) && ! $this->is_checkout_page() );
	}

	/**
	 * Check if the current page is the cart page.
	 *
	 * @return bool True if the current page is the cart page, false otherwise.
	 */
	private function is_cart_page(): bool {
		return is_cart() || has_block( 'woocommerce/cart' );
	}

	/**
	 * Check if the current page is the checkout page.
	 *
	 * @return bool True if the current page is the checkout page, false otherwise.
	 */
	private function is_checkout_page(): bool {
		return is_checkout() || has_block( 'woocommerce/checkout' );
	}

	/**
	 * Check if the current page is the product page.
	 *
	 * @return bool True if the current page is the product page, false otherwise.
	 */
	private function is_product_page() {
		return is_product() || wc_post_content_has_shortcode( 'product_page' );
	}
}
