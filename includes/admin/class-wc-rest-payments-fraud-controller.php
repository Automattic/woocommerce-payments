<?php
/**
 * Class WC_REST_Payments_Fraud_Controller
 *
 * @package WooCommerce\Payments\Admin
 */

defined( 'ABSPATH' ) || exit;

/**
 * REST controller for fraud mitigation.
 */
class WC_REST_Payments_Fraud_Controller extends WC_Payments_REST_Controller {
	/**
	 * Endpoint path.
	 *
	 * @var string
	 */
	protected $rest_base = 'payments/fraud';

	/**
	 * Configure REST API routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/forter_token',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'send_forter_token' ],
				'permission_callback' => [ $this, 'check_permission' ],
				'args'                => [
					'token' => [
						'required' => true,
					],
				],
			]
		);
	}

	/**
	 * Forward token to Fraud Service.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 */
	public function send_forter_token( $request ) {
		$token = $request['token'];
		WC_Payments::get_fraud_service()->send_forter_token( $token );
	}

	/**
	 * Verify access.
	 */
	public function check_permission() {
		return current_user_can( 'manage_woocommerce' );
	}
}
