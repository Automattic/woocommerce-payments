<?php
/**
 * Class WC_Payments_Subscriptions_Upgrader
 *
 * @package WooCommerce\Payments
 */

use WCPay\Payment_Methods\Giropay_Payment_Gateway;
use WCPay\Payment_Methods\Sepa_Payment_Gateway;
use WCPay\Payment_Methods\Sofort_Payment_Gateway;
use WCPay\Payment_Methods\UPE_Payment_Gateway;

/**
 * WC_Payments_Subscriptions_Upgrader class
 */
class WC_Payments_Subscriptions_Upgrader extends WC_Payments_Subscriptions_Background_Migrator {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->scheduled_hook = 'wc_payments_schedule_wcpay_subscription_upgrades';
		$this->repair_hook    = 'wc_payments_tokenize_wcpay_subscription';
	}

	/**
	 * Gets the WP_Query object which can be used to find WCPay Subscriptions which need to be tokenized.
	 *
	 * @param int $items_per_page The number of items to return per page.
	 * @param int $page           The page of results to get. 0 index-based. Optional. Default is 0 - first page.
	 *
	 * @return WP_Query The WP_Query object used to find items that need updating.
	 */
	public function get_query( $items_per_page, $page = 0 ) {
		return new WP_Query(
			[
				'post_type'      => 'shop_subscription',
				'posts_per_page' => $items_per_page,
				'paged'          => $page,
				'orderby'        => 'ID',
				'order'          => 'ASC', // Get the subscriptions in ascending order by ID so any new subscriptions created after process started running will be at the end and not cause issues with paging.
				'post_status'    => 'any',
				'fields'         => 'ids',
				'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					'relation' => 'AND',
					// Has a WCPay Subscription ID.
					[
						'key'     => WC_Payments_Subscription_Service::SUBSCRIPTION_ID_META_KEY,
						'compare' => 'EXISTS',
					],
					// Is a WC Pay Subscription.
					[
						'key'     => '_payment_method',
						'compare' => 'IN',
						'value'   => array_unique(
							[
								WC_Payment_Gateway_WCPay::GATEWAY_ID,
								Giropay_Payment_Gateway::GATEWAY_ID,
								Sepa_Payment_Gateway::GATEWAY_ID,
								Sofort_Payment_Gateway::GATEWAY_ID,
								UPE_Payment_Gateway::GATEWAY_ID,
							]
						),
					],
				],
			]
		);
	}

	/**
	 * Migrate a WCPay Subscription into a WooCommerce Payments tokenized subscription.
	 *
	 * @param int $subscription_id The subscription that needs upgrading.
	 */
	public function handle_item( $subscription_id ) {
		$subscription = wcs_get_subscription( $subscription_id );

		if ( ! $subscription || ! WC_Payments_Subscription_Service::is_wcpay_subscription( $subscription ) ) {
			return;
		}

		$wcpay_subscription = WC_Payments_Subscriptions::get_subscription_service()->get_wcpay_subscription( $subscription );

		// Only cancel subscription which aren't already cancelled.
		if ( isset( $wcpay_subscription['status'] ) && 'canceled' !== $wcpay_subscription['status'] ) {
			// Cancel the subscription.
			WC_Payments_Subscriptions::get_subscription_service()->cancel_subscription( $subscription );
		}

		// Archive the WCPay Subscription ID.
		$wcpay_subscription_id = WC_Payments_Subscriptions::get_subscription_service()->get_wcpay_subscription_id( $subscription );
		$subscription->update_meta_data( WC_Payments_Subscription_Service::SUBSCRIPTION_ID_META_KEY . '_archived', $wcpay_subscription_id );
		$subscription->delete_meta_data( WC_Payments_Subscription_Service::SUBSCRIPTION_ID_META_KEY );
		$subscription->save();
	}
}
