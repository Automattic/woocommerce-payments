<?php
/**
 * Class WC_Payments_Subscriptions_Migrator
 *
 * @package WooCommerce\Payments
 */

use WCPay\Exceptions\API_Exception;

require_once __DIR__ . '/class-wc-payments-subscription-migration-log-handler.php';

/**
 * Handles migrating WCPay Subscriptions to tokenized subscriptions.
 *
 * This class extends the WCS_Background_Repairer for scheduling and running the individual migration actions.
 */
class WC_Payments_Subscriptions_Migrator extends WCS_Background_Repairer {

	/**
	 * Valid subscription statuses to cancel a subscription at Stripe.
	 *
	 * @var array $active_statuses
	 */
	private $active_statuses = [ 'active', 'past_due', 'trialing', 'paused' ];

	/**
	 * WCPay Subscription meta keys for migrated data.
	 *
	 * @var array $migrated_meta_keys
	 */
	private $meta_keys_to_migrate = [
		WC_Payments_Subscription_Service::SUBSCRIPTION_ID_META_KEY,
		WC_Payments_Invoice_Service::ORDER_INVOICE_ID_KEY,
		WC_Payments_Invoice_Service::PENDING_INVOICE_ID_KEY,
		WC_Payments_Subscription_Service::SUBSCRIPTION_DISCOUNT_IDS_META_KEY,
	];

	/**
	 * WC_Payments_API_Client instance.
	 *
	 * @var WC_Payments_API_Client
	 */
	private $api_client;

	/**
	 * WC_Payments_Subscription_Migration_Log_Handler instance.
	 *
	 * @var WC_Payments_Subscription_Migration_Log_Handler
	 */
	protected $logger;

	/**
	 * The Action Scheduler hook used to find and schedule individual migrations of WCPay Subscriptions.
	 *
	 * @var string
	 */
	public $scheduled_hook = 'wcpay_schedule_subscription_migrations';

	/**
	 * The Action Scheduler hook to migrate a WCPay Subscription.
	 *
	 * @var string
	 */
	public $migrate_hook = 'wcpay_migrate_subscription';

	/**
	 * The option name used to store a batch identifier for the current migration batch.
	 *
	 * @var string
	 */
	private $migration_batch_identifier_option = 'wcpay_subscription_migration_batch';

	/**
	 * Constructor.
	 *
	 * @param WC_Payments_API_Client|null $api_client WC_Payments_API_Client instance.
	 */
	public function __construct( $api_client = null ) {
		$this->api_client = $api_client;
		$this->logger     = new WC_Payments_Subscription_Migration_Log_Handler();

		// Don't copy migrated subscription meta keys to related orders.
		add_filter( 'wc_subscriptions_object_data', [ $this, 'exclude_migrated_meta' ], 10, 1 );

		// Add manual migration tool to WooCommerce > Status > Tools.
		add_filter( 'woocommerce_debug_tools', [ $this, 'add_manual_migration_tool' ] );

		$this->init();
	}

	/**
	 * Migrates a WCPay Subscription to a tokenized WooPayments subscription powered by WC Subscriptions
	 *
	 * Migration process:
	 *   1. Validate the request to migrate subscription
	 *   2. Fetches the subscription from Stripe
	 *   3. Cancels the subscription at Stripe if it is active
	 *   4. Update the subscription meta to indicate that it has been migrated
	 *   5. Add an order note on the subscription
	 *
	 * @param int $subscription_id The ID of the subscription to migrate.
	 */
	public function migrate_wcpay_subscription( $subscription_id ) {
		try {
			add_action( 'action_scheduler_unexpected_shutdown', [ $this, 'log_unexpected_shutdown' ], 10, 2 );
			add_action( 'action_scheduler_failed_execution', [ $this, 'log_unexpected_action_failure' ], 10, 2 );

			$subscription       = $this->validate_subscription_to_migrate( $subscription_id );
			$wcpay_subscription = $this->fetch_wcpay_subscription( $subscription );

			$this->logger->log( sprintf( 'Migrating subscription #%d (%s)', $subscription_id, $wcpay_subscription['id'] ) );

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

				$this->logger->log( sprintf( '---- Next payment date updated to %s to ensure active subscription has a pending scheduled payment.', $new_next_payment ) );
			}

			$this->update_wcpay_subscription_meta( $subscription );

			$subscription->add_order_note( __( 'This subscription has been successfully migrated to a WooPayments tokenized subscription.', 'woocommerce-payments' ) );

			$this->logger->log( '---- SUCCESS: Subscription migrated.' );
		} catch ( \Exception $e ) {
			$this->logger->log( $e->getMessage() );
		}

