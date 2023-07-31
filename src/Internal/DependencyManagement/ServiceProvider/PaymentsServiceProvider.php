<?php
/**
 * Payments service provider file.
 *
 * @package WooPayments
 */

namespace WooPayments\Internal\DependencyManagement\ServiceProvider;

use WCPay\Core\Mode;
use WooPayments\Internal\DependencyManagement\AbstractServiceProvider;
use WooPayments\Internal\Service\PaymentProcessingService;
use WooPayments\Internal\Service\ExampleService;

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
	];

	/**
	 * Registers all provided classes.
	 */
	public function register(): void {
		$container = $this->getContainer();

		$container->addShared( PaymentProcessingService::class );

		$container->addShared( ExampleService::class )
			->addArgument( Mode::class );
	}
}
