<?php
/**
 * Class Alipay_Payment_Method
 *
 * @package WCPay\Payment_Methods
 */

namespace WCPay\Payment_Methods;

use WC_Payments_Token_Service;
use WCPay\Constants\Country_Code;

/**
 * Alipay Payment Method class extending UPE base class
 */
class Alipay_Payment_Method extends UPE_Payment_Method {

	const PAYMENT_METHOD_STRIPE_ID = 'alipay';

	/**
	 * Constructor for Alipay payment method
	 *
	 * @param WC_Payments_Token_Service $token_service Token class instance.
	 */
	public function __construct( $token_service ) {
		parent::__construct( $token_service );
		$this->stripe_id                    = self::PAYMENT_METHOD_STRIPE_ID;
		$this->is_reusable                  = false;
		$this->is_bnpl                      = false;
		$this->icon_url                     = plugins_url( 'assets/images/payment-methods/card-pill.svg', WCPAY_PLUGIN_FILE );
		$this->currencies = [
			'CNY',
			'AUD',
			'CAD',
			'EUR',
			'GBP',
			'HKD',
			'JPY',
			'SGD',
			'MYR',
			'NZD',
			'USD',
		];
		$this->accept_only_domestic_payment = false;
		$this->countries                    = [ Country_Code::CHINA ];
	}

	/**
	 * Returns payment method title
	 *
	 * @param string|null $account_country Country of merchants account.
	 * @param array|false $payment_details Optional payment details from charge object.
	 *
	 * @return string
	 */
	public function get_title( ?string $account_country = null, $payment_details = false ) {
		return __( 'Alipay', 'woocommerce-payments' );
	}

	/**
	 * Returns testing credentials to be printed at checkout in test mode.
	 *
	 * @param string $account_country The country of the account.
	 * @return string
	 */
	public function get_testing_instructions( string $account_country ) {
		return '';
	}
}
