<?php
/**
 * Class Main_Payment_Method
 *
 * @package WCPay\Payment_Methods
 */

namespace WCPay\Payment_Methods;

use WC_Payments_Token_Service;
use WCPay\Constants\Payment_Method;

/**
 * Main Payment Method class.
 */
class Main_Payment_Method extends UPE_Payment_Method {

	const PAYMENT_METHOD_STRIPE_ID = Payment_Method::MAIN;

	/**
	 * Constructor for card payment method
	 *
	 * @param WC_Payments_Token_Service $token_service Token class instance.
	 */
	public function __construct( $token_service ) {
		parent::__construct( $token_service );
		$this->stripe_id   = self::PAYMENT_METHOD_STRIPE_ID;
		$this->title       = __( 'Main Gateway', 'woocommerce-payments' );
		$this->is_reusable = false;
		$this->currencies  = [ '###' ]; // No currencies are supported.
		$this->icon_url    = '';
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
