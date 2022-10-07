<?php
/**
 * Class Bancontact_Payment_Method
 *
 * @package WCPay\Payment_Methods
 */

namespace WCPay\Payment_Methods;

use WC_Payments_Token_Service;

/**
 * Bancontact Payment Method class extending UPE base class
 */
class Bancontact_Payment_Method extends UPE_Payment_Method {

	const PAYMENT_METHOD_STRIPE_ID = 'bancontact';

	/**
	 * Constructor for Bancontact payment method
	 *
	 * @param WC_Payments_Token_Service $token_service Token class instance.
	 */
	public function __construct( $token_service ) {
		parent::__construct( $token_service );
		$this->stripe_id   = self::PAYMENT_METHOD_STRIPE_ID;
		$this->title       = 'Bancontact';
		$this->is_reusable = false;
		$this->currencies  = [ 'EUR' ];
		$this->icon_url    = plugins_url( 'assets/images/payment-methods/bancontact.svg', WCPAY_PLUGIN_FILE );
	}

	/**
	 * Returns testing credentials to be printed at checkout in test mode.
	 *
	 * @return string|bool
	 */
	public function get_testing_instructions() {
		return false;
	}

}
