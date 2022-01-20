<?php
/**
 * Class WC_Payments_Blocks_Payment_Method
 *
 * @package WooCommerce\Payments
 */

/**
 * The payment method, which allows the gateway to work with WooCommerce Blocks.
 */
class WC_Payments_UPE_Blocks_Payment_Method extends WC_Payments_Blocks_Payment_Method {
	/**
	 * Defines all scripts, necessary for the payment method.
	 *
	 * @return string[] A list of script handles.
	 */
	public function get_payment_method_script_handles() {
		wp_enqueue_style(
			'wc-blocks-checkout-style',
			plugins_url( 'dist/upe-blocks-checkout.css', WCPAY_PLUGIN_FILE ),
			[],
			'1.0'
		);

		wp_register_script(
			'stripe',
			'https://js.stripe.com/v3/',
			[],
			'3.0',
			true
		);

		wp_register_script(
			'WCPAY_BLOCKS_CHECKOUT',
			plugins_url( 'dist/upe-blocks-checkout.js', WCPAY_PLUGIN_FILE ),
			[ 'stripe' ],
			'1.0.1',
			true
		);
		wp_set_script_translations( 'WCPAY_BLOCKS_CHECKOUT', 'woocommerce-payments' );

		return [ 'WCPAY_BLOCKS_CHECKOUT' ];
	}
}
