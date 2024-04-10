<?php
/**
 * GenericServiceProvider class.
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\DependencyManagement\ServiceProvider;

use WC_Payments_Account;
use WC_Payments_Order_Service;
use WCPay\Core\Mode;
use WCPay\Internal\DependencyManagement\AbstractServiceProvider;
use WCPay\Internal\Logger;
use WCPay\Internal\Proxy\HooksProxy;
use WCPay\Internal\Proxy\LegacyProxy;
use WCPay\Internal\Service\Level3Service;
use WCPay\Internal\Service\OrderService;
use WCPay\Internal\Service\SessionService;
use WCPay\Internal\PluginManagement\TranslationsLoader;

/**
 * WCPay payments generic service provider.
 */
class GenericServiceProvider extends AbstractServiceProvider {
	/**
	 * Contains all provided classes/aliases.
	 *
	 * @var string[]
	 */
	protected $provides = [
		Logger::class,
		OrderService::class,
		Level3Service::class,
		TranslationsLoader::class,
	];

	/**
	 * Registers all provided classes.
	 */
	public function register(): void {

		$container = $this->getContainer();

		$container->add( 'wc_get_logger', 'wc_get_logger' );
		$container->addShared( Logger::class )
			->addArgument( 'wc_get_logger' )
			->addArgument( Mode::class );

		$container->addShared( OrderService::class )
			->addArgument( WC_Payments_Order_Service::class )
			->addArgument( LegacyProxy::class )
			->addArgument( WC_Payments_Account::class )
			->addArgument( HooksProxy::class );

		$container->addShared( Level3Service::class )
			->addArgument( OrderService::class )
			->addArgument( WC_Payments_Account::class )
			->addArgument( LegacyProxy::class );

		$container->addShared( SessionService::class )
			->addArgument( LegacyProxy::class );

		$container->addShared( TranslationsLoader::class )
			->addArgument( Logger::class )
			->addArgument( HooksProxy::class );
	}
}
