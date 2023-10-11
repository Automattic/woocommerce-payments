<?php
/**
 * Class WC_REST_Payments_Deposits_Controller
 *
 * @package WooCommerce\Payments\Admin
 */

use WCPay\Core\Server\Request;
use WCPay\Core\Server\Request\List_Deposits;

defined( 'ABSPATH' ) || exit;

/**
 * REST controller for deposits.
 */
class WC_REST_Payments_Deposits_Controller extends WC_Payments_REST_Controller {

	/**
	 * Endpoint path.
	 *
	 * @var string
	 */
	protected $rest_base = 'payments/deposits';

	/**
	 * Configure REST API routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_deposits' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/summary',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_deposits_summary' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/overview',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_deposits_overview' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/download',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'get_deposits_export' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<deposit_id>\w+)',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_deposit' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/overview-all',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_all_deposits_overviews' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'manual_deposit' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
	}

	/**
	 * Retrieve deposits to respond with via API.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 */
	public function get_deposits( $request ) {
		$wcpay_request = List_Deposits::from_rest_request( $request );

		return $wcpay_request->handle_rest_request();
	}

	/**
	 * Retrieve deposits summary to respond with via API.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 */
	public function get_deposits_summary( $request ) {
		$filters = $this->get_deposits_filters( $request );
		return $this->forward_request( 'get_deposits_summary', [ $filters ] );
	}

	/**
	 * Retrieve overview of deposits to respond with via API.
	 */
	public function get_deposits_overview() {
		$request = Request::get( WC_Payments_API_Client::DEPOSITS_API . '/overview' );
		$request->assign_hook( 'wcpay_get_deposits_overview' );
		return $request->handle_rest_request();
	}

	/**
	 * Retrieve an overview of all deposits from the API.
	 */
	public function get_all_deposits_overviews() {
		$request = Request::get( WC_Payments_API_Client::DEPOSITS_API . '/overview-all' );
		$request->assign_hook( 'wcpay_get_all_deposits_overviews' );
		return $request->handle_rest_request();
	}

	/**
	 * Retrieve deposit to respond with via API.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 */
	public function get_deposit( $request ) {
		$deposit_id    = $request->get_param( 'deposit_id' );
		$wcpay_request = Request::get( WC_Payments_API_Client::DEPOSITS_API, $deposit_id );
		$wcpay_request->assign_hook( 'wcpay_get_deposit' );
		return $wcpay_request->handle_rest_request();
	}

	/**
	 * Initiate deposits export via API.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 */
	public function get_deposits_export( $request ) {
		$user_email = $request->get_param( 'user_email' );
		$filters    = $this->get_deposits_filters( $request );

		return $this->forward_request( 'get_deposits_export', [ $filters, $user_email ] );
	}

	/**
	 * Extract deposits filters from request
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 */
	private function get_deposits_filters( $request ) {
		return array_filter(
			[
				'match'             => $request->get_param( 'match' ),
				'store_currency_is' => $request->get_param( 'store_currency_is' ),
				'date_before'       => $request->get_param( 'date_before' ),
				'date_after'        => $request->get_param( 'date_after' ),
				'date_between'      => $request->get_param( 'date_between' ),
				'status_is'         => $request->get_param( 'status_is' ),
				'status_is_not'     => $request->get_param( 'status_is_not' ),
			],
			static function ( $filter ) {
				return null !== $filter;
			}
		);
	}

	/**
	 * Trigger a manual deposit.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 */
	public function manual_deposit( $request ) {
		$params = $request->get_params();
		return $this->forward_request( 'manual_deposit', [ $params['type'], $params['transaction_ids'] ] );
	}
}
