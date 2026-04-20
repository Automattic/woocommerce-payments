<?php
/**
 * Class Woo_Payments_Payment_Methods_Config
 *
 * @package WCPay\Inline_Script_Payloads
 */

namespace WCPay\Inline_Script_Payloads;

/**
 * Class Woo_Payments_Payment_Methods_Config.
 * This should be used anywhere the payment methods config is needed when not available from the `wcpayConfig` object.
 * Use it with `wp_add_inline_script`.
 *
 * The advantage of this class with the `__toString` magic method is that the JS payload is lazily calculated
 * _only_ when the dependent script is actually loaded to the page.
 */
class Woo_Payments_Payment_Methods_Config {
	/**
	 * Lazily calculates and returns the string that will be added to the page by the `wp_add_inline_script` function.
	 *
	 * @return string
	 */
	public function __toString() {
		$payment_methods_config = rawurlencode( wp_json_encode( \WC_Payments::get_wc_payments_checkout()->get_all_payment_method_config() ) );

		return "
			window.wooPaymentsPaymentMethodsConfig = JSON.parse( decodeURIComponent( '" . esc_js( $payment_methods_config ) . "' ) );
			";
	}
}
