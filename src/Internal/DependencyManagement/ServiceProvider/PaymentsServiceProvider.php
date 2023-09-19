<?php
/**
 * Payments service provider file.
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\DependencyManagement\ServiceProvider;

use Automattic\WooCommerce\Utilities\PluginUtil;
use WCPay\Core\Mode;
use WCPay\Database_Cache;
use WCPay\Internal\DependencyManagement\AbstractServiceProvider;
use WCPay\Internal\Payment\Router;
use WCPay\Internal\Service\PaymentProcessingService;
use WCPay\Internal\Service\ExampleService;
use WCPay\Internal\Service\ExampleServiceWithDependencies;

/**
 * WCPay payments service provider.
 */
class PaymentsServiceProvider extends AbstractServiceProvider {
	/**
	 * Contains all provided classes/aliases.
	 *
	 * @var string[]
	 */
	protected $provides = [
		PaymentProcessingService::class,
		Router::class,
		ExampleService::class,
		ExampleServiceWithDependencies::class,
	];

	/**
	 * Registers all provided classes.
	 */
	public function register(): void {
		$container = $this->getContainer();

		$container->addShared( PaymentProcessingService::class );

		$container->addShared( Router::class )
			->addArgument( Database_Cache::class );

		$container->addShared( ExampleService::class );
		$container->addShared( ExampleServiceWithDependencies::class )
			->addArgument( ExampleService::class )
			->addArgument( Mode::class )
			->addArgument( PluginUtil::class );
	}
}
