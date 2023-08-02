<?php
/**
 * Abstract service provider file.
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\DependencyManagement;

use WCPay\Vendor\League\Container\ServiceProvider\AbstractServiceProvider as BaseProvider;

/**
 * WCPay base service provider.
 */
abstract class AbstractServiceProvider extends BaseProvider {
	/**
	 * Contains all provided classes/aliases.
	 *
	 * @var string[]
	 */
	protected $provides = [];

	/**
	 * Checks whether a specific class is provided.
	 *
	 * @param string $id ID of the class/alias to provide.
	 */
	public function provides( string $id ): bool {
		return in_array( $id, $this->provides, true );
	}
}
