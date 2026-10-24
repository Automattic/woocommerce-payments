<?php
/**
 * Class WC_Payments_REST_Controller
 *
 * @package WooCommerce\Payments\Admin
 */

use WCPay\Core\Exceptions\Server\Request\Invalid_Request_Parameter_Exception;
use WCPay\Exceptions\API_Exception;

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
	 * Forwards request to API client with taking care of API_Exception.
	 *
	 * Invalid_Request_Parameter_Exception is a sibling of API_Exception rather than a subclass, so
	 * it needs its own catch: without one, a request class rejecting a parameter escapes as an
	 * uncaught fatal instead of a bad request. Other Base_Exception types are left uncaught on
	 * purpose, since they signal a programming error rather than a bad request.
	 *
	 * @param string $api_method - API method name.
	 * @param array  $args - API method args.
	 *
	 * @return WP_Error|mixed - Method result, or WP_Error in case of an API or parameter exception.
	 */
	public function forward_request( $api_method, $args ) {
		try {
			$response = call_user_func_array( [ $this->api_client, $api_method ], $args );
		} catch ( API_Exception $e ) {
			$response = new WP_Error( $e->get_error_code(), $e->getMessage() );
		} catch ( Invalid_Request_Parameter_Exception $e ) {
			$response = new WP_Error( $e->get_error_code(), $e->getMessage(), [ 'status' => 400 ] );
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
