<?php
/**
 * Class Affirm_Payment_Method
 *
 * @package WCPay\Payment_Methods
 */

namespace WCPay\Payment_Methods;

use WC_Payments_Token_Service;
use WC_Payments_Utils;
use WCPay\Constants\Country_Code;
use WCPay\Constants\Currency_Code;
use WCPay\MultiCurrency\MultiCurrency;

/**
 * Affirm Payment Method class extending UPE base class
 */
class Affirm_Payment_Method extends UPE_Payment_Method {

	const PAYMENT_METHOD_STRIPE_ID = 'affirm';

	/**
	 * Constructor for link payment method
	 *
	 * @param WC_Payments_Token_Service $token_service Token class instance.
	 */
	public function __construct( $token_service ) {
		parent::__construct( $token_service );
		$this->stripe_id                    = self::PAYMENT_METHOD_STRIPE_ID;
		$this->title                        = __( 'Affirm', 'woocommerce-payments' );
		$this->is_reusable                  = false;
		$this->icon_url                     = plugins_url( 'assets/images/payment-methods/affirm-logo.svg', WCPAY_PLUGIN_FILE );
		$this->dark_icon_url                = plugins_url( 'assets/images/payment-methods/affirm-logo-dark.svg', WCPAY_PLUGIN_FILE );
		$this->currencies                   = [ Currency_Code::UNITED_STATES_DOLLAR, Currency_Code::CANADIAN_DOLLAR ];
		$this->accept_only_domestic_payment = true;
		$this->limits_per_currency          = [
			Currency_Code::CANADIAN_DOLLAR      => [
				Country_Code::CANADA => [
					'min' => 5000,
					'max' => 3000000,
				], // Represents CAD 50 - 30,000 CAD.
			],
			Currency_Code::UNITED_STATES_DOLLAR => [
				Country_Code::UNITED_STATES => [
					'min' => 5000,
					'max' => 3000000,
				], // Represents USD 50 - 30,000 USD.
			],
		];
		$this->countries                    = [ Country_Code::UNITED_STATES, Country_Code::CANADA ];
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
