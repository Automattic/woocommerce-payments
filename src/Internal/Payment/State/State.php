<?php
/**
 * Class State
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Payment\State;

use WCPay\Internal\Payment\Exception\MethodUnavailableException;
use WCPay\Internal\Payment\Payment;
use WCPay\Internal\Payment\Response\ResponseInterface;
use WCPay\Internal\Payment\PaymentRequest;
use WCPay\Internal\Payment\StateFactory;

/**
 * Base class for all payment states.
 */
abstract class State {
	/**
	 * Stores the factory, used to generate new states.
	 *
	 * @var StateFactory
	 */
	private $state_factory;

	/**
	 * Payment (context) of the state.
	 *
	 * @var Payment
	 */
	private $payment;

	/**
	 * Class constructor, only meant for storing dependencies.
	 *
	 * @param StateFactory $state_factory State factory.
	 */
	public function __construct( StateFactory $state_factory ) {
		$this->state_factory = $state_factory;
	}

	/**
	 * Stores the internal payment of the state.
	 *
	 * @param Payment $payment Without a payment (context), states are not functional.
	 */
	public function set_context( Payment $payment ) {
		$this->payment = $payment;
	}

	/**
	 * Returns the context of the state.
	 *
	 * @return Payment
	 */
	public function get_context() {
		return $this->payment;
	}

	/**
	 * Creates a new state, associated with the current payment.
	 *
	 * @param string $state_class Classname of the next state.
	 * @return State
	 */
	protected function create_state( string $state_class ) {
		$state = $this->state_factory->create_state( $state_class );
		$state->set_context( $this->payment );
		return $state;
	}

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

	// State-specific methods might declare a return type, but
	// their hollow definitions here would only throw an exception.
	// phpcs:disable Squiz.Commenting.FunctionComment.InvalidNoReturn

	/**
	 * Payment processing method.
	 *
	 * @param PaymentRequest $request Payment request, containing the necessary parameters.
	 * @return State                  Next payment state.
	 * @throws MethodUnavailableException In case the method is not available in the state.
	 */
	public function process( PaymentRequest $request ) {
		$this->throw_method_exception( __METHOD__ );
	}

	/**
	 * Returns the response, expected by the gateway's `process_payment()` method.
	 *
	 * @return ResponseInterface
	 * @throws MethodUnavailableException In case the method is not available in the state.
	 * @psalm-suppress InvalidReturnType If this method does not throw, it will return an array.
	 */
	public function get_processing_response() {
		$this->throw_method_exception( __METHOD__ );
	}

	// phpcs:enable Squiz.Commenting.FunctionComment.InvalidNoReturn
}
