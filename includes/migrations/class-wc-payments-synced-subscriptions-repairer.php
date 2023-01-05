<?php
/**
 * Class WC_Payments_Synced_Subscriptions_Repair
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Migrations;

use WCPay\Exceptions\API_Exception;

defined( 'ABSPATH' ) || exit;

/**
 * WC_Payments_Synced_Subscriptions_Repairer
 */
class WC_Payments_Synced_Subscriptions_Repairer extends \WCS_Background_Repairer {

	/**
	 * A WC_Payments_Subscription_Service instance to interact with for updating subscriptions.
	 *
	 * @var WC_Payments_Subscription_Service
	 */
	private $subscription_service;

	/**
	 * Instance of WC_Payments_API_Client used to send WCPay request.
	 *
	 * @var WC_Payments_API_Client
	 */
	private $payments_api_client;

	/**
	 * WC_Payments_Synced_Subscriptions_Repair constructor.
	 *
	 * @param \WC_Payments_Subscription_Service $subscription_service A WC_Payments_Subscription_Service instance to interact with for updating subscriptions.
	 * @param \WC_Payments_API_Client           $payments_api_client  Instance of WC_Payments_API_Client used to send WCPay request.
	 * @param \WC_Logger_Interface              $logger               Instance of a WC_Logger for logging purposes.
	 */
	public function __construct( \WC_Payments_Subscription_Service $subscription_service, \WC_Payments_API_Client $payments_api_client, \WC_Logger_Interface $logger ) {
		$this->scheduled_hook       = 'wc_schedule_wcpay_synced_subscription_repair';
		$this->repair_hook          = 'wcpay_synced_subscription_repair';
		$this->log_handle           = 'wcpay-repair-synced-subscriptions';
		$this->logger               = $logger;
		$this->subscription_service = $subscription_service;
		$this->payments_api_client  = $payments_api_client;
	}

	/**
	 * Schedules the repair if the store is impacted by the synced subscription payment date bug.
	 *
	 * This function is called on the `woocommerce_woocommerce_payments_updated` hook before WCPayments version is updated.
	 *
	 * Stores are eligible if they meet the following criteria:
	 *  - previous version of WC Pay is 3.2.0 or greater and less than 5.3.0.
	 *  - the store has synced subscriptions.
	 *  - the store is charging the full price on signup.
	 */
	public function maybe_schedule_repair() {
		$previous_wcpay_version = get_option( 'woocommerce_woocommerce_payments_version' );

		if ( ! class_exists( '\WC_Subscriptions_Synchroniser' ) ) {
			return;
		}

		// This bug only impacts stores with synced subscriptions where the customer is charged the full price on signup.
		if ( ! \WC_Subscriptions_Synchroniser::is_syncing_enabled() || get_option( \WC_Subscriptions_Synchroniser::$setting_id_proration, 'no' ) !== 'recurring' ) {
			return;
		}

		// This bug impacted all WCPay subscriptions stores from 3.2.0 to 5.3.0.
		if ( version_compare( $previous_wcpay_version, '3.2.0', '>=' ) && version_compare( $previous_wcpay_version, '5.3.0', '<' ) ) {
			$this->schedule_repair();
		}
	}

	/**
	 * Gets a batch of Subscriptions to repair.
	 *
	 * @param int $page The page of Subscriptions to get.
	 * @return array An array of Subscriptions to repair.
	 */
	protected function get_items_to_repair( $page ) {
		// Get WCPay subscriptions which are synced.
		return wcs_get_orders_with_meta_query(
			[
				'type'           => 'shop_subscription',
				'status'         => [ 'wc-active', 'wc-on-hold' ],
				'payment_method' => \WC_Payment_Gateway_WCPay::GATEWAY_ID,
				'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					[
						'key'     => '_contains_synced_subscription',
						'compare' => 'EXISTS',
					],
					[
						'key'     => \WC_Payments_Subscription_Service::SUBSCRIPTION_ID_META_KEY,
						'compare' => 'EXISTS',
					],
				],
				'limit'          => 50,
				'return'         => 'ids',
				'paged'          => $page,
			]
		);
	}

	/**
	 * Repairs a single Subscription.
	 *
	 * Sets the next payment date on the WCPay subscription to match the next payment date in WooCommerce.
	 * Only subscriptions with a next payment date in the future with a discrepancy of more than 1 day are repaired.
	 *
	 * @param int $subscription_id The ID of the Subscription to repair.
	 */
	public function repair_item( $subscription_id ) {
		$subscription = wcs_get_subscription( $subscription_id );

		if ( ! $subscription ) {
			return;
		}

		$wcpay_subscription = $this->subscription_service->get_wcpay_subscription( $subscription );

		if ( ! $wcpay_subscription ) {
			return;
		}

		// Check if the subscription's next payment date is more 1 day out of sync.
		$next_payment_time       = $subscription->get_time( 'next_payment' );
		$wcpay_next_payment_time = $wcpay_subscription['current_period_end'];

		if ( $next_payment_time < gmdate( 'U' ) ) {
			$this->logger->info(
				sprintf( 'Subscription %1$s next payment date is in the past. Skipping.', $subscription_id ),
				[
					'source' => $this->log_handle,
				]
			);
			return;
		}

		// If the next payment date is in the past, don't update it. These subscriptions will need to be fixed manually.
		if ( abs( $next_payment_time - $wcpay_next_payment_time ) < DAY_IN_SECONDS ) {
			$this->logger->info(
				sprintf( 'Subscription %1$s next payment date is within 1 day of WCPay subscription %2$s next payment date. Skipping.', $subscription_id, $wcpay_subscription['id'] ),
				[
					'source' => $this->log_handle,
				]
			);
			return;
		}

		// Update the subscription's next payment date.
		try {
			$this->payments_api_client->update_subscription( $wcpay_subscription['id'], [ 'trial_end' => $next_payment_time ] );
			$this->logger->info(
				sprintf( 'Updated subscription %1$s next payment date from %2$s to %3$s.', $subscription_id, gmdate( 'Y-m-d H:i:s', $wcpay_next_payment_time ), gmdate( 'Y-m-d H:i:s', $next_payment_time ) ),
				[
					'source' => $this->log_handle,
				]
			);
		} catch ( API_Exception $e ) {
			$next_payment_date       = gmdate( 'Y-m-d H:i:s', $next_payment_time );
			$wcpay_next_payment_date = gmdate( 'Y-m-d H:i:s', $wcpay_next_payment_time );

			$this->logger->error(
				sprintf( 'Failed to update subscription %1$s next payment date from %2$s to %3$s. Error: %4$s', $subscription_id, $wcpay_next_payment_date, $next_payment_date, $e->getMessage() ),
				[
					'source' => $this->log_handle,
				]
			);
		}
	}
}
