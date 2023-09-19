<?php
/**
 * Class SavedPaymentMethod
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Payment\PaymentMethod;

use WC_Payment_Token;

/**
 * Representation of a newly entered payment method.
 */
class SavedPaymentMethod implements PaymentMethodInterface {
	/**
	 * Contains the WooCommerce token.
	 *
	 * @var WC_Payment_Token
	 */
	private $token;

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
	public function get_id(): string {
		return $this->token->get_token();
	}

	/**
	 * Retrieves the data of the payment method for storage.
	 *
	 * @return array
	 */
	public function get_data(): array {
		return [
			'type' => 'saved',
			'id'   => $this->token->get_id(),
		];
	}
}
