<?php
/**
 * Class InitialState
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Payment\State;

use WCPay\Internal\Payment\Exception\StateTransitionException;

/**
 * Initial state, representing a freshly created payment.
 */
class InitialState extends PaymentState {
	/**
	 * Initialtes the payment process.
	 *
	 * @return CompletedState The next state.
	 * @throws StateTransitionException In case the completed state could not be initialized.
	 */
	public function process() {
		return $this->create_state( CompletedState::class );
	}
}
