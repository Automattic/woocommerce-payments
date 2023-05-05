<?php
/**
 * Class WC_REST_Payments_Capital_Controller
 *
 * @package WooCommerce\Payments\Admin
 */

use WCPay\Core\Server\Request\Get_Request;

defined( 'ABSPATH' ) || exit;

/**
 * REST controller for Capital loans functionality.
 */
class WC_REST_Payments_Capital_Controller extends WC_Payments_REST_Controller {

	/**
	 * Endpoint path.
	 *
	 * @var string
	 */
	protected $rest_base = 'payments/capital';

	/**
	 * Configure REST API routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/active_loan_summary',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_active_loan_summary' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/loans',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_loans' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
	}

	/**
	 * Retrieve the summary of the active Capital loan.
	 */
	public function get_active_loan_summary() {
		$request = Get_Request::create();
		$request->set_api( WC_Payments_API_Client::CAPITAL_API . '/active_loan_summary' );
		return $request->send( 'wcpay_get_active_loan_summary_request' );
	}

	/**
	 * Retrieve all the past and present Capital loans.
	 */
	public function get_loans() {
		$request = Get_Request::create();
		$request->set_api( WC_Payments_API_Client::CAPITAL_API . '/loans' );
		return $request->send( 'wcpay_get_loans_request' );
	}

}
