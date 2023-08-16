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
	const POST_TYPE                          = 'wcpay-webhook';
	const TYPE_TAXONOMY                      = 'wcpay-webhook-type';
	const LIVEMODE_TAXONOMY                  = 'wcpay-webhook-livemode';
	const CONTINUOUS_FETCH_FLAG_EVENTS_LIST  = 'has_more';
	const CONTINUOUS_FETCH_FLAG_ACCOUNT_DATA = 'has_more_failed_events';
	const WEBHOOK_FETCH_EVENTS_ACTION        = 'wcpay_webhook_fetch_events';
	const WEBHOOK_PROCESS_EVENT_ACTION       = 'wcpay_webhook_process_event';
	const WEBHOOK_PROCESS_EVENTS_ACTION      = 'wcpay_webhook_process_events';
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
	 * @param WC_Payments_API_Client                 $payments_api_client        WooCommerce Payments API client.
	 * @param WC_Payments_Action_Scheduler_Service   $action_scheduler_service   Wrapper for ActionScheduler service.
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

		add_action( 'woocommerce_payments_account_refreshed', [ $this, 'maybe_schedule_failed_webhook_events' ] );
		add_action( self::WEBHOOK_FETCH_EVENTS_ACTION, [ $this, 'schedule_processing_failed_webhook_events' ] );
		add_action( self::WEBHOOK_PROCESS_EVENT_ACTION, [ $this, 'process_event' ] );

		// Schedule the action if not already scheduled.
		add_action(
			'plugins_loaded',
			function() {
				if ( ! as_next_scheduled_action( self::WEBHOOK_PROCESS_EVENTS_ACTION ) ) {
					as_schedule_recurring_action( time(), 60, self::WEBHOOK_PROCESS_EVENTS_ACTION );
				}
			}
		);
		// Hook the scheduled action to the method.
		add_action( self::WEBHOOK_PROCESS_EVENTS_ACTION, [ $this, 'load_and_process_events' ] );
	}

	/**
	 * During the account data refresh, check the relevant flag to remaining failed events on the WooCommerce Payments server,
	 * and decide whether scheduling a job to fetch them.
	 *
	 * @param  mixed|array $account Account data retrieved from WooCommerce Payments server.
	 *
	 * @return void
	 */
	public function maybe_schedule_failed_webhook_events( $account ) {
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
	public function schedule_processing_failed_webhook_events() {
		try {
			$payload = $this->payments_api_client->get_failed_webhook_events();
		} catch ( API_Exception $e ) {
			Logger::error( 'Can not fetch failed events from the server. Error:' . $e->getMessage() );
			return;
		}

		// Schedules another fetch in case this batch does not contain all stored events.
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

			$this->store_event( $event );
		}
	}

	/**
	 * Process an event through ActionScheduler.
	 *
	 * @param  string $event_id Event ID.
	 * @deprecated This method (and the action in the constructor) should be removed in the next version.
	 *
	 * @return void
	 */
	public function process_event( string $event_id ) {
		Logger::info( 'Start processing event: ' . $event_id );

		// Use md5 to overcome the limit of transient name (172 characters) while Stripe event ID can be up to 255.
		$transient_name = 'wcpay_failed_event_' . md5( $event_id );

		$data       = get_transient( $transient_name );
		$event_data = false === $data ? null : $data;

		delete_transient( $transient_name );

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
	 * Schedule a job to fetch failed events.
	 *
	 * @return void
	 */
	private function schedule_fetch_events() {
		$this->action_scheduler_service->schedule_job( time(), self::WEBHOOK_FETCH_EVENTS_ACTION );
		Logger::info( 'Successfully schedule a job to fetch failed events from the server.' );
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

		register_taxonomy(
			self::TYPE_TAXONOMY,
			self::POST_TYPE,
			[
				'public'       => false,
				'show_in_rest' => false,
			]
		);

		register_taxonomy(
			self::LIVEMODE_TAXONOMY,
			self::POST_TYPE,
			[
				'public'       => false,
				'show_in_rest' => false,
			]
		);
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
	public function should_delay_event( array $event ) {

		// If the payment method is SEPA, return false immediately.
		if ( isset( $event['data']['object']['payment_method_details']['type'] ) && Sepa_Payment_Method::PAYMENT_METHOD_STRIPE_ID === $event['data']['object']['payment_method_details']['type'] ) {
			return false;
		}

		// Could be replaced with array of events later on.
		return self::PAYMENT_INTENT_SUCCEEDED_EVENT === $event['type'];
	}

	/**
	 * Stores an event to be processed later.
	 *
	 * @param array $event The event as it was received from the server.
	 */
	public function store_event( array $event ) {
		// Retry mechanisms might try to store an existing webhook.
		$args     = [ 'id' => $event['id'] ];
		$existing = $this->get_events( $args );
		if ( ! empty( $existing ) ) {
			return;
		}
		if ( isset( $event['livemode'] ) ) {
			$livemode = $event['livemode'] ? 1 : 0;
		} else {
			$livemode = WC_Payments::mode()->is_live() ? 1 : 0;
		}

		$post_arr = [
			'post_title'   => $event['id'] . ' (' . $event['type'] . ')',
			'post_name'    => $event['id'],
			'post_type'    => self::POST_TYPE,
			'post_parent'  => $event['data']['object']['metadata']['order_id'] ?? 0,
			'post_content' => maybe_serialize( $event['data'] ),
			'post_date'    => $this->get_event_processing_time_from_event_type( $event['type'] ?? '' ),
		];

		$post_id = wp_insert_post( $post_arr );

		wp_set_post_terms( $post_id, $event['type'], self::TYPE_TAXONOMY );
		wp_set_post_terms( $post_id, $livemode, self::LIVEMODE_TAXONOMY );

		if ( is_wp_error( $post_id ) || 0 === $post_id ) {
			Logger::error( 'Could not store event in CPT. Event data: ' . var_export( $event, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
		}
	}

	/**
	 * Loads and processes events.
	 *
	 * @param array $args Arguments for the `get_events` method.
	 *
	 * @return array[]    The processed events.
	 */
	public function load_and_process_events( array $args = [] ) {
		$events = $this->get_events( $args );

		foreach ( $events as $event ) {
			$this->process( $event );
		}

		return $events;
	}

	/**
	 * Loads stored events from the database.
	 *
	 * @param array $extra {
	 *     Additional arguments for the query (Optional).
	 *
	 *     @type string       $id    The ID of an existing event when querying a specific one.
	 *     @type int          $count Limit of the query, `-1` removes it. May cause performance issues. Defaults to 10.
	 *     @type int          $order The ID of an order, if the webhook is related to that order.
	 *     @type string|array $type  The type (string) or types (array) of events to query. Defaults to all.
	 *     @type bool         $live  Whether to load live or test mode events. Defaults to the current gateway mode.
	 * }
	 * @return array[] Stored events, which match the given criteria.
	 */
	public function get_events( array $extra = [] ): array {
		$live = $extra['live'] ?? WC_Payments::mode()->is_live();
		$args = [
			'post_type'      => self::POST_TYPE,
			'post_status'    => 'any',
			'posts_per_page' => 10,
			'orderby'        => 'date',
			'order'          => 'ASC',
		];
		// Use tax_query to differentiate between live and test events.
		$args['tax_query'] = [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			[
				'taxonomy' => self::LIVEMODE_TAXONOMY,
				'terms'    => $live ? 1 : 0,
				'field'    => 'name',
			],
		];

		if ( isset( $extra['id'] ) ) {
			$args['name'] = $extra['id'];
		}

		if ( isset( $extra['count'] ) ) {
			$args['posts_per_page'] = $extra['count'];
		}

		if ( isset( $extra['type'] ) ) {
			$args['tax_query'][] = [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				[
					'taxonomy' => self::TYPE_TAXONOMY,
					'terms'    => $extra['type'],
					'field'    => 'name',
				],
			];

			$args['tax_query']['relation'] = 'AND';
		}

		if ( isset( $extra['order'] ) ) {
			$args['post_parent'] = absint( $extra['order'] );
		}
		$posts        = get_posts( $args );
		$events       = [];
		$current_time = new DateTime();

		foreach ( $posts as $post ) {

			$post_date = new DateTime( $post->post_date );

			if ( $post_date > $current_time ) {
				break; // Data is already sorted by date and all other posts will be for future, so we will break from loop.
			}

			$terms          = wp_get_post_terms( $post->ID, self::TYPE_TAXONOMY );
			$livemode_terms = wp_get_post_terms( $post->ID, self::LIVEMODE_TAXONOMY );

			$events[] = [
				'id'       => $post->post_name,
				'type'     => ! empty( $terms ) ? $terms[0]->name : null,
				'livemode' => ! empty( $livemode_terms ) ? ( (int) $livemode_terms[0]->name ) : null,
				'data'     => maybe_unserialize( $post->post_content ),
				'_post_id' => $post->ID,
			];
		}

		return $events;
	}

	/**
	 * Processes an event, and removes it from the storage once processed.
	 *
	 * @param array $event The event, formatted by `post_to_event()`.
	 */
	protected function process( $event ) {
		// Process the event.
		try {
			$this->webhook_processing_service->process( $event );
		} catch ( Exception $e ) {
			Logger::error( 'Failed processing event ' . $event['id'] . '. Reason: ' . $e->getMessage() );
		}

		// Immediately delete the event.
		if ( isset( $event['_post_id'] ) ) {
			wp_delete_post( $event['_post_id'], true );
		}
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

		// Check if the action's hook matches.
		if ( $action->get_hook() === self::WEBHOOK_PROCESS_EVENTS_ACTION ) {

			// Delete logs associated with the action ID.
			$wpdb->delete( "{$wpdb->prefix}actionscheduler_logs", [ 'action_id' => $action_id ] );
		}
	}

	/**
	 * Determines when event should be processed based on the event type.
	 *
	 * @param string $event_type Event type.
	 *
	 * @return string
	 */
	private function get_event_processing_time_from_event_type( string $event_type ): string {
		$date = new DateTime(); // Default is current date time.
		if ( self::PAYMENT_INTENT_SUCCEEDED_EVENT === $event_type ) {
			$date->add( new DateInterval( 'PT2M' ) );  // Delay by 2 minutes.

		}
		return $date->format( 'Y-m-d H:i:s' );
	}
}
