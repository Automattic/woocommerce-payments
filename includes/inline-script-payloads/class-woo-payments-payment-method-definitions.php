<?php
/**
 * Class Woo_Payments_Payment_Method_Definitions
 *
 * @package WCPay\Inline_Script_Payloads
 */

namespace WCPay\Inline_Script_Payloads;

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
		$account                           = \WC_Payments::get_account_service()->get_cached_account_data();
		$account_country                   = isset( $account['country'] ) ? strtoupper( $account['country'] ) : '';
		$payment_method_map                = \WC_Payments::get_payment_method_map();
		$payment_method_information_object = [];

		foreach ( $payment_method_map as $id => $payment_method ) {
			$payment_method_information_object[ $id ] =
				$payment_method->get_payment_method_information_object( $account_country );
		}

		$payment_method_information_object = rawurlencode( wp_json_encode( $payment_method_information_object ) );

		return "
			window.wooPaymentsPaymentMethodDefinitions = JSON.parse( decodeURIComponent( '" . esc_js( $payment_method_information_object ) . "' ) );
			";
	}
}
