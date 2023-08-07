<?php
/**
 * Class JCB_Payment_Method
 *
 * @package WCPay\Payment_Methods
 */

namespace WCPay\Payment_Methods;

use WP_User;
use WC_Payments_Token_Service;

/**
 * JCB Payment Method class extending UPE base class
 */
class JCB_Payment_Method extends UPE_Payment_Method {

	const PAYMENT_METHOD_STRIPE_ID = 'jcb';

	/**
	 * Constructor for JCB payment method
	 *
	 * @param WC_Payments_Token_Service $token_service Token class instance.
	 */
	public function __construct( $token_service ) {
		parent::__construct( $token_service );
		$this->stripe_id   = self::PAYMENT_METHOD_STRIPE_ID;
		$this->title       = 'JCB';
		$this->is_reusable = false;
		$this->currencies  = [ 'JPY' ];
		$this->icon_url    = plugins_url( 'assets/images/payment-methods/sofort.svg', WCPAY_PLUGIN_FILE );
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
