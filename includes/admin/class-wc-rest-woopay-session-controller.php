<?php
/**
 * Class WC_REST_WooPay_Adapted_Extensions_Controller
 *
 * @package WooCommerce\Payments\Admin
 *
 *
 * NOTE: This API endpoint is never registered and not available for use.
 * It is only available here as a temporary workaround for the issue described in
 * https://github.com/Automattic/woocommerce-payments/issues/6304
 *
 * This file can be removed once the issue is resolved.
 */

defined( 'ABSPATH' ) || exit;

use WCPay\WooPay\WooPay_Session;

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
		// TODO: We need to give this proper permission checks, required fields, etc.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_session_data' ],
				'permission_callback' => '__return_true',
			]
		);
	}

	/**
	 * Retrieve WooPay session data.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response
	 */
	public function get_session_data( WP_REST_Request $request ): WP_REST_Response {
		
		$response = WooPay_Session::get_init_session_request();
		// This was needed as the preloaded requests were not honoring the cart token and so were empty carts.
		// It would be ideal to get this to successfully preload the cart data so WooPay doesn't need to make 
		// a separate request to get the cart data.
		unset( $response['preloaded_requests'] );

		return rest_ensure_response( $response );
	}
}

