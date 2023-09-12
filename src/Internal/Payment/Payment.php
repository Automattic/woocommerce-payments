<?php
/**
 * Class Payment
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Payment;

use WC_Order;
use WCPay\Internal\Payment\Exception\StateTransitionException;
use WCPay\Internal\Payment\Exception\MethodUnavailableException;
use WCPay\Internal\Payment\State\InitialState;
use WCPay\Internal\Payment\State\State;

/**
 * Payment object.
 *
 * This object will represent a payment through its lifecycle.
 */
class Payment {
	/**
	 * State factory.
	 *
	 * @var StateFactory
	 */
	private $state_factory;

	/**
	 * Current state of the payment.
	 *
	 * @var State
	 */
	private $state;

	/**
	 * Order requiring/having payment.
	 *
	 * @var WC_Order
	 */
	private $order;

	/**
	 * Payment constructor.
	 *
	 * @param WC_Order     $order         Order requiring payment.
	 * @param StateFactory $state_factory Factory for states.
	 * @param string       $initial_state Class name of the state. Optional, defaults to `InitialState`.
	 * @throws StateTransitionException If the initial state cannot be entered.
	 */
	public function __construct(
		WC_Order $order,
		StateFactory $state_factory,
		string $initial_state = InitialState::class
	) {
		// Dependencies.
		$this->state_factory = $state_factory;

		// Working data.
		$this->order = $order;

		// Start with the right state.
		$this->set_state( $initial_state );
	}

	/**
	 * Updates the internal state of the payment.
	 *
	 * This method expects the class name of the state, instead of an object. That is
	 * both to manage dependencies, and to make sure that states cannot be manipulated
	 * outside of the payment's context.
	 *
	 * @param string $state_class Name of the new state. Must be a child of `State`.
	 * @throws StateTransitionException In case the new state could not be created.
	 */
	public function set_state( string $state_class ) {
		$state = $this->state_factory->create_state( $state_class );

		// Store the state internally, and link it to the payment.
		$this->state = $state;
		$state->set_context( $this );
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
	 * Preparation method.
	 *
	 * @param string $payment_method_id ID of the payment method to use.
	 * @throws MethodUnavailableException In case the current state does not support the method.
	 * @throws StateTransitionException If the transition to the next state fails.
	 */
	public function prepare( string $payment_method_id ) {
		$this->state->prepare( $payment_method_id );
	}

	/**
	 * Returns the response, expected from a gateway's `process_payment()` method.
	 *
	 * @return array Whenever the current state implements the method.
	 * @throws MethodUnavailableException In case the current state does not support the method.
	 */
	public function get_gateway_response() {
		return $this->state->get_gateway_response();
	}
}
