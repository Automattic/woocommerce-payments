<?php
/**
 * Class WooContainerDelegate
 *
 * @package WooPayments
 */

namespace WooPayments\Internal\DependencyManagement;

use Psr\Container\ContainerInterface;

/**
 * WooCommerce container delegate.
 *
 * This class refers to the WooCommerce container to allow
 * delegation within from within our primary container.
 */
class WooContainerDelegate implements ContainerInterface {
	/**
	 * Finds an entry of the container by its identifier and returns it.
	 *
	 * @param string $id Identifier of the entry to look for.
	 * @return mixed Entry.
	 */
	public function get( string $id ) {
		return wc_get_container()->get( $id );
	}

	/**
	 * Returns true if the container can return an entry for the given identifier.
	 * Returns false otherwise.
	 *
	 * @param string $id Identifier of the entry to look for.
	 * @return bool
	 */
	public function has( string $id ) {
		return wc_get_container()->has( $id );
	}
}
