<?php
/**
 * Class Wp_Admin_Payment_Methods_Config
 *
 * @package WCPay\Inline_Script_Payloads
 */

namespace WCPay\Inline_Script_Payloads;

/**
 * Class Wp_Admin_Payment_Methods_Config.
 * To be only used in the `wp-admin` area, to provide the configuration for the payment methods to the JS files.
 * Use it with `wp_add_inline_script`.
 *
 * The advantage of this class with the `__toString` magic method is that the JS payload is lazily calculated
 * _only_ when the dependent script is actually loaded to the page.
 */
class Wp_Admin_Payment_Methods_Config {
	/**
	 * WC_Payment_Gateway_WCPay instance.
	 *
	 * @var \WC_Payment_Gateway_WCPay
	 */
	private $gateway;

	/**
	 * Wp_Admin_Payment_Methods_Config constructor.
	 *
	 * @param \WC_Payment_Gateway_WCPay $gateway WC_Payment_Gateway_WCPay instance.
	 */
	public function __construct( \WC_Payment_Gateway_WCPay $gateway ) {
		$this->gateway = $gateway;
	}

	/**
	 * Lazily calculates and returns the string that will be added to the page by the `wp_add_inline_script` function.
	 *
	 * @return string
	 */
	public function __toString() {
		$config                    = [];
		$available_payment_methods = $this->gateway->get_upe_available_payment_methods();
		foreach ( $available_payment_methods as $payment_method_id ) {
			$payment_method = $this->gateway->wc_payments_get_payment_method_by_id( $payment_method_id );
			if ( $payment_method ) {
				$config[ $payment_method_id ] = [
					'currencies'       => $payment_method->get_currencies(),
					'allows_pay_later' => $payment_method->is_bnpl(),
				];
			}
		}

		return '
			var wcpayWpAdminPaymentMethodsConfig = wcpayWpAdminPaymentMethodsConfig || ' . wp_json_encode( $config ) . ';
			';
	}
}
