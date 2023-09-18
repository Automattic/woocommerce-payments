<?php
/**
 * Class Payment
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Payment;

use WC_Order;
use WCPay\Internal\Payment\State\InitialState;
use WCPay\Internal\Payment\State\State;

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

	/**
	 * Generates and returns the current state of the payment.
	 *
	 * @param StateFactory $state_factory A factory that generates states with dependencies.
	 * @return State
	 */
	public function get_state( StateFactory $state_factory ): State {
		$state = $state_factory->create_state( InitialState::class );
		$state->set_context( $this );
		return $state;
	}
}
