<?php
/**
 * Class SavedPaymentMethod
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Payment\PaymentMethod;

/**
 * Representation of a saved payment method.
 */
class SavedPaymentMethod implements PaymentMethodInterface {
	/**
	 * External ID of the payment method.
	 *
	 * @var string
	 */
	private $id;

	/**
	 * Contains the WooCommerce token ID.
	 *
	 * @var int
	 */
	private $token_id;

	/**
	 * Class constructor.
	 *
	 * @param string $id       External ID of the payment method.
	 * @param int    $token_id Internal WooCommerce token ID.
	 */
	public function __construct( string $id, int $token_id ) {
		$this->id       = $id;
		$this->token_id = $token_id;
	}

	/**
	 * Returns the ID of the payment method.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return $this->id;
	}

	/**
	 * Returns the ID of the Woo payment token.
	 *
	 * @return int
	 */
	public function get_token_id(): int {
		return $this->token_id;
	}

	/**
	 * Retrieves the data of the payment method for storage.
	 *
	 * @return array
	 */
	public function get_data(): array {
		return [
			'type'     => 'saved',
			'id'       => $this->id,
			'token_id' => $this->token_id,
		];
	}
}
