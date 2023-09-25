<?php
/**
 * Class InitialState
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Payment\State;

/**
 * Initial payment state.
 */
class InitialState extends State {
	/**
	 * Processes a new payment.
	 *
	 * @return State Returns the next payment state.
	 */
	public function process() {
		$context = $this->get_context();

		return $this->create_state( CompletedState::class );
	}
}
