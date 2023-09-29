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
	 * @param string         $state_class Name of the state class.
	 * @param PaymentContext $context     Context for the new state.
	 * @return PaymentState               The generated payment state instance.
	 * @throws StateTransitionException   When the class name is not a state.
	 */
	public function create_state( string $state_class, PaymentContext $context ): PaymentState {
		if ( ! is_subclass_of( $state_class, PaymentState::class ) ) {
			throw new StateTransitionException(
				sprintf(
					// Translators: %1$s is the PHP class for a new payment state, %1$s is the state base class.
					__( 'The class %1$s is not a subclass of %2$s', 'woocommerce-payments' ),
					$state_class,
					PaymentState::class
				)
			);
		}

		try {
			$state = $this->container->get( $state_class );
		} catch ( ContainerException $e ) {
			throw new StateTransitionException( $e->getMessage(), $e->getCode(), $e );
		}

		$state->set_context( $context );
		return $state;
	}
}
