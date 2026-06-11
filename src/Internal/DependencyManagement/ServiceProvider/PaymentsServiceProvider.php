<?php
/**
 * Payments service provider file.
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\DependencyManagement\ServiceProvider;

use Automattic\WooCommerce\Utilities\PluginUtil;
use WC_Payments_API_Client;
use WCPay\Core\Mode;
use WCPay\Internal\DependencyManagement\AbstractServiceProvider;
use WCPay\Internal\Proxy\HooksProxy;
use WCPay\Internal\Proxy\LegacyProxy;
use WCPay\Internal\Service\DisputeService;
use WCPay\Internal\Service\DuplicatePaymentPreventionService;
use WCPay\Internal\Service\ExampleService;
use WCPay\Internal\Service\ExampleServiceWithDependencies;
use WCPay\Internal\Service\FileService;
use WCPay\Internal\Service\RefundService;
use WCPay\Internal\Service\SessionService;

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
		ExampleService::class,
		ExampleServiceWithDependencies::class,
		DuplicatePaymentPreventionService::class,
		DisputeService::class,
		RefundService::class,
		FileService::class,
	];

	/**
	 * Registers all provided classes.
	 */
	public function register(): void {
		$container = $this->getContainer();

		$container->addShared( DuplicatePaymentPreventionService::class )
			->addArgument( SessionService::class )
			->addArgument( HooksProxy::class )
			->addArgument( LegacyProxy::class );

		$container->addShared( ExampleService::class );
		$container->addShared( ExampleServiceWithDependencies::class )
			->addArgument( ExampleService::class )
			->addArgument( Mode::class )
			->addArgument( PluginUtil::class );

		$container->addShared( RefundService::class );

		$container->addShared( DisputeService::class )
			->addArgument( WC_Payments_API_Client::class );

		$container->addShared( FileService::class )
			->addArgument( WC_Payments_API_Client::class );
	}
}
