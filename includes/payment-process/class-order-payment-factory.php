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
	 */
	public function load_or_create_order_payment( WC_Order $order ) {
		$payment = new Order_Payment( $this->payment_storage );
		$payment->load_from_order( $order );
		return $payment;
	}
}
