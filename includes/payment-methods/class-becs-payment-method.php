<?php
/**
 * Class Becs_Payment_Method
 *
 * @package WCPay\Payment_Methods
 */

namespace WCPay\Payment_Methods;

use WC_Payments_Token_Service;

/**
 * Becs Payment Method class extending UPE base class
 */
class Becs_Payment_Method extends UPE_Payment_Method {

	/**
	 * Constructor for Becs payment method
	 *
	 * @param WC_Payments_Token_Service $token_service Token class instance.
	 */
	public function __construct( $token_service ) {
		parent::__construct( $token_service );
		$this->stripe_id   = 'au_becs_debit';
		$this->title       = 'BECS Direct Debit';
		$this->is_reusable = false;
		$this->currencies  = [ 'AUD' ];
	}
}
