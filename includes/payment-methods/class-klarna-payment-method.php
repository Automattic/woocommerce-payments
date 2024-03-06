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
		$this->icon_url                     = plugins_url( 'assets/images/payment-methods/klarna-pill.svg', WCPAY_PLUGIN_FILE );
		$this->currencies                   = [ Currency_Code::UNITED_STATES_DOLLAR, Currency_Code::POUND_STERLING, Currency_Code::EURO, Currency_Code::DANISH_KRONE, Currency_Code::NORWEGIAN_KRONE, Currency_Code::SWEDISH_KRONA ];
		$this->accept_only_domestic_payment = true;
		$this->countries                    = [ Country_Code::UNITED_STATES, Country_Code::UNITED_KINGDOM, Country_Code::AUSTRIA, Country_Code::GERMANY, Country_Code::NETHERLANDS, Country_Code::BELGIUM, Country_Code::SPAIN, Country_Code::ITALY, Country_Code::IRELAND, Country_Code::DENMARK, Country_Code::FINLAND, Country_Code::NORWAY, Country_Code::SWEDEN ];
		$this->limits_per_currency          = [
			Currency_Code::UNITED_STATES_DOLLAR => [
				Country_Code::UNITED_STATES => [
					'min' => 0,
					'max' => 1000000,
				],
			],
			Currency_Code::POUND_STERLING       => [
				Country_Code::UNITED_KINGDOM => [
					'min' => 0,
					'max' => 1150000,
				],
			],
			Currency_Code::EURO                 => [
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
			Currency_Code::DANISH_KRONE         => [
				Country_Code::DENMARK => [
					'min' => 100,
					'max' => 100000000,
				],
			],
			Currency_Code::NORWEGIAN_KRONE      => [
				Country_Code::NORWAY => [
					'min' => 0,
					'max' => 100000000,
				],
			],
			Currency_Code::SWEDISH_KRONA        => [
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
