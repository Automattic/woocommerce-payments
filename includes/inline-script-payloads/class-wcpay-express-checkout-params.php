<?php
/**
 * Class WCPay_Express_Checkout_Params
 *
 * @package WCPay\Inline_Script_Payloads
 */

namespace WCPay\Inline_Script_Payloads;

use WC_Payments_Express_Checkout_Button_Handler;

/**
 * Class WCPay_Express_Checkout_Params.
 * Use it with `wp_add_inline_script`.
 *
 * The advantage of this class with the `__toString` magic method is that the JS payload is lazily calculated
 * _only_ when the dependent script is actually loaded to the page.
 */
class WCPay_Express_Checkout_Params {
	/**
	 * Express Checkout Element button handler instance.
	 *
	 * @var WC_Payments_Express_Checkout_Button_Handler
	 */
	private $express_checkout_button_handler;

	/**
	 * WCPay_Express_Checkout_Params constructor.
	 *
	 * @param WC_Payments_Express_Checkout_Button_Handler $express_checkout_button_handler Express Checkout Element button handler.
	 */
	public function __construct( WC_Payments_Express_Checkout_Button_Handler $express_checkout_button_handler ) {
		$this->express_checkout_button_handler = $express_checkout_button_handler;
	}

	/**
	 * Lazily calculates and returns the string that will be added to the page by the `wp_add_inline_script` function.
	 *
	 * @return string
	 */
	public function __toString() {
		$params           = $this->express_checkout_button_handler->get_express_checkout_params();
		$encoded_response = wp_json_encode( $params );

		return "
			window.wcpayExpressCheckoutParams = JSON.parse( decodeURIComponent( '" . esc_js( rawurlencode( false === $encoded_response ? '' : $encoded_response ) ) . "' ) );
			";
	}
}
