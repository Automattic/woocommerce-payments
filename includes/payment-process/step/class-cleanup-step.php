<?php
/**
 * Class Cleanup_Step
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Payment_Process\Step;

use WCPay\Payment_Methods\UPE_Payment_Gateway;
use WCPay\Payment_Methods\UPE_Split_Payment_Gateway;
use WCPay\Payment_Process\Order_Payment;
use WCPay\Payment_Process\Payment;

/**
 * Cleans up everything at the end.
 */
class Cleanup_Step extends Abstract_Step {
	/**
	 * Returns the ID of the step.
	 *
	 * @return string
	 */
	public function get_id() {
		return 'cleanup';
	}

	/**
	 * Checks if the step is applicable.
	 *
	 * @param Payment $payment The processing payment.
	 * @return bool
	 */
	public function is_applicable( Payment $payment ) {
		return $payment instanceof Order_Payment;
	}

	/**
	 * Completes the step.
	 *
	 * @param Payment $payment The processing payment.
	 */
	public function complete( Payment $payment ) {
		if ( ! $payment instanceof Order_Payment ) {
			return;
		}

		UPE_Payment_Gateway::remove_upe_payment_intent_from_session();
		UPE_Split_Payment_Gateway::remove_upe_payment_intent_from_session();
	}
}
