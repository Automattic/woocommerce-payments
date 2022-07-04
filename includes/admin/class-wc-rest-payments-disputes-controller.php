<?php
/**
 * Class WC_REST_Payments_Disputes_Controller
 *
 * @package WooCommerce\Payments\Admin
 */

defined( 'ABSPATH' ) || exit;

use WCPay\Database_Cache;

/**
 * REST controller for disputes.
 */
class WC_REST_Payments_Disputes_Controller extends WC_Payments_REST_Controller {

	/**
	 * Database_Cache instance.
	 *
	 * @var Database_Cache
	 */
	private $database_cache;

	/**
	 * Endpoint path.
	 *
	 * @var string
	 */
	protected $rest_base = 'payments/disputes';

	/**
	 * WC_REST_Payments_Disputes_Controller constructor.
	 *
	 * @param WC_Payments_API_Client $api_client     WooCommerce Payments API client.
	 * @param Database_Cache         $database_cache Database_Cache instance.
	 */
	public function __construct( WC_Payments_API_Client $api_client, Database_Cache $database_cache ) {
		$this->api_client     = $api_client;
		$this->database_cache = $database_cache;
	}

	/**
	 * Configure REST API routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_disputes' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/summary',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_disputes_summary' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/download',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'get_disputes_export' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/status_counts',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_dispute_status_counts' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<dispute_id>\w+)',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_dispute' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<dispute_id>\w+)',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'update_dispute' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<dispute_id>\w+)/close',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'close_dispute' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
	}

	/**
	 * Retrieve disputes to respond with via API.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 */
	public function get_disputes( WP_REST_Request $request ) {
		$page      = (int) $request->get_param( 'page' );
		$page_size = (int) $request->get_param( 'pagesize' );
		$sort      = $request->get_param( 'sort' ) ?? 'created';
		$direction = $request->get_param( 'direction' ) ?? 'desc';
		$filters   = $this->get_disputes_filters( $request );

		return $this->forward_request( 'list_disputes', [ $page, $page_size, $sort, $direction, $filters ] );
	}

	/**
	 * Retrieve transactions summary to respond with via API.
	 *
	 * @param  WP_REST_Request $request Request data.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_disputes_summary( WP_REST_Request $request ) {
		$filters = $this->get_disputes_filters( $request );
		return $this->forward_request( 'get_disputes_summary', [ $filters ] );
	}

	/**
	 * Retrieve dispute to respond with via API.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 */
	public function get_dispute( $request ) {
		$dispute_id = $request->get_param( 'dispute_id' );
		return $this->forward_request( 'get_dispute', [ $dispute_id ] );
	}

	/**
	 * Update and respond with dispute via API.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 */
	public function update_dispute( $request ) {
		$params = $request->get_params();
		return $this->forward_request(
			'update_dispute',
			[
				$params['dispute_id'],
				$params['evidence'],
				$params['submit'],
				$params['metadata'],
			]
		);
	}

	/**
	 * Close and respond with dispute via API.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 */
	public function close_dispute( $request ) {
		$dispute_id = $request->get_param( 'dispute_id' );
		return $this->forward_request( 'close_dispute', [ $dispute_id ] );
	}

	/**
	 * Initiate disputes export via API.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 */
	public function get_disputes_export( $request ) {
		$user_email = $request->get_param( 'user_email' );
		$filters    = $this->get_disputes_filters( $request );

		return $this->forward_request( 'get_disputes_export', [ $filters, $user_email ] );
	}

	/**
	 * Retrieve a list of dispute statuses and a total count for each status via API.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_REST_Response The response containing the dispute status counts.
	 */
	public function get_dispute_status_counts( WP_REST_Request $request ) {
		$statuses          = $request->get_param( 'statuses' );
		$possible_statuses = [
			'warning_needs_response',
			'warning_under_review',
			'warning_closed',
			'needs_response',
			'under_review',
			'charge_refunded',
			'won',
			'lost',
		];
		if ( ! $statuses ) {
			$statuses = $possible_statuses;
		}
		$statuses = array_filter(
			$statuses,
			function( $status ) use ( $possible_statuses ) {
				return in_array( $status, $possible_statuses, true );
			}
		);

		$disputes_status_counts = $this->database_cache->get_or_add(
			Database_Cache::DISPUTE_STATUS_COUNTS_KEY,
			[ $this->api_client, 'get_dispute_status_counts' ],
			// We'll consider all array values to be valid as the cache is only invalidated when it is deleted or it expires.
			'is_array'
		);

		if ( empty( $disputes_status_counts ) ) {
			$disputes_status_counts = new stdClass();
		}

		$disputes_status_counts = array_intersect_key( $disputes_status_counts, array_flip( $statuses ) );

		return rest_ensure_response( $disputes_status_counts );
	}

	/**
	 * Extract disputes filters from request
	 * The reason to map the filter properties is to keep consitency between the front-end models.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 */
	private function get_disputes_filters( $request ) {
		return array_filter(
			[
				'match'           => $request->get_param( 'match' ),
				'currency_is'     => $request->get_param( 'store_currency_is' ),
				'created_before'  => $request->get_param( 'date_before' ),
				'created_after'   => $request->get_param( 'date_after' ),
				'created_between' => $request->get_param( 'date_between' ),
				'search'          => $request->get_param( 'search' ),
				'status_is'       => $request->get_param( 'status_is' ),
				'status_is_not'   => $request->get_param( 'status_is_not' ),
			],
			static function ( $filter ) {
				return null !== $filter;
			}
		);
	}
}
