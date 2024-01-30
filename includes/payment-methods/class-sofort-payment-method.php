<?php
/**
 * Class Sofort_Payment_Method
 *
 * @package WCPay\Payment_Methods
 */

namespace WCPay\Payment_Methods;

use WCPay\Constants\Country_Code;
use WP_User;
use WC_Payments_Token_Service;
use WCPay\Constants\Currency_Code;

/**
 * Sofort Payment Method class extending UPE base class
 */
class Sofort_Payment_Method extends UPE_Payment_Method {

	const PAYMENT_METHOD_STRIPE_ID = 'sofort';

	/**
	 * Constructor for Sofort payment method
	 *
	 * @param WC_Payments_Token_Service $token_service Token class instance.
	 */
	public function __construct( $token_service ) {
		parent::__construct( $token_service );
		$this->stripe_id   = self::PAYMENT_METHOD_STRIPE_ID;
		$this->title       = 'Sofort';
		$this->is_reusable = false;
		$this->currencies  = [ Currency_Code::EURO ];
		$this->icon_url    = plugins_url( 'assets/images/payment-methods/sofort.svg', WCPAY_PLUGIN_FILE );
		$this->countries   = [ Country_Code::AUSTRIA, Country_Code::BELGIUM, Country_Code::GERMANY, Country_Code::NETHERLANDS, Country_Code::SPAIN ];
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
