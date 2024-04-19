<?php
/**
 * Class WC_REST_Payments_Reporting_Controller
 *
 * @package WooCommerce\Payments\Admin
 */

use WCPay\Core\Server\Request\Get_Reporting_Payment_Activity;

defined( 'ABSPATH' ) || exit;

/**
 * REST controller for customers.
 */
class WC_REST_Payments_Reporting_Controller extends WC_Payments_REST_Controller {

	/**
	 * Endpoint path.
	 *
	 * @var string
	 */
	protected $rest_base = 'payments/reporting';

	/**
	 * Configure REST API routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/payment_activity',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_payment_activity' ],
					'permission_callback' => [ $this, 'check_permission' ],
				],
			]
		);
	}

	/**
	 * Retrieve the Payment Activity data.
	 *
	 * @param WP_REST_Request $request The request.
	 */
	public function get_payment_activity( $request ) {
		$wcpay_request = Get_Reporting_Payment_Activity::create();
		$wcpay_request->set_date_start( $request->get_param( 'date_start' ) );
		$wcpay_request->set_date_end( $request->get_param( 'date_end' ) );
		$wcpay_request->set_timezone( $request->get_param( 'timezone' ) );
		return $wcpay_request->handle_rest_request();
	}
}
