<?php
/**
 * Abstract UPE Payment Method class
 *
 * Handles general functionality for UPE payment methods
 *
 * @package WCPay\Payment_Methods
 */

namespace WCPay\Payment_Methods;

/**
 * Extendable abstract class for payment methods.
 */
abstract class UPE_Payment_Method {

	/**
	 * Can payment method be saved or reused?
	 *
	 * @var bool
	 */
	protected $can_reuse_payment_method;

	/**
	 * Instance of WC Payments Token Service to save payment method
	 *
	 * @var WC_Payments_Token_Service
	 */
	protected $token_service;

	/**
	 * Create instance of payment method
	 *
	 * @param WC_Payments_Token_Service $token_service Instance of WC_Payments_Token_Service.
	 */
	public function __construct( $token_service ) {
		$this->token_service = $token_service;
	}

	/**
	 * Returns payment method ID
	 *
	 * @return string
	 */
	public function get_id() {
		return self::STRIPE_ID;
	}

	/**
	 * Returns payment method title
	 *
	 * @return string
	 */
	public function get_title() {
		return self::TITLE;
	}

	/**
	 * Returns boolean dependent on whether payment method
	 * can be used at checkout
	 *
	 * @var bool
	 */
	public function is_enabled_at_checkout() {
		return true;
	}

	/**
	 * Returns boolean dependent on whether payment method
	 * will support saved payments/subscription payments
	 *
	 * @return bool
	 */
	public function is_payment_method_reusable() {
		return $this->can_reuse_payment_method;
	}

	/**
	 * Add payment method to user and return WC payment token
	 *
	 * @param WP_User $user User to get payment token from.
	 * @param string  $payment_method_id Stripe payment method ID string.
	 *
	 * @return WC_Payment_Token_CC|WC_Payment_Token_Sepa WC object for payment token.
	 */
	public function get_token_for_payment_method( $user, $payment_method_id ) {
		return $this->token_service->add_payment_method_to_user( $user, $payment_method_id );
	}
}
