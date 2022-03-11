<?php
/**
 * WC_Payments_Webhook_Reliability_Service class
 *
 * @package WooCommerce\Payments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use WCPay\Exceptions\API_Exception;
use WCPay\Exceptions\Invalid_Webhook_Data_Exception;
use WCPay\Logger;

/**
 * Improve webhook reliability by fetching failed events from the server,
 * then process them with ActionScheduler
 */
class WC_Payments_Webhook_Reliability_Service {
	const CONTINUOUS_FETCH_FLAG_EVENTS_LIST  = 'has_more';
	const CONTINUOUS_FETCH_FLAG_ACCOUNT_DATA = 'has_more_failed_events';
	const WEBHOOK_FETCH_EVENTS_ACTION        = 'wcpay_webhook_fetch_events';
	const WEBHOOK_PROCESS_EVENT_ACTION       = 'wcpay_webhook_process_event';

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
	 * Webhook Processing Service.
	 *
	 * @var WC_Payments_Webhook_Processing_Service
	 */
	private $webhook_processing_service;

	/**
	 * WC_Payments_Webhook_Reliability_Service constructor.
	 *
	 * @param WC_Payments_API_Client                 $payments_api_client WooCommerce Payments API client.
	 * @param WC_Payments_Action_Scheduler_Service   $action_scheduler_service Wrapper for ActionScheduler service.
	 * @param WC_Payments_Webhook_Processing_Service $webhook_processing_service WC_Payments_Webhook_Processing_Service instance.
	 */
	public function __construct(
		WC_Payments_API_Client $payments_api_client,
		WC_Payments_Action_Scheduler_Service $action_scheduler_service,
		WC_Payments_Webhook_Processing_Service $webhook_processing_service
	) {
		$this->payments_api_client        = $payments_api_client;
		$this->action_scheduler_service   = $action_scheduler_service;
		$this->webhook_processing_service = $webhook_processing_service;

		add_action( 'woocommerce_payments_account_refreshed', [ $this, 'maybe_schedule_fetch_events' ] );
		add_action( self::WEBHOOK_FETCH_EVENTS_ACTION, [ $this, 'fetch_events_and_schedule_processing_jobs' ] );
		add_action( self::WEBHOOK_PROCESS_EVENT_ACTION, [ $this, 'process_event' ] );
	}

	/**
	 * During the account data refresh, check the relevant flag to remaining failed events on the WooCommerce Payments server,
	 * and decide whether scheduling a job to fetch them.
	 *
	 * @param  mixed|array $account Account data retrieved from WooCommerce Payments server.
	 *
	 * @return void
	 */
	public function maybe_schedule_fetch_events( $account ) {
		if ( ! is_array( $account ) ) {
			return;
		}

		if ( $account[ self::CONTINUOUS_FETCH_FLAG_ACCOUNT_DATA ] ?? false ) {
			$this->schedule_fetch_events();
		}
	}

	/**
	 * Fetch failed events from the WooCommerce Payments server through ActionScheduler.
	 *
	 * @return void
	 */
	public function fetch_events_and_schedule_processing_jobs() {
		try {
			$payload = $this->payments_api_client->get_failed_webhook_events();
		} catch ( API_Exception $e ) {
			Logger::error( 'Can not fetch failed events from the server. Error:' . $e->getMessage() );
			return;
		}

		if ( $payload[ self::CONTINUOUS_FETCH_FLAG_EVENTS_LIST ] ?? false ) {
			$this->schedule_fetch_events();
		}

		// Save the data, and schedule a job for each event.
		$events = $payload['data'] ?? [];
		foreach ( $events as $event ) {
			if ( ! isset( $event['id'] ) ) {
				Logger::error( 'Event ID does not exist. Event data: ' . var_export( $event, true ) ); // phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_var_export
				continue;
			}

			$this->set_event_data( $event );
			$this->schedule_process_event( $event['id'] );
		}
	}

	/**
	 * Process an event through ActionScheduler.
	 *
	 * @param  string $event_id Event ID.
	 *
	 * @return void
	 */
	public function process_event( string $event_id ) {
		Logger::info( 'Start processing event: ' . $event_id );

		$event_data = $this->get_event_data( $event_id );

		$this->delete_event_data( $event_id );

		if ( null === $event_data ) {
			Logger::error( 'Stop processing as no data available for event: ' . $event_id );
			return;
		}

		try {
			$this->webhook_processing_service->process( $event_data );
			Logger::info( 'Successfully processed event ' . $event_id );
		} catch ( Invalid_Webhook_Data_Exception $e ) {
			Logger::error( 'Failed processing event ' . $event_id . '. Reason: ' . $e->getMessage() );
		}
	}

	/**
	 * Schedule a job to process an event later.
	 *
	 * @param  string $event_id Event ID.
	 *
	 * @return void
	 */
	private function schedule_process_event( string $event_id ) {
		$this->action_scheduler_service->schedule_job( time(), self::WEBHOOK_PROCESS_EVENT_ACTION, [ 'event_id' => $event_id ] );
		Logger::info( 'Successfully schedule a job to processing event: ' . $event_id );
	}

	/**
	 * Schedule a job to fetch failed events.
	 *
	 * @return void
	 */
	private function schedule_fetch_events() {
		$this->action_scheduler_service->schedule_job( time(), self::WEBHOOK_FETCH_EVENTS_ACTION );
		Logger::info( 'Successfully schedule a job to fetch failed events from the server.' );
	}

	/**
	 * Get the transient name to interact with the storage.
	 *
	 * @param  string $event_id Event ID.
	 *
	 * @return string
	 */
	private function get_transient_name_for_event_id( string $event_id ): string {
		// Use md5 to overcome the limit of transient name (172 characters) while Stripe event ID can be up to 255.
		return 'wcpay_failed_event_' . md5( $event_id );
	}

	/**
	 * Save the event data.
	 *
	 * @param  array $event_data Event data.
	 *
	 * @return bool True if the value was set, false otherwise.
	 */
	public function set_event_data( array $event_data ) {
		if ( ! isset( $event_data['id'] ) ) {
			return false;
		}

		return set_transient( $this->get_transient_name_for_event_id( $event_data['id'] ), $event_data, DAY_IN_SECONDS );
	}

	/**
	 * Delete the event data.
	 *
	 * @param  string $event_id Event ID.
	 *
	 * @return bool True if the event data is deleted, false otherwise.
	 */
	public function delete_event_data( string $event_id ): bool {
		return delete_transient( $this->get_transient_name_for_event_id( $event_id ) );
	}

	/**
	 * Retrieve the event data. Return null if the data does not exist.
	 *
	 * @param  string $event_id Event ID.
	 *
	 * @return ?array
	 */
	public function get_event_data( string $event_id ) {
		$data = get_transient( $this->get_transient_name_for_event_id( $event_id ) );
		return false === $data ? null : $data;
	}
}
