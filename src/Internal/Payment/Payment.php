<?php
/**
 * Class Payment
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Payment;

use WC_Order;

/**
 * Payment object.
 *
 * This is a data object, used as context and storage for payment states.
 */
class Payment {
	/**
	 * Order requiring/having payment.
	 *
	 * @var WC_Order
	 */
	private $order;

	/**
	 * Payment constructor.
	 *
	 * @param WC_Order $order Order requiring payment.
	 */
	public function __construct( WC_Order $order ) {
		$this->order = $order;
	}

	/**
	 * Returns the payment's order.
	 *
	 * @return WC_Order
	 */
	public function get_order() {
		return $this->order;
	}
}
