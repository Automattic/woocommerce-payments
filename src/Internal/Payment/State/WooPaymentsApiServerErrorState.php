<?php
/**
 * Class WooPaymentsApiServerErrorState
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Payment\State;

/**
 * Erroneous state, caused when WooPayments API return error response.
 */
class WooPaymentsApiServerErrorState extends AbstractPaymentErrorState {

	/**
	 * Determines whether an error should be logged.
	 *
	 * @return bool True if the error should be logged, otherwise false.
	 */
	public function should_log_error() : bool {
		return true;
	}
}
