<?php
/**
 * Class WC_Payments_UPE_Split_Blocks_Payment_Method
 *
 * @package WooCommerce\Payments
 */

/**
 * The payment method, which allows the split UPE gateway to work with WooCommerce Blocks.
 */
class WC_Payments_UPE_Split_Blocks_Payment_Method extends WC_Payments_Blocks_Payment_Method {
	/**
	 * Defines all scripts, necessary for the payment method.
	 *
	 * @return string[] A list of script handles.
	 */
	public function get_payment_method_script_handles() {
		$classic_blocks_scripts = parent::get_payment_method_script_handles();

		wp_register_script(
			'WCPAY_BLOCKS_UPE_SPLIT_CHECKOUT',
			plugins_url( 'dist/upe-split-blocks-checkout.js', WCPAY_PLUGIN_FILE ),
			[ 'stripe' ],
			'1.0.1',
			true
		);
		wp_set_script_translations( 'WCPAY_BLOCKS_UPE_SPLIT_CHECKOUT', 'woocommerce-payments' );

		return array_merge( $classic_blocks_scripts, [ 'WCPAY_BLOCKS_UPE_SPLIT_CHECKOUT' ] );
	}
}
