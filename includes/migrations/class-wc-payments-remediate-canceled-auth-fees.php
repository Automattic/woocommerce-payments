<?php
/**
 * Class WC_Payments_Remediate_Canceled_Auth_Fees
 *
 * @package WooCommerce\Payments\Migrations
 */

defined( 'ABSPATH' ) || exit;

use WCPay\Constants\Intent_Status;

/**
 * Remediates incorrect transaction fees and refunds for canceled authorizations.
 *
 * Between April 2023 / v5.8.0 (commit 2d13751) and November 2025 (commit 3058f59ad),
 * canceled authorizations incorrectly had transaction fees and refund objects created.
 * This migration cleans up those incorrect records.
 */
class WC_Payments_Remediate_Canceled_Auth_Fees {

	/**
	 * Option key for tracking remediation status.
	 */
	const STATUS_OPTION_KEY = 'wcpay_fee_remediation_status';

	/**
	 * Option key for tracking last processed order ID.
	 */
	const LAST_ORDER_ID_OPTION_KEY = 'wcpay_fee_remediation_last_order_id';

	/**
	 * Option key for tracking current batch size.
	 */
	const BATCH_SIZE_OPTION_KEY = 'wcpay_fee_remediation_batch_size';

	/**
	 * Option key for tracking statistics.
	 */
	const STATS_OPTION_KEY = 'wcpay_fee_remediation_stats';

	/**
	 * Action Scheduler hook name.
	 */
	const ACTION_HOOK = 'wcpay_remediate_canceled_authorization_fees';

	/**
	 * Action Scheduler hook name for dry run.
	 */
	const DRY_RUN_ACTION_HOOK = 'wcpay_remediate_canceled_authorization_fees_dry_run';

	/**
	 * Option key for tracking dry run mode.
	 */
	const DRY_RUN_OPTION_KEY = 'wcpay_fee_remediation_dry_run';

	/**
	 * Starting batch size.
	 */
	const INITIAL_BATCH_SIZE = 20;

	/**
	 * Minimum batch size.
	 */
	const MIN_BATCH_SIZE = 10;

	/**
	 * Maximum batch size.
	 */
	const MAX_BATCH_SIZE = 100;

	/**
	 * Target minimum execution time (seconds).
	 */
	const TARGET_MIN_TIME = 5;

	/**
	 * Target maximum execution time (seconds).
	 */
	const TARGET_MAX_TIME = 20;

