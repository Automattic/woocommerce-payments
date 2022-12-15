<?php
/**
 * Class WC_REST_Payments_Disputes_Controller
 *
 * @package WooCommerce\Payments\Admin
 */

use WCPay\Core\Server\Request\List_Disputes;

defined( 'ABSPATH' ) || exit;

/**
 * REST controller for disputes.
 */
class WC_REST_Payments_Disputes_Controller extends WC_Payments_REST_Controller {

	/**
	 * Endpoint path.
	 *
	 * @var string
	 */
	protected $rest_base = 'payments/disputes';

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
		$wcpay_request = List_Disputes::create();
		$wcpay_request->set_page( (int) $request->get_param( 'page' ) );
		$wcpay_request->set_page_size( (int) $request->get_param( 'pagesize' ) );
		$wcpay_request->set_sort_by( $request->get_param( 'sort' ) ?? 'created' );
		$wcpay_request->set_sort_direction( $request->get_param( 'direction' ) ?? 'desc' );
		$wcpay_request->set_filters( $this->get_disputes_filters( $request ) );

		return $wcpay_request->handle_rest_request( 'wcpay_list_disputes_request', $request );
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
