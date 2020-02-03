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
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<transaction_id>\w+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_transaction' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);
	}

	/**
	 * Retrieve transactions to respond with via API.
	 */
	public function get_transactions() {
		// phpcs:disable WordPress.Security.NonceVerification.NoNonceVerification
		$page      = isset( $_GET['page'] ) ? intval( $_GET['page'] ) : 0;
		$page_size = isset( $_GET['pagesize'] ) ? intval( $_GET['pagesize'] ) : 25;
		// phpcs:enable WordPress.Security.NonceVerification.NoNonceVerification

		return rest_ensure_response( $this->api_client->list_transactions( $page, $page_size ) );
	}

	/**
	 * Retrieve transaction to respond with via API.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 */
	public function get_transaction( $request ) {
		$transaction_id = $request->get_params()['transaction_id'];
		return rest_ensure_response( $this->api_client->get_transaction( $transaction_id ) );
	}

	/**
	 * Retrieve transactions summary to respond with via API.
	 */
	public function get_transactions_summary() {
		return rest_ensure_response( $this->api_client->get_transactions_summary() );
	}

	/**
	 * Verify access.
	 */
	public function check_permission() {
		return current_user_can( 'manage_woocommerce' );
	}
}
