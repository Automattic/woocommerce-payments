<?php
/**
 * GenericServiceProvider class.
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\DependencyManagement\ServiceProvider;

use WC_Payments_Account;
use WCPay\Core\Mode;
use WCPay\Internal\DependencyManagement\AbstractServiceProvider;
use WCPay\Internal\Logger;
use WCPay\Internal\LoggerContext;
use WCPay\Internal\Proxy\HooksProxy;
use WCPay\Internal\Proxy\LegacyProxy;
use WCPay\Internal\Service\DisputeReadinessService;
use WCPay\Internal\Service\Level3Service;
use WCPay\Internal\Service\OrderService;
use WCPay\Internal\Service\PaymentEventRepository;
use WCPay\Internal\Service\PaymentEventRecorder;
use WCPay\Internal\Service\PaymentActivityReader;
use WCPay\Internal\Service\CheckoutSalesSnapshot;
use WCPay\Internal\Service\HistoricalPaymentValuation;
use WCPay\Internal\Service\PaymentReceiptIndex;
use WCPay\Internal\Service\PaymentEventWebhook;
use WCPay\Internal\Service\PaymentReportRepository;
use WCPay\Internal\Service\PaymentEventSchema;
use WCPay\Internal\Service\PaymentEventRecovery;
use WCPay\Internal\Service\PaymentEventRecoveryScheduler;
use WCPay\Internal\Service\PaymentAttemptDiscovery;
use WCPay\Internal\Service\PaymentAttemptDiscoveryScheduler;
use WCPay\Internal\Service\RefundHistoryCollection;
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
		LoggerContext::class,
		OrderService::class,
		PaymentEventRecorder::class,
		PaymentActivityReader::class,
		CheckoutSalesSnapshot::class,
		HistoricalPaymentValuation::class,
		PaymentReceiptIndex::class,
		PaymentEventWebhook::class,
		PaymentReportRepository::class,
		PaymentEventRepository::class,
		PaymentEventSchema::class,
		PaymentEventRecovery::class,
		PaymentEventRecoveryScheduler::class,
		PaymentAttemptDiscovery::class,
		PaymentAttemptDiscoveryScheduler::class,
		RefundHistoryCollection::class,
		Level3Service::class,
		DisputeReadinessService::class,
		TranslationsLoader::class,
	];

	/**
	 * Registers all provided classes.
	 */
	public function register(): void {

		$container = $this->getContainer();

		$container->add( PaymentEventRecorder::class )
			->addArgument( PaymentEventRepository::class )
			->addArgument( PaymentReceiptIndex::class );
		$container->addShared(
			PaymentReceiptIndex::class,
			static function () use ( $container ) {
				global $wpdb;
				return new PaymentReceiptIndex( $wpdb, $container->get( PaymentEventRepository::class ) );
			}
		);
		$container->addShared( PaymentReportRepository::class )->addArgument( PaymentEventRepository::class );
		$container->addShared( CheckoutSalesSnapshot::class );
		$container->addShared( PaymentActivityReader::class )
			->addArgument( PaymentReceiptIndex::class )
			->addArgument( PaymentReportRepository::class );
		$container->addShared( HistoricalPaymentValuation::class )
			->addArgument( PaymentReceiptIndex::class )
			->addArgument( PaymentReportRepository::class )
			->addArgument( PaymentEventRepository::class )
			->addArgument( PaymentActivityReader::class );
		$container->add( PaymentEventWebhook::class )
			->addArgument( PaymentReceiptIndex::class )
			->addArgument( PaymentReportRepository::class )
			->addArgument( PaymentEventRecoveryScheduler::class );
		$container->add( RefundHistoryCollection::class );
		$container->add( PaymentEventRecovery::class )
			->addArgument( PaymentEventRepository::class )
			->addArgument( PaymentEventRecorder::class )
			->addArgument( RefundHistoryCollection::class );
		$container->add( PaymentEventRecoveryScheduler::class )
			->addArgument( PaymentEventRepository::class )
			->addArgument( PaymentEventRecovery::class );

		$container->add( PaymentAttemptDiscovery::class );
		$container->add( PaymentAttemptDiscoveryScheduler::class )
			->addArgument( PaymentAttemptDiscovery::class )
			->addArgument( PaymentEventRepository::class )
			->addArgument( PaymentEventRecoveryScheduler::class );

		$container->add( 'wc_get_logger', 'wc_get_logger' );
		$container->addShared( Logger::class )
			->addArgument( 'wc_get_logger' )
			->addArgument( Mode::class );

		$container->addShared( LoggerContext::class );

		// Shared: every read re-probes the event schema, so a per-consumer instance
		// multiplies those metadata queries across one customer-history render.
		$container->addShared(
			PaymentEventRepository::class,
			static function () {
				global $wpdb;
				return new PaymentEventRepository( $wpdb );
			}
		);
		$container->add(
			PaymentEventSchema::class,
			static function () use ( $container ) {
				global $wpdb;
				return new PaymentEventSchema( $container->get( PaymentEventRepository::class ), $wpdb );
			}
		);

		$container->addShared( OrderService::class )
			->addArgument( LegacyProxy::class )
			->addArgument( HooksProxy::class );

		$container->addShared( Level3Service::class )
			->addArgument( OrderService::class )
			->addArgument( WC_Payments_Account::class )
			->addArgument( LegacyProxy::class );

		$container->addShared( DisputeReadinessService::class )
			->addArgument( WC_Payments_Account::class );

		$container->addShared( SessionService::class )
			->addArgument( LegacyProxy::class );

		$container->addShared( TranslationsLoader::class )
			->addArgument( Logger::class )
			->addArgument( HooksProxy::class );
	}
}
