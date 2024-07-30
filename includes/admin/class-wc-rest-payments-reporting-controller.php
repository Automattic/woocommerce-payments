<?php
/**
 * Class WC_REST_Payments_Reporting_Controller
 *
 * @package WooCommerce\Payments\Admin
 */

use WCPay\Core\Server\Request\Get_Reporting_Payment_Activity;
use WCPay\Core\Server\Request\Request_Utils;

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
		$wcpay_request                = Get_Reporting_Payment_Activity::create();
		$date_start_in_store_timezone = $this->format_date_to_wp_timezone( $request->get_param( 'date_start' ) );
		$date_end_in_store_timezone   = $this->format_date_to_wp_timezone( $request->get_param( 'date_end' ) );
		$wcpay_request->set_date_start( $date_start_in_store_timezone );
		$wcpay_request->set_date_end( $date_end_in_store_timezone );
		$wcpay_request->set_timezone( $request->get_param( 'timezone' ) );
		$wcpay_request->set_currency( $request->get_param( 'currency' ) );
		return $wcpay_request->handle_rest_request();
	}



	/**
	 * Formats a date string to the WordPress timezone.
	 *
	 * @param string $date_string The date string to be formatted.
	 * @return string The formatted date string in the 'Y-m-d\TH:i:s' format.
	 */
	private function format_date_to_wp_timezone( $date_string ) {
		$date = Request_Utils::format_transaction_date_by_timezone( $date_string, '+00:00' );
		$date = new DateTime( $date );
		return $date->format( 'Y-m-d\\TH:i:s' );
	}
}
