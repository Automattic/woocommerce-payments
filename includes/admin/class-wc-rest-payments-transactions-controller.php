<?php
/**
 * Class WC_REST_Payments_Transactions_Controller
 *
 * @package WooCommerce\Payments\Admin
 */

defined( 'ABSPATH' ) || exit;

/**
 * REST controller for transactions.
 */
class WC_REST_Payments_Transactions_Controller extends WP_REST_Controller {

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
	protected $rest_base = 'payments/transactions';

	/**
	 * Client for making requests to the WooCommerce Payments API
	 *
	 * @var WC_Payments_API_Client
	 */
	private $api_client;

	/**
	 * WC_REST_Payments_Transactions_Controller constructor.
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
				'callback'            => array( $this, 'get_transactions' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/summary',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_transactions_summary' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);
	}

	/**
	 * Retrieve transactions summary to respond with via API.
	 */
	public function get_transactions_summary() {
		// Temporary code returning mock data for now.
		// TODO: Should be replaced with a real API call at a later date.
		// Note: monetary value is currently in US cents to avoid floating point numbers.
		return rest_ensure_response(
			array(
				'number_of_transactions' => 123,
				'total'                  => 12400,
				'fees'                   => 12500,
				'net'                    => 12600,
			)
		);
	}

	/**
	 * Retrieve transactions to respond with via API.
	 */
	public function get_transactions() {
		return rest_ensure_response( $this->api_client->list_transactions() );
	}

	/**
	 * Verify access.
	 */
	public function check_permission() {
		return current_user_can( 'manage_woocommerce' );
	}
}
