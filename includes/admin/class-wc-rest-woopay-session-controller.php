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
				// POST is what an attested caller sends: the envelope travels in the body,
				// out of access logs, browser history and Referer headers. GET stays for a
				// caller that signs instead — WooPay sends one whenever it is told to keep
				// signing for this store, and that rollback only works if the route still
				// answers. See WooPay_Session::has_valid_request_signature().
				'methods'             => WP_REST_Server::READABLE . ', ' . WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'get_session_data' ],
				'permission_callback' => [ $this, 'check_permission' ],
				'args'                => [
					// Not required: an attested request carries the email inside the
					// envelope, and sending it in the clear as well would expose the
					// shopper's address for nothing. Still accepted, because a signed
					// request has no envelope to read it from.
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
	 * What it accepts instead is an attestation envelope, which proves WooPay composed the
	 * request without attaching a reusable credential to it. See
	 * `WooPay_Session::get_woopay_attestation()`.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return bool True if the request is from WooPay and carries proof of it.
	 */
	public function check_permission( WP_REST_Request $request ) {
		if ( ! WooPay_Session::is_request_from_woopay() ) {
			Logger::log( 'WooPay session route denied: the request does not identify as WooPay.' );

			return false;
		}

		// A signed request proves WooPay composed it just as an envelope does, and is what a
		// store receives while WooPay is told to keep signing for it.
		if ( WooPay_Session::has_valid_request_signature() ) {
			return true;
		}

		// Not the attested *email*: a guest shopper has no email to name, and the envelope
		// still proves the request came from WooPay.
		if ( null !== WooPay_Session::get_woopay_attestation( $request ) ) {
			return true;
		}

		// Which it was matters: a Cart-Token here is a caller using the wrong credential
		// rather than none, and `get_woopay_attestation()` has already said why an envelope
		// was refused if one was presented at all.
		Logger::log(
			WooPay_Session::is_valid_request_with_cart_token()
				? 'WooPay session route denied: a Cart-Token does not authorize this route, which needs a signature or an attestation.'
				: 'WooPay session route denied: no signature and no usable attestation.'
		);

		return false;
	}
}
