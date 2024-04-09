<?php
/**
 * Class WC_REST_Payments_Reporting_Controller
 *
 * @package WooCommerce\Payments\Admin
 */

use WCPay\Core\Server\Request;
use WCPay\Exceptions\API_Exception;

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
	 * Constructor.
	 *
	 * @param WC_Payments_API_Client $api_client    WooCommerce Payments API client.
	 */
	public function __construct(
		WC_Payments_API_Client $api_client
	) {
		parent::__construct( $api_client );
	}

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
	 * Retrieve transaction to respond with via API.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 */
	public function get_payment_activity( $request ) {
		$wcpay_request = Request::get( WC_Payments_API_Client::REPORTING_API );
		$wcpay_request->assign_hook( 'wcpay_get_payment_activity' );
		return $wcpay_request->handle_rest_request();
	}
}
