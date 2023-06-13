<?php
/**
 * Class Strategy
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Payment\Strategy;

use WCPay\Payment\Payment;
use WCPay\Payment\State\Payment_State;

/**
 * Represents a payment processing strategy.
 */
abstract class Strategy {
	/**
	 * Processes the payment.
	 *
	 * @param  Payment $payment The payment to process.
	 * @return Payment_State    The next state the payment should transition to.
	 */
	abstract public function process( Payment $payment ): Payment_State;
}
