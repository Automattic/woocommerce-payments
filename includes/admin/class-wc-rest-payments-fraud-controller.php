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
	public function register_routes() {}

	/**
	 * Verify access.
	 */
	public function check_permission() {
		return current_user_can( 'manage_woocommerce' );
	}
}
