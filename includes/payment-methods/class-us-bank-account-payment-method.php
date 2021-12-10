<?php
/**
 * Class US_Bank_Account_Payment_Method
 *
 * @package WCPay\Payment_Methods
 */

namespace WCPay\Payment_Methods;

use WC_Payments_Token_Service;

/**
 * US Bank Account Payment Method class extending UPE base class
 */
class US_Bank_Account_Payment_Method extends UPE_Payment_Method {

	/**
	 * Constructor for US Bank Account payment method
	 *
	 * @param WC_Payments_Token_Service $token_service Token class instance.
	 */
	public function __construct( $token_service ) {
		parent::__construct( $token_service );
		$this->stripe_id   = 'us_bank_account';
		$this->title       = 'US Bank Account';
		$this->is_reusable = false;
		$this->currencies  = [ 'USD' ];
	}
}
