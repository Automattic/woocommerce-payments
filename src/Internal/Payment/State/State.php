<?php
/**
 * Class State
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Payment\State;

use WCPay\Internal\Payment\Exception\MethodUnavailableException;
use WCPay\Internal\Payment\Payment;

/**
 * Base class for all states.
 */
abstract class State {
	/**
	 * Payment (context) of the state.
	 *
	 * @var Payment
	 */
	protected $payment;

	/**
	 * Changes the context of the state.
	 * Without a payment (context), states are not functional.
	 *
	 * @param Payment $payment Context of the state.
	 */
	public function set_context( Payment $payment ) {
		$this->payment = $payment;
	}

	// State-specific methods might declare a return type, but
	// their hollow definitions here would only throw an exception.
	// phpcs:disable Squiz.Commenting.FunctionComment.InvalidNoReturn

	/**
	 * Returns the response, expected from a gateway's `process_payment()` method.
	 *
	 * @return array Whenever the current state implements the method.
	 * @throws MethodUnavailableException In case the method is not available in the state.
	 * @psalm-suppress InvalidReturnType If this method does not throw, it will return an array.
	 */
	public function get_gateway_response() {
		$this->throw_method_exception( __METHOD__ );
	}

	/**
	 * Preparation method.
	 *
	 * Not available in most states, unless overloaded.
	 *
	 * @param string $payment_method_id ID of the payment method to use.
	 * @throws MethodUnavailableException In case the method is not available in the state.
	 */
	public function prepare( string $payment_method_id ) {
		$this->throw_method_exception( __METHOD__ );
	}

	// phpcs:enable Squiz.Commenting.FunctionComment.InvalidNoReturn

	/**
	 * Throws an exception, indicating that a given method is not available.
	 *
	 * @param string $method_name The name of the called method.
	 * @throws MethodUnavailableException
	 */
	private function throw_method_exception( $method_name ) {
		throw new MethodUnavailableException(
			sprintf(
				// translators: %s is the name of a method of the payment object.
				__( 'The %s method is not available in the current payment state.', 'woocommerce-payments' ),
				$method_name
			)
		);
	}
}
