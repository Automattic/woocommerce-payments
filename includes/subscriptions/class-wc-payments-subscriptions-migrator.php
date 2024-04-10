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
	 * WC_Payments_Token_Service instance.
	 *
	 * @var WC_Payments_Token_Service
	 */
	private $token_service;

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
	 * @param WC_Payments_API_Client|null    $api_client    WC_Payments_API_Client instance.
	 * @param WC_Payments_Token_Service|null $token_service WC_Payments_Token_Service instance.
	 */
	public function __construct( $api_client = null, $token_service = null ) {
		$this->api_client    = $api_client;
		$this->token_service = $token_service;
		$this->logger        = new WC_Payments_Subscription_Migration_Log_Handler();

		// Don't copy migrated subscription meta keys to related orders.
		add_filter( 'wc_subscriptions_object_data', [ $this, 'exclude_migrated_meta' ], 10, 1 );

		// Add manual migration tool to WooCommerce > Status > Tools.
		add_filter( 'woocommerce_debug_tools', [ $this, 'add_manual_migration_tool' ] );

		// Schedule the single migration action with two args. This is needed because the WCS_Background_Repairer parent class only hooks on with one arg.
		add_action( $this->migrate_hook . '_retry', [ $this, 'migrate_wcpay_subscription' ], 10, 2 );

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
	 * @param int $attempt         The number of times migration has been attempted.
	 */
	public function migrate_wcpay_subscription( $subscription_id, $attempt = 0 ) {
		try {
			add_action( 'action_scheduler_unexpected_shutdown', [ $this, 'handle_unexpected_shutdown' ], 10, 2 );
			add_action( 'action_scheduler_failed_execution', [ $this, 'handle_unexpected_action_failure' ], 10, 2 );

			$this->logger->log( sprintf( 'Migrating subscription #%1$d.%2$s', $subscription_id, ( $attempt > 0 ? ' Attempt: ' . ( (int) $attempt + 1 ) : '' ) ) );

			$subscription       = $this->validate_subscription_to_migrate( $subscription_id );
			$wcpay_subscription = $this->fetch_wcpay_subscription( $subscription );

			$this->maybe_cancel_wcpay_subscription( $wcpay_subscription );

			if ( $subscription->has_status( 'active' ) ) {
				$this->update_next_payment_date( $subscription, $wcpay_subscription );
			}

			// If the subscription is active or on-hold, verify the payment method is valid and set correctly that it continues to renew.
			if ( $subscription->has_status( [ 'active', 'on-hold' ] ) ) {
				$this->verify_subscription_payment_token( $subscription, $wcpay_subscription );
			}

			$this->update_wcpay_subscription_meta( $subscription );

			if ( WC_Payment_Gateway_WCPay::GATEWAY_ID === $subscription->get_payment_method() ) {
				$subscription->add_order_note( __( 'This subscription has been successfully migrated to a WooPayments tokenized subscription.', 'woocommerce-payments' ) );
			}

			$this->logger->log( sprintf( '---- Subscription #%d migration complete.', $subscription_id ) );
		} catch ( \Exception $e ) {
			$this->logger->log( $e->getMessage() );

			$this->maybe_reschedule_migration( $subscription_id, $attempt, $e );
		}

		remove_action( 'action_scheduler_unexpected_shutdown', [ $this, 'handle_unexpected_shutdown' ] );
		remove_action( 'action_scheduler_failed_execution', [ $this, 'handle_unexpected_action_failure' ] );
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
			throw new \Exception( sprintf( '---- Skipping migration of subscription #%d. The WooCommerce Subscriptions extension is not active.', $subscription_id ) );
		}

		if ( WC_Payments_Subscriptions::is_duplicate_site() ) {
			throw new \Exception( sprintf( '---- Skipping migration of subscription #%d. Site is in staging mode.', $subscription_id ) );
		}

		$subscription = wcs_get_subscription( $subscription_id );

		if ( ! $subscription ) {
			throw new \Exception( sprintf( '---- Skipping migration of subscription #%d. Subscription not found.', $subscription_id ) );
		}

		$migrated_wcpay_subscription_id = $subscription->get_meta( '_migrated_wcpay_subscription_id', true );

		if ( ! empty( $migrated_wcpay_subscription_id ) ) {
			throw new \Exception( sprintf( '---- Skipping migration of subscription #%1$d (%2$s). Subscription has already been migrated.', $subscription_id, $migrated_wcpay_subscription_id ) );
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
			throw new \Exception( sprintf( '---- Skipping migration of subscription #%d. Subscription is not a WCPay Subscription.', $subscription->get_id() ) );
		}

		try {
			// Fetch the subscription from Stripe.
			$wcpay_subscription = $this->api_client->get_subscription( $wcpay_subscription_id );
		} catch ( API_Exception $e ) {
			throw new \Exception( sprintf( '---- ERROR: Failed to fetch subscription #%1$d (%2$s) from Stripe. %3$s', $subscription->get_id(), $wcpay_subscription_id, $e->getMessage() ) );
		}

		if ( empty( $wcpay_subscription['id'] ) || empty( $wcpay_subscription['status'] ) ) {
			throw new \Exception( sprintf( '---- ERROR: Cannot migrate subscription #%1$d (%2$s). Invalid data fetched from Stripe: %3$s', $subscription->get_id(), $wcpay_subscription_id, var_export( $wcpay_subscription, true ) ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
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
			$this->logger->log( sprintf( '---- Stripe subscription (%1$s) has "%2$s" status. Canceling the subscription.', $wcpay_subscription['id'], $this->get_wcpay_subscription_status( $wcpay_subscription ) ) );

			try {
				// Cancel the subscription in Stripe.
				$wcpay_subscription = $this->api_client->cancel_subscription( $wcpay_subscription['id'] );
			} catch ( API_Exception $e ) {
				throw new \Exception( sprintf( '---- ERROR: Failed to cancel the Stripe subscription (%1$s). %2$s', $wcpay_subscription['id'], $e->getMessage() ) );
			}

			$this->logger->log( sprintf( '---- Stripe subscription (%1$s) successfully canceled.', $wcpay_subscription['id'] ) );
		} else {
			// Statuses that don't need to be canceled: incomplete, incomplete_expired, canceled, unpaid.
			$this->logger->log( sprintf( '---- Stripe subscription (%1$s) has "%2$s" status. Skipping canceling the subscription at Stripe.', $wcpay_subscription['id'], $this->get_wcpay_subscription_status( $wcpay_subscription ) ) );
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
	 * Updates the subscription's next payment date in WooCommerce to ensure a smooth transition to on-site billing.
	 *
	 * There's a scenario where a WCPay subscription is active but has no pending renewal scheduled action.
	 * Once migrated, this results in an active subscription that will remain active forever, without processing a renewal order.
	 *
	 * To ensure that all migrated subscriptions have a pending scheduled action, we need to reschedule the next payment date by
	 * updating the date on the subscription.
	 *
	 * In priority order the new next payment date will be:
	 *  - The existing WooCommerce next payment date if it's in the future.
	 *  - The Stripe subscription's current_period_end if it's in the future.
	 *  - A newly calculated next payment date using the WC_Subscription::calculate_date() method.
	 *
	 * @param WC_Subscription $subscription       The WC Subscription being migrated.
	 * @param array           $wcpay_subscription The subscription data from Stripe.
	 */
	private function update_next_payment_date( $subscription, $wcpay_subscription ) {
		try {
			// Just update the existing WC Subscription's next payment date if it's in the future.
			if ( $subscription->get_time( 'next_payment' ) > time() ) {
				$new_next_payment = gmdate( 'Y-m-d H:i:s', $subscription->get_time( 'next_payment' ) + 1 );

				$subscription->update_dates( [ 'next_payment' => $new_next_payment ] );
				$this->logger->log( sprintf( '---- Next payment date updated to %1$s to ensure subscription #%2$d has a pending scheduled payment.', $new_next_payment, $subscription->get_id() ) );

				return;
			}

			// If the subscription was still using WooPayments, use the Stripe subscription's next payment time (current_period_end) if it's in the future.
			if ( WC_Payment_Gateway_WCPay::GATEWAY_ID === $subscription->get_payment_method() && isset( $wcpay_subscription['current_period_end'] ) && absint( $wcpay_subscription['current_period_end'] ) > time() ) {
				$new_next_payment = gmdate( 'Y-m-d H:i:s', absint( $wcpay_subscription['current_period_end'] ) );

				$subscription->update_dates( [ 'next_payment' => $new_next_payment ] );
				$this->logger->log( sprintf( '---- Next payment date updated to %1$s to match Stripe subscription record and to ensure subscription #%2$d has a pending scheduled payment.', $new_next_payment, $subscription->get_id() ) );

				return;
			}

			// Lastly calculate the next payment date.
			$new_next_payment = $subscription->calculate_date( 'next_payment' );

			if ( wcs_date_to_time( $new_next_payment ) > time() ) {
				$subscription->update_dates( [ 'next_payment' => $new_next_payment ] );
				$this->logger->log( sprintf( '---- Calculated a new next payment date (%1$s) to ensure subscription #%2$d has a pending scheduled payment in the future.', $new_next_payment, $subscription->get_id() ) );

				return;
			}

			// If we got here the next payment date is in the past, the Stripe subscription is missing a "current_period_end" or it's in the past, and calculating a new date also failed. Log an error.
			$this->logger->log(
				sprintf(
					'---- ERROR: Failed to update subscription #%1$d next payment date. Current next payment date (%2$s) is in the past, Stripe "current_period_end" data is invalid (%3$s) and an attempt to calculate a new date also failed (%4$s).',
					$subscription->get_id(),
					gmdate( 'Y-m-d H:i:s', $subscription->get_time( 'next_payment' ) ),
					isset( $wcpay_subscription['current_period_end'] ) ? gmdate( 'Y-m-d H:i:s', absint( $wcpay_subscription['current_period_end'] ) ) : 'no data',
					$new_next_payment
				)
			);
		} catch ( \Exception $e ) {
			$this->logger->log( sprintf( '---- ERROR: Failed to update subscription #%1$d next payment date. %2$s', $subscription->get_id(), $e->getMessage() ) );
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
	 * Verifies the payment token on the subscription matches the default payment method on the WCPay Subscription.
	 *
	 * This function does two things:
	 * 1. If the subscription doesn't have a WooPayments payment token, set it to the default payment method from Stripe Billing.
	 * 2. If the subscription has a token, verify the token matches the token on the Stripe Billing subscription
	 *
	 * @param WC_Subscription $subscription       The subscription to verify the payment token on.
	 * @param array           $wcpay_subscription The subscription data from Stripe.
	 */
	private function verify_subscription_payment_token( $subscription, $wcpay_subscription ) {
		// If the subscription's payment method isn't set to WooPayments, we skip this token step.
		if ( $subscription->get_payment_method() !== WC_Payment_Gateway_WCPay::GATEWAY_ID ) {
			$this->logger->log( sprintf( '---- Skipped verifying the payment token. Subscription #%1$d has "%2$s" as the payment method.', $subscription->get_id(), $subscription->get_payment_method() ) );
			return;
		}

		if ( empty( $wcpay_subscription['default_payment_method'] ) ) {
			$this->logger->log( sprintf( '---- Could not verify the payment method. Stripe Billing subscription (%1$s) does not have a default payment method.', $wcpay_subscription['id'] ?? 'unknown' ) );
			return;
		}

		$tokens   = $subscription->get_payment_tokens();
		$token_id = end( $tokens );
		$token    = ! $token_id ? null : WC_Payment_Tokens::get( $token_id );

		// If the token matches the default payment method on the Stripe Billing subscription, we're done here.
		if ( $token && $token->get_token() === $wcpay_subscription['default_payment_method'] ) {
			$this->logger->log( sprintf( '---- Payment token on subscription #%1$d matches the payment method on the Stripe Billing subscription (%2$s).', $subscription->get_id(), $wcpay_subscription['id'] ?? 'unknown' ) );
			return;
		}

		// At this point we know the subscription doesn't have a token or the token doesn't match, add one using the default payment method on the WCPay Subscription.
		$new_token = $this->maybe_create_and_update_payment_token( $subscription, $wcpay_subscription );

		if ( $new_token ) {
			$this->logger->log( sprintf( '---- Payment token on subscription #%1$d has been updated (from %2$s to %3$s) to match the payment method on the Stripe Billing subscription.', $subscription->get_id(), $token ? $token->get_token() : 'missing', $wcpay_subscription['default_payment_method'] ) );
		}
	}

	/**
	 * Locates a payment token or creates one if it doesn't exist, then updates the subscription with the new token.
	 *
	 * @param WC_Subscription $subscription       The subscription to add the payment token to.
	 * @param array           $wcpay_subscription The subscription data from Stripe.
	 *
	 * @return WC_Payment_Token|false The new payment token or false if the token couldn't be created.
	 */
	private function maybe_create_and_update_payment_token( $subscription, $wcpay_subscription ) {
		$token           = false;
		$user            = new WP_User( $subscription->get_user_id() );
		$customer_tokens = WC_Payment_Tokens::get_tokens(
			[
				'user_id'    => $user->ID,
				'gateway_id' => WC_Payment_Gateway_WCPay::GATEWAY_ID,
				'limit'      => WC_Payment_Gateway_WCPay::USER_FORMATTED_TOKENS_LIMIT,
			]
		);

		foreach ( $customer_tokens as $customer_token ) {
			if ( $customer_token->get_token() === $wcpay_subscription['default_payment_method'] ) {
				$token = $customer_token;
				break;
			}
		}

		// If we didn't find a token linked to the subscription customer, create one.
		if ( ! $token ) {
			try {
				$token = $this->token_service->add_payment_method_to_user( $wcpay_subscription['default_payment_method'], $user );
				$this->logger->log( sprintf( '---- Created a new payment token (%1$s) for subscription #%2$d.', $token->get_token(), $subscription->get_id() ) );
			} catch ( \Exception $e ) {
				$this->logger->log( sprintf( '---- WARNING: Subscription #%1$d is missing a payment token and we failed to create one. Error: %2$s', $subscription->get_id(), $e->getMessage() ) );
				return;
			}
		}

		// Prevent the WC_Payments_Subscriptions class from attempting to update the Stripe Billing subscription's payment method while we set the token.
		remove_action( 'woocommerce_payment_token_added_to_order', [ WC_Payments_Subscriptions::get_subscription_service(), 'update_wcpay_subscription_payment_method' ], 10 );

		$subscription->add_payment_token( $token );

		// Reattach.
		add_action( 'woocommerce_payment_token_added_to_order', [ WC_Payments_Subscriptions::get_subscription_service(), 'update_wcpay_subscription_payment_method' ], 10, 3 );

		return $token;
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
	public function handle_unexpected_shutdown( $action_id, $error = null ) {
		$migration_args = $this->get_migration_action_args( $action_id );

		if ( ! isset( $migration_args['migrate_subscription'], $migration_args['attempt'] ) ) {
			return;
		}

		if ( ! empty( $error['type'] ) && in_array( $error['type'], [ E_ERROR, E_PARSE, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR ], true ) ) {
			$this->logger->log( sprintf( '---- ERROR: Unexpected shutdown while migrating subscription #%1$d: %2$s in %3$s on line %4$s.', $migration_args['migrate_subscription'], $error['message'] ?? 'No message', $error['file'] ?? 'no file found', $error['line'] ?? '0' ) );
		}

		$this->maybe_reschedule_migration( $migration_args['migrate_subscription'], $migration_args['attempt'] );
	}

	/**
	 * Handles any unexpected failures that occur while processing a single migration action
	 * by logging an error message and rescheduling the action to retry.
	 *
	 * @param string    $action_id The Action Scheduler action ID.
	 * @param Exception $exception The exception thrown during action processing.
	 */
	public function handle_unexpected_action_failure( $action_id, $exception ) {
		$migration_args = $this->get_migration_action_args( $action_id );

		if ( ! isset( $migration_args['migrate_subscription'], $migration_args['attempt'] ) ) {
			return;
		}

		$this->logger->log( sprintf( '---- ERROR: Unexpected failure while migrating subscription #%1$d: %2$s', $migration_args['migrate_subscription'], $exception->getMessage() ) );
		$this->maybe_reschedule_migration( $migration_args['migrate_subscription'], $migration_args['attempt'] );
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
		$wcpay_subscriptions_count = $this->get_stripe_billing_subscription_count();

		if ( $wcpay_subscriptions_count < 1 ) {
			return $tools;
		}

		// Disable the button if a migration is currently in progress.
		$disabled = $this->is_migrating();

		$tools['migrate_wcpay_subscriptions'] = [
			'name'             => __( 'Migrate Stripe Billing subscriptions', 'woocommerce-payments' ),
			'button'           => $disabled ? __( 'Migration in progress', 'woocommerce-payments' ) . '&#8230;' : __( 'Migrate Subscriptions', 'woocommerce-payments' ),
			'desc'             => sprintf(
				// translators: %1$s is a new line character and %2$d is the number of subscriptions.
				__( 'This tool will migrate all Stripe Billing subscriptions to tokenized subscriptions with WooPayments.%1$sNumber of Stripe Billing subscriptions found: %2$d', 'woocommerce-payments' ),
				'<br>',
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
	 * Gets the subscription ID and number of attempts from the action args.
	 *
	 * @param int $action_id The action ID to get data from.
	 *
	 * @return array
	 */
	private function get_migration_action_args( $action_id ) {
		$action = ActionScheduler_Store::instance()->fetch_action( $action_id );

		if ( ! $action || ( $this->migrate_hook !== $action->get_hook() && $this->migrate_hook . '_retry' !== $action->get_hook() ) ) {
			return [];
		}

		$action_args = $action->get_args();

		if ( ! isset( $action_args['migrate_subscription'] ) ) {
			return [];
		}

		return array_merge(
			[
				'migrate_subscription' => 0,
				'attempt'              => 0,
			],
			$action_args
		);
	}

	/**
	 * Reschedules a subscription migration with increasing delays depending on number of attempts.
	 *
	 * After max retries, an exception is thrown if one was passed.
	 *
	 * @param int             $subscription_id The ID of the subscription to retry.
	 * @param int             $attempt         The number of times migration has been attempted.
	 * @param \Exception|null $exception       The exception thrown during migration.
	 *
	 * @throws \Exception If max attempts and exception passed is not null.
	 */
	public function maybe_reschedule_migration( $subscription_id, $attempt = 0, $exception = null ) {
		// Number of seconds to wait before retrying the migration, increasing with each attempt up to 7 attempts (12 hours).
		$retry_schedule = [ 60, 300, 600, 1800, HOUR_IN_SECONDS, 6 * HOUR_IN_SECONDS, 12 * HOUR_IN_SECONDS ];

		// If the exception thrown contains "Skipping migration", don't reschedule the migration.
		if ( $exception && false !== strpos( $exception->getMessage(), 'Skipping migration' ) ) {
			return;
		}

		if ( isset( $retry_schedule[ $attempt ] ) && $attempt < 7 ) {
			$this->logger->log( sprintf( '---- Rescheduling migration of subscription #%1$d.', $subscription_id ) );

			as_schedule_single_action(
				gmdate( 'U' ) + $retry_schedule[ $attempt ],
				$this->migrate_hook . '_retry',
				[
					'migrate_subscription' => $subscription_id,
					'attempt'              => $attempt + 1,
				]
			);
		} else {
			$this->logger->log( sprintf( '---- FAILED: Subscription #%d could not be migrated.', $subscription_id ) );

			if ( $exception ) {
				// Before throwing the exception, remove the action_scheduler failure hook to prevent the exception being logged again.
				remove_action( 'action_scheduler_failed_execution', [ $this, 'handle_unexpected_action_failure' ] );

				throw $exception;
			}
		}
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
	 * Gets the total number of subscriptions to migrate.
	 *
	 * @return int The total number of subscriptions to migrate.
	 */
	public function get_stripe_billing_subscription_count() {
		$result = wcs_get_orders_with_meta_query(
			[
				'status'     => 'any',
				'return'     => 'ids',
				'type'       => 'shop_subscription',
				'limit'      => - 1,
				'meta_query' => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					[
						'key'     => WC_Payments_Subscription_Service::SUBSCRIPTION_ID_META_KEY,
						'compare' => 'EXISTS',
					],
				],
			]
		);

		return is_countable( $result ) ? count( $result ) : 0;
	}

	/**
	 * Determines if a migration is currently in progress.
	 *
	 * A migration is considered to be in progress if the initial migration action or an individual subscription
	 * action (or retry) is scheduled.
	 *
	 * @return bool True if a migration is in progress, false otherwise.
	 */
	public function is_migrating() {
		return (bool) as_next_scheduled_action( $this->scheduled_hook ) || (bool) as_next_scheduled_action( $this->migrate_hook ) || (bool) as_next_scheduled_action( $this->migrate_hook . '_retry' );
	}

	/**
	 * Runs any actions that need to handle the completion of the migration.
	 */
	protected function unschedule_background_updates() {
		parent::unschedule_background_updates();

		delete_option( $this->migration_batch_identifier_option );
	}
}
