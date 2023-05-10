<?php
/**
 * Class Payment_Factory
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Payment;

use WCPay\Payment\Storage\Payment_Storage;
use WCPay\Payment\Payment;
use WCPay\Payment\Payment_Method\Payment_Method_Factory;

/**
 * Manages payment objects without orders to base them on.
 */
class Payment_Factory {
	/**
	 * Holds the payments storage.
	 *
	 * @var Payment_Storage
	 */
	protected $payment_storage;

	/**
	 * The factory for payment methods.
	 *
	 * @var Payment_Method_Factory
	 */
	protected $payment_method_factory;

	/**
	 * Initializes the factory.
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
	 * Loads a payment object from storage.
	 *
	 * @return Payment The newly created payment.
	 */
	public function create_payment() {
		$payment = new Payment( $this->payment_storage, $this->payment_method_factory );
		$payment->save(); // Force the saving process, as the ID comes from storage.
		return $payment;
	}

	/**
	 * Loads an existing payment.
	 *
	 * @param string $id The ID of the payment.
	 * @return Payment The payment object.
	 */
	public function load_payment( string $id ) {
		$payment = new Payment( $this->payment_storage, $this->payment_method_factory );
		$payment->set_id( $id );
		$this->payment_storage->load( $payment );
		return $payment;
	}
}
