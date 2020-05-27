<?php
/**
 * Class WC_REST_Payments_Deposits_Controller
 *
 * @package WooCommerce\Payments\Admin
 */

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
			'/' . $this->rest_base . '/overview',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_deposits_overview' ],
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
	}

	/**
	 * Retrieve deposits to respond with via API.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 */
	public function get_deposits( $request ) {
		$page      = intval( $request->get_params()['page'] );
		$page_size = intval( $request->get_params()['pagesize'] );
		return $this->forward_request( 'list_deposits', [ $page, $page_size ] );
	}

	/**
	 * Retrieve overview of deposits to respond with via API.
	 */
	public function get_deposits_overview() {
		return $this->forward_request( 'get_deposits_overview', [] );
	}

	/**
	 * Retrieve deposit to respond with via API.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 */
	public function get_deposit( $request ) {
		$deposit_id = $request->get_params()['deposit_id'];
		return $this->forward_request( 'get_deposit', [ $deposit_id ] );
	}
}
