<?php
/**
 * Class Abstract_Step
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Payment_Process\Step;

use WCPay\Payment_Process\Payment;

/**
 * An abstract class, base for all payment process steps.
 *
 * A step object will be created for each payment step, so storing
 * local properties is possible and encouraged.
 */
abstract class Abstract_Step {
	/**
	 * Checks if the step is applicable to a specific payment.
	 *
	 * @param Payment $payment The payment, which is being processed.
	 * @return bool
	 */
	public function is_applicable( Payment $payment ) {
		return true;
	}

	/**
	 * Collects data for the step, before it's time to process.
	 *
	 * @param Payment $payment The payment, which is being processed.
	 */
	public function collect_data( Payment $payment ) {
		// Nothing to do here, but child steps might.
	}

	/**
	 * Performs the main actionable steps.
	 *
	 * @param Payment $payment The payment, which is being processed.
	 */
	public function action( Payment $payment ) {
		// Nothing to do here, but child steps might.
	}

	/**
	 * Completes the processing of a step, ex. storing meta data, etc.
	 *
	 * @param Payment $payment The payment, which is being processed.
	 */
	public function complete( Payment $payment ) {
		// Nothing to do here, but child steps might.
	}
}
