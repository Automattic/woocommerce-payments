<?php
/**
 * Class Container
 *
 * @package WooCommerce\Payments
 */

namespace WCPay;

use Psr\Container\ContainerInterface;
use WCPay\Internal\DependencyManagement\DelegateContainer\WooContainer;
use WCPay\Internal\DependencyManagement\ExtendedContainer;
use WCPay\Internal\DependencyManagement\ServiceProvider\PaymentsServiceProvider;

/**
 * WCPay Dependency Injection Container.
 *
 * Wraps the ExtendedContainer implementation to only allow public access to
 * certain methods of the internal container.
 *
 * The public methods of this class should be a subset of the ExtendedContainer
 * methods, and follow their signatures. Avoid adding other methods here.
 *
 * During tests, the internal ExtendedContainer is available through
 * the `wcpay_get_test_container()` method, allowing full manipulation.
 */
class Container implements ContainerInterface {
	/**
	 * Internal container instance.
	 *
	 * @var ExtendedContainer
	 */
	private $container;

	/**
	 * Initializes the container.
	 *
	 * Dependencies should not be provided during runtime, but will allow
	 * mocking during tests. This is only needed for the container.
	 *
	 * @param WooContainer $woo_container The delegate container for WooCommerce (Optional).
	 */
	public function __construct( WooContainer $woo_container = null ) {
		$this->container = new ExtendedContainer();

		// Allow the container to be used as a dependency.
		$this->container->addShared( static::class, $this );

		// Add shared services.
		$this->load_providers();

		// Allow delegating unresolved queries to the WooCommerce container.
		$this->container->delegate( $woo_container ?? new WooContainer() );
	}

	/**
	 * Retrieves an instance of a given class.
	 *
	 * @template ID
	 * @param class-string<ID> $id The ID of the class to retrieve.
	 * @return ID
	 */
	public function get( $id ) {
		return $this->container->get( $id );
	}

	/**
	 * Checks if a class is available.
	 *
	 * @param string $id The ID of the class to check.
	 * @return bool
	 */
	public function has( $id ) {
		return $this->container->has( $id );
	}

	/**
	 * Loads all available providers into the container.
	 */
	private function load_providers() {
		$this->container->addServiceProvider( new PaymentsServiceProvider() );
	}
}
