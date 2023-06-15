<?php
/**
 * Class Affirm_Payment_Method
 *
 * @package WCPay\Payment_Methods
 */

namespace WCPay\Payment_Methods;

use WC_Payments_Token_Service;
use WC_Payments_Utils;

/**
 * Affirm Payment Method class extending UPE base class
 */
class Affirm_Payment_Method extends UPE_Payment_Method {

	const PAYMENT_METHOD_STRIPE_ID = 'affirm';

	const PAYMENT_METHOD_TOTAL_LIMIT = [
		// See https://stripe.com/docs/payments/buy-now-pay-later#product-support.
		'CAD' => [
			'min' => 5000,
			'max' => 3000000,
		], // Represents CAD 50 - 30,000 CAD.
		'USD' => [
			'min' => 5000,
			'max' => 3000000,
		], // Represents USD 50 - 30,000 USD.
	];

	/**
	 * Constructor for link payment method
	 *
	 * @param WC_Payments_Token_Service $token_service Token class instance.
	 */
	public function __construct( $token_service ) {
		parent::__construct( $token_service );
		$this->stripe_id   = self::PAYMENT_METHOD_STRIPE_ID;
		$this->title       = __( 'Affirm', 'woocommerce-payments' );
		$this->is_reusable = false;
		$this->currencies  = [ 'USD', 'CAD' ];
		$this->icon_url    = plugins_url( 'assets/images/payment-methods/woo.svg', WCPAY_PLUGIN_FILE );
	}

	/**
	 * Returns boolean dependent on whether payment method can be used at checkout.
	 *
	 * @return bool
	 */
	public function is_enabled_at_checkout() {
		$is_enabled = parent::is_enabled_at_checkout();
		if ( $is_enabled && isset( WC()->cart ) ) {
			$currency = get_woocommerce_currency();
			$amount   = WC_Payments_Utils::prepare_amount( WC()->cart->get_total( '' ), $currency );
			if ( $amount > 0 ) {
				$range = self::PAYMENT_METHOD_TOTAL_LIMIT[ $currency ];
				return $amount >= $range['min'] && $amount <= $range['max'];
			}
		}
		return $is_enabled;
	}

	/**
	 * Returns testing credentials to be printed at checkout in test mode.
	 *
	 * @return string
	 */
	public function get_testing_instructions() {
		return '';
	}
}
