<?php
namespace WCPay\Payment_Process\Step;

use WCPay\Payment_Process\Payment;

abstract class Abstract_Step {
	/**
	 * Returns the ID of the step.
	 *
	 * @return string
	 */
	abstract public function get_id();

	public function is_applicable( Payment $payment ) {
		return true;
	}

	public function collect_data( Payment $payment ) {
		// Nothing to do here, but child steps might.
	}

	public function action( Payment $payment ) {
		// Nothing to do here, but child steps might.
	}

	public function complete( Payment $payment ) {
		// Nothing to do here, but child steps might.
	}

	// /**
	//  * Prepares a payment.
	//  */
	// public function prepare( Payment $payment );

	// /**
	//  * Performs the necessary steps.
	//  */
	// public function act( Payment $payment );
}
