<?php
/**
 * Class WC_REST_WooPay_Session_Controller
 *
 * @package WooCommerce\Payments\Admin
 */

defined( 'ABSPATH' ) || exit;

use WCPay\WooPay\WooPay_Session;
use Automattic\Jetpack\Connection\Rest_Authentication;
use WCPay\Logger;

/**
 * REST controller to check get WooPay extension data for user.
 */
class WC_REST_WooPay_Session_Controller extends WP_REST_Controller {

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
	protected $rest_base = 'woopay/session';

	/**
	 * Configure REST API routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_session_data' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
	}

	/**
	 * Retrieve WooPay session data.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_Error|WP_REST_Response The initial session request data.
	 */
	public function get_session_data( WP_REST_Request $request ): WP_REST_Response {
		try {
			// phpcs:ignore
			/**
			 * @psalm-suppress UndefinedClass
			 */
			$response = WooPay_Session::get_init_session_request( null, null, null, $request );

			return rest_ensure_response( $response );
		} catch ( Exception $e ) {
			$error = new WP_Error( 'wcpay_server_error', $e->getMessage(), [ 'status' => 400 ] );
			Logger::log( 'Error validating cart token from WooPay request: ' . $e->getMessage() );

			return rest_convert_error_to_response( $error );
		}
	}

	/**
	 * Check permission confirms that the request is from WooPay.
	 *
	 * @return bool True if request is from WooPay and has a valid signature.
	 */
	public function check_permission() {
		return $this->is_request_from_woopay() && $this->has_valid_request_signature();
	}

	/**
	 * Returns true if the request that's currently being processed is signed with the blog token.
	 *
	 * @return bool True if the request signature is valid.
	 */
	private function has_valid_request_signature(): bool {
		return apply_filters( 'wcpay_woopay_is_signed_with_blog_token', Rest_Authentication::is_signed_with_blog_token() );
	}

	/**
	 * Returns true if the request that's currently being processed is from WooPay, false
	 * otherwise.
	 *
	 * @return bool True if request is from WooPay.
	 */
	private function is_request_from_woopay(): bool {
		return isset( $_SERVER['HTTP_USER_AGENT'] ) && 'WooPay' === $_SERVER['HTTP_USER_AGENT'];
	}
}
