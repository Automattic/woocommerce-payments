<?php
/**
 * Class Container
 *
 * @package WooPayments
 */

namespace WooPayments;

use Psr\Container\ContainerInterface;
use WooPayments\Internal\DependencyManagement\ExtendedContainer;
use WooPayments\Internal\DependencyManagement\WooContainerDelegate;

/**
 * WooPayments Dependency Injection Container.
 *
 * Wraps the ExtendedContainer implementation to only allow
 * public access to certain methods of the internal container.
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

		$this->container->addShared( static::class, $this );

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
