<?php
/**
 * Payment service class
 *
 * @package WCPay
 */

namespace WCPay\Payment;

/**
 * This class will load payments.
 */
class Service {
	/**
	 * Generates a new payment.
	 *
	 * @return Payment
	 */
	public function create_payment() {
		return new Payment();
	}
}
