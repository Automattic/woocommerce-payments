<?php
/**
 * Class WC_REST_Payments_Charges_Controller
 *
 * @package WooCommerce\Payments\Admin
 */

defined( 'ABSPATH' ) || exit;

/**
 * REST controller for charges.
 */
class WC_REST_Payments_Charges_Controller extends WP_REST_Controller {

	/**
	 * Endpoint namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'wc/v3';

	/**
	 * Endpoint path.
	 *
	 * @var string
	 */
	protected $rest_base = 'payments/charges';

	/**
	 * Client for making requests to the WooCommerce Payments API
	 *
	 * @var WC_Payments_API_Client
	 */
	private $api_client;

	/**
	 * WC_REST_Payments_Charges_Controller constructor.
	 *
	 * @param WC_Payments_API_Client $api_client - WooCommerce Payments API client.
	 */
	public function __construct( WC_Payments_API_Client $api_client ) {
		$this->api_client = $api_client;
	}

	/**
	 * Configure REST API routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<charge_id>\w+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_charge' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);
	}

	/**
	 * Retrieve charge to respond with via API.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 */
	public function get_charge( $request ) {
		$charge_id = $request->get_params()['charge_id'];
		$charge    = $this->api_client->get_charge( $charge_id );

		$raw_details     = $charge['billing_details']['address'];
		$billing_details = array();

		$billing_details['city']      = ( ! empty( $raw_details['city'] ) ) ? $raw_details['city'] : '';
		$billing_details['country']   = ( ! empty( $raw_details['country'] ) ) ? $raw_details['country'] : '';
		$billing_details['address_1'] = ( ! empty( $raw_details['line1'] ) ) ? $raw_details['line1'] : '';
		$billing_details['address_2'] = ( ! empty( $raw_details['line2'] ) ) ? $raw_details['line2'] : '';
		$billing_details['postcode']  = ( ! empty( $raw_details['postal_code'] ) ) ? $raw_details['postal_code'] : '';
		$billing_details['state']     = ( ! empty( $raw_details['state'] ) ) ? $raw_details['state'] : '';

		$charge['billing_details']['formatted_address'] = WC()->countries->get_formatted_address( $billing_details );

		return rest_ensure_response( $charge );
	}

	/**
	 * Verify access.
	 */
	public function check_permission() {
		return current_user_can( 'manage_woocommerce' );
	}
}
