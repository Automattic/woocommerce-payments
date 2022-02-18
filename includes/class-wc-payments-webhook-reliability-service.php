<?php
// phpcs:ignoreFile TODO using this only when working.

/**
 * WC_Payments_Webhook_Reliability_Service class
 *
 * @package WooCommerce\Payments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use WCPay\Logger;

/**
 * Improve webhook reliability by fetching failed events from the server.
 * And process them with ActionScheduler
 */
class WC_Payments_Webhook_Reliability_Service {
	const FETCHING_EVENTS_LIMIT = 10;

	/**
	 * Client for making requests to the WooCommerce Payments API
	 *
	 * @var WC_Payments_API_Client
	 */
	private $payments_api_client;

	/**
	 * WC_Payments_Webhook_Reliability_Service constructor.
	 *
	 * @param WC_Payments_API_Client $payments_api_client Payments API client.
	 */
	public function __construct( WC_Payments_API_Client $payments_api_client ) {
		$this->payments_api_client = $payments_api_client;
	}

	private function get_events() {

	}

	public function schedule_fetch_events() {

	}

	public function save_event_data() {

	}

	public function clear_event_data() {

	}

	public function schedule_process_event() {

	}

	public function process_event_data() {

	}
}
