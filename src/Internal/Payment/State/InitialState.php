<?php
/**
 * Class InitialState
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Payment\State;

use WCPay\Internal\Payment\Exception\StateTransitionException;

/**
 * Initial payment state.
 */
class InitialState extends State {
	/**
	 * Prepares the payment, and transitions to the prepared state.
	 *
	 * @param string $payment_method_id ID of the payment method to use.
	 * @throws StateTransitionException In case the transition to CompletedState fails.
	 */
	public function prepare( string $payment_method_id ) {
		$this->payment->set_state( CompletedState::class );
	}
}
