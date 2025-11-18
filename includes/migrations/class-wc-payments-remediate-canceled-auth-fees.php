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
 * Between April 2023 (commit 2d13751) and November 2025 (commit 3058f59ad),
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
	 * Version where bug was introduced (5.8.0 = April 2023, commit 2d13751).
	 */
	const BUG_INTRODUCED_VERSION = '5.8.0';

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
	 * @return array Statistics array with keys: processed, remediated, skipped, errors.
	 */
	public function get_stats(): array {
		$default = [
			'processed'  => 0,
			'remediated' => 0,
			'skipped'    => 0,
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
	 * Clean up all remediation options.
	 *
	 * @return void
	 */
	private function cleanup(): void {
		delete_option( self::STATUS_OPTION_KEY );
		delete_option( self::LAST_ORDER_ID_OPTION_KEY );
		delete_option( self::BATCH_SIZE_OPTION_KEY );
		delete_option( self::STATS_OPTION_KEY );
	}

	/**
	 * Get affected orders that need remediation.
	 *
	 * @param int $limit Number of orders to retrieve.
	 * @return WC_Order[] Array of WC_Order objects.
	 */
	public function get_affected_orders( int $limit ): array {
		global $wpdb;

		$last_order_id = $this->get_last_order_id();

		// Build the SQL query to find orders with canceled intent status and fees.
		// We need to join the postmeta table multiple times to check for the different conditions.
		$sql = "
			SELECT DISTINCT p.ID
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id
			INNER JOIN {$wpdb->postmeta} pm_fee ON p.ID = pm_fee.post_id
			WHERE p.post_type IN ('shop_order', 'shop_order_placehold')
			AND p.post_date >= %s
			AND pm_status.meta_key = '_intention_status'
			AND pm_status.meta_value = %s
			AND (pm_fee.meta_key = '_wcpay_transaction_fee' OR pm_fee.meta_key = '_wcpay_net')
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

		// Convert order IDs to WC_Order objects.
		$orders = [];
		foreach ( $order_ids as $order_id ) {
			$order = wc_get_order( $order_id );
			if ( $order ) {
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

		// Schedule next batch if we got a full batch (indicates more to process).
		if ( count( $orders ) === $batch_size ) {
			$this->schedule_next_batch();
		} else {
			// Last partial batch - mark complete.
			$this->mark_complete();
			$this->log_completion();
			$this->cleanup();
		}
	}

	/**
	 * Schedule the next batch.
	 *
	 * @return void
	 */
	private function schedule_next_batch(): void {
		if ( ! function_exists( 'as_schedule_single_action' ) ) {
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
	 * Log completion.
	 *
	 * @return void
	 */
	private function log_completion(): void {
		$stats = $this->get_stats();
		wc_get_logger()->info(
			sprintf(
				'Remediation complete. Processed: %d, Remediated: %d, Skipped: %d, Errors: %d',
				$stats['processed'],
				$stats['remediated'],
				$stats['skipped'],
				$stats['errors']
			),
			[ 'source' => 'wcpay-fee-remediation' ]
		);
	}

	/**
	 * Remediate a single order.
	 *
	 * @param WC_Order $order Order to remediate.
	 * @return bool True on success, false on failure.
	 */
	public function remediate_order( WC_Order $order ): bool {
		try {
			// Capture current values for the note.
			$fee          = $order->get_meta( '_wcpay_transaction_fee', true );
			$net          = $order->get_meta( '_wcpay_net', true );
			$refunds      = $order->get_refunds();
			$refund_count = count( $refunds );
			$refund_total = 0;

			// Calculate total refund amount.
			foreach ( $refunds as $refund ) {
				$refund_total += abs( $refund->get_amount() );
			}

			// Delete all refund objects.
			foreach ( $refunds as $refund ) {
				$refund->delete( true ); // Force delete, bypass trash.
			}

			// Remove fee metadata.
			$order->delete_meta_data( '_wcpay_transaction_fee' );
			$order->delete_meta_data( '_wcpay_net' );
			$order->delete_meta_data( '_wcpay_refund_id' );
			$order->delete_meta_data( '_wcpay_refund_status' );

			// Build detailed note.
			$note_parts = [ 'Removed incorrect data from canceled authorization:' ];

			if ( $refund_count > 0 ) {
				$note_parts[] = sprintf(
					'- Deleted %d refund object%s totaling %s',
					$refund_count,
					$refund_count > 1 ? 's' : '',
					wc_price( $refund_total, [ 'currency' => $order->get_currency() ] )
				);
			}

			if ( ! empty( $fee ) ) {
				$note_parts[] = sprintf(
					'- Removed transaction fee: %s',
					wc_price( $fee, [ 'currency' => $order->get_currency() ] )
				);
			}

			if ( ! empty( $net ) ) {
				$note_parts[] = sprintf(
					'- Removed net amount: %s',
					wc_price( $net, [ 'currency' => $order->get_currency() ] )
				);
			}

			$note_parts[] = '';
			$note_parts[] = 'These records were incorrectly created for an authorization that was never captured.';
			$note_parts[] = 'No actual payment or refund occurred.';

			$order->add_order_note( implode( "\n", $note_parts ) );
			$order->save();

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
	 * Maybe schedule remediation if version gate conditions are met.
	 *
	 * @param string $new_version New plugin version.
	 * @return void
	 */
	public function maybe_schedule_remediation( string $new_version ): void {
		// Check if already complete.
		if ( $this->is_complete() ) {
			return;
		}

		// Get previous version.
		$previous_version = get_option( 'woocommerce_woocommerce_payments_version', '' );

		// Skip if new install (no previous version).
		if ( empty( $previous_version ) ) {
			return;
		}

		// Skip if previous version was before bug introduction.
		if ( version_compare( $previous_version, self::BUG_INTRODUCED_VERSION, '<' ) ) {
			return;
		}

		// Skip if already scheduled.
		if ( function_exists( 'as_has_scheduled_action' ) && as_has_scheduled_action( self::ACTION_HOOK ) ) {
			return;
		}

		// Mark as running and schedule first batch.
		$this->mark_running();

		if ( function_exists( 'as_schedule_single_action' ) ) {
			as_schedule_single_action(
				time() + 60, // Start in 1 minute.
				self::ACTION_HOOK,
				[],
				'woocommerce-payments'
			);

			wc_get_logger()->info(
				sprintf(
					'Scheduled fee remediation. Upgrading from %s to %s',
					$previous_version,
					$new_version
				),
				[ 'source' => 'wcpay-fee-remediation' ]
			);
		}
	}
}
