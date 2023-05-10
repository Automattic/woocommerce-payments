<?php
/**
 * Class New_Payment_Method
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Payment\Payment_Method;

use WC_Payment_Gateway_WCPay;

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

	/**
	 * Returns the ID of the payment method.
	 *
	 * @return string
	 */
	public function get_id() {
		return $this->id;
	}

	/**
	 * Checks if the new payment method should be saved.
	 *
	 * During normal orders the payment method is saved when the customer enters a new one and chooses to save it.
	 * This is just a helper, use the payment object to check if the payment method should be saved.
	 *
	 * @param array $request The current request.
	 * @return bool          A boolean flag.
	 */
	public static function should_be_saved( $request ) {
		return ! empty( $request[ 'wc-' . WC_Payment_Gateway_WCPay::GATEWAY_ID . '-new-payment-method' ] );
	}
}
