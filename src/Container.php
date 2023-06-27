<?php
/**
 * Container class.
 *
 * @package WCPay
 */

namespace WCPay;

use WCPay\Internal\DependencyManagement\ExtendedContainer;
use WCPay\Internal\DependencyManagement\ServiceProvider\PaymentsServiceProvider;
use WCPay\Payment\Service;

/**
 * WCPay's DI Container.
 *
 * This class wraps and controls the internal implementation.
 */
class Container {
	/**
	 * Holds the internal container implementation.
	 *
	 * @var ExtendedContainer
	 */
	private $container;

	/**
	 * Contains the classes of all available providers.
	 *
	 * @var string[]
	 */
	private $providers = [
		PaymentsServiceProvider::class,
	];

	/**
	 * Instantiates the container.
	 */
	public function __construct() {
		$this->container = new ExtendedContainer();

		// Add this container.
		$this->container->addShared( static::class, $this );

		// Use providers for the rest.
		foreach ( $this->providers as $provider ) {
			$this->container->addServiceProvider( new $provider() );
		}

		// Temporary, to be removed.
		$this->container->addShared( Test::class )->addArgument( Service::class );
	}

	/**
	 * Retrieves an instance of a given class.
	 *
	 * @param string $id The ID of the class to retrieve.
	 * @return mixed     Entry.
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
	 * Adds an external dependency to the container.
	 *
	 * @param string $id       ID of the dependency.
	 * @param object $instance Instance to use.
	 */
	public function add_external_instance( $id, $instance ) {
		return $this->container->add( $id, $instance );
	}
}