		remove_action( 'action_scheduler_unexpected_shutdown', [ $this, 'log_unexpected_shutdown' ] );
		remove_action( 'action_scheduler_failed_execution', [ $this, 'log_unexpected_action_failure' ] );
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
	 *   - incomplete: the subscription was created but no payment method was added to the subscription
	 *   - incomplete_expired: the incomplete subscription expired after 24hrs of no payment method being added.
	 *   - canceled: the subscription is already canceled
	 *   - unpaid: this status is not used by subscriptions in WooCommerce Payments
	 *
	 * @param array $wcpay_subscription The subscription data from Stripe.
	 *
	 * @throws \Exception If there's an error canceling the subscription at Stripe.
	 */
	private function maybe_cancel_wcpay_subscription( $wcpay_subscription ) {
		// Valid statuses to cancel subscription at Stripe: active, past_due, trialing, paused.
		if ( in_array( $wcpay_subscription['status'], $this->active_statuses, true ) ) {
			$this->logger->log( sprintf( '---- Subscription at Stripe has "%s" status. Canceling the subscription.', $this->get_wcpay_subscription_status( $wcpay_subscription ) ) );

			try {
				// Cancel the subscription in Stripe.
				$wcpay_subscription = $this->api_client->cancel_subscription( $wcpay_subscription['id'] );
			} catch ( API_Exception $e ) {
				throw new \Exception( sprintf( '---- ERROR: Failed to cancel the subscription at Stripe. %s', $e->getMessage() ) );
			}

			$this->logger->log( '---- Subscription successfully canceled at Stripe.' );
		} else {
			// Statuses that don't need to be canceled: incomplete, incomplete_expired, canceled, unpaid.
			$this->logger->log( sprintf( '---- Subscription has "%s" status. Skipping canceling the subscription at Stripe.', $this->get_wcpay_subscription_status( $wcpay_subscription ) ) );
		}
	}

	/**
	 * Migrates WCPay Subscription related metadata to a new key prefixed with `_migrated` and deletes the old meta.
	 *
	 * @param WC_Subscription $subscription The subscription with wcpay meta saved.
	 */
	private function update_wcpay_subscription_meta( $subscription ) {
		$updated = false;

		/**
		 * If this subscription is being migrated while scheduling individual actions is on-going, make sure we store meta on the subscription
		 * so that it's still returned by the query in @see get_items_to_repair() to not affect the limit and pagination.
		 */
		$migration_start = get_option( $this->migration_batch_identifier_option, 0 );

		if ( 0 !== $migration_start ) {
			$subscription->update_meta_data( '_wcpay_subscription_migrated_during', $migration_start );
			$updated = true;
		}

		foreach ( $this->meta_keys_to_migrate as $meta_key ) {
			if ( $subscription->meta_exists( $meta_key ) ) {
				$subscription->update_meta_data( '_migrated' . $meta_key, $subscription->get_meta( $meta_key, true ) );
				$subscription->delete_meta_data( $meta_key );

				$updated = true;
			}
		}

		if ( $updated ) {
			$subscription->save();
		}
	}

