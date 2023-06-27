<?php
/**
 * Container class.
 *
 * @package WCPay
 */

namespace WCPay;

use WC_Payment_Gateway_WCPay;
use WCPay\Internal\DependencyManagement\ExtendedContainer;
use WCPay\Payment\Service;
use WCPay\Payment\State\InitialState;
use WCPay\Payment\State\PreparedState;

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
	protected $container;

	/**
	 * Instantiates the container.
	 */
	public function __construct() {
		$this->container = new ExtendedContainer();

		$this->container->add( static::class, $this );
		$this->container->add( Service::class )->addArgument( static::class );
		$this->container->add( Test::class )->addArgument( Service::class );
		$this->container->add( InitialState::class );
		$this->container->add( PreparedState::class )->addArgument( WC_Payment_Gateway_WCPay::class );
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
