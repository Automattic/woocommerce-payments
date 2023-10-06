<?php
/**
 * Class AbstractPaymentState
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Payment\State;

use WCPay\Vendor\League\Container\Exception\ContainerException;
use WCPay\Internal\Payment\Exception\StateTransitionException;
use WCPay\Internal\Payment\PaymentContext;
use WCPay\Internal\Payment\PaymentRequest;
use WCPay\Internal\Payment\PaymentRequestException;
use WCPay\Exceptions\Order_Not_Found_Exception;

/**
 * Base class for payment states.
 */
abstract class AbstractPaymentState {
	/**
	 * Holds the context of the payments.
	 *
	 * The context contains working payment data,
	 * while states are purely functional and stateless.
	 *
	 * @var PaymentContext
	 */
	private $context;

	/**
	 * A factory for states.
	 *
	 * States can
	 *
	 * @var StateFactory
	 */
	private $state_factory;

	/**
	 * Class constructor, only meant for storing dependencies.
	 *
	 * @param StateFactory $state_factory Factory for payment states.
	 */
	public function __construct( StateFactory $state_factory ) {
		$this->state_factory = $state_factory;
	}

	/**
	 * Stores the payment context.
	 *
	 * @param PaymentContext $context Payment context.
	 */
	public function set_context( PaymentContext $context ) {
		$this->context = $context;
	}

	/**
	 * Returns the payment context.
	 *
	 * @return PaymentContext
	 */
	public function get_context(): PaymentContext {
		return $this->context;
	}

	/**
	 * Creates a new instance of a given payment state class.
	 *
	 * States control the payment flow, and allow transitions to the next state.
	 * This method should only be called whenever the process is ready to transition
	 * to the next state, as each new state will be considered the payment's latest one.
	 *
	 * @param string $state_class The class of the state to crate.
	 * @return AbstractPaymentState
	 * @throws StateTransitionException In case the new state could not be created.
	 * @throws ContainerException       When the dependency container cannot instantiate the state.
	 */
	protected function create_state( string $state_class ) {
		$state = $this->state_factory->create_state( $state_class, $this->context );

		// This is where logging will be added.

		return $state;
	}

	/**
	 * State-specific methods might declare a return type, but
	 * their hollow definitions here would only throw an exception.
	 * phpcs:disable Squiz.Commenting.FunctionComment.InvalidNoReturn
	 */

	/**
	 * Initialtes the payment process.
	 *
	 * @param PaymentRequest $request    The incoming payment processing request.
	 * @return CompletedState            The next state.
	 * @throws StateTransitionException  In case the completed state could not be initialized.
	 * @throws ContainerException        When the dependency container cannot instantiate the state.
	 * @throws Order_Not_Found_Exception Order could not be found.
	 * @throws PaymentRequestException   When data is not available or invalid.
	 * @psalm-suppress InvalidReturnType If this method does not throw, it will return a new state.
	 */
	public function process( PaymentRequest $request ) {
		$this->throw_unavailable_method_exception( __METHOD__ );
	}

	/**
	 * Throws an exception, indicating that a given method is not available.
	 *
	 * @param string $method_name The name of the called method.
	 * @throws StateTransitionException
	 */
	private function throw_unavailable_method_exception( string $method_name ) {
		throw new StateTransitionException(
			sprintf(
				// translators: %1$s is the name of a method of the payment object, %2$s is its current state.
				__( 'The %1$s method is not available in the current payment state (%2$s).', 'woocommerce-payments' ),
				$method_name,
				get_class( $this )
			)
		);
	}
}
