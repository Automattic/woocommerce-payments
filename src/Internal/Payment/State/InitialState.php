<?php
/**
 * Class InitialState
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Payment\State;

use WCPay\Vendor\League\Container\Exception\ContainerException;
use WCPay\Internal\Payment\Exception\StateTransitionException;

/**
 * Initial state, representing a freshly created payment.
 */
class InitialState extends AbstractPaymentState {
	/**
	 * Initialtes the payment process.
	 *
	 * @return CompletedState           The next state.
	 * @throws StateTransitionException In case the completed state could not be initialized.
	 * @throws ContainerException       When the dependency container cannot instantiate the state.
	 */
	public function process() {
		return $this->create_state( CompletedState::class );
	}
}
