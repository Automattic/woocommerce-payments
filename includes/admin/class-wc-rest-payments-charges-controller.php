<?php
/**
 * Class WC_REST_Payments_Charges_Controller
 *
 * @package WooCommerce\Payments\Admin
 */

use WCPay\Exceptions\API_Exception;

defined( 'ABSPATH' ) || exit;

/**
 * REST controller for charges.
 */
class WC_REST_Payments_Charges_Controller extends WC_Payments_REST_Controller {

	/**
	 * Endpoint path.
	 *
	 * @var string
	 */
	protected $rest_base = 'payments/charges';

	/**
	 * Configure REST API routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<charge_id>\w+)',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_charge' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
	}

	/**
	 * Retrieve charge to respond with via API.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 */
	public function get_charge( $request ) {
		$charge_id = $request->get_param( 'charge_id' );

		try {
			$charge = $this->api_client->get_charge( $charge_id );
		} catch ( API_Exception $e ) {
			return rest_ensure_response( new WP_Error( 'wcpay_get_charge', $e->getMessage() ) );
		}

		return rest_ensure_response( $charge );
	}
}
