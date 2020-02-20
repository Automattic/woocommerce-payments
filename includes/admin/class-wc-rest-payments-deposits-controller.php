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
class WC_REST_Payments_Deposits_Controller extends WP_REST_Controller {

	/**
	 * Endpoint namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'wc/v3';

	/**
	 * Endpoint path.
	 *
	 * @var string
	 */
	protected $rest_base = 'payments/deposits';

	/**
	 * Client for making requests to the WooCommerce Payments API
	 *
	 * @var WC_Payments_API_Client
	 */
	private $api_client;

	/**
	 * WC_REST_Payments_Deposits_Controller constructor.
	 *
	 * @param WC_Payments_API_Client $api_client - WooCommerce Payments API client.
	 */
	public function __construct( WC_Payments_API_Client $api_client ) {
		$this->api_client = $api_client;
	}

	/**
	 * Configure REST API routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_deposits' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<deposit_id>\w+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_deposit' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
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
		return rest_ensure_response( $this->api_client->list_deposits( $page, $page_size ) );
	}

	/**
	 * Retrieve deposit to respond with via API.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 */
	public function get_deposit( $request ) {
		$deposit_id = $request->get_params()['deposit_id'];
		return rest_ensure_response( $this->api_client->get_deposit( $deposit_id ) );
	}

	/**
	 * Verify access.
	 */
	public function check_permission() {
		return current_user_can( 'manage_woocommerce' );
	}
}
