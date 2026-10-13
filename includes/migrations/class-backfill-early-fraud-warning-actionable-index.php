<?php
/**
 * Class Backfill_Early_Fraud_Warning_Actionable_Index
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Migrations;

defined( 'ABSPATH' ) || exit;

use WC_Order;
use WC_Payments_Action_Scheduler_Service;
use WC_Payments_Order_Service;
use WCPay\Database_Cache;

/**
 * Indexes early fraud warnings stored before the actionable index existed.
 *
 * 11.1.0 and 11.2.0 stored warnings without the actionable index, which only a new
 * webhook writes, so the Payments Overview task missed warnings that were still
 * actionable at upgrade.
 *
 * @since 11.3.0
 */
class Backfill_Early_Fraud_Warning_Actionable_Index {

	/**
	 * Action Scheduler hook that processes one page of flagged orders.
	 */
	const BATCH_HOOK = 'wcpay_backfill_early_fraud_warning_actionable_index';

	/**
	 * Orders loaded per batch.
	 */
	const BATCH_SIZE = 100;

	/**
	 * Order service.
	 *
	 * @var WC_Payments_Order_Service
	 */
	private $order_service;

	/**
	 * Action Scheduler service.
	 *
	 * @var WC_Payments_Action_Scheduler_Service
	 */
	private $action_scheduler_service;

	/**
	 * Database cache.
	 *
	 * @var Database_Cache
	 */
	private $database_cache;

	/**
	 * Constructor.
	 *
	 * @param WC_Payments_Order_Service            $order_service            Order service.
	 * @param WC_Payments_Action_Scheduler_Service $action_scheduler_service Action Scheduler service.
	 * @param Database_Cache                       $database_cache           Database cache.
	 */
	public function __construct(
		WC_Payments_Order_Service $order_service,
		WC_Payments_Action_Scheduler_Service $action_scheduler_service,
		Database_Cache $database_cache
	) {
		$this->order_service            = $order_service;
		$this->action_scheduler_service = $action_scheduler_service;
		$this->database_cache           = $database_cache;
	}

	/**
	 * Registers the batch handler, which Action Scheduler runs outside the upgrade request.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( self::BATCH_HOOK, [ $this, 'process_batch' ] );
	}

	/**
	 * Schedules the backfill when upgrading from a release without the index.
	 *
	 * @return void
	 */
	public function maybe_migrate(): void {
		$previous_version = get_option( 'woocommerce_woocommerce_payments_version' );
		if ( version_compare( '11.3.0', $previous_version, '>' ) ) {
			$this->schedule_batch( 1 );
		}
	}

	/**
	 * Indexes one page of orders carrying a stored warning, then schedules the next.
	 *
	 * Pages through every flagged order rather than only unindexed ones, since
	 * resolved warnings never gain the index and would otherwise refill each page.
	 *
	 * @param int $page The 1-based page to process.
	 *
	 * @return void
	 */
	public function process_batch( $page = 1 ): void {
		$page   = max( 1, (int) $page );
		$orders = wc_get_orders(
			[
				'limit'    => self::BATCH_SIZE,
				'paged'    => $page,
				'orderby'  => 'ID',
				'order'    => 'ASC',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_key' => WC_Payments_Order_Service::WCPAY_EARLY_FRAUD_WARNING_META_KEY,
			]
		);

		foreach ( $orders as $order ) {
			$this->index_order( $order );
		}

		if ( count( $orders ) === self::BATCH_SIZE ) {
			$this->schedule_batch( $page + 1 );
			return;
		}

		// The Overview lists may have been cached without the orders indexed above.
		$this->database_cache->delete_early_fraud_warning_caches();
	}

	/**
	 * Adds the actionable index to one order whose stored warning is still actionable.
	 *
	 * @param WC_Order $order The order.
	 *
	 * @return void
	 */
	private function index_order( WC_Order $order ): void {
		$early_fraud_warning = $this->order_service->get_early_fraud_warning_for_order( $order );
		if ( empty( $early_fraud_warning['efw_actionable'] ) ) {
			return;
		}

		// A webhook processed after the upgrade already wrote the index from newer data.
		if ( $order->meta_exists( WC_Payments_Order_Service::WCPAY_EARLY_FRAUD_WARNING_ACTIONABLE_META_KEY ) ) {
			return;
		}

		// Write only the index: rewriting the warning could restore one a webhook just resolved,
		// while a stale index is harmless because the Overview query rechecks the warning.
		$order->update_meta_data(
			WC_Payments_Order_Service::WCPAY_EARLY_FRAUD_WARNING_ACTIONABLE_META_KEY,
			(string) $early_fraud_warning['created']
		);
		$order->save_meta_data();
	}

	/**
	 * Schedules a batch to run as soon as Action Scheduler picks it up.
	 *
	 * @param int $page The 1-based page to process.
	 *
	 * @return void
	 */
	private function schedule_batch( int $page ): void {
		$this->action_scheduler_service->schedule_job( time(), self::BATCH_HOOK, [ $page ] );
	}
}
