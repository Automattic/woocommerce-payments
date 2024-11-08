<?php
/**
 * Class Eps_Payment_Method
 *
 * @package WCPay\Payment_Methods
 */

namespace WCPay\Payment_Methods;

use WC_Payments_Token_Service;
use WCPay\Constants\Country_Code;
use WCPay\Constants\Currency_Code;

/**
 * EPS Payment Method class extending UPE base class
 */
class Eps_Payment_Method extends UPE_Payment_Method {

	const PAYMENT_METHOD_STRIPE_ID = 'eps';

	/**
	 * Constructor for EPS payment method
	 *
	 * @param WC_Payments_Token_Service $token_service Token class instance.
	 */
	public function __construct( $token_service ) {
		parent::__construct( $token_service );
		$this->stripe_id   = self::PAYMENT_METHOD_STRIPE_ID;
		$this->title       = 'EPS';
		$this->is_reusable = false;
		$this->currencies  = [ Currency_Code::EURO ];
		$this->icon_url    = plugins_url( 'assets/images/payment-methods/eps.svg', WCPAY_PLUGIN_FILE );
		$this->countries   = [ Country_Code::AUSTRIA ];
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
