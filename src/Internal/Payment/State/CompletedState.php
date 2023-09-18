<?php
/**
 * Class CompletedState
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Payment\State;

use WCPay\Internal\Payment\Response\ProcessingCompleted;

/**
 * Represents the payment in complete (successful) state.
 */
class CompletedState extends State {
	/**
	 * Returns the response, expected from a gateway's `process_payment()` method.
	 *
	 * @return ProcessingCompleted Whenever the current state implements the method.
	 */
	public function get_processing_response() {
		$url = $this->payment->get_order()->get_checkout_order_received_url();
		return new ProcessingCompleted( $url );
	}
}
