<?php
/**
 * Class WC_REST_Payments_Orders_Controller
 *
 * @package WooCommerce\Payments\Admin
 */

defined( 'ABSPATH' ) || exit;

/**
 * REST controller for order processing.
 */
class WC_REST_Payments_Fraud_Outcomes_Controller extends WC_Payments_REST_Controller {

	/**
	 * Endpoint path.
	 *
	 * @var string
	 */
	protected $rest_base = 'payments/fraud_outcomes';

	/**
	 * Configure REST API routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\w+)/latest',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_latest_fraud_outcome' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
	}

	/**
	 * Retrieve charge to respond with via API.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 */
	public function get_latest_fraud_outcome( $request ) {
		$id = $request->get_param( 'id' );

		return $this->forward_request( 'get_latest_fraud_outcome', [ $id ] );
	}
}
