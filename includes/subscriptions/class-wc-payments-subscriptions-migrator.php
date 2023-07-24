<?php
/**
 * Class WC_Payments_Subscriptions_Migrator
 *
 * @package WooCommerce\Payments
 */

use WCPay\Exceptions\API_Exception;

/**
 * Handles migrating WCPay Subscriptions to tokenized subscriptions.
 */
class WC_Payments_Subscriptions_Migrator {

	/**
	 * Valid subscription statuses to cancel a subscription at Stripe.
	 *
	 * @var array $active_statuses
	 */
	private $active_statuses = [ 'active', 'past_due', 'trialing', 'paused' ];

	/**
	 * WC_Payments_API_Client instance.
	 *
	 * @var WC_Payments_API_Client
	 */
	private $api_client;

	/**
	 * Constructor.
	 *
	 * @param WC_Payments_API_Client|null $api_client WC_Payments_API_Client instance.
	 */
	public function __construct( $api_client = null ) {
		$this->api_client = $api_client;

		// Hook onto Scheduled Action to migrate wcpay subscription.
		// add_action( 'wcpay_migrate_subscription', [ $this, 'migrate_wcpay_subscription' ] );.
	}

	/**
	 * Migrate WCPay Subscription to WC Subscriptions
	 *
	 * Migration process:
	 * 1. Validate the request to migrate subscription
	 * 2. Fetches the subscription from Stripe
	 * 3. Cancels the subscription at Stripe if it is active
	 * 4. Update the subscription meta to indicate that it has been migrated
	 * 5. Add an order note on the subscription
	 *
	 * @param int $subscription_id The ID of the subscription to migrate.
	 */
	public function migrate_wcpay_subscription( $subscription_id ) {
		try {
			$subscription       = $this->validate_subscription_to_migrate( $subscription_id );
			$wcpay_subscription = $this->fetch_wcpay_subscription( $subscription );

			$this->log( sprintf( 'Migrating subscription #%d (%s)', $subscription_id, $wcpay_subscription['id'] ) );

			$this->maybe_cancel_wcpay_subscription( $wcpay_subscription );

			/**
			 * There's a scenario where a WCPay subscription is active but has no pending renewal scheduled action.
			 * Once migrated, this results in an active subscription that will remain active forever, without processing a renewal order.
			 *
			 * To ensure that all migrated subscriptions have a pending scheduled action, we need to reschedule the next payment date by
			 * updating the date on the subscription.
			 */
			if ( $subscription->has_status( 'active' ) && $subscription->get_time( 'next_payment' ) > time() ) {
				$new_next_payment = gmdate( 'Y-m-d H:i:s', $subscription->get_time( 'next_payment' ) + 1 );
				$subscription->update_dates( [ 'next_payment' => $new_next_payment ] );

				$this->log( sprintf( '---- Next payment date updated to %s to ensure active subscription has a pending scheduled payment.', $new_next_payment ) );
			}

			// Remove the WCPay Subscription metadata that tells us the subscription's billing cycle is managed by Stripe's billing engine. Keep it stored in separate meta.
			$subscription->update_meta_data( '_migrated_wcpay_subscription_id', $wcpay_subscription['id'] );
			$subscription->delete_meta_data( WC_Payments_Subscription_Service::SUBSCRIPTION_ID_META_KEY );

			$subscription->add_order_note( __( 'This subscription has been successfully migrated to a WooPayments tokenized subscription.', 'woocommerce-payments' ) );

			$subscription->save();

			$this->log( '---- SUCCESS: Subscription migrated.' );
		} catch ( \Exception $e ) {
			$this->log( $e->getMessage() );
		}
	}

	/**
	 * Validates the request to migrate a WCPay Subscription.
	 *
	 * Only allows migration if:
	 * - The WooCommerce Subscription extension is active
	 * - Store is not in staging mode or is a duplicate site
	 * - The subscription ID is a valid subscription
	 * - The subscription has not already been migrated
	 *
	 * @param int $subscription_id The ID of the subscription to migrate.
	 *
	 * @throws \Exception Skip the migration if the request is invalid.
	 */
	private function validate_subscription_to_migrate( $subscription_id ) {
		if ( ! class_exists( 'WC_Subscriptions' ) ) {
			throw new \Exception( sprintf( 'Skipping migration of subscription #%d. The WooCommerce Subscriptions extension is not active.', $subscription_id ) );
		}

		if ( WC_Payments_Subscriptions::is_duplicate_site() ) {
			throw new \Exception( sprintf( 'Skipping migration of subscription #%d. Site is in staging mode.', $subscription_id ) );
		}

		$subscription = wcs_get_subscription( $subscription_id );

		if ( ! $subscription ) {
			throw new \Exception( sprintf( 'Skipping migration of subscription #%d. Subscription not found.', $subscription_id ) );
		}

		$migrated_wcpay_subscription_id = $subscription->get_meta( '_migrated_wcpay_subscription_id', true );

		if ( ! empty( $migrated_wcpay_subscription_id ) ) {
			throw new \Exception( sprintf( 'Skipping migration of subscription #%d (%s). Subscription has already been migrated.', $subscription_id, $migrated_wcpay_subscription_id ) );
		}

		return $subscription;
	}

