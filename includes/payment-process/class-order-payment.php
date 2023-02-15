<?php
/**
 * Class Order_Payment
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Payment_Process;

use WC_Order;

/**
 * Handles payments, which are specifically associated with orders.
 */
class Order_Payment extends Payment {
	/**
	 * Holds the order, which is/will be paid.
	 *
	 * @var WC_Order
	 */
	protected $order;

	/**
	 * Sets the order, used for the payment.
	 * Orders are required for payments, but set through the order payment factory.
	 *
	 * @see Order_Payment_Factory::load_or_create_order_payment()
	 * @param WC_Order $order The order for the payment.
	 */
	public function set_order( WC_Order $order ) {
		$this->order = $order;
	}

	/**
	 * Returns the order, associated with the payment.
	 *
	 * @return WC_Order The order for the payment.
	 */
	public function get_order() {
		return $this->order;
	}
}
