<?php
/**
 * Payments service provider file.
 *
 * @package WooPayments
 */

namespace WooPayments\Internal\DependencyManagement\ServiceProvider;

use Automattic\WooCommerce\Utilities\PluginUtil;
use WCPay\Core\Mode;
use WooPayments\Internal\DependencyManagement\AbstractServiceProvider;
use WooPayments\Internal\Service\PaymentProcessingService;
use WooPayments\Internal\Service\ExampleService;
use WooPayments\Internal\Service\ExampleServiceWithDependencies;

/**
 * WooPayments payments service provider.
 */
class PaymentsServiceProvider extends AbstractServiceProvider {
	/**
	 * Contains all provided classes/aliases.
	 *
	 * @var string[]
	 */
	protected $provides = [
		PaymentProcessingService::class,
		ExampleService::class,
		ExampleServiceWithDependencies::class,
	];

	/**
	 * Registers all provided classes.
	 */
	public function register(): void {
		$container = $this->getContainer();

		$container->addShared( PaymentProcessingService::class );

		$container->addShared( ExampleService::class );
		$container->addShared( ExampleServiceWithDependencies::class )
			->addArgument( ExampleService::class )
			->addArgument( Mode::class )
			->addArgument( PluginUtil::class );
	}
}
