<?php
/**
 * Class StateFactory
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Payment\State;

use WCPay\Container;
use WCPay\Vendor\League\Container\Exception\ContainerException;
use WCPay\Internal\Payment\Exception\StateTransitionException;
use WCPay\Internal\Payment\PaymentContext;

/**
 * A factory for payment states.
 *
 * The main purpose of this class is to work as an extension of the
 * WooPayments dependency container, only allowing access to payment states
 */
class StateFactory {
	/**
	 * Holds the WooPayments DI container.
	 *
	 * @var Container
	 */
	private $container;

	/**
	 * Class constructor.
	 *
	 * @param Container $container Dependency container.
	 */
	public function __construct( Container $container ) {
		$this->container = $container;
	}

	/**
	 * Creates a new state based on class name.
	 *
	 * @template ConcreteState
	 * @param class-string<ConcreteState> | string $state_class Name of the state class.
	 * @param PaymentContext                       $context     Context for the new state.
	 *
	 * @return AbstractPaymentState | ConcreteState                        The generated payment state instance.
	 * @throws ContainerException         When the dependency container cannot instantiate the state.
	 * @throws StateTransitionException   When the class name is not a state.
	 */
	public function create_state( string $state_class, PaymentContext $context ): AbstractPaymentState {
		if ( ! is_subclass_of( $state_class, AbstractPaymentState::class ) ) {
			throw new StateTransitionException(
				esc_html(
					sprintf(
					// Translators: %1$s is the PHP class for a new payment state, %1$s is the state base class.
						__( 'The class %1$s is not a subclass of %2$s', 'woocommerce-payments' ),
						$state_class,
						AbstractPaymentState::class
					)
				)
			);
		}

		$state = $this->container->get( $state_class );
		$context->log_state_transition( $state_class );
		$state->set_context( $context );

		return $state;
	}
}
