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
	/**
	 * Client for making requests to the WooCommerce Payments API
	 *
	 * @var WC_Payments_API_Client
	 */
	private $payments_api_client;


	/**
	 * Constructor for WC_Payments_Action_Scheduler_Service.
	 *
	 * @param WC_Payments_API_Client $payments_api_client - WooCommerce Payments API client.
	 */
	public function __construct(
		WC_Payments_API_Client $payments_api_client
	) {
		$this->payments_api_client = $payments_api_client;

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
	}

	/**
	 * This function is a hook that will be called by ActionScheduler when an order is created.
	 * It will make a request to the Payments API to track this event.
	 *
	 * @param array $order_data  The data for the order which has been created.
	 *
	 * @return bool
	 */
	public function track_new_order_action( $order_data ) {
		// Ensure that we have the order data to send.
		if ( empty( $order_data ) ) {
			return false;
		}

		$result = $this->payments_api_client->track_order( $order_data, false );

		return $result;
	}

	/**
	 * This function is a hook that will be called by ActionScheduler when an order is updated.
	 * It will make a request to the Payments API to track this event.
	 *
	 * @param array $order_data  The data for the order which has been updated.
	 *
	 * @return bool
	 */
	public function track_update_order_action( $order_data ) {
		// Ensure that we have the order data to send.
		if ( empty( $order_data ) ) {
			return false;
		}

		$result = $this->payments_api_client->track_order( $order_data, true );

		return $result;
	}

	/**
	 * Schedule an action scheduler job.
	 *
	 * @param int    $timestamp - When the job will run.
	 * @param string $hook      - The hook to trigger.
	 * @param array  $args      - An array containing the arguments to be passed to the hook.
	 * @param string $group     - The group to assign this job to.
	 *
	 * @return void
	 */
	public function schedule_job( $timestamp, $hook, $args = [], $group = '' ) {
		as_schedule_single_action( $timestamp, $hook, $args, $group );
	}
}
