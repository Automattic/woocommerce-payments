<?php
/**
 * Class WC_REST_Payments_Disputes_Controller
 *
 * @package WooCommerce\Payments\Admin
 */

defined( 'ABSPATH' ) || exit;

/**
 * REST controller for disputes.
 */
class WC_REST_Payments_Disputes_Controller extends WP_REST_Controller {

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
	protected $rest_base = 'payments/disputes';

	/**
	 * Client for making requests to the WooCommerce Payments API
	 *
	 * @var WC_Payments_API_Client
	 */
	private $api_client;

	/**
	 * WC_REST_Payments_Disputes_Controller constructor.
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
			'/' . $this->rest_base . '/(?P<dispute_id>\w+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_dispute' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<dispute_id>\w+)',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'update_dispute' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);
	}

	/**
	 * Retrieve dispute to respond with via API.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 */
	public function get_dispute( $request ) {
		$dispute_id = $request->get_params()['dispute_id'];
		return rest_ensure_response( $this->api_client->get_dispute( $dispute_id ) );
	}

	/**
	 * Update and respond with dispute via API.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 */
	public function update_dispute( $request ) {
		$params   = $request->get_params();
		$response = $this->api_client->update_dispute(
			$params['dispute_id'],
			$params['evidence'],
			$params['submit']
		);

		return rest_ensure_response( $response );
	}

	/**
	 * Verify access.
	 */
	public function check_permission() {
		return current_user_can( 'manage_woocommerce' );
	}
}
