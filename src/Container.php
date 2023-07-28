<?php
/**
 * Class Container
 *
 * @package WooPayments
 */

namespace WooPayments;

use Psr\Container\ContainerInterface;
use WooPayments\Internal\DependencyManagement\ExtendedContainer;
use WooPayments\Internal\DependencyManagement\ServiceProvider\PaymentsServiceProvider;
use WooPayments\Internal\DependencyManagement\WooContainerDelegate;

/**
 * WooPayments Dependency Injection Container.
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
	 */
	public function __construct() {
		$this->container = new ExtendedContainer();

		// Allow the container to be used as a dependency.
		$this->container->addShared( static::class, $this );

		// Add shared services.
		$this->container->addServiceProvider( new PaymentsServiceProvider() );

		// Allow delegating unresolved queries to the WooCommerce container.
		$this->container->delegate( new WooContainerDelegate() );
	}

	/**
	 * Retrieves an instance of a given class.
	 *
	 * @param string $id The ID of the class to retrieve.
	 * @return mixed
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
}
