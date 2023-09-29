<?php
/**
 * Class Container
 *
 * @package WooCommerce\Payments
 */

namespace WCPay;

use Psr\Container\ContainerInterface;
use WCPay\Vendor\League\Container\Exception\ContainerException;
use WCPay\Internal\DependencyManagement\ExtendedContainer;
use WCPay\Internal\DependencyManagement\ServiceProvider\PaymentsServiceProvider;
use WCPay\Internal\DependencyManagement\DelegateContainer\LegacyContainer;
use WCPay\Internal\DependencyManagement\DelegateContainer\WooContainer;

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
	 * Dependencies should not be provided during runtime,
	 * but are useful while testing the container.
	 *
	 * @param LegacyContainer $legacy_container Delegate container for classes in `includes` (Optional).
	 * @param WooContainer    $woo_container    Delegate container for WooCommerce (Optional).
	 */
	public function __construct(
		LegacyContainer $legacy_container = null,
		WooContainer $woo_container = null
	) {
		$this->container = new ExtendedContainer();

		// Allow the container to be used as a dependency.
		$this->container->addShared( static::class, $this );

		// Add shared services.
		$this->load_providers();

		// Allow delegating unresolved queries to classes from `includes`.
		$this->container->delegate( $legacy_container ?? new LegacyContainer() );

		// Allow delegating unresolved queries to the WooCommerce container.
		$this->container->delegate( $woo_container ?? new WooContainer() );
	}

	/**
	 * Retrieves an instance of a given class.
	 *
	 * @template ID
	 * @param class-string<ID> $id The ID of the class to retrieve.
	 * @return ID
	 * @throws ContainerException In case the ID could not be resolved or instantiated.
	 *
	 * Psalm expects $id to be a string, based on ContainerInterface.
	 * @psalm-suppress MoreSpecificImplementedParamType
	 *
	 * PSR-11 containers declares to throw an un-throwable interface
	 * (it does not extend Throwable), and Psalm does not accept it.
	 * @psalm-suppress MissingThrowsDocblock
	 */
	public function get( $id ) {
		try {
			return $this->container->get( $id );
		} catch ( \Throwable $e ) {
			throw new ContainerException( $e->getMessage(), $e->getCode(), $e );
		}
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
