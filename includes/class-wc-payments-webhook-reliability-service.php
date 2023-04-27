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
	const POST_TYPE                          = 'wcpay-webhook';
	const TYPE_TAXONOMY                      = 'wcpay-webhook-type';
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
		return in_array(
			$event['type'],
			[
				'payment_intent.succeeded',
			],
			true
		);
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

		$post_arr = [
			'post_title'   => $event['id'] . ' (' . $event['type'] . ')',
			'post_name'    => $event['id'],
			'post_type'    => self::POST_TYPE,
			'post_content' => maybe_serialize( $event['data'] ),
			'menu_order'   => $event['livemode'] ? 1 : 0,
			'tax_input'    => [
				self::TYPE_TAXONOMY => [ $event['type'] ],
			],
		];

		switch ( $event['type'] ) {
			case 'payment_intent.succeeded':
				$post_arr['post_parent'] = $event['data']['object']['metadata']['order_id'];
				break;
		}

		$post_id = wp_insert_post( $post_arr );
		if ( is_wp_error( $post_id ) || 0 <= $post_id ) {
			Logger::error( 'Could not store event in CPT. Event ID: ' . $event['id'] );
		}
	}

	/**
	 * Converts a post to an array, which resembles an event from the server.
	 *
	 * @param WP_Post $post The post to convert.
	 * @return array
	 */
	protected function post_to_event( $post ) {
		$type = null;

		foreach ( wp_get_post_terms( $post->ID, self::TYPE_TAXONOMY ) as $term ) {
			$type = $term->name;
		}

		return [
			'id'       => $post->post_name,
			'type'     => $type,
			'livemode' => $post->menu_order > 0,
			'data'     => maybe_unserialize( $post->post_content ),
			'_post_id' => $post->ID,
		];
	}

	/**
	 * Loads and processes events.
	 *
	 * @param array $args Arguments for the `get_events` method.
	 * @return array[]    The processed events.
	 */
	public function load_and_process_events( $args ) {
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
	 *     @type string       $id    The ID of an existing evnet when querying a specific one.
	 *     @type int          $count Limit of the query, `-1` removes it. May cause performance issues. Defaults to 10.
	 *     @type int          $order The ID of an order, if the webhook is related to that order.
	 *     @type string|array $type  The type (string) or types (array) of events to query. Defaults to all.
	 *     @type bool         $live  Whether to load live or test mode events. Defaults to the current gateway mode.
	 * }
	 * @return array[] Stored events, which match the given criteria. See `->post_to_event()`.
	 */
	protected function get_events( $extra = null ) {
		$live = isset( $extra['live'] ) ? $extra['live'] : ! WC_Payments::get_gateway()->is_in_test_mode();
		$args = [
			'post_type'      => self::POST_TYPE,
			'post_status'    => 'any',
			'posts_per_page' => 10,
			'menu_order'     => $live ? 1 : 0,
		];

		if ( isset( $extra['id'] ) ) {
			$args['name'] = $extra['id'];
		}

		if ( isset( $extra['count'] ) ) {
			$args['posts_per_page'] = $extra['count'];
		}

		if ( isset( $extra['type'] ) ) {
			$args['tax_query'] = [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				[
					'taxonomy' => self::TYPE_TAXONOMY,
					'terms'    => $extra['type'],
					'field'    => 'name',
				],
			];
		}

		if ( isset( $extra['order'] ) ) {
			$args['post_parent'] = absint( $extra['order'] );
		}

		return array_map( [ $this, 'post_to_event' ], get_posts( $args ) );
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
			Logger::error( 'Failed processing event ' . $event_id . '. Reason: ' . $e->getMessage() );
		}

		// Immediately delete the event.
		wp_delete_post( $event['_post_id'], true );
	}
}
