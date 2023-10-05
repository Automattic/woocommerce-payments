<?php
/**
 * Class Affirm_Payment_Method
 *
 * @package WCPay\Payment_Methods
 */

namespace WCPay\Payment_Methods;

use WC_Payments_Token_Service;
use WC_Payments_Utils;
use WCPay\MultiCurrency\MultiCurrency;

/**
 * Affirm Payment Method class extending UPE base class
 */
class Klarna_Payment_Method extends UPE_Payment_Method {

	const PAYMENT_METHOD_STRIPE_ID = 'klarna';

	/**
	 * Constructor for link payment method
	 *
	 * @param WC_Payments_Token_Service $token_service Token class instance.
	 */
	public function __construct( $token_service ) {
		parent::__construct( $token_service );
		$this->stripe_id                    = self::PAYMENT_METHOD_STRIPE_ID;
		$this->title                        = __( 'Klarna', 'woocommerce-payments' );
		$this->is_reusable                  = false;
		$this->icon_url                     = plugins_url( 'assets/images/payment-methods/klarna.svg', WCPAY_PLUGIN_FILE );
		$this->currencies                   = [ 'USD', 'GBP', 'EUR' ];
		$this->accept_only_domestic_payment = true;
		$this->countries                    = [ 'US', 'GB', 'AT', 'DE', 'NL', 'BE', 'ES', 'IT' ];
		$this->limits_per_currency          = [
			'USD' => [
				'min' => 1000,
				'max' => 500000,
			], // Represents USD 10 - 5,000 AUD.
			'GBP' => [
				'min' => 1000,
				'max' => 500000,
			], // Represents GBP 10 - 5,000 AUD.
			'EUR' => [
				'min' => 1000,
				'max' => 500000,
			], // Represents EUR 10 - 5,000 AUD.
		];
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
