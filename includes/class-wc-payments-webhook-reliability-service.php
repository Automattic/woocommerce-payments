<?php
// phpcs:ignoreFile TODO using this only when working.
// TODO - consider singleton?

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
	const WEBHOOK_FETCH_EVENTS_ACTION = 'wcpay_webhook_fetch_events';
	const WEBHOOK_PROCESS_EVENT_ACTION = 'wcpay_webhook_process_event';

	/**
	 * Client for making requests to the WooCommerce Payments API.
	 *
	 * @var WC_Payments_API_Client
	 */
	private $payments_api_client;

	/**
	 * WC_Payments_Action_Scheduler_Service.
	 *
	 * @var WC_Payments_Action_Scheduler_Service
	 */
	private $action_scheduler_service;

	/**
	 * WC_Payments_Webhook_Reliability_Service constructor.
	 *
	 * @param WC_Payments_API_Client $payments_api_client Payments API client.
	 */
	public function __construct( WC_Payments_API_Client $payments_api_client ) {
		$this->payments_api_client = $payments_api_client;
		$this->action_scheduler_service = new WC_Payments_Action_Scheduler_Service( $this->payments_api_client );

		add_action( self::WEBHOOK_FETCH_EVENTS_ACTION, [$this, 'fetch_events'] );
		add_action( self::WEBHOOK_PROCESS_EVENT_ACTION, [$this, 'process_event'] );
		add_action( 'woocommerce_payments_account_refreshed', [ $this, 'maybe_schedule_fetch_events'] );
	}

	private function fetch_events() {
		$payload = $this->payments_api_client->get_failed_webhook_events();

		$remaining_failed_events = (int) $payload['failed_webhook_count'] ?? 0; // TODO - maybe `failed_webhook_count` (int) or `has_more` (bool). See server PR 1633
		if ( $remaining_failed_events > 0 ) {
			$this->schedule_fetch_events();
		}

		$events = $payload[ 'data' ] ?? [];
		foreach ( $events as $event_data ) {
			$this->schedule_process_event( $event_data );
		}
	}


	public function schedule_fetch_events() {
		$this->action_scheduler_service->schedule_job( time(), self::WEBHOOK_FETCH_EVENTS_ACTION );
	}

	public function maybe_schedule_fetch_events( array $account ) {
		$remaining_failed_events = (int) $account['failed_webhook_count'] ?? 0; // TODO - maybe `failed_webhook_count` (int) or `has_more` (bool). See server PR 1633
		if ( $remaining_failed_events > 0 ) {
			$this->schedule_fetch_events();
		}
	}

	public function schedule_process_event( array $event_data ) {
		$this->action_scheduler_service->schedule_job( time(), self::WEBHOOK_PROCESS_EVENT_ACTION, $event_data );
	}

	public function process_event( array $event_data ) {
		// TODO with the following pseudo-code
		// Verify data?
		// Create a request based on this data. Dispatch to Webhook_Controller
		// Monitor and log?
		// Delete data if saving it somewhere.
	}
}
