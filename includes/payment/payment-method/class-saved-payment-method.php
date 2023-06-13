<?php
/**
 * Class Stored_Payment_Method
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Payment\Payment_Method;

use WC_Payment_Token;

/**
 * Representation of a stored payment method (token).
 *
 * WooCommerce already has the WC_Payment_Token class, and this
 * one is a wrapper around the payment method, used during checkout.
 */
class Saved_Payment_Method implements Payment_Method {
	/**
	 * Contains the WooCommerce token.
	 *
	 * @var WC_Payment_Token
	 */
	protected $token;

	/**
	 * Class constructor.
	 *
	 * @param WC_Payment_Token $token The WooCommerce token.
	 */
	public function __construct( WC_Payment_Token $token ) {
		$this->token = $token;
	}

	/**
	 * Returns the ID of the payment method.
	 *
	 * @return string
	 */
	public function get_id() {
		return $this->token->get_token();
	}

	/**
	 * Returns the complete token object.
	 *
	 * @return WC_Payment_Token
	 */
	public function get_token() {
		return $this->token;
	}

	/**
	 * Retrieves the data of the payment method for storage.
	 *
	 * @return array
	 */
	public function get_data() {
		return [
			'type' => 'saved',
			'id'   => $this->token->get_id(),
		];
	}
}
