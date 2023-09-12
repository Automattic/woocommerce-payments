<?php
/**
 * Class GatewayService
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Service;

use WC_Order;
use WC_Payment_Gateway_WCPay;
use WCPay\Container;

/**
 * Service for accessing gateway methods.
 *
 * __This service is a workaround!__
 * Various gateway methods, coming from core classes, cannot be
 * extracted out of the gateway. Use this service to access them.
 *
 * The gateway here is a dynamic dependency, accessed through the
 * container, instead of a constructor parameter. That's because
 * the gateway depends on various services, and there would be a
 * circular dependency otherwise.
 *
 * Before adding the method you need here, please consider
 * whether it could be extracted out of the gateway instead.
 */
class GatewayService {
	/**
	 * Dependency Container.
	 *
	 * @var Container
	 */
	protected $container;

	/**
	 * Class constructor.
	 *
	 * @param Container $container Factory for states.
	 */
	public function __construct( Container $container ) {
		$this->container = $container;
	}

	/**
	 * Shortcut for loading the gateway object.
	 *
	 * @return WC_Payment_Gateway_WCPay
	 *
	 * The gateway is initialized on `plugins_loaded` and should be always available.
	 * @psalm-suppress MissingThrowsDocblock
	 */
	private function get_gateway() {
		return $this->container->get( WC_Payment_Gateway_WCPay::class );
	}

	/**
	 * Get the return url (thank you / order received page).
	 *
	 * @param WC_Order $order Order object.
	 * @return string
	 */
	public function get_return_url( WC_Order $order ) {
		return $this->get_gateway()->get_return_url( $order );
	}
}
