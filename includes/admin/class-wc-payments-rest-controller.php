<?php
/**
 * Class WC_Payments_REST_Controller
 *
 * @package WooCommerce\Payments\Admin
 */

defined( 'ABSPATH' ) || exit;

/**
 * REST controller for transactions.
 */
class WC_Payments_REST_Controller extends WP_REST_Controller {

	/**
	 * Endpoint namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'wc/v3';

	/**
	 * Client for making requests to the WooCommerce Payments API
	 *
	 * @var WC_Payments_API_Client
	 */
	protected $api_client;

	/**
	 * WC_Payments_REST_Controller constructor.
	 *
	 * @param WC_Payments_API_Client $api_client - WooCommerce Payments API client.
	 */
	public function __construct( WC_Payments_API_Client $api_client ) {
		$this->api_client = $api_client;
	}

	/**
	 * Forwards request to API client with taking care of WC_Payments_API_Exception.
	 *
	 * @param string $api_method - API method name.
	 * @param array  $args - API method args.
	 * @param string $err_code - Optional error code to use for WP_Error.
	 *
	 * @return WP_Error|mixed - Method result of WP_Error in case of WC_Payments_API_Exception.
	 */
	public function forward_request( $api_method, $args, $err_code = '' ) {
		try {
			$response = call_user_func_array( [ $this->api_client, $api_method ], $args );
		} catch ( WC_Payments_API_Exception $e ) {
			$code     = $err_code ? $err_code : 'wcpay_' . $api_method;
			$response = new WP_Error( $code, $e->getMessage() );
		}

		return rest_ensure_response( $response );
	}

	/**
	 * Verify access.
	 *
	 * Override this method if custom permissions required.
	 */
	public function check_permission() {
		return current_user_can( 'manage_woocommerce' );
	}
}
