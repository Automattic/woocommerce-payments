<?php
/**
 * Class WC_REST_WooPay_Session_Controller
 *
 * @package WooCommerce\Payments\Admin
 */

defined( 'ABSPATH' ) || exit;

use WCPay\WooPay\WooPay_Session;
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
	protected $namespace = 'payments/woopay';

	/**
	 * Endpoint path.
	 *
	 * @var string
	 */
	protected $rest_base = 'session';

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
				'args'                => [
					// Not required: a caller that attests to the email in an encrypted
					// envelope has no reason to also send it in the clear. Kept accepted so
					// WooPay versions that only send this arg keep working.
					'email' => [
						'type'     => 'string',
						'format'   => 'email',
						'required' => false,
					],
				],
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
	public function get_session_data( WP_REST_Request $request ): object {
		try {
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
	 * Deliberately stricter than the proxied Store API traffic, which accepts a Cart-Token.
	 * This is not proxied shopper traffic: the response carries the store's own session
	 * material, and reaching it creates a Stripe customer as a side effect. A Cart-Token
	 * only establishes that the caller holds a cart, which every shopper holds for their
	 * own, so it does not establish enough here.
	 *
	 * What it accepts instead is proof that WooPay composed the request — either the
	 * legacy blog token signature, or an attestation envelope, which establishes the same
	 * key without attaching a reusable credential to the request. See
	 * `WooPay_Session::get_woopay_attestation()` and WOOPAY-463.
	 *
	 * @return bool True if the request is from WooPay and carries proof of it.
	 */
	public function check_permission() {
		if ( ! $this->is_request_from_woopay() ) {
			Logger::log( 'WooPay session route denied: the request does not identify as WooPay.' );

			return false;
		}

		$auth_level = WooPay_Session::get_request_auth_level();

		if ( WooPay_Session::AUTH_BLOG_TOKEN === $auth_level ) {
			return true;
		}

		// Not the attested *email*: a guest shopper has no email to name, and the envelope
		// still proves the request came from WooPay.
		if ( null !== WooPay_Session::get_woopay_attestation() ) {
			return true;
		}

		// Which of the two it was matters: a Cart-Token here is a caller using the wrong
		// credential rather than none, and `get_woopay_attestation()` has already said why
		// an envelope was refused if one was presented at all.
		Logger::log(
			WooPay_Session::AUTH_CART_TOKEN === $auth_level
				? 'WooPay session route denied: a Cart-Token does not authorize this route, which needs a signature or an attestation.'
				: 'WooPay session route denied: no blog token signature and no usable attestation.'
		);

		return false;
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
