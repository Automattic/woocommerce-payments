<?php
/**
 * Class WC_REST_Payments_Locations_Controller
 *
 * @package WooCommerce\Payments\Admin
 */

defined( 'ABSPATH' ) || exit;

/**
 * REST controller for account details and status.
 */
class WC_REST_Payments_Locations_Controller extends WC_Payments_REST_Controller {

	/**
	 * Endpoint path.
	 *
	 * @var string
	 */
	protected $rest_base = 'payments/location';

	/**
	 * Configure REST API routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/payments/location',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_location_data' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
	}

	/**
	 * Get account details via API.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_location_data( $request ) {
		$location = [
					'id' => 'tml_ESCNWwpwlfv5JB',
					'object'     => 'terminal.location',
					'display_name'=> 'Hotel Plaza',
					];

		return rest_ensure_response( $location );
	}
}
