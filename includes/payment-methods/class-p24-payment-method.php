<?php
/**
 * Class P24_Payment_Method
 *
 * @package WCPay\Payment_Methods
 */

namespace WCPay\Payment_Methods;

use WC_Payments_Token_Service;
use WCPay\Constants\Country_Code;
use WCPay\Constants\Currency_Code;

/**
 * P24 Payment Method class extending UPE base class
 */
class P24_Payment_Method extends UPE_Payment_Method {

	const PAYMENT_METHOD_STRIPE_ID = 'p24';

	/**
	 * Constructor for P24 payment method
	 *
	 * @param WC_Payments_Token_Service $token_service Token class instance.
	 */
	public function __construct( $token_service ) {
		parent::__construct( $token_service );
		$this->stripe_id   = self::PAYMENT_METHOD_STRIPE_ID;
		$this->title       = 'Przelewy24 (P24)';
		$this->is_reusable = false;
		$this->currencies  = [ Currency_Code::EURO, Currency_Code::POLISH_ZLOTY ];
		$this->icon_url    = plugins_url( 'assets/images/payment-methods/p24.svg', WCPAY_PLUGIN_FILE );
		$this->countries   = [ Country_Code::POLAND ];
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
