<?php
/**
 * Class StateFactory
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Payment;

use WCPay\Container;
use WCPay\Internal\DependencyManagement\ContainerException;
use WCPay\Internal\Payment\Exception\StateTransitionException;
use WCPay\Internal\Payment\State\State;

/**
 * Factory for payment states.
 */
class StateFactory {
	/**
	 * Dependency Container.
	 *
	 * @var Container
	 */
	protected $container;

	/**
	 * Payment constructor.
	 *
	 * @param Container $container Dependency container for loading states.
	 */
	public function __construct( Container $container ) {
		$this->container = $container;
	}

	/**
	 * Generates a new payment state.
	 *
	 * This method expects the class name of the state, instead of an object. That is
	 * both to manage dependencies, and to make sure that states cannot be manipulated
	 * outside of the payment's context.
	 *
	 * @param string $state_class Name of the new state. Must be a child of `State`.
	 * @throws StateTransitionException If the provided class is not the one of a state.
	 */
	public function create_state( string $state_class ) {
		$state = null;

		if ( is_subclass_of( $state_class, State::class ) ) {
			try {
				// Load the state from the container.
				$state = $this->container->get( $state_class );
			} catch ( ContainerException $e ) {
				// A proper exception would be thrown if the state is null.
				$state = null;
			}
		}

		if ( is_null( $state ) ) {
			throw new StateTransitionException(
				sprintf(
					// Translators: %1$s is the PHP class for a new payment state, %1$s is the state base class.
					__( 'The class %1$s either does not exist, or is not a subclass of %2$s', 'woocommerce-payments' ),
					$state_class,
					State::class
				)
			);
		}

		return $state;
	}
}
