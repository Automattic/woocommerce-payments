<?php
/**
 * Class Payment
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Payment_Process;

use WCPay\Payment_Process\Storage\Payment_Storage;

/**
 * Main class, representing payments.
 */
abstract class Payment {
	/**
	 * Payment storage, used to store the payment.
	 *
	 * @var Payment_Storage
	 */
	protected $payment_storage;

	/**
	 * Holds the ID of the payment.
	 *
	 * @var string
	 */
	protected $id;

	/**
	 * Holds the ID of the used payment method.
	 *
	 * @var string
	 */
	protected $payment_method;

	/**
	 * Instantiates the class.
	 *
	 * @param Payment_Storage $storage The payment storage object.
	 */
	public function __construct( Payment_Storage $storage ) {
		$this->payment_storage = $storage;
	}

	/**
	 * Loads the payment from an array.
	 *
	 * @param array $data The pre-existing payment data.
	 */
	public function load_data( array $data ) {
		$this->payment_method = $data['payment_method'];
	}

	/**
	 * Returns the payment data, ready to store.
	 *
	 * @return array An array with everything important.
	 */
	public function get_data() {
		return [
			'payment_method' => $this->payment_method,
		];
	}

	/**
	 * Saves the payment data in storage.
	 */
	public function save() {
		$this->payment_storage->store( $this );
	}

	/**
	 * Allows the ID of the object to be stored.
	 *
	 * @param mixed $id The ID of the payment, used for storage.
	 */
	public function set_id( $id ) {
		$this->id = $id;
	}

	/**
	 * Returns the ID of the order if any.
	 */
	public function get_id() {
		return $this->id;
	}
}
