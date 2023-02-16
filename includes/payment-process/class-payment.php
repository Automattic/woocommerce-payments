<?php
/**
 * Class Payment
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Payment_Process;

use WCPay\Payment_Process\Storage\Payment_Storage;
use WCPay\Payment_Process\Payment_Method\Payment_Method;
use WCPay\Payment_Process\Payment_Method\Payment_Method_Factory;

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
	 * The factory for payment methods.
	 *
	 * @var Payment_Method_Factory
	 */
	protected $payment_method_factory;

	/**
	 * Holds the payment method.
	 *
	 * @var Payment_Method
	 */
	protected $payment_method;

	/**
	 * Instantiates the class.
	 *
	 * @param Payment_Storage        $storage                Storage to load/save payments from/to.
	 * @param Payment_Method_Factory $payment_method_factory Factory for payment methods.
	 */
	public function __construct(
		Payment_Storage $storage,
		Payment_Method_Factory $payment_method_factory
	) {
		$this->payment_storage        = $storage;
		$this->payment_method_factory = $payment_method_factory;
	}

	/**
	 * Loads the payment from an array.
	 *
	 * @param array $data The pre-existing payment data.
	 */
	public function load_data( array $data ) {
		if ( isset( $data['payment_method'] ) && ! empty( $data['payment_method'] ) ) {
			$this->payment_method = $this->payment_method_factory->from_storage( $data['payment_method'] );
		}
	}

	/**
	 * Returns the payment data, ready to store.
	 *
	 * @return array An array with everything important.
	 */
	public function get_data() {
		return [
			'payment_method' => $this->payment_method->get_data(),
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
	 * Returns the ID of the payment if any.
	 */
	public function get_id() {
		return $this->id;
	}

	/**
	 * Changes the payment method, used for the payment.
	 *
	 * @param Payment_Method $payment_method The used payment method.
	 */
	public function set_payment_method( Payment_Method $payment_method ) {
		$this->payment_method = $payment_method;
	}
}
