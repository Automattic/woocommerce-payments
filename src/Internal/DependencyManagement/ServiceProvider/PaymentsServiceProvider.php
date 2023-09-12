<?php
/**
 * Payments service provider file.
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\DependencyManagement\ServiceProvider;

use Automattic\WooCommerce\Utilities\PluginUtil;
use WCPay\Container;
use WCPay\Core\Mode;
use WCPay\Database_Cache;
use WCPay\Internal\DependencyManagement\AbstractServiceProvider;
use WCPay\Internal\Payment\Router;
use WCPay\Internal\Payment\State\InitialState;
use WCPay\Internal\Payment\State\CompletedState;
use WCPay\Internal\Payment\StateFactory;
use WCPay\Internal\Proxy\LegacyProxy;
use WCPay\Internal\Service\PaymentProcessingService;
use WCPay\Internal\Service\ExampleService;
use WCPay\Internal\Service\ExampleServiceWithDependencies;
use WCPay\Internal\Service\GatewayService;

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
		StateFactory::class,
		PaymentProcessingService::class,
		Router::class,
		InitialState::class,
		CompletedState::class,
		GatewayService::class,
		ExampleService::class,
		ExampleServiceWithDependencies::class,
	];

	/**
	 * Registers all provided classes.
	 */
	public function register(): void {
		$container = $this->getContainer();

		// ToDo: This belongs in a different provider.
		$container->addShared( GatewayService::class )
			->addArgument( Container::class );

		$container->addShared( Router::class )
			->addArgument( Database_Cache::class );

		$container->addShared( StateFactory::class )
			->addArgument( Container::class );
		$container->add( InitialState::class );
		$container->add( CompletedState::class )
			->addArgument( GatewayService::class );

		$container->addShared( PaymentProcessingService::class )
			->addArgument( StateFactory::class )
			->addArgument( LegacyProxy::class );

		$container->addShared( ExampleService::class );
		$container->addShared( ExampleServiceWithDependencies::class )
			->addArgument( ExampleService::class )
			->addArgument( Mode::class )
			->addArgument( PluginUtil::class );
	}
}
