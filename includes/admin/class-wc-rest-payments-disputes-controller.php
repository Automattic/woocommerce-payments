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
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_disputes' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);
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

		register_rest_route(
			$this->namespace,
			'/payments/file',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'upload_evidence' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<dispute_id>\w+)/close',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'close_dispute' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);
	}

	/**
	 * Retrieve disputes to respond with via API.
	 */
	public function get_disputes() {
		return $this->forward_request( 'list_disputes', [] );
	}

	/**
	 * Retrieve dispute to respond with via API.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 */
	public function get_dispute( $request ) {
		$dispute_id = $request->get_params()['dispute_id'];
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
	 * Create file and respond with file object via API.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 */
	public function upload_evidence( $request ) {
		try {
			$response = $this->api_client->upload_evidence( $request );
			return rest_ensure_response( $response );
		} catch ( WC_Payments_API_Exception $e ) {
			$http_code = $e->get_http_code();
			// TODO: Update when more informative errors are sent by the server.
			$message = 400 === $http_code
				? $e->getMessage()
				: __( 'Upload failed.', 'woocommerce-payments' );
			return new WP_Error( $http_code, $message );
		}
	}

	/**
	 * Close and respond with dispute via API.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 */
	public function close_dispute( $request ) {
		$dispute_id = $request->get_params()['dispute_id'];
		return $this->forward_request( 'close_dispute', [ $dispute_id ] );
	}
}
