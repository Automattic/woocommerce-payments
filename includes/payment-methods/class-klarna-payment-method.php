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
		$this->countries                    = [ Country_Code::UNITED_STATES, Country_Code::UNITED_KINGDOM, Country_Code::AUSTRIA, Country_Code::GERMANY, Country_Code::NETHERLANDS, Country_Code::BELGIUM, Country_Code::SPAIN, Country_Code::ITALY, Country_Code::IRELAND, Country_Code::DENMARK, Country_Code::FINLAND, Country_Code::NORWAY, Country_Code::SWEDEN ];
		$this->limits_per_currency          = [
			'USD' => [
				Country_Code::UNITED_STATES => [
					'min' => 0,
					'max' => 1000000,
				],
			],
			'GBP' => [
				Country_Code::UNITED_KINGDOM => [
					'min' => 0,
					'max' => 1150000,
				],
			],
			'EUR' => [
				Country_Code::AUSTRIA     => [
					'min' => 1,
					'max' => 1000000,
				],
				Country_Code::BELGIUM     => [
					'min' => 1,
					'max' => 1000000,
				],
				Country_Code::GERMANY     => [
					'min' => 1,
					'max' => 1000000,
				],
				Country_Code::NETHERLANDS => [
					'min' => 1,
					'max' => 1500000,
				],
				Country_Code::FINLAND     => [
					'min' => 0,
					'max' => 1000000,
				],
				Country_Code::SPAIN       => [
					'min' => 0,
					'max' => 1000000,
				],
				Country_Code::IRELAND     => [
					'min' => 0,
					'max' => 400000,
				],
				Country_Code::ITALY       => [
					'min' => 0,
					'max' => 1000000,
				],
			],
			'DKK' => [
				Country_Code::DENMARK => [
					'min' => 100,
					'max' => 100000000,
				],
			],
			'NOK' => [
				Country_Code::NORWAY => [
					'min' => 0,
					'max' => 100000000,
				],
			],
			'SEK' => [
				Country_Code::SWEDEN => [
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
