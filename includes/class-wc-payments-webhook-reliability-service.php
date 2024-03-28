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
use WCPay\Payment_Methods\Sepa_Payment_Method;

/**
 * Improve webhook reliability by fetching failed events from the server,
 * then process them with ActionScheduler
 */
class WC_Payments_Webhook_Reliability_Service {
	const CONTINUOUS_FETCH_FLAG_EVENTS_LIST  = 'has_more';
	const CONTINUOUS_FETCH_FLAG_ACCOUNT_DATA = 'has_more_failed_events';
	const WEBHOOK_FETCH_EVENTS_ACTION        = 'wcpay_webhook_fetch_events';
	const WEBHOOK_PROCESS_EVENT_ACTION       = 'wcpay_webhook_process_event';
	const POST_TYPE                          = 'wcpay-webhook';
	const PAYMENT_INTENT_SUCCEEDED_EVENT     = 'payment_intent.succeeded';

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
		// Add cleanup action.
		add_action( 'action_scheduler_after_execute', [ $this, 'maybe_cleanup_logs_after_processing' ], 1, 2 );
	}

	/**
	 * Checks if an event should be delayed for asynchronous processing.
	 *
	 * This list includes events, which might arrive synchronously
	 * and cause concurrency issues with the rest of the gateway's code.
	 *
	 * @param array $event The event array.
	 * @return bool        Whether to delay the processing of the event.
	 */
	public function should_delay_event( array $event ): bool {

		// If the payment method is SEPA, return false immediately.
		if ( isset( $event['data']['object']['payment_method_details']['type'] ) && Sepa_Payment_Method::PAYMENT_METHOD_STRIPE_ID === $event['data']['object']['payment_method_details']['type'] ) {
			return false;
		}

		// Could be replaced with array of events later on.
		return self::PAYMENT_INTENT_SUCCEEDED_EVENT === $event['type'];
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

			$this->schedule_process_event( $event );
		}
	}

	/**
	 * Process an event through ActionScheduler.
	 *
	 * @param int $post_id Event post ID.
	 *
	 * @return void
	 */
	public function process_event( $post_id ) {
		Logger::info( 'Start processing event: ' . $post_id );

		$post = get_post( $post_id );
		if ( ! $post ) {
			return; // Something deleted this post. Bail out.
		}

		try {
			$this->webhook_processing_service->process( maybe_unserialize( $post->post_content ) );
			wp_delete_post( $post_id ); // Remove post.
			Logger::info( 'Successfully processed delayed webhook event ' . $post_id );
		} catch ( Invalid_Webhook_Data_Exception $e ) {
			Logger::error( 'Failed processing event ' . $post_id . '. Reason: ' . $e->getMessage() );
		}
	}

	/**
	 * Schedule a job to process an event later.
	 *
	 * @param array $event Event data.
	 *
	 * @return void
	 */
	public function schedule_process_event( array $event ) {
		$current_post = get_posts(
			[
				'name'        => $event['id'],
				'post_type'   => self::POST_TYPE,
				'numberposts' => 1,
				'post_status' => 'any',
			]
		);

		if ( ! empty( $current_post ) ) {
			return; // Bail out since we already have this event stored.
		}
		if ( ! isset( $event['livemode'] ) ) {
			$event['livemode'] = WC_Payments::mode()->is_live();
		}
		$post_arr = [
			'post_title'   => $event['id'] . ' (' . $event['type'] . ')',
			'post_name'    => $event['id'],
			'post_type'    => self::POST_TYPE,
			'post_parent'  => $event['data']['object']['metadata']['order_id'] ?? 0,
			'post_content' => maybe_serialize( $event ),
		];

		$post_id = wp_insert_post( $post_arr );

		if ( is_wp_error( $post_id ) || 0 === $post_id ) {
			Logger::error( 'Could not store event in CPT. Processing it now. Event data: ' . var_export( $event, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
			$this->webhook_processing_service->process( $event );
			return;
		}

		// Replace this with switch case when there are more events.
		if ( self::PAYMENT_INTENT_SUCCEEDED_EVENT === $event['type'] ?? '' ) {
			$delayed_time = 2 * MINUTE_IN_SECONDS;
		} else {
			$delayed_time = 0;
		}
		$this->action_scheduler_service->schedule_job( strtotime( "+{$delayed_time} seconds" ), self::WEBHOOK_PROCESS_EVENT_ACTION, [ 'post_id' => $post_id ] );
		Logger::info( 'Successfully schedule a job to processing event: ' . $event['id'] . ' with post ID ' . $post_id );
	}

	/**
	 * Determines when event should be processed based on the event type.
	 *
	 * @param string $event_type Event type.
	 *
	 * @return string
	 */


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
	 * Clean the action scheduler logs.
	 *
	 * @param int                    $action_id Action id.
	 * @param ActionScheduler_Action $action    The action object.
	 *
	 * @return void
	 */
	private function maybe_cleanup_logs_after_processing( $action_id, $action ) {
		global $wpdb;

		// Ensure $action is an instance of ActionScheduler_Action.
		if ( ! $action instanceof ActionScheduler_Action ) {
			$action = ActionScheduler::store()->fetch_action( $action_id );
		}

		// Fetch the status of the action directly from the database.
		$status = $wpdb->get_var( $wpdb->prepare( "SELECT status FROM {$wpdb->prefix}actionscheduler_actions WHERE action_id = %d", $action_id ) );

		// Check if the action's hook matches and the status is "complete".
		if ( $action->get_hook() === self::WEBHOOK_PROCESS_EVENT_ACTION && 'complete' === $status ) {

			// Delete logs associated with the action ID.
			$wpdb->delete( "{$wpdb->prefix}actionscheduler_logs", [ 'action_id' => $action_id ] );
		}
	}

	/**
	 * Registers the service's post type.
	 */
	public function init() {
		register_post_type(
			self::POST_TYPE,
			[
				'public'       => false,
				'show_in_rest' => false,
				'can_export'   => false,
			]
		);
	}
}
