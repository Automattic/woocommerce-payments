<?php
/**
 * Class Completed_State
 *
 * @package WooCommerce\Payments
 */

 namespace WCPay\Payment\State;

/**
 * Represents a payment in the complete state, which requires no further processing.
 */
final class Completed_State extends Payment_State {
	/**
	 * Indicates that in this state the payment requires no further processing.
	 *
	 * @return bool
	 */
	public function is_processing_finished() {
		return true;
	}
}
