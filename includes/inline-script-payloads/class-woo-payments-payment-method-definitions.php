<?php
/**
 * Class Woo_Payments_Payment_Method_Definitions
 *
 * @package WCPay\Inline_Script_Payloads
 */

namespace WCPay\Inline_Script_Payloads;

use WCPay\PaymentMethods\Configs\Utils\PaymentMethodUtils;

/**
 * Class Woo_Payments_Payment_Method_Definitions.
 * To be only used in the `wp-admin` area, to provide the configuration for the payment methods to the JS files.
 * Use it with `wp_add_inline_script`.
 *
 * The advantage of this class with the `__toString` magic method is that the JS payload is lazily calculated
 * _only_ when the dependent script is actually loaded to the page.
 */
class Woo_Payments_Payment_Method_Definitions {
	/**
	 * Lazily calculates and returns the string that will be added to the page by the `wp_add_inline_script` function.
	 *
	 * @return string
	 */
	public function __toString() {
		$payment_method_definitions = rawurlencode( PaymentMethodUtils::get_payment_method_definitions_json() );

		return "
			window.wooPaymentsPaymentMethodDefinitions = JSON.parse( decodeURIComponent( '" . esc_js( $payment_method_definitions ) . "' ) );
			";
	}
}
