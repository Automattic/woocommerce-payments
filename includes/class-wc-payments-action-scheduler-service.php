<?php
/**
 * WC_Payments_Action_Scheduler_Service class
 *
 * @package WooCommerce\Payments
 */

use WCPay\Compatibility_Service;
use WCPay\Constants\Order_Mode;

defined( 'ABSPATH' ) || exit;

/**
 * Class which handles setting up all ActionScheduler hooks.
 */
class WC_Payments_Action_Scheduler_Service {

	const GROUP_ID = 'woocommerce_payments';

	/**
	 * Client for making requests to the WooCommerce Payments API
	 *
	 * @var WC_Payments_API_Client
	 */
	private $payments_api_client;

	/**
	 * WC_Payments_Order_Service instance for updating order statuses.
	 *
	 * @var WC_Payments_Order_Service
	 */
	private $order_service;

	/**
	 * Compatibility service instance for updating compatibility data.
	 *
	 * @var Compatibility_Service
	 */
	private $compatibility_service;

	/**
	 * Constructor for WC_Payments_Action_Scheduler_Service.
	 *
	 * @param WC_Payments_API_Client    $payments_api_client - WooCommerce Payments API client.
	 * @param WC_Payments_Order_Service $order_service - Order Service.
	 * @param Compatibility_Service     $compatibility_service - Compatibility service instance.
	 */
	public function __construct(
		WC_Payments_API_Client $payments_api_client,
		WC_Payments_Order_Service $order_service,
		Compatibility_Service $compatibility_service
	) {
		$this->payments_api_client   = $payments_api_client;
		$this->order_service         = $order_service;
		$this->compatibility_service = $compatibility_service;
	}

	/**
	 * Initialize all hooks.
	 *
	 * @return void
	 */
	public function init_hooks() {
		$this->ensure_recurring_actions();
		$this->add_action_scheduler_hooks();
	}

	/**
	 * Ensure all recurring actions are scheduled.
	 *
	 * @return void
	 */
	public function ensure_recurring_actions() {
		if ( function_exists( 'as_supports' ) && as_supports( 'ensure_recurring_actions_hook' ) ) {
			// Preferred since AS v3.9.3 (WC 10.1): runs periodically in the background.
			add_action( 'action_scheduler_ensure_recurring_actions', [ $this, 'schedule_recurring_actions' ] );
		} elseif ( is_admin() ) {
			// Backward compatible fallback: runs on every admin request.
			$this->schedule_recurring_actions();
		}
	}

	/**
	 * Schedule all recurring actions.
	 *
	 * If the action is already scheduled, it won't be rescheduled again.
	 *
	 * @return void
	 */
	public function schedule_recurring_actions() {
		// Check if Action Scheduler is available.
		if ( ! function_exists( 'as_schedule_recurring_action' ) || ! function_exists( 'as_has_scheduled_action' ) ) {
			return;
		}

		if ( ! as_has_scheduled_action( WC_Payments_Account::STORE_SETUP_SYNC_ACTION ) ) {
			// Delay the first run by 10 to 60 seconds (randomly) so it doesn't occur in the same request.
			// The random delay is to avoid large number of sites hitting the API at the same time if they all
			// get updated at the same time (e.g. via WP cron).
			as_schedule_recurring_action( time() + wp_rand( 10, 60 ), 6 * HOUR_IN_SECONDS, WC_Payments_Account::STORE_SETUP_SYNC_ACTION, [], self::GROUP_ID, true );
		}
	}

	/**
	 * Attach hooks for all ActionScheduler actions.
	 *
	 * @return void
	 */
	public function add_action_scheduler_hooks() {
		add_action( 'wcpay_track_new_order', [ $this, 'track_new_order_action' ] );
		add_action( 'wcpay_track_update_order', [ $this, 'track_update_order_action' ] );
		add_action( WC_Payments_Order_Service::ADD_FEE_BREAKDOWN_TO_ORDER_NOTES, [ $this->order_service, 'add_fee_breakdown_to_order_notes' ], 10, 3 );
		add_action( Compatibility_Service::UPDATE_COMPATIBILITY_DATA, [ $this->compatibility_service, 'update_compatibility_data_hook' ], 10, 0 );
	}

	/**
	 * This function is a hook that will be called by ActionScheduler when an order is created.
	 * It will make a request to the Payments API to track this event.
	 *
	 * @param array $order_id  The ID of the order that has been created.
	 *
	 * @return bool
	 */
	public function track_new_order_action( $order_id ) {
		return $this->track_order( $order_id, false );
	}

	/**
	 * This function is a hook that will be called by ActionScheduler when an order is updated.
	 * It will make a request to the Payments API to track this event.
	 *
	 * @param int $order_id  The ID of the order which has been updated.
	 *
	 * @return bool
	 */
	public function track_update_order_action( $order_id ) {
		return $this->track_order( $order_id, true );
	}

