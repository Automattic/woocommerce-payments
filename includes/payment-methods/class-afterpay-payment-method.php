<?php
/**
 * Class Afterpay_Payment_Method
 *
 * @package WCPay\Payment_Methods
 */

namespace WCPay\Payment_Methods;

use WC_Payments_Token_Service;
use WC_Payments_Utils;

/**
 * Afterpay Payment Method class extending UPE base class
 */
class Afterpay_Payment_Method extends UPE_Payment_Method {

	const PAYMENT_METHOD_STRIPE_ID = 'afterpay_clearpay';

	const PAYMENT_METHOD_TOTAL_LIMIT = [
		// See https://stripe.com/docs/payments/afterpay-clearpay#collection-schedule.
		'AUD' => [
			'min' => 100,
			'max' => 200000,
		], // Represents AUD 1 - 2,000 AUD.
		'CAD' => [
			'min' => 100,
			'max' => 200000,
		], // Represents CAD 1 - 2,000 CAD.
		'NZD' => [
			'min' => 100,
			'max' => 200000,
		], // Represents NZD 1 - 2,000 NZD.
		'GBP' => [
			'min' => 100,
			'max' => 100000,
		], // Represents GBP 1 - 1,000 GBP.
		'USD' => [
			'min' => 100,
			'max' => 400000,
		], // Represents USD 1 - 4,000 USD.
		'EUR' => [
			'min' => 100,
			'max' => 100000,
		], // Represents EUR 1 - 1,000 EUR.
	];

	/**
	 * Constructor for link payment method
	 *
	 * @param WC_Payments_Token_Service $token_service Token class instance.
	 */
	public function __construct( $token_service ) {
		parent::__construct( $token_service );
		$this->stripe_id   = self::PAYMENT_METHOD_STRIPE_ID;
		$this->title       = __( 'Afterpay', 'woocommerce-payments' );
		$this->is_reusable = false;
		$this->currencies  = [ 'USD', 'CAD', 'AUD', 'NZD', 'GBP', 'EUR' ];
		$this->icon_url    = plugins_url( 'assets/images/payment-methods/afterpay.svg', WCPAY_PLUGIN_FILE );
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
