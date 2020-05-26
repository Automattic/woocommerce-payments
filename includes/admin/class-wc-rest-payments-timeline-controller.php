<?php
/**
 * Class WC_REST_Payments_Timeline_Controller
 *
 * @package WooCommerce\Payments\Admin
 */

defined( 'ABSPATH' ) || exit;

/**
 * REST controller for the timeline, which includes all events related to an intention.
 */
class WC_REST_Payments_Timeline_Controller extends WP_REST_Controller {

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
	protected $rest_base = 'payments/timeline';

	/**
	 * Client for making requests to the WooCommerce Payments API
	 *
	 * @var WC_Payments_API_Client
	 */
	private $api_client;

	/**
	 * WC_REST_Payments_Timeline_Controller constructor.
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
			'/' . $this->rest_base . '/(?P<intention_id>\w+)',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_timeline' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
	}

	/**
	 * Retrieve timeline to respond with via API.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 */
	public function get_timeline( $request ) {
		try {
			$intention_id = $request->get_params()['intention_id'];
			return $this->api_client->get_timeline( $intention_id );
		} catch ( WC_Payments_API_Exception $e ) {
			return rest_ensure_response( new WP_Error( 'wcpay_get_timeline', $e->getMessage() ) );
		}
	}

	/**
	 * Verify access.
	 */
	public function check_permission() {
		return current_user_can( 'manage_woocommerce' );
	}
}
