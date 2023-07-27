<?php
/**
 * Payments service provider file.
 *
 * @package WooPayments
 */

namespace WooPayments\Internal\DependencyManagement\ServiceProvider;

use WooPayments\Internal\DependencyManagement\AbstractServiceProvider;
use WooPayments\Internal\Service\PaymentProcessingService;

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
	];

	/**
	 * Registers all provided classes.
	 */
	public function register(): void {
		$container = $this->getContainer();
		$container->addShared( PaymentProcessingService::class );
	}
}