	/**
	 * Returns the subscription status from the WCPay subscription data for logging purposes.
	 *
	 * If a subscription is on-hold in WC we wouldn't have changed the status of the subscription at Stripe, instead, the
	 * subscription would remain active and set `pause_collection` behavior to `void` so that the subscription is not charged.
	 *
	 * The purpose of this function is to handle the `paused_collection` value when mapping the subscription status at Stripe to
	 * a status for logging.
	 *
	 * @param array $wcpay_subscription The subscription data from Stripe.
	 *
	 * @return string The WCPay subscription status for logging purposes.
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
	 * Prevents migrated WCPay subscription metadata being copied to subscription related orders (renewal/switch/resubscribe).
	 *
	 * @param array $meta_data The meta data to be copied.
	 * @return array The meta data to be copied.
	 */
	public function exclude_migrated_meta( $meta_data ) {
		foreach ( $this->meta_keys_to_migrate as $key ) {
			unset( $meta_data[ '_migrated' . $key ] );
		}

		return $meta_data;
	}

	/**
	 * Logs any fatal errors that occur while processing a scheduled migrate WCPay Subscription action.
	 *
	 * @param string $action_id The Action Scheduler action ID.
	 * @param array  $error     The error data.
	 */
	public function log_unexpected_shutdown( $action_id, $error = null ) {
		if ( ! empty( $error['type'] ) && in_array( $error['type'], [ E_ERROR, E_PARSE, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR ], true ) ) {
			$this->logger->log( sprintf( '---- ERROR: %s in %s on line %s.', $error['message'] ?? 'No message', $error['file'] ?? 'no file found', $error['line'] ?? '0' ) );
		}
	}

	/**
	 * Logs any unexpected failures that occur while processing a scheduled migrate WCPay Subscription action.
	 *
	 * @param string    $action_id The Action Scheduler action ID.
	 * @param Exception $exception The exception thrown during action processing.
	 */
	public function log_unexpected_action_failure( $action_id, $exception ) {
		$this->logger->log( sprintf( '---- ERROR: %s', $exception->getMessage() ) );
	}

	/**
	 * Adds a manual migration tool to WooCommerce > Status > Tools.
	 *
	 * This tool is only loaded on stores that have:
	 *  - WC Subscriptions extension activated
	 *  - Subscriptions with WooPayments feature disabled
	 *  - Existing WCPay Subscriptions that can be migrated
	 *
	 * @param array $tools List of WC debug tools.
	 *
	 * @return array List of WC debug tools.
	 */
	public function add_manual_migration_tool( $tools ) {
		if ( WC_Payments_Features::is_wcpay_subscriptions_enabled() || ! class_exists( 'WC_Subscriptions' ) ) {
			return $tools;
		}

		// Get number of WCPay Subscriptions that can be migrated.
		$wcpay_subscriptions_count = count(
			wcs_get_orders_with_meta_query(
				[
					'status'     => 'any',
					'return'     => 'ids',
					'type'       => 'shop_subscription',
					'limit'      => -1,
					'meta_query' => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
						[
							'key'     => WC_Payments_Subscription_Service::SUBSCRIPTION_ID_META_KEY,
							'compare' => 'EXISTS',
						],
					],
				]
			)
		);

		if ( $wcpay_subscriptions_count < 1 ) {
			return $tools;
		}

		$disabled = as_next_scheduled_action( $this->scheduled_hook );

		$tools['migrate_wcpay_subscriptions'] = [
			'name'             => __( 'Migrate Stripe Billing subscriptions', 'woocommerce-payments' ),
			'button'           => $disabled ? __( 'Migration in progress', 'woocommerce-payments' ) . '&#8230;' : __( 'Migrate Subscriptions', 'woocommerce-payments' ),
			'desc'             => sprintf(
				// translators: %1$s and %2$s are <a> tags, %3$d is the number of subscriptions.
				__( 'This tool will migrate all Stripe Billing subscriptions to tokenized subscriptions with WooPayments. %1$sRead more.%2$sNumber of Stripe Billing subscriptions found: %3$d', 'woocommerce-payments' ),
				'<a href="" target="_blank">',
				'</a><br>',
				$wcpay_subscriptions_count,
			),
			'callback'         => [ $this, 'schedule_migrate_wcpay_subscriptions_action' ],
			'disabled'         => $disabled,
			'requires_refresh' => true,
		];

