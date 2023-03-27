<?php
/**
 * Class Order_Payment_Factory
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Payment_Process;

use WC_Order;
use WCPay\Payment_Process\Storage\Payment_Storage;
use WCPay\Payment_Process\Order_Payment;
use WCPay\Payment_Process\Payment_Method\Payment_Method_Factory;

/**
 * Manages payment objects based on orders.
 */
class Order_Payment_Factory {
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
	 * Loads the payment object for an existing order.
	 * If it does not exist, a new one will be created and stored.
	 *
	 * @param WC_Order $order The order, which is about to be paid.
	 * @return Order_Payment  Either a newly created payment object, or an existing one.
	 */
	public function load_or_create_order_payment( WC_Order $order ) {
		$payment = new Order_Payment( $this->payment_storage, $this->payment_method_factory );
		$payment->set_order( $order );
		$this->payment_storage->load( $payment );

		// If no file was loaded, generate an ID, and store the payment information.
		if ( ! $payment->get_id() ) {
			// Forcing a save will let the storage method generate an ID.
			$payment->save();
		}

		return $payment;
	}

	/**
	 * If required, this will create a new payment for an existing order.
	 *
	 * EXPERIMENTAL PURPOSES ONLY!
	 * Normally an order should have a single payment object!
	 *
	 * @param  WC_Order $order The order, which needs payment.
	 * @return Order_Payment   A newly created object.
	 */
	public function create_order_payment( WC_Order $order ) {
		$payment = new Order_Payment( $this->payment_storage, $this->payment_method_factory );
		$payment->set_order( $order );
		$payment->save();
		return $payment;
	}

	/**
	 * Converts a non-order payment to an order payment.
	 *
	 * @param Payment  $payment An existing payment, even if it does not belong to an order.
	 * @param WC_Order $order   An order that the new payment should belong to.
	 * @return Order_Payment    The converted payment.
	 */
	public function covert_payment_to_order_payment( Payment $payment, WC_Order $order ) {
		$order_payment = new Order_Payment( $this->payment_storage, $this->payment_method_factory );
		$order_payment->set_order( $order );
		$order_payment->load_data( $payment->get_data() );

		// Save the new payment.
		$order_payment->save();

		// Delete the previous payment.
		$payment->delete();

		return $order_payment;
	}
}
