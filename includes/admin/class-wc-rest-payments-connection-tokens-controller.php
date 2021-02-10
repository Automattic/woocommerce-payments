<?php
/**
 * Class WC_REST_Payments_Connection_Tokens_Controller
 *
 * @package WooCommerce\Payments\Admin
 */

defined( 'ABSPATH' ) || exit;

/**
 * REST controller for terminal tokens.
 */
class WC_REST_Payments_Connection_Tokens_Controller extends WC_Payments_REST_Controller {

	/**
	 * Endpoint path.
	 *
	 * @var string
	 */
	protected $rest_base = 'payments/connection_tokens';

	/**
	 * Configure REST API routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/payments/connection_tokens',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'create_token' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
	}

	/**
	 * Create a connection token via API.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 */
	public function create_token( $request ) {
		$response = $this->forward_request( 'create_token', [ $request ] );

		// As an aid to mobile clients, tuck in the test_mode flag in the response returned to the request.
		if ( is_a( $response, 'WP_REST_Response' ) ) {
			if ( property_exists( $response, 'data' ) ) {
				if ( is_array( $response->data ) ) {
					$response->data['test_mode'] = WC_Payments::get_gateway()->is_in_test_mode();
				}
			}
		}

		return $response;
	}
}
