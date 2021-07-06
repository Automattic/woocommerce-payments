<?php
/**
 * Class Sofort_Payment_Method
 *
 * @package WCPay\Payment_Methods
 */

namespace WCPay\Payment_Methods;

use WP_User;
use WC_Payments_Token_Service;
use WC_Payment_Token_WCPay_SEPA;

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
		$this->is_reusable = true;
	}

	/**
	 * Add payment method to user and return WC payment token
	 *
	 * @param WP_User     $user User to get payment token from.
	 * @param string|bool $payment_method_id Stripe payment method ID string.
	 *
	 * @return WC_Payment_Token_WCPay_SEPA WC object for payment token.
	 */
	public function get_payment_token_for_user( $user, $payment_method_id = false ) {
		// TODO: This function will need to work a little differently...
		return $this->token_service->add_payment_method_to_user( $user, $payment_method_id );
	}

}
