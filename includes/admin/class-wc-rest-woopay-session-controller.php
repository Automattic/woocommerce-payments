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
				// POST only: the envelope travels in the body rather than a URL that reaches
				// access logs, browser history and Referer headers, and the route was never
				// idempotent anyway — reaching it creates a Stripe customer. The email rides
				// inside the envelope, so there is no query arg to declare either. See
				// WooPay_Session::get_woopay_attestation().
				'methods'             => WP_REST_Server::CREATABLE,
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
	 * What it accepts instead is an attestation envelope, which proves WooPay composed the
	 * request without attaching a reusable credential to it. See
	 * `WooPay_Session::get_woopay_attestation()` and WOOPAY-463.
	 *
	 * @return bool True if the request is from WooPay and carries proof of it.
	 */
	public function check_permission() {
		if ( ! $this->is_request_from_woopay() ) {
			Logger::log( 'WooPay session route denied: the request does not identify as WooPay.' );

			return false;
		}

		// Not the attested *email*: a guest shopper has no email to name, and the envelope
		// still proves the request came from WooPay.
		if ( null !== WooPay_Session::get_woopay_attestation() ) {
			return true;
		}

		// Which it was matters: a Cart-Token here is a caller using the wrong credential
		// rather than none, and `get_woopay_attestation()` has already said why an envelope
		// was refused if one was presented at all.
		Logger::log(
			WooPay_Session::AUTH_CART_TOKEN === WooPay_Session::get_request_auth_level()
				? 'WooPay session route denied: a Cart-Token does not authorize this route, which needs an attestation.'
				: 'WooPay session route denied: no usable attestation.'
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
