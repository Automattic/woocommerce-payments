<?php
/**
 * Proxies service provider file.
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\DependencyManagement\ServiceProvider;

use WCPay\Internal\DependencyManagement\AbstractServiceProvider;
use WCPay\Internal\Proxy\HooksProxy;
use WCPay\Internal\Proxy\LegacyProxy;

/**
 * WCPay proxies service provider.
 */
class ProxiesServiceProvider extends AbstractServiceProvider {
	/**
	 * Contains all provided classes/aliases.
	 *
	 * @var string[]
	 */
	protected $provides = [
		LegacyProxy::class,
		HooksProxy::class,
	];

	/**
	 * Registers all provided classes.
	 */
	public function register(): void {
		$container = $this->getContainer();

		$container->addShared( LegacyProxy::class );
		$container->addShared( HooksProxy::class );
	}
}
