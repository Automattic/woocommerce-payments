<?php
/**
 * Class WC_REST_Payments_Charges_Controller
 *
 * @package WooCommerce\Payments\Admin
 */

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
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_charge' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);
	}

	/**
	 * Retrieve charge to respond with via API.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 */
	public function get_charge( $request ) {
		$charge_id = $request->get_params()['charge_id'];
		return $this->forward_request( 'get_charge', [ $charge_id ] );
	}
}
