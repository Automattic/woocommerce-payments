<?php
/**
 * Class CompletedState
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Payment\State;

/**
 * Represents the payment in complete (successful) state.
 */
class CompletedState extends State {
	/**
	 * Returns the response, expected from a gateway's `process_payment()` method.
	 *
	 * @return string URL to redirect to.
	 */
	public function get_processing_response() {
		return $this->get_context()->get_order()->get_checkout_order_received_url();
	}
}