	/**
	 * Bug introduction date (April 1, 2023 - commit 2d13751).
	 */
	const BUG_START_DATE = '2023-04-01';

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Empty - call init() to register hooks.
	}

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( self::ACTION_HOOK, [ $this, 'process_batch' ] );
		add_action( self::DRY_RUN_ACTION_HOOK, [ $this, 'process_batch_dry_run' ] );
	}

	/**
	 * Check if dry run mode is enabled.
	 *
	 * @return bool True if dry run mode is enabled.
	 */
	public function is_dry_run(): bool {
		return (bool) get_option( self::DRY_RUN_OPTION_KEY, false );
	}

	/**
	 * Enable dry run mode.
	 *
	 * @return void
	 */
	private function enable_dry_run(): void {
		update_option( self::DRY_RUN_OPTION_KEY, true );
	}

	/**
	 * Disable dry run mode.
	 *
	 * @return void
	 */
	private function disable_dry_run(): void {
		delete_option( self::DRY_RUN_OPTION_KEY );
	}

	/**
	 * Check if remediation is complete.
	 *
	 * @return bool True if remediation is complete.
	 */
	public function is_complete(): bool {
		return 'completed' === get_option( self::STATUS_OPTION_KEY, '' );
	}

	/**
	 * Mark remediation as complete.
	 *
	 * @return void
	 */
	private function mark_complete(): void {
		update_option( self::STATUS_OPTION_KEY, 'completed' );
	}

	/**
	 * Mark remediation as running.
	 *
	 * @return void
	 */
	private function mark_running(): void {
		update_option( self::STATUS_OPTION_KEY, 'running' );
	}

	/**
	 * Get current batch size.
	 *
	 * @return int Current batch size.
	 */
	public function get_batch_size(): int {
		return (int) get_option( self::BATCH_SIZE_OPTION_KEY, self::INITIAL_BATCH_SIZE );
	}

	/**
	 * Update batch size.
	 *
	 * @param int $size New batch size.
	 * @return void
	 */
	public function update_batch_size( int $size ): void {
		$size = max( self::MIN_BATCH_SIZE, min( self::MAX_BATCH_SIZE, $size ) );
		update_option( self::BATCH_SIZE_OPTION_KEY, $size );
	}

	/**
	 * Get last processed order ID.
	 *
	 * @return int Last processed order ID.
	 */
	public function get_last_order_id(): int {
		return (int) get_option( self::LAST_ORDER_ID_OPTION_KEY, 0 );
	}

	/**
	 * Update last processed order ID.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public function update_last_order_id( int $order_id ): void {
		update_option( self::LAST_ORDER_ID_OPTION_KEY, $order_id );
	}

	/**
	 * Get remediation statistics.
	 *
	 * @return array Statistics array with keys: processed, remediated, errors.
	 */
	public function get_stats(): array {
		$default = [
			'processed'  => 0,
			'remediated' => 0,
			'errors'     => 0,
		];

		$stats = get_option( self::STATS_OPTION_KEY, [] );
		return array_merge( $default, $stats );
	}

	/**
	 * Increment a statistic counter.
	 *
	 * @param string $key Stat key to increment.
	 * @return void
	 */
	public function increment_stat( string $key ): void {
		$stats = $this->get_stats();
		if ( isset( $stats[ $key ] ) ) {
			++$stats[ $key ];
			update_option( self::STATS_OPTION_KEY, $stats );
		}
	}

	/**
	 * Clean up temporary remediation options.
	 *
	 * Preserves the status and stats options so merchants can see completion
	 * information in the Tools page. Only removes temporary processing options.
	 *
	 * @return void
	 */
	private function cleanup(): void {
		// Delete only temporary processing options.
		// Keep STATUS_OPTION_KEY and STATS_OPTION_KEY so merchants can see completion info.
		delete_option( self::LAST_ORDER_ID_OPTION_KEY );
		delete_option( self::BATCH_SIZE_OPTION_KEY );
		delete_option( self::DRY_RUN_OPTION_KEY );
	}

	/**
	 * Clean up after dry run completes.
	 *
	 * Unlike the actual remediation cleanup, this removes ALL options including
	 * status and stats, so merchants can still run the actual remediation afterward.
	 * Dry run is just a preview - it shouldn't block the real action.
	 *
	 * @return void
	 */
	private function cleanup_dry_run(): void {
		delete_option( self::LAST_ORDER_ID_OPTION_KEY );
		delete_option( self::BATCH_SIZE_OPTION_KEY );
		delete_option( self::DRY_RUN_OPTION_KEY );
		delete_option( self::STATUS_OPTION_KEY );
		delete_option( self::STATS_OPTION_KEY );
	}

	/**
	 * Check if HPOS is enabled.
	 *
	 * This method is protected to allow mocking in tests.
	 *
	 * @return bool True if HPOS is enabled.
	 */
	protected function is_hpos_enabled(): bool {
		return WC_Payments_Utils::is_hpos_tables_usage_enabled();
	}

	/**
	 * Get affected orders that need remediation.
	 *
	 * @param int $limit Number of orders to retrieve.
	 * @return WC_Order[] Array of WC_Order objects.
	 */
	public function get_affected_orders( int $limit ): array {
		if ( $this->is_hpos_enabled() ) {
			return $this->get_affected_orders_hpos( $limit );
		}

		return $this->get_affected_orders_cpt( $limit );
	}

	/**
	 * Get affected orders using HPOS custom tables.
	 *
	 * @param int $limit Number of orders to retrieve.
	 * @return WC_Order[] Array of WC_Order objects.
	 */
	private function get_affected_orders_hpos( int $limit ): array {
		global $wpdb;

		$last_order_id = $this->get_last_order_id();
		$orders_table  = $wpdb->prefix . 'wc_orders';
		$meta_table    = $wpdb->prefix . 'wc_orders_meta';

		// Build the SQL query to find orders with canceled intent status that have either:
		// 1. Incorrect fee metadata (_wcpay_transaction_fee or _wcpay_net), OR
		// 2. Refund objects (which shouldn't exist for never-captured authorizations), OR
		// 3. Incorrect order status of 'wc-refunded' (should be 'wc-cancelled').
		$sql = "
			SELECT DISTINCT o.id
			FROM {$orders_table} o
			INNER JOIN {$meta_table} pm_status ON o.id = pm_status.order_id
			LEFT JOIN {$meta_table} pm_fee ON o.id = pm_fee.order_id
				AND pm_fee.meta_key IN ('_wcpay_transaction_fee', '_wcpay_net')
			LEFT JOIN {$orders_table} refunds ON o.id = refunds.parent_order_id
				AND refunds.type = 'shop_order_refund'
			WHERE o.type = 'shop_order'
			AND o.date_created_gmt >= %s
			AND pm_status.meta_key = '_intention_status'
			AND pm_status.meta_value = %s
			AND (pm_fee.order_id IS NOT NULL OR refunds.id IS NOT NULL OR o.status = 'wc-refunded')
		";

		$params = [ self::BUG_START_DATE, Intent_Status::CANCELED ];

		// Add offset based on last order ID.
		if ( $last_order_id > 0 ) {
			$sql     .= ' AND o.id > %d';
			$params[] = $last_order_id;
		}

		// Add ordering and limit.
		$sql     .= ' ORDER BY o.id ASC LIMIT %d';
		$params[] = $limit;

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$order_ids = $wpdb->get_col( $wpdb->prepare( $sql, $params ) );

		return $this->convert_ids_to_orders( $order_ids );
	}

	/**
	 * Get affected orders using CPT (posts) storage.
	 *
	 * @param int $limit Number of orders to retrieve.
	 * @return WC_Order[] Array of WC_Order objects.
	 */
	private function get_affected_orders_cpt( int $limit ): array {
		global $wpdb;

		$last_order_id = $this->get_last_order_id();

		// Build the SQL query to find orders with canceled intent status that have either:
		// 1. Incorrect fee metadata (_wcpay_transaction_fee or _wcpay_net), OR
		// 2. Refund objects (which shouldn't exist for never-captured authorizations), OR
		// 3. Incorrect order status of 'wc-refunded' (should be 'wc-cancelled').
		$sql = "
			SELECT DISTINCT p.ID
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id
			LEFT JOIN {$wpdb->postmeta} pm_fee ON p.ID = pm_fee.post_id
				AND pm_fee.meta_key IN ('_wcpay_transaction_fee', '_wcpay_net')
			LEFT JOIN {$wpdb->posts} refunds ON p.ID = refunds.post_parent
				AND refunds.post_type = 'shop_order_refund'
			WHERE p.post_type IN ('shop_order', 'shop_order_placeholder')
			AND p.post_date >= %s
			AND pm_status.meta_key = '_intention_status'
			AND pm_status.meta_value = %s
			AND (pm_fee.post_id IS NOT NULL OR refunds.ID IS NOT NULL OR p.post_status = 'wc-refunded')
		";

		$params = [ self::BUG_START_DATE, Intent_Status::CANCELED ];

		// Add offset based on last order ID.
		if ( $last_order_id > 0 ) {
			$sql     .= ' AND p.ID > %d';
			$params[] = $last_order_id;
		}

		// Add ordering and limit.
		$sql     .= ' ORDER BY p.ID ASC LIMIT %d';
		$params[] = $limit;

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$order_ids = $wpdb->get_col( $wpdb->prepare( $sql, $params ) );

		return $this->convert_ids_to_orders( $order_ids );
	}

	/**
	 * Convert order IDs to WC_Order objects.
	 *
	 * @param array $order_ids Array of order IDs.
	 * @return WC_Order[] Array of WC_Order objects.
	 */
	private function convert_ids_to_orders( array $order_ids ): array {
		$orders = [];
		foreach ( $order_ids as $order_id ) {
			$order = wc_get_order( $order_id );
			if ( $order instanceof WC_Order ) {
				$orders[] = $order;
			}
		}

		return $orders;
	}

	/**
	 * Adjust batch size based on execution time.
	 *
	 * @param float $execution_time Execution time in seconds.
	 * @return void
	 */
	public function adjust_batch_size( float $execution_time ): void {
		$current_size = $this->get_batch_size();

		if ( $execution_time < self::TARGET_MIN_TIME ) {
			// Too fast - double batch size.
			$this->update_batch_size( $current_size * 2 );
		} elseif ( $execution_time > self::TARGET_MAX_TIME ) {
			// Too slow - halve batch size.
			$this->update_batch_size( (int) ( $current_size / 2 ) );
		}
		// Otherwise, keep current size.
	}

	/**
	 * Process a batch of orders.
	 *
	 * @return void
	 */
	public function process_batch(): void {
		// Check if already complete.
		if ( $this->is_complete() ) {
			return;
		}

		$start_time = microtime( true );
		$batch_size = $this->get_batch_size();
		$orders     = $this->get_affected_orders( $batch_size );

		// If no orders found, mark as complete.
		if ( empty( $orders ) ) {
			$this->mark_complete();
			$this->log_completion();
			$this->cleanup();
			return;
		}

		// Process each order.
		foreach ( $orders as $order ) {
			$this->increment_stat( 'processed' );

			if ( $this->remediate_order( $order ) ) {
				$this->increment_stat( 'remediated' );
				wc_get_logger()->info(
					sprintf( 'Remediated order %d', $order->get_id() ),
					[ 'source' => 'wcpay-fee-remediation' ]
				);
			} else {
				$this->increment_stat( 'errors' );
			}

			// Update last order ID.
			$this->update_last_order_id( $order->get_id() );
		}

		// Adjust batch size based on execution time.
		$execution_time = microtime( true ) - $start_time;
		$this->adjust_batch_size( $execution_time );

		// Log batch completion.
		wc_get_logger()->info(
			sprintf(
				'Processed batch of %d orders in %.2f seconds. New batch size: %d',
				count( $orders ),
				$execution_time,
				$this->get_batch_size()
			),
			[ 'source' => 'wcpay-fee-remediation' ]
		);

		// Always schedule next batch to check for more orders.
		// The batch will complete when get_affected_orders() returns empty.
		// This is more reliable than checking count vs batch_size, which can
		// incorrectly mark complete if the query returns fewer orders due to
		// transient issues (DB performance, caching, etc.).
		$this->schedule_next_batch();
	}

	/**
	 * Process a batch of orders in dry run mode.
	 *
	 * @return void
	 */
	public function process_batch_dry_run(): void {
		// Check if already complete (but only if not in dry run mode - dry run uses separate tracking).
		if ( $this->is_complete() && ! $this->is_dry_run() ) {
			return;
		}

		$batch_size = $this->get_batch_size();
		$orders     = $this->get_affected_orders( $batch_size );

		// If no orders found, dry run is done.
		if ( empty( $orders ) ) {
			$this->log_completion_dry_run();
			$this->cleanup_dry_run();
			return;
		}

		// Process each order in dry run mode.
		foreach ( $orders as $order ) {
			$this->increment_stat( 'processed' );

			if ( $this->remediate_order( $order, true ) ) {
				$this->increment_stat( 'remediated' );
			} else {
				$this->increment_stat( 'errors' );
			}

			// Update last order ID.
			$this->update_last_order_id( $order->get_id() );
		}

		// Log batch completion.
		wc_get_logger()->info(
			sprintf(
				'[DRY RUN] Processed batch of %d orders.',
				count( $orders )
			),
			[ 'source' => 'wcpay-fee-remediation' ]
		);

		// Always schedule next batch to check for more orders.
		// The batch will complete when get_affected_orders() returns empty.
		$this->schedule_next_batch_dry_run();
	}

	/**
	 * Schedule the next batch.
	 *
	 * @return void
	 */
	private function schedule_next_batch(): void {
		if ( ! function_exists( 'as_schedule_single_action' ) ) {
			wc_get_logger()->warning(
				'Action Scheduler is not available. Cannot schedule next batch for fee remediation.',
				[ 'source' => 'wcpay-fee-remediation' ]
			);
			return;
		}

		as_schedule_single_action(
			time() + 60, // 1 minute from now.
			self::ACTION_HOOK,
			[],
			'woocommerce-payments'
		);
	}

	/**
	 * Schedule the next batch for dry run.
	 *
	 * @return void
	 */
	private function schedule_next_batch_dry_run(): void {
		if ( ! function_exists( 'as_schedule_single_action' ) ) {
			wc_get_logger()->warning(
				'Action Scheduler is not available. Cannot schedule next batch for fee remediation dry run.',
				[ 'source' => 'wcpay-fee-remediation' ]
			);
			return;
		}

		as_schedule_single_action(
			time() + 60, // 1 minute from now.
			self::DRY_RUN_ACTION_HOOK,
			[],
			'woocommerce-payments'
		);
	}

	/**
	 * Log completion.
	 *
	 * @return void
	 */
	private function log_completion(): void {
		$stats = $this->get_stats();
		wc_get_logger()->info(
			sprintf(
				'Remediation complete. Processed: %d, Remediated: %d, Errors: %d',
				$stats['processed'],
				$stats['remediated'],
				$stats['errors']
			),
			[ 'source' => 'wcpay-fee-remediation' ]
		);
	}

	/**
	 * Log completion for dry run.
	 *
	 * @return void
	 */
	private function log_completion_dry_run(): void {
		$stats = $this->get_stats();
		wc_get_logger()->info(
			sprintf(
				'[DRY RUN] Complete. Found %d orders that would be remediated. No changes were made. Check the WooCommerce logs for details on each order.',
				$stats['remediated']
			),
			[ 'source' => 'wcpay-fee-remediation' ]
		);
	}

	/**
	 * Remediate a single order.
	 *
	 * @param WC_Order $order   Order to remediate.
	 * @param bool     $dry_run If true, only log what would be changed without modifying data.
	 * @return bool True on success, false on failure.
	 */
	public function remediate_order( WC_Order $order, bool $dry_run = false ): bool {
		try {
			// Capture current values for the note.
			$fee            = $order->get_meta( '_wcpay_transaction_fee', true );
			$net            = $order->get_meta( '_wcpay_net', true );
			$refunds        = $order->get_refunds();
			$current_status = $order->get_status();

			// Only delete refunds that were created by WCPay (have _wcpay_refund_id metadata).
			// This avoids deleting manually-created refunds or refunds from other plugins.
			$wcpay_refunds      = $this->get_wcpay_refunds( $refunds );
			$wcpay_refund_count = count( $wcpay_refunds );
			$wcpay_refund_total = 0;

			// Calculate refund IDs and totals.
			$refund_ids = [];
			foreach ( $wcpay_refunds as $refund ) {
				$wcpay_refund_total += abs( $refund->get_amount() );
				$refund_ids[]        = $refund->get_id();
			}

			// Check if status would change.
			$would_change_status = 'refunded' === $current_status;

			// Build log message for dry run or note for actual remediation.
			$changes = [];

			if ( $would_change_status ) {
				$changes[] = 'Changed order status from "Refunded" to "Cancelled"';
			}

			if ( $wcpay_refund_count > 0 ) {
				$changes[] = sprintf(
					'Deleted %d WooPayments refund object%s (IDs: %s) totaling %s',
					$wcpay_refund_count,
					$wcpay_refund_count > 1 ? 's' : '',
					implode( ', ', $refund_ids ),
					wc_price( $wcpay_refund_total, [ 'currency' => $order->get_currency() ] )
				);
			}

			if ( ! empty( $fee ) ) {
				$changes[] = sprintf(
					'Removed transaction fee: %s',
					wc_price( $fee, [ 'currency' => $order->get_currency() ] )
				);
			}

			if ( ! empty( $net ) ) {
				$changes[] = sprintf(
					'Removed net amount: %s',
					wc_price( $net, [ 'currency' => $order->get_currency() ] )
				);
			}

			// In dry run mode, just log what would happen and return.
			if ( $dry_run ) {
				if ( ! empty( $changes ) ) {
					wc_get_logger()->info(
						sprintf(
							'[DRY RUN] Order %d would be remediated: %s',
							$order->get_id(),
							wp_strip_all_tags( implode( '; ', $changes ) )
						),
						[ 'source' => 'wcpay-fee-remediation' ]
					);
				}
				return true;
			}

			// Actually perform the remediation.
			$parent_order_id = $order->get_id();
			foreach ( $wcpay_refunds as $refund ) {
				$refund_id = $refund->get_id();

				// Delete refund stats BEFORE deleting the refund (while it still exists).
				// We do this proactively because the woocommerce_before_delete_order hook
				// may not have its handlers registered in Action Scheduler context.
				$this->delete_order_stats( $refund_id );

				$refund->delete( true ); // Force delete, bypass trash.

				// Fire the hook WC expects for refund deletion.
				do_action( 'woocommerce_refund_deleted', $refund_id, $parent_order_id );
			}

			// Remove fee metadata from the order.
			$order->delete_meta_data( '_wcpay_transaction_fee' );
			$order->delete_meta_data( '_wcpay_net' );
			$order->delete_meta_data( '_wcpay_refund_id' );
			$order->delete_meta_data( '_wcpay_refund_status' );

			// Fix incorrect order status: 'refunded' should be 'cancelled' for never-captured authorizations.
			if ( $would_change_status ) {
				$order->set_status( 'cancelled', '', false ); // Don't trigger status change emails.
			}

			// Build detailed note.
			$note_parts = [ 'Removed incorrect data from canceled authorization:' ];
			foreach ( $changes as $change ) {
				$note_parts[] = '- ' . $change;
			}
			$note_parts[] = '';
			$note_parts[] = 'These records were incorrectly created for an authorization that was never captured.';
			$note_parts[] = 'No actual payment or refund occurred.';

			$order->add_order_note( implode( "\n", $note_parts ) );
			$order->save();

			// Fallback sync in case the woocommerce_refund_deleted hook doesn't
			// fully update analytics for edge cases.
			$this->sync_order_stats( $order->get_id() );

			return true;

		} catch ( Exception $e ) {
			// Log error but don't throw - let calling code handle retry.
			wc_get_logger()->error(
				sprintf( 'Failed to remediate order %d: %s', $order->get_id(), $e->getMessage() ),
				[ 'source' => 'wcpay-fee-remediation' ]
			);
			return false;
		}
	}

	/**
	 * Filter refunds to only include those created by WooPayments.
	 *
	 * WooPayments-created refunds have the _wcpay_refund_id metadata.
	 * This ensures we don't delete manually-created refunds or refunds from other plugins.
	 *
	 * @param WC_Order_Refund[] $refunds Array of refund objects.
	 * @return WC_Order_Refund[] Array of WooPayments-created refunds.
	 */
	private function get_wcpay_refunds( array $refunds ): array {
		return array_filter(
			$refunds,
			function ( $refund ) {
				// Check if this refund was created by WCPay (has the refund ID metadata).
				$wcpay_refund_id = $refund->get_meta( '_wcpay_refund_id', true );
				return ! empty( $wcpay_refund_id );
			}
		);
	}

	/**
	 * Sync order stats to WooCommerce Analytics.
	 *
	 * Fallback sync in case the woocommerce_refund_deleted hook doesn't
	 * fully update analytics for edge cases.
	 *
	 * @param int $order_id Order ID to sync.
	 * @return void
	 */
	protected function sync_order_stats( int $order_id ): void {
		// Check if the OrdersStatsDataStore class exists (requires WooCommerce Admin / WooCommerce 4.0+).
		if ( ! class_exists( 'Automattic\WooCommerce\Admin\API\Reports\Orders\Stats\DataStore' ) ) {
			return;
		}

		try {
			\Automattic\WooCommerce\Admin\API\Reports\Orders\Stats\DataStore::sync_order( $order_id );
		} catch ( Exception $e ) {
			// Log but don't fail - analytics sync is not critical.
			wc_get_logger()->warning(
				sprintf( 'Failed to sync order %d to analytics: %s', $order_id, $e->getMessage() ),
				[ 'source' => 'wcpay-fee-remediation' ]
			);
		}
	}

	/**
	 * Delete order stats from WooCommerce Analytics.
	 *
	 * Uses WooCommerce's DataStore::delete_order() API to properly remove
	 * the order/refund stats row from the wc_order_stats table.
	 *
	 * This must be called BEFORE the refund is deleted, while it still exists,
	 * so the WC API can perform its internal checks.
	 *
	 * @param int $order_id Order or refund ID to delete stats for.
	 * @return void
	 */
	protected function delete_order_stats( int $order_id ): void {
		// Check if the DataStore class exists (requires WooCommerce Admin / WooCommerce 4.0+).
		if ( ! class_exists( 'Automattic\WooCommerce\Admin\API\Reports\Orders\Stats\DataStore' ) ) {
			return;
		}

		try {
			// Use WooCommerce's proper API to delete the stats row.
			// This handles all internal state management and fires appropriate hooks.
			\Automattic\WooCommerce\Admin\API\Reports\Orders\Stats\DataStore::delete_order( $order_id );

			wc_get_logger()->info(
				sprintf( 'Deleted stats row for refund %d via WC DataStore API', $order_id ),
				[ 'source' => 'wcpay-fee-remediation' ]
			);
		} catch ( Exception $e ) {
			// Log but don't fail - analytics cleanup is not critical.
			wc_get_logger()->warning(
				sprintf( 'Failed to delete stats for order %d: %s', $order_id, $e->getMessage() ),
				[ 'source' => 'wcpay-fee-remediation' ]
			);
		}
	}

	/**
	 * Schedule remediation to run in the background.
	 *
	 * This is the public method called from the WooCommerce Tools page.
	 *
	 * @return void
	 */
	public function schedule_remediation(): void {
		// Mark as running and schedule first batch.
		$this->mark_running();
		$this->disable_dry_run();

		if ( function_exists( 'as_schedule_single_action' ) ) {
			as_schedule_single_action(
				time() + 10, // Start in 10 seconds.
				self::ACTION_HOOK,
				[],
				'woocommerce-payments'
			);

			wc_get_logger()->info(
				'Scheduled fee remediation from WooCommerce Tools.',
				[ 'source' => 'wcpay-fee-remediation' ]
			);
		}
	}

	/**
	 * Schedule dry run to preview what would be remediated.
	 *
	 * This allows merchants to see what orders would be affected before
	 * committing to the actual remediation.
	 *
	 * @return void
	 */
	public function schedule_dry_run(): void {
		// Mark as running and enable dry run mode.
		$this->mark_running();
		$this->enable_dry_run();

		if ( function_exists( 'as_schedule_single_action' ) ) {
			as_schedule_single_action(
				time() + 10, // Start in 10 seconds.
				self::DRY_RUN_ACTION_HOOK,
				[],
				'woocommerce-payments'
			);

			wc_get_logger()->info(
				'Scheduled fee remediation DRY RUN from WooCommerce Tools.',
				[ 'source' => 'wcpay-fee-remediation' ]
			);
		}
	}

	/**
	 * Check if there are any orders that need remediation.
	 *
	 * @return bool True if there are affected orders.
	 */
	public function has_affected_orders(): bool {
		$orders = $this->get_affected_orders( 1 );
		return ! empty( $orders );
	}
}
