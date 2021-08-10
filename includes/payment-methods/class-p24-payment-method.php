<?php
/**
 * Class P24_Payment_Method
 *
 * @package WCPay\Payment_Methods
 */

namespace WCPay\Payment_Methods;

use WC_Payments_Token_Service;

/**
 * P24 Payment Method class extending UPE base class
 */
class P24_Payment_Method extends UPE_Payment_Method {

	/**
	 * Constructor for P24 payment method
	 *
	 * @param WC_Payments_Token_Service $token_service Token class instance.
	 */
	public function __construct( $token_service ) {
		parent::__construct( $token_service );
		$this->stripe_id   = 'p24';
		$this->title       = 'Przelewy24 (P24)';
		$this->is_reusable = false;
		$this->currencies  = [ 'EUR', 'PLN' ];
	}
}
