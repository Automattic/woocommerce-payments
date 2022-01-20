<?php
/**
 * Class Sofort_Payment_Method
 *
 * @package WCPay\Payment_Methods
 */

namespace WCPay\Payment_Methods;

use WP_User;
use WC_Payments_Token_Service;

/**
 * Sofort Payment Method class extending UPE base class
 */
class Sofort_Payment_Method extends UPE_Payment_Method {

	/**
	 * Constructor for Sofort payment method
	 *
	 * @param WC_Payments_Token_Service $token_service Token class instance.
	 */
	public function __construct( $token_service ) {
		parent::__construct( $token_service );
		$this->stripe_id   = 'sofort';
		$this->title       = 'Sofort';
		$this->is_reusable = false;
		$this->currencies  = [ 'EUR' ];
	}
}
