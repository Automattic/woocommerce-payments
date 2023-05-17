<?php
/**
 * Class WC_REST_WooPay_Store_Data_Controller
 *
 * @package WooCommerce\Payments\Admin
 */

use WCPay\WooPay\WooPay_Utilities;

defined( 'ABSPATH' ) || exit;

/**
 * REST controller to get store data.
 */
class WC_REST_WooPay_Store_Data_Controller extends WP_REST_Controller {

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
	protected $rest_base = 'woopay/store_data';

	/**
	 * Configure REST API routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_store_data' ],
				'permission_callback' => [ $this, 'check_nonce' ],
			]
		);
	}

	/**
	 * Retrieve if a user exists by email address.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response
	 */
	public function get_store_data( WP_REST_Request $request ): WP_REST_Response {
		$woopay_utils = new WooPay_Utilities();

		return new WP_REST_Response( $woopay_utils->get_store_data( WC_Payments::get_account_service() ) );
	}

	/**
	 * Check whether a given store api nonce is valid.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 *
	 * @return \WP_Error|boolean
	 */
	public function check_nonce( WP_REST_Request $request ) {
		$is_nonce_valid = wp_verify_nonce( $request->get_param( 'cart_nonce' ), 'wc_store_api' );
		return $is_nonce_valid;
	}
}
