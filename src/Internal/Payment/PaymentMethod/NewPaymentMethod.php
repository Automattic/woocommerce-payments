<?php
/**
 * Class NewPaymentMethod
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Payment\PaymentMethod;

/**
 * Representation of a newly entered payment method.
 */
class NewPaymentMethod implements PaymentMethodInterface {
	/**
	 * External ID of the payment method.
	 *
	 * @var string
	 */
	private $id;

	/**
	 * Class constructor.
	 *
	 * @param string $id External ID of the payment method.
	 */
	public function __construct( string $id ) {
		$this->id = $id;
	}

	/**
	 * Retrieves the data of the payment method for storage.
	 *
	 * @return array
	 */
	public function get_data(): array {
		return [
			'type' => 'new',
			'id'   => $this->id,
		];
	}

	/**
	 * Returns the ID of the payment method.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return $this->id;
	}
}
