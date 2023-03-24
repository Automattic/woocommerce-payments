<?php
/**
 * WC_Payments_Action_Scheduler_Service class
 *
 * @package WooCommerce\Payments
 */

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
	 * Constructor for WC_Payments_Action_Scheduler_Service.
	 *
	 * @param WC_Payments_API_Client    $payments_api_client - WooCommerce Payments API client.
	 * @param WC_Payments_Order_Service $order_service - Order Service.
	 */
	public function __construct(
		WC_Payments_API_Client $payments_api_client, WC_Payments_Order_Service $order_service
	) {
		$this->payments_api_client = $payments_api_client;
		$this->order_service       = $order_service;

		$this->add_action_scheduler_hooks();
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
		$order_mode = $order->get_meta( '_wcpay_mode' );

		if ( $order_mode ) {
			$current_mode = WC_Payments::mode()->is_test() ? 'test' : 'prod';
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
	 * Schedule an action scheduler job. Also unschedules (replaces) any previous instances of the same job.
	 * This prevents duplicate jobs, for example when multiple events fire as part of the order update process.
	 * The `as_unschedule_action` function will only replace a job which has the same $hook, $args AND $group.
	 *
	 * @param int    $timestamp - When the job will run.
	 * @param string $hook      - The hook to trigger.
	 * @param array  $args      - An array containing the arguments to be passed to the hook.
	 * @param string $group     - The AS group the action will be created under.
	 *
	 * @return void
	 */
	public function schedule_job( int $timestamp, string $hook, array $args = [], string $group = self::GROUP_ID ) {
		// Unschedule any previously scheduled instances of this particular job.
		as_unschedule_action( $hook, $args, $group );

		// Schedule the job.
		as_schedule_single_action( $timestamp, $hook, $args, $group );
	}

	/**
	 * Checks to see if there is a Pending action with the same hook already.
	 *
	 * @param string $hook Hook name.
	 *
	 * @return bool
	 */
	public function pending_action_exists( string $hook ): bool {
		$actions = as_get_scheduled_actions(
			[
				'hook'   => $hook,
				'status' => ActionScheduler_Store::STATUS_PENDING,
				'group'  => self::GROUP_ID,
			]
		);

		return count( $actions ) > 0;
	}
}
