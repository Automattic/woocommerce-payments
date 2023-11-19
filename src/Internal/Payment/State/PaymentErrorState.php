<?php
/**
 * Class PaymentErrorState
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Payment\State;

/**
 * Erroneous state, caused by invalid/non-working customer input.
 *
 * Though this is an erroneous state, it is not unexpected, and
 * represents an error on the buyer's side, which cannot be fixed through code.
 */
class PaymentErrorState extends AbstractPaymentErrorState {

	/**
	 * Determines whether an error should be logged.
	 *
	 * @return bool True if the error should be logged, otherwise false.
	 */
	public function should_log_error() : bool {
		return true;
	}
}
