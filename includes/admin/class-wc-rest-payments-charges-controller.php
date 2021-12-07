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

		$raw_details     = $charge['billing_details']['address'];
		$billing_details = [];

		$billing_details['city']      = ( ! empty( $raw_details['city'] ) ) ? $raw_details['city'] : '';
		$billing_details['country']   = ( ! empty( $raw_details['country'] ) ) ? $raw_details['country'] : '';
		$billing_details['address_1'] = ( ! empty( $raw_details['line1'] ) ) ? $raw_details['line1'] : '';
		$billing_details['address_2'] = ( ! empty( $raw_details['line2'] ) ) ? $raw_details['line2'] : '';
		$billing_details['postcode']  = ( ! empty( $raw_details['postal_code'] ) ) ? $raw_details['postal_code'] : '';
		$billing_details['state']     = ( ! empty( $raw_details['state'] ) ) ? $raw_details['state'] : '';

		$charge['billing_details']['formatted_address'] = WC()->countries->get_formatted_address( $billing_details );

		return rest_ensure_response( $charge );
	}
}
