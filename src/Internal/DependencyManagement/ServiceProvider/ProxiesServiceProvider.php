<?php
/**
 * Proxies service provider file.
 *
 * @package WooPayments
 */

namespace WooPayments\Internal\DependencyManagement\ServiceProvider;

use WooPayments\Internal\DependencyManagement\AbstractServiceProvider;
use WooPayments\Internal\Proxy\HooksProxy;
use WooPayments\Internal\Proxy\LegacyProxy;

/**
 * WooPayments Proxies service provider.
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
