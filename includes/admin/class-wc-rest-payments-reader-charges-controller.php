<?php
/**
 * Class WC_REST_Payments_Reader_Charges
 *
 * @package WooCommerce\Payments\Admin
 */

use WCPay\Exceptions\API_Exception;

defined( 'ABSPATH' ) || exit;

/**
 * REST controller for reader charges.
 */
class WC_REST_Payments_Reader_Charges_Controller extends WC_Payments_REST_Controller {

	/**
	 * Endpoint path.
	 *
	 * @var string
	 */
	protected $rest_base = 'payments/reader-charges';

	/**
	 * Configure REST API routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/summary/(?P<transaction_id>\w+)',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_summary' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
	}

	/**
	 * Retrieve payment readers charges to respond with via API.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_Error|WP_HTTP_Response|WP_REST_Response
	 */
	public function get_summary( $request ) {

		$transaction_id = $request->get_param( 'transaction_id' );

		try {
			// retrieve transaction details to get the charge date.
			$transaction = $this->api_client->get_transaction( $transaction_id );

			if ( empty( $transaction ) ) {
				return rest_ensure_response( [] );
			}
			$summary = $this->api_client->get_readers_charge_summary( gmdate( 'Y-m-d', $transaction['created'] ) );
		} catch ( API_Exception $e ) {
			return rest_ensure_response( new WP_Error( 'wcpay_get_summary', $e->getMessage() ) );
		}

		return rest_ensure_response( $summary );
	}

}
