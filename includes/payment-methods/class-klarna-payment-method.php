<?php
/**
 * Class Affirm_Payment_Method
 *
 * @package WCPay\Payment_Methods
 */

namespace WCPay\Payment_Methods;

use WC_Payments_Token_Service;
use WC_Payments_Utils;
use WCPay\Constants\Country_Codes;
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
		$this->currencies                   = [ 'USD', 'GBP', 'EUR', 'DKK', 'NOK', 'SEK' ];
		$this->accept_only_domestic_payment = true;
		$this->countries                    = [ Country_Codes::UNITED_STATES, Country_Codes::UNITED_KINGDOM, Country_Codes::AUSTRIA, Country_Codes::GERMANY, Country_Codes::NETHERLANDS, Country_Codes::BELGIUM, Country_Codes::SPAIN, Country_Codes::ITALY, Country_Codes::IRELAND, Country_Codes::DENMARK, Country_Codes::FINLAND, Country_Codes::NORWAY, Country_Codes::SWEDEN ];
		$this->limits_per_currency          = [
			'USD' => [
				Country_Codes::UNITED_STATES => [
					'min' => 0,
					'max' => 1000000,
				],
			],
			'GBP' => [
				Country_Codes::UNITED_KINGDOM => [
					'min' => 0,
					'max' => 1150000,
				],
			],
			'EUR' => [
				Country_Codes::AUSTRIA => [
					'min' => 1,
					'max' => 1000000,
				],
				Country_Codes::BELGIUM => [
					'min' => 1,
					'max' => 1000000,
				],
				Country_Codes::GERMANY => [
					'min' => 1,
					'max' => 1000000,
				],
				Country_Codes::NETHERLANDS => [
					'min' => 1,
					'max' => 1500000,
				],
				Country_Codes::FINLAND => [
					'min' => 0,
					'max' => 1000000,
				],
				Country_Codes::SPAIN => [
					'min' => 0,
					'max' => 1000000,
				],
				Country_Codes::IRELAND => [
					'min' => 0,
					'max' => 400000,
				],
				Country_Codes::ITALY => [
					'min' => 0,
					'max' => 1000000,
				],
			],
			'DKK' => [
				Country_Codes::DENMARK => [
					'min' => 100,
					'max' => 100000000,
				],
			],
			'NOK' => [
				Country_Codes::NORWAY => [
					'min' => 0,
					'max' => 100000000,
				],
			],
			'SEK' => [
				Country_Codes::SWEDEN => [
					'min' => 0,
					'max' => 15000000,
				],
			],
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
