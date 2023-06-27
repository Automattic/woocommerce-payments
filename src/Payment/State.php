<?php
/**
 * Abstract State Class.
 *
 * @package WCPay
 */

namespace WCPay\Payment;

use Exception;

/**
 * Payment state base class.
 */
abstract class State {
	/**
	 * Context of the state.
	 *
	 * @var Payment
	 */
	protected $context;

	/**
	 * Sets the context of the state.
	 *
	 * @param Payment $payment The payment object/context.
	 */
	public function set_context( Payment $payment ) {
		$this->context = $payment;
	}

	/**
	 * Returns the response of the checkout process.
	 *
	 * @throws \Exception
	 */
	public function get_response() {
		throw new Exception( 'Method not defined' );
	}
}
