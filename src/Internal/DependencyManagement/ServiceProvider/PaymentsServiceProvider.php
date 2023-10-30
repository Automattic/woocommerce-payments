<?php
/**
 * Payments service provider file.
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\DependencyManagement\ServiceProvider;

use Automattic\WooCommerce\Utilities\PluginUtil;
use WC_Payments_Customer_Service;
use WCPay\Container;
use WCPay\Core\Mode;
use WCPay\Database_Cache;
use WCPay\Internal\DependencyManagement\AbstractServiceProvider;
use WCPay\Internal\Logger;
use WCPay\Internal\Payment\Router;
use WCPay\Internal\Payment\State\AuthenticationRequiredState;
use WCPay\Internal\Payment\State\CompletedState;
use WCPay\Internal\Payment\State\InitialState;
use WCPay\Internal\Payment\State\PaymentErrorState;
use WCPay\Internal\Payment\State\ProcessedState;
use WCPay\Internal\Payment\State\DuplicateOrderDetectedState;
use WCPay\Internal\Payment\State\StateFactory;
use WCPay\Internal\Payment\State\SystemErrorState;
use WCPay\Internal\Proxy\HooksProxy;
use WCPay\Internal\Proxy\LegacyProxy;
use WCPay\Internal\Service\DuplicatePaymentPreventionService;
use WCPay\Internal\Service\PaymentProcessingService;
use WCPay\Internal\Service\ExampleService;
use WCPay\Internal\Service\ExampleServiceWithDependencies;
use WCPay\Internal\Service\Level3Service;
use WCPay\Internal\Service\OrderService;
use WCPay\Internal\Service\PaymentRequestService;
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
		PaymentProcessingService::class,
		Router::class,
		StateFactory::class,
		InitialState::class,
		DuplicateOrderDetectedState::class,
		AuthenticationRequiredState::class,
		ProcessedState::class,
		CompletedState::class,
		SystemErrorState::class,
		PaymentErrorState::class,
		ExampleService::class,
		ExampleServiceWithDependencies::class,
		PaymentRequestService::class,
		DuplicatePaymentPreventionService::class,
	];

	/**
	 * Registers all provided classes.
	 */
	public function register(): void {
		$container = $this->getContainer();

		$container->addShared( StateFactory::class )
			->addArgument( Container::class );

		$container->addShared( PaymentProcessingService::class )
			->addArgument( StateFactory::class )
			->addArgument( LegacyProxy::class );

		$container->addShared( PaymentRequestService::class );

		$container->addShared( DuplicatePaymentPreventionService::class )
			->addArgument( OrderService::class )
			->addArgument( SessionService::class )
			->addArgument( Logger::class )
			->addArgument( HooksProxy::class )
			->addArgument( LegacyProxy::class );

		$container->add( InitialState::class )
			->addArgument( StateFactory::class )
			->addArgument( OrderService::class )
			->addArgument( WC_Payments_Customer_Service::class )
			->addArgument( Level3Service::class )
			->addArgument( PaymentRequestService::class )
			->addArgument( DuplicatePaymentPreventionService::class );

		$container->add( ProcessedState::class )
			->addArgument( StateFactory::class )
			->addArgument( OrderService::class )
			->addArgument( DuplicatePaymentPreventionService::class );

		$container->add( AuthenticationRequiredState::class )
			->addArgument( StateFactory::class );

		$container->add( CompletedState::class )
			->addArgument( StateFactory::class );

		$container->add( SystemErrorState::class )
			->addArgument( StateFactory::class );

		$container->add( PaymentErrorState::class )
			->addArgument( StateFactory::class );

		$container->add( DuplicateOrderDetectedState::class )
			->addArgument( StateFactory::class );

		$container->addShared( Router::class )
			->addArgument( Database_Cache::class );

		$container->addShared( ExampleService::class );
		$container->addShared( ExampleServiceWithDependencies::class )
			->addArgument( ExampleService::class )
			->addArgument( Mode::class )
			->addArgument( PluginUtil::class );
	}
}