		return $tools;
	}

	/**
	 * Schedules the initial migration action which signals the start of the migration process.
	 */
	public function schedule_migrate_wcpay_subscriptions_action() {
		if ( as_next_scheduled_action( $this->scheduled_hook ) ) {
			return;
		}

		update_option( $this->migration_batch_identifier_option, time() );

		$this->logger->log( 'Started scheduling subscription migrations.' );
		$this->schedule_repair();
	}

	/**
	 * Override WCS_Background_Repairer methods.
	 */

	/**
	 * Initialize class variables and hooks to handle scheduling and running migration hooks in the background.
	 */
	public function init() {
		$this->repair_hook = $this->migrate_hook;

		parent::init();
	}

	/**
	 * Schedules an individual action to migrate a subscription.
	 *
	 * Overrides the parent class function to make two changes:
	 * 1. Don't schedule an action if one already exists.
	 * 2. Schedules the migration to happen in one minute instead of in one hour.
	 *
	 * @param int $item The ID of the subscription to migrate.
	 */
	public function update_item( $item ) {
		if ( ! as_next_scheduled_action( $this->migrate_hook, [ 'migrate_subscription' => $item ] ) ) {
			as_schedule_single_action( gmdate( 'U' ) + 60, $this->migrate_hook, [ 'migrate_subscription' => $item ] );
		}

		unset( $this->items_to_repair[ $item ] );
	}

	/**
	 * Migrates an individual subscription.
	 *
	 * The repair_item() function is called by the parent class when the individual scheduled action is run.
	 * This acts as a wrapper for the migrate_wcpay_subscription() function.
	 *
	 * @param int $item The ID of the subscription to migrate.
	 */
	public function repair_item( $item ) {
		$this->migrate_wcpay_subscription( $item );
	}

	/**
	 * Gets a batch of 100 subscriptions to migrate.
	 *
	 * Because this function fetches items in batches using limit and paged query args, we need to make sure
	 * the paging of this query is consistent regardless of whether some subscriptions have been repaired/migrated in between.
	 *
	 * To do this, we use the $this->migration_batch_identifier_option value to identify subscriptions previously returned by
	 * this function that have been migrated so they will still be considered for paging.
	 *
	 * @param int $page The page of results to fetch.
	 *
	 * @return int[] The IDs of the subscriptions to migrate.
	 */
	public function get_items_to_repair( $page ) {
		$items_to_migrate = wcs_get_orders_with_meta_query(
			[
				'return'     => 'ids',
				'type'       => 'shop_subscription',
				'limit'      => 100,
				'status'     => 'any',
				'paged'      => $page,
				'order'      => 'ASC',
				'orderby'    => 'ID',
				'meta_query' => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					'relation' => 'OR',
					[
						'key'     => WC_Payments_Subscription_Service::SUBSCRIPTION_ID_META_KEY,
						'compare' => 'EXISTS',
					],
					// We need to include subscriptions which have already been migrated as part of this migration group to make
					// sure correct paging is maintained. As subscriptions are migrated they would migrate the WCPay subscription ID
					// meta key and therefore fall out of this query's scope - messing with the paging of future queries.
					// Subscriptions with the `migrated_during` meta aren't expected to be returned by this query, they are included to pad out the earlier pages.
					[
						'key'     => '_wcpay_subscription_migrated_during',
						'value'   => get_option( $this->migration_batch_identifier_option, 0 ),
						'compare' => '=',
					],
				],
			]
		);

		if ( empty( $items_to_migrate ) ) {
			$this->logger->log( 'Finished scheduling subscription migrations.' );
		}

		return $items_to_migrate;
	}

	/**
	 * Runs any actions that need to handle the completion of the migration.
	 */
	protected function unschedule_background_updates() {
		parent::unschedule_background_updates();

		delete_option( $this->migration_batch_identifier_option );
	}
}