	/**
	 * Fetches the subscription from Stripe and verifies it has a valid ID and status.
	 *
	 * Returns false if the request returns an unexpected result.
	 *
	 * @param WC_Subscription $subscription The WC subscription to migrate.
	 *
	 * @return array
	 *
	 * @throws \Exception If there's an error fetching the subscription from Stripe.
	 */
	private function fetch_wcpay_subscription( $subscription ) {
		$wcpay_subscription_id = WC_Payments_Subscription_Service::get_wcpay_subscription_id( $subscription );

		if ( ! $wcpay_subscription_id ) {
			throw new \Exception( sprintf( 'Skipping migration of subscription #%d. Subscription is not a WCPay Subscription.', $subscription->get_id() ) );
		}

		try {
			// Fetch the subscription from Stripe.
			$wcpay_subscription = $this->api_client->get_subscription( $wcpay_subscription_id );
		} catch ( API_Exception $e ) {
			throw new \Exception( sprintf( 'Error migrating subscription #%d (%s). Failed to fetch the subscription. %s', $subscription->get_id(), $wcpay_subscription_id, $e->getMessage() ) );
		}

		if ( empty( $wcpay_subscription['id'] ) || empty( $wcpay_subscription['status'] ) ) {
			throw new \Exception( sprintf( 'Error migrating subscription #%d (%s). Invalid subscription data from Stripe: %s', $subscription->get_id(), $wcpay_subscription_id, var_export( $wcpay_subscription, true ) ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
		}

		return $wcpay_subscription;
	}

	/**
	 * Cancels the subscription at Stripe if it is active.
	 *
	 * This function checks the status on the subscription at Stripe then cancels it if it's a valid status and logs any errors.
	 *
	 * We skip canceling any subscriptions at Stripe that are:
	 * - incomplete: the subscription was created but no payment method was added to the subscription
	 * - incomplete_expired: the incomplete subscription expired after 24hrs of no payment method being added.
	 * - canceled: the subscription is already canceled
	 * - unpaid: this status is not used by subscriptions in WooCommerce Payments
	 *
	 * @param array $wcpay_subscription The subscription data from Stripe.
	 *
	 * @throws \Exception If there's an error canceling the subscription at Stripe.
	 */
	private function maybe_cancel_wcpay_subscription( $wcpay_subscription ) {
		// Valid statuses to cancel subscription at Stripe: active, past_due, trialing, paused.
		if ( in_array( $wcpay_subscription['status'], $this->active_statuses, true ) ) {
			$this->log( sprintf( '---- Subscription at Stripe has "%s" status. Canceling the subscription.', $this->get_wcpay_subscription_status( $wcpay_subscription ) ) );

			try {
				// Cancel the subscription in Stripe.
				$wcpay_subscription = $this->api_client->cancel_subscription( $wcpay_subscription['id'] );
			} catch ( API_Exception $e ) {
				throw new \Exception( sprintf( '---- ERROR: Failed to cancel the subscription at Stripe. %s', $e->getMessage() ) );
			}

			$this->log( '---- Subscription successfully canceled at Stripe.' );
		} else {
			// Statuses that don't need to be canceled: incomplete, incomplete_expired, canceled, unpaid.
			$this->log( sprintf( '---- Subscription has "%s" status. Skipping canceling the subscription at Stripe.', $this->get_wcpay_subscription_status( $wcpay_subscription ) ) );
		}
	}

	/**
	 * Returns the subscription status from the WCPay subscription data for logging purposes.
	 *
	 * When a subscription is on-hold, we don't change the status of the subscription at Stripe, instead, we set
	 * the subscription as active and set the `pause_collection` behavior to `void` so that the subscription is not charged.
	 *
	 * The purpose of this function is factor in the `paused_collection` value when determining the subscription status at Stripe.
	 *
	 * @param array $wcpay_subscription The subscription data from Stripe.
	 *
	 * @return string
	 */
	private function get_wcpay_subscription_status( $wcpay_subscription ) {
		if ( empty( $wcpay_subscription['status'] ) ) {
			return 'unknown';
		}

		if ( 'active' === $wcpay_subscription['status'] && ! empty( $wcpay_subscription['pause_collection']['behavior'] ) && 'void' === $wcpay_subscription['pause_collection']['behavior'] ) {
			return 'paused';
		}

		return $wcpay_subscription['status'];
	}

	/**
	 * Logs a migration message. TODO: implement logging.
	 *
	 * @param string $message The message to log.
	 */
	public function log( $message ) {
		error_log( $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log  -- Will remove this once we have a proper logger.
	}
}
