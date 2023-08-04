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
		WC_Payments_Utils::enqueue_style(
			'wc-blocks-checkout-style',
			plugins_url( 'dist/upe-blocks-checkout.css', WCPAY_PLUGIN_FILE ),
			[],
			'1.0',
			'all'
		);

		wp_register_script(
			'stripe',
			'https://js.stripe.com/v3/',
			[],
			'3.0',
			true
		);

		WC_Payments::register_script_with_dependencies( 'WCPAY_BLOCKS_UPE_CHECKOUT', 'dist/upe-blocks-checkout', [ 'stripe' ] );
		wp_set_script_translations( 'WCPAY_BLOCKS_UPE_CHECKOUT', 'woocommerce-payments' );

		return [ 'WCPAY_BLOCKS_UPE_CHECKOUT' ];
	}
}
