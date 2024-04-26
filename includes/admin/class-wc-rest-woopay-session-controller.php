<?php
/**
 * Class WC_REST_WooPay_Session_Controller
 *
 * @package WooCommerce\Payments\Admin
 */

defined( 'ABSPATH' ) || exit;

use WCPay\WooPay\WooPay_Session;
use Automattic\Jetpack\Connection\Rest_Authentication;
use Automattic\WooCommerce\StoreApi\Utilities\JsonWebToken;
use WCPay\Exceptions\Rest_Request_Exception;
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
			$payload = $this->validated_cart_token_payload( $request->get_header( 'cart_token' ) );
			$user_id = (int) $payload->user_id ?? null;

			if ( is_int( $user_id ) && $user_id > 0 ) {
				wp_set_current_user( $user_id );
			}

			// phpcs:ignore
			/**
			 * @psalm-suppress UndefinedClass
			 */
			$response = WooPay_Session::get_init_session_request( null, null, null, $request );

			return rest_ensure_response( $response );
		} catch ( Rest_Request_Exception $e ) {
			$error_code = $e->getCode() === 400 ? 'rest_invalid_param' : 'wcpay_server_error';
			$error      = new WP_Error( $error_code, $e->getMessage(), [ 'status' => $e->getCode() ] );

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
	 * Validates the cart token and returns its payload.
	 *
	 * @param string|null $cart_token The cart token to validate.
	 *
	 * @return object The validated cart token.
	 *
	 * @throws Rest_Request_Exception If the cart token is invalid, missing, or cannot be validated.
	 */
	public function validated_cart_token_payload( $cart_token ): object {
		if ( ! $cart_token ) {
			throw new Rest_Request_Exception( 'Missing cart token.', 400 );
		}

		if ( ! class_exists( JsonWebToken::class ) ) {
			throw new Rest_Request_Exception( 'Cannot validate cart token.', 500 );
		}

		if ( ! JsonWebToken::validate( $cart_token, '@' . wp_salt() ) ) {
			throw new Rest_Request_Exception( 'Invalid cart token.', 400 );
		}

		return JsonWebToken::get_parts( $cart_token )->payload;
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
