<?php
/**
 * Class WC_REST_Payments_Timeline_Controller
 *
 * @package WooCommerce\Payments\Admin
 */

use WCPay\Core\Server\Request\Refund_Charge;
use WCPay\Exceptions\API_Exception;

defined( 'ABSPATH' ) || exit;

/**
 * REST controller for the timeline, which includes all events related to an intention.
 */
class WC_REST_Payments_Refunds_Controller extends WC_Payments_REST_Controller {
	/**
	 * Endpoint path.
	 *
	 * @var string
	 */
	protected $rest_base = 'payments/refund';

	/**
	 * Configure REST API routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'process_refund' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
	}

	/**
	 * Makes direct refund bypassing any order checks.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 */
	public function process_refund( $request ) {
		$charge_id = $request->get_param( 'charge_id' );
		$amount    = $request->get_param( 'amount' );

		try {
			$refund_request = Refund_Charge::create( $charge_id );
			$refund_request->set_charge( $charge_id );
			$refund_request->set_amount( $amount );
			$refund_request->set_reason( $amount );
			$response = $refund_request->send();
		} catch ( API_Exception $e ) {
			return rest_ensure_response( new WP_Error( 'wcpay_refund_payment', $e->getMessage() ) );
		}

		return rest_ensure_response( $response );
	}
}
