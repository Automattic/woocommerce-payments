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

	const DEFAULT_PRIORITY = 10;

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
		add_action( 'wcpay_track_new_order', [ $this, 'track_new_order_action' ], self::DEFAULT_PRIORITY, 2 );
	}

	/**
	 * This function is a hook that will be called by ActionScheduler when an order is created.
	 * It will make a request to the Payments API to track this event.
	 *
	 * @param int   $order_id    The ID of the order that has been created.
	 * @param array $order_data  The data for the order which has been created.
	 *
	 * @return bool
	 */
	public function track_new_order_action( $order_id, $order_data ) {
		// @todo Do we need to add logging here of whether the API call was successful?

		// Ensure that we have an order ID and order data to send.
		if ( is_null( $order_id ) || empty( $order_data ) ) {
			return false;
		}

		$result = $this->payments_api_client->track_new_order( $order_id, $order_data );
	}
}
