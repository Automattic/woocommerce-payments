<?php
/**
 * Payments service provider file.
 *
 * @package WCPay
 */

namespace WCPay\Internal\DependencyManagement\ServiceProvider;

use WC_Payment_Gateway_WCPay;
use WCPay\Container;
use WCPay\Internal\DependencyManagement\AbstractServiceProvider;
use WCPay\Payment\Service;
use WCPay\Payment\State\InitialState;
use WCPay\Payment\State\PreparedState;

/**
 * WCPay's payments service provider.
 */
class PaymentsServiceProvider extends AbstractServiceProvider {
	/**
	 * Contains all provided classes/aliases.
	 *
	 * @var string[]
	 */
	protected $provides = [
		Service::class,
		InitialState::class,
		PreparedState::class,
	];

	/**
	 * Registers all provided classes.
	 */
	public function register(): void {
		$container = $this->getContainer();

		$container
			->addShared( Service::class )
			->addArgument( Container::class );

		$container->add( InitialState::class );

		$container->add( PreparedState::class )
			->addArgument( WC_Payment_Gateway_WCPay::class );
	}
}
