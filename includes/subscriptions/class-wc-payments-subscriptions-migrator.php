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
	 * Constructor.
	 */
	public function __construct() {
		// Hook onto Scheduled Action to migrate wcpay subscription.
		// add_action( 'wcpay_migrate_subscription', [ $this, 'migrate_wcpay_subscription' ] );.
	}

	/**
	 * Migrate WCPay Subscription to WC Subscriptions
	 *
	 * Migration process:
	 * 1. Validate the request to migrate subscription
	 * 2. Fetches the subscription from Stripe
	 * 3. Cancels the subscription at stripe if it is active
	 * 4. Update the subscription meta to indicate that it has been migrated
	 * 5. Add an order note on the subscription
	 *
	 * @param int $subscription_id The ID of the subscription to migrate.
	 */
	public function migrate_wcpay_subscription( $subscription_id ) {
		$subscription = $this->fetch_and_validate_subscription_to_migrate( $subscription_id );

		if ( ! $subscription ) {
			return;
		}

		if ( $this->has_subscription_already_migrated( $subscription ) ) {
			return;
		}

		$wcpay_subscription = $this->fetch_wcpay_subscription( $subscription );

		if ( ! $wcpay_subscription ) {
			return;
		}

		$this->log( sprintf( 'Migrating subscription #%d (%s)', $subscription_id, $wcpay_subscription['id'] ) );

		$this->maybe_cancel_wcpay_subscription( $wcpay_subscription );

		// Remove the WCPay Subscription metadata that tells us the subscription's billing cycle is managed by Stripe's billing engine. Keep it stored in separate meta.
		$subscription->update_meta_data( '_migrated_wcpay_subscription_id', $wcpay_subscription['id'] );
		$subscription->delete_meta_data( WC_Payments_Subscription_Service::SUBSCRIPTION_ID_META_KEY );

		$subscription->add_order_note( 'This subscription has been successfully migrated to a tokenized subscription.' );

		$subscription->save();

		$this->log( '---- SUCCESS: Subscription migrated.' );
	}

	/**
	 * Fetches and validates the subscription to migrate. Returns false if the subscription cannot be migrated.
	 *
	 * Only allows migration if:
	 * - The WooCommerce Subscription extension is active
	 * - Store is not in staging mode or is a duplicate site
	 * - Subscription ID belongs to a valid subscription
	 *
	 * @param int $subscription_id The ID of the subscription to migrate.
	 *
	 * @return WC_Subscription|bool
	 */
	private function fetch_and_validate_subscription_to_migrate( $subscription_id ) {
		if ( ! class_exists( 'WC_Subscriptions' ) ) {
			$this->log( sprintf( 'Skipping migration of subscription #%d. The WooCommerce Subscriptions extension is not active.', $subscription_id ) );
			return false;
		}

		if ( WC_Payments_Subscriptions::is_duplicate_site() ) {
			$this->log( sprintf( 'Skipping migration of subscription #%d. Site is in staging mode.', $subscription_id ) );
			return false;
		}

		$subscription = wcs_get_subscription( $subscription_id );
		if ( ! $subscription ) {
			$this->log( sprintf( 'Skipping migration of subscription #%d. Subscription not found.', $subscription_id ) );
			return false;
		}

		return $subscription;
	}

	/**
	 * Checks if the subscription has already been migrated.
	 *
	 * Looks for the `_migrated_wcpay_subscription_id` metadata  on the subscription and if found,
	 * this function double-checks the subscription in Stripe is not active.
	 *
	 * @param WC_Subscription $subscription The WC subscription to migrate.
	 *
	 * @return bool
	 */
	private function has_subscription_already_migrated( $subscription ) {
		$migrated_wcpay_subscription_id = $subscription->get_meta( '_migrated_wcpay_subscription_id', true );

		if ( empty( $migrated_wcpay_subscription_id ) ) {
			return false;
		}

		try {
			$wcpay_subscription = WC_Payments::get_payments_api_client()->get_subscription( $migrated_wcpay_subscription_id );
		} catch ( API_Exception $e ) {
			$this->log( sprintf( 'Error fetching an already migrated subscription (%s). Could not verify the subscription is canceled in Stripe. %s', $migrated_wcpay_subscription_id, $e->getMessage() ) );
		}

		if ( ! empty( $wcpay_subscription['status'] ) ) {
			if ( in_array( $wcpay_subscription['status'], $this->active_statuses, true ) ) {
				try {
					WC_Payments::get_payments_api_client()->cancel_subscription( $migrated_wcpay_subscription_id );
				} catch ( API_Exception $e ) {
					$this->log( sprintf( 'Error canceling an already migrated subscription (%s). Failed to cancel subscription at Stripe. %s', $migrated_wcpay_subscription_id, $e->getMessage() ) );
				}
			} else {
				$this->log( sprintf( 'Skipping migration of subscription #%d (%s). Subscription has already been migrated and has "%s" status in Stripe.', $subscription->get_id(), $migrated_wcpay_subscription_id, $wcpay_subscription['status'] ) );
			}
		}

		return true;

	}

	/**
	 * Fetches the subscription from Stripe and verifies it has a valid ID and status.
	 *
	 * Returns false if the request returns an unexpected result.
	 *
	 * @param WC_Subscription $subscription The WC subscription to migrate.
	 *
	 * @return array|bool
	 */
	private function fetch_wcpay_subscription( $subscription ) {
		$wcpay_subscription    = false;
		$wcpay_subscription_id = WC_Payments_Subscription_Service::get_wcpay_subscription_id( $subscription );

		if ( ! $wcpay_subscription_id ) {
			$this->log( sprintf( 'Skipping migration of subscription #%d. Subscription is not a WCPay Subscription.', $subscription->get_id() ) );
			return false;
		}

		try {
			// Fetch the subscription from stripe.
			$wcpay_subscription = WC_Payments::get_payments_api_client()->get_subscription( $wcpay_subscription_id );
		} catch ( API_Exception $e ) {
			$this->log( sprintf( 'Error migrating subscription #%d (%s). Failed to fetch subscription from Stripe. %s', $subscription->get_id(), $wcpay_subscription_id, $e->getMessage() ) );
			return false;
		}

		if ( empty( $wcpay_subscription['id'] ) || empty( $wcpay_subscription['status'] ) ) {
			$this->log( sprintf( 'Error migrating subscription #%d (%s). Invalid subscription data from Stripe: %s', $subscription->get_id(), $wcpay_subscription_id, var_export( $wcpay_subscription, true ) ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
			return false;
		}

		return $wcpay_subscription;
	}

	/**
	 * Cancels the subscription at Stripe if it is active.
	 *
	 * This function checks the status on the subscription at Stripe and cancels it and logs any errors.
	 *
	 * We skip canceling any subscriptions at Stripe that are:
	 * - incomplete: the subscription was created but no payment method was added to the subscription
	 * - incomplete_expired: the incomplete subscription expired after 24hrs of no payment method being added.
	 * - canceled: the subscription is already canceled
	 * - unpaid: this status is not used by subscriptions in WooCommerce Payments
	 *
	 * @param array $wcpay_subscription The subscription data from Stripe.
	 *
	 * @return void
	 */
	private function maybe_cancel_wcpay_subscription( $wcpay_subscription ) {
		// Valid statuses to cancel subscription at Stripe: active, past_due, trialing, paused.
		if ( in_array( $wcpay_subscription['status'], $this->active_statuses, true ) ) {
			$this->log( sprintf( '---- Subscription at Stripe has "%s" status. Canceling the subscription.', $wcpay_subscription['status'] ) );

			try {
				// Cancel the subscription in stripe.
				$wcpay_subscription = WC_Payments::get_payments_api_client()->cancel_subscription( $wcpay_subscription['id'] );
			} catch ( API_Exception $e ) {
				$this->log( sprintf( '---- ERROR: Failed to cancel the subscription at Stripe. %s', $e->getMessage() ) );
				return;
			}

			$this->log( '---- Subscription successfully canceled at stripe.' );
		} else {
			// Statuses that don't need to be canceled: incomplete, incomplete_expired, canceled, unpaid.
			$this->log( sprintf( '---- Subscription has "%s" status. Skipping canceling the subscription at stripe.', $wcpay_subscription['status'] ) );
		}
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
