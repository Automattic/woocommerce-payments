<?php
/**
 * Class Registry
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Hooks;

use WCPay\Container;
use WCPay\Internal\Proxy\HooksProxy;
use WCPay\Internal\Service\ExampleServiceWithDependencies;

/**
 * A registry for classes that use WordPress hooks.
 */
class Registry {
	/**
	 * Dependency container.
	 *
	 * @var Container
	 */
	private $container;

	/**
	 * Proxy for hooks.
	 *
	 * @var HooksProxy
	 */
	private $hooks_proxy;

	/**
	 * Contains all insances, which have been initialized, preventing it from happening more than once.
	 *
	 * @var HasHooksInterface[]
	 */
	private $instances = [];

	/**
	 * Class constructor.
	 *
	 * @param Container  $container   The dependency container.
	 * @param HooksProxy $hooks_proxy The proxy for registering hooks.
	 */
	public function __construct(
		Container $container,
		HooksProxy $hooks_proxy
	) {
		$this->container   = $container;
		$this->hooks_proxy = $hooks_proxy;
	}

	/**
	 * Registers the hooks for all provided classes.
	 *
	 * @param string[] $class_names The classes of services, registering hooks.
	 */
	public function register( array $class_names ) {
		foreach ( $class_names as $class_name ) {
			$instance = $this->container->get( $class_name );
			$this->init( $instance );
		}
	}

	/**
	 * Initializes the hooks of a given instance.
	 *
	 * @param HasHooksInterface $instance The instance to initialize.
	 */
	private function init( HasHooksInterface $instance ) {
		if ( in_array( $instance, $this->instances, true ) ) {
			return;
		}

		$instance->init_hooks( $this->hooks_proxy );
		$this->instances[] = $instance;
	}
}
