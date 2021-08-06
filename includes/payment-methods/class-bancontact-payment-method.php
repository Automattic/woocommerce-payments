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

	/**
	 * Constructor for Bancontact payment method
	 *
	 * @param WC_Payments_Token_Service $token_service Token class instance.
	 */
	public function __construct( $token_service ) {
		parent::__construct( $token_service );
		$this->stripe_id   = 'bancontact';
		$this->title       = 'Bancontact';
		$this->is_reusable = false;
		$this->currencies  = [ 'EUR' ];
	}
}
