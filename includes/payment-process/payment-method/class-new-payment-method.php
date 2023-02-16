<?php
/**
 * Class New_Payment_Method
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Payment_Process\Payment_Method;

/**
 * Representation of a newly entered payment method.
 */
class New_Payment_Method implements Payment_Method {
	/**
	 * Contains the payment provider payment method ID.
	 *
	 * @var string
	 */
	protected $id;

	/**
	 * Class constructor.
	 *
	 * @param string $id The external ID of the payment method.
	 */
	public function __construct( string $id ) {
		$this->id = $id;
	}

	/**
	 * Retrieves the data of the payment method for storage.
	 *
	 * @return array
	 */
	public function get_data() {
		return [
			'type' => 'new',
			'id'   => $this->id,
		];
	}
}
