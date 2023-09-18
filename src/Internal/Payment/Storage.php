<?php
/**
 * Class Storage
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Payment;

use WC_Order;
use WCPay\Internal\Payment\State\State;

/**
 * Storage for payments.
 */
class Storage {
	/**
	 * State factory.
	 *
	 * @var StateFactory
	 */
	private $state_factory;

	/**
	 * Class constructor.
	 *
	 * @param StateFactory $state_factory A factory for payment states.
	 */
	public function __construct( StateFactory $state_factory ) {
		$this->state_factory = $state_factory;
	}

	/**
	 * Loads or creates the payment object for an order.
	 *
	 * Currently this method always creates a new payment.
	 * This will change in the future.
	 *
	 * @param WC_Order $order Order that requires/has payment.
	 * @return State          Current state of the order payment.
	 */
	public function get_order_payment( WC_Order $order ) {
		$payment = new Payment( $order );
		$state   = $payment->get_state( $this->state_factory );
		return $state;
	}
}
