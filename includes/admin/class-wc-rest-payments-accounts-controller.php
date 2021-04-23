<?php
/**
 * Class WC_REST_Payments_Accounts_Controller
 *
 * @package WooCommerce\Payments\Admin
 */

defined( 'ABSPATH' ) || exit;

/**
 * REST controller for account details and status.
 */
class WC_REST_Payments_Accounts_Controller extends WC_Payments_REST_Controller {

	/**
	 * Endpoint path.
	 *
	 * @var string
	 */
	protected $rest_base = 'payments/accounts';

	/**
	 * Configure REST API routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/payments/accounts',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_account_data' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
	}

	/**
	 * Get accounts details via API.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 */
	public function get_account_data( $request ) {
		return WC_Payments::get_account_service()->get_cached_account_data();
	}
}
