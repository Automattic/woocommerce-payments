<?php
/**
 * Class WooContainer
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\DependencyManagement\DelegateContainer;

use WCPay\Vendor\Psr\Container\ContainerInterface;

/**
 * WooCommerce container delegate.
 *
 * This class refers to the WooCommerce container to allow
 * delegation within from within our primary container.
 */
class WooContainer implements ContainerInterface {
	/**
	 * Finds an entry of the container by its identifier and returns it.
	 *
	 * @param string $id Identifier of the entry to look for.
	 * @return mixed Entry.
	 * @psalm-suppress MissingThrowsDocblock.
	 */
	public function get( $id ) {
		return wc_get_container()->get( $id );
	}

	/**
	 * Returns true if the container can return an entry for the given identifier.
	 * Returns false otherwise.
	 *
	 * @param string $id Identifier of the entry to look for.
	 * @return bool
	 */
	public function has( $id ) {
		return wc_get_container()->has( $id );
	}
}