	/**
	 * Track an order by making a request to the Payments API.
	 *
	 * @param mixed $order_id  The ID of the order which has been updated/created.
	 * @param bool  $is_update Is this an update event. If false, it is assumed this is a creation event.
	 *
	 * @return bool
	 */
	private function track_order( $order_id, $is_update = false ) {
		// Get the order details.
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return false;
		}

		// If we do not have a valid payment method for this order, don't send the request.
		$payment_method = $this->order_service->get_payment_method_id_for_order( $order );
		if ( empty( $payment_method ) ) {
			return false;
		}
		$order_mode = $order->get_meta( WC_Payments_Order_Service::WCPAY_MODE_META_KEY );

		if ( $order_mode ) {
			$current_mode = WC_Payments::mode()->is_test() ? Order_Mode::TEST : Order_Mode::PRODUCTION;
			if ( $current_mode !== $order_mode ) {
				// If mode doesn't match make sure to stop order tracking to prevent order tracking issues.
				// False will be returned so maybe future crons will have correct mode.
				return false;
			}
		}

		// Send the order data to the Payments API to track it.
		$response = $this->payments_api_client->track_order(
			array_merge(
				$order->get_data(),
				[
					'_payment_method_id'  => $payment_method,
					'_stripe_customer_id' => $this->order_service->get_customer_id_for_order( $order ),
					'_wcpay_mode'         => $order_mode,
				]
			),
			$is_update
		);

		if ( 'success' === $response['result'] && ! $is_update ) {
			// Update the metadata to reflect that the order creation event has been fired.
			$order->add_meta_data( '_new_order_tracking_complete', 'yes' );
			$order->save_meta_data();
		}

		return ( 'success' === ( $response['result'] ?? null ) );
	}

	/**
	 * Schedule an action scheduler job.
	 *
	 * Also, unschedules (replaces) any previous instances of the same job.
	 * This prevents duplicate jobs, for example when multiple events fire as part of the order update process.
	 * We will only replace a job which has the same $hook, $args AND $group.
	 *
	 * @param int    $timestamp When the job will run.
	 * @param string $hook      The hook to trigger.
	 * @param array  $args      Optional. An array containing the arguments to be passed to the hook.
	 *                          Defaults to an empty array.
	 * @param string $group     Optional. The AS group the action will be created under.
	 *                          Defaults to 'woocommerce_payments'.
	 *
	 * @return void
	 */
	public function schedule_job( int $timestamp, string $hook, array $args = [], string $group = self::GROUP_ID ) {
		// The `action_scheduler_init` hook was introduced in ActionScheduler 3.5.5 (WooCommerce 7.9.0).
		if ( version_compare( WC()->version, '7.9.0', '>=' ) ) {
			// If the ActionScheduler is already initialized, schedule the job.
			if ( did_action( 'action_scheduler_init' ) ) {
				$this->schedule_action_and_prevent_duplicates( $timestamp, $hook, $args, $group );
			} else {
				// The ActionScheduler is not initialized yet; we need to schedule the job when it fires the init hook.
				add_action(
					'action_scheduler_init',
					function () use ( $timestamp, $hook, $args, $group ) {
						$this->schedule_action_and_prevent_duplicates( $timestamp, $hook, $args, $group );
					}
				);
			}
		} else {
			$this->schedule_action_and_prevent_duplicates( $timestamp, $hook, $args, $group );
		}
	}

	/**
	 * Checks to see if there is a Pending action with the same hook already.
	 *
	 * @param string $hook Hook name.
	 *
	 * @return bool
	 */
	public function pending_action_exists( string $hook ): bool {
		return as_has_scheduled_action( $hook, [], self::GROUP_ID );
	}

	/**
	 * Schedule an action while unscheduling any scheduled actions that are exactly the same.
	 *
	 * We will look for scheduled actions with the same name, args and group when unscheduling.
	 *
	 * @param int    $timestamp When the action will run.
	 * @param string $action    The action name to schedule.
	 * @param array  $args      Optional. An array containing the arguments to be passed to the action.
	 *                          Defaults to an empty array.
	 * @param string $group     Optional. The ActionScheduler group the action will be created under.
	 *                          Defaults to 'woocommerce_payments'.
	 *
	 * @return void
	 */
	private function schedule_action_and_prevent_duplicates( int $timestamp, string $action, array $args = [], string $group = self::GROUP_ID ) {
		// Unschedule any previously scheduled actions with the same name, args, and group combination.
		// It is more efficient/performant to check if the action is already scheduled before unscheduling it.
		// @see https://github.com/Automattic/woocommerce-payments/issues/6662.
		if ( as_has_scheduled_action( $action, $args, $group ) ) {
			as_unschedule_action( $action, $args, $group );
		}

		as_schedule_single_action( $timestamp, $action, $args, $group );
	}
}
