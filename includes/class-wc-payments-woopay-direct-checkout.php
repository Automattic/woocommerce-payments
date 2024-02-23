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
