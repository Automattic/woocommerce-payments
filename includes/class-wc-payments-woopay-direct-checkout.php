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
		WC_Payments::register_script_with_dependencies( 'WCPAY_WOOPAY_DIRECT_CHECKOUT', 'dist/woopay-direct-checkout' );

		wp_enqueue_script( 'WCPAY_WOOPAY_DIRECT_CHECKOUT' );
	}
}
