<?php
/**
 * Class Order_Payment_Factory
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Payment_Process;

use Automattic\WooCommerce\Admin\Features\OnboardingTasks\Tasks\Payments;
use WC_Order;
use WCPay\Payment_Process\Storage\Payment_Storage;
use WCPay\Payment_Process\Order_Payment;

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
	 * Initializes the factory.
	 *
	 * @param Payment_Storage $storage The storage to load/save payments from/to.
	 */
	public function __construct( Payment_Storage $storage ) {
		$this->payment_storage = $storage;
	}

	/**
	 * Loads the payment object for an existing order.
	 * If it does not exist, a new one will be created and stored.
	 *
	 * @param WC_Order $order The order, which is about to be paid.
	 * @return Order_Payment  Either a newly created payment object, or an existing one.
	 */
	public function load_or_create_order_payment( WC_Order $order ) {
		$payment = new Order_Payment( $this->payment_storage );
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
		$payment = new Order_Payment( $this->payment_storage );
		$payment->set_order( $order );
		$payment->save();
		return $payment;
	}
}
