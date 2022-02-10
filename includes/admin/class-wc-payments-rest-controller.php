<?php
/**
 * Class WC_Payments_REST_Controller
 *
 * @package WooCommerce\Payments\Admin
 */

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

		// Timestamp headers.
		add_action( 'rest_pre_dispatch', [ $this, 'track_pre_dispatch' ], 10, 3 );
		add_action( 'rest_request_before_callbacks', [ $this, 'track_request_before_callbacks' ], 10, 3 );
		add_action( 'rest_request_after_callbacks', [ $this, 'track_request_after_callbacks' ], 10, 3 );

	}

	/**
	 * Sets tracking header for rest_pre_dispatch action.
	 *
	 * @param mixed            $result  Response to replace the requested version with. Can be anything
	 *                                  a normal endpoint can return, or null to not hijack the request.
	 * @param \WP_REST_Server  $server  Server instance.
	 * @param \WP_REST_Request $request Request used to generate the response.
	 *
	 * @return null
	 */
	public function track_pre_dispatch( $result, $server, $request ) {
		$this->set_time_metric_header( 'Pre-Dispatch' );
		return null;
	}

	/**
	 * Sets tracking header for rest_request_before_callbacks.
	 *
	 * @param mixed            $result  Response to replace the requested version with. Can be anything
	 *                                  a normal endpoint can return, or null to not hijack the request.
	 * @param array            $handler Route handler used for the request.
	 * @param \WP_REST_Request $request Request used to generate the response.
	 *
	 * @return null
	 */
	public function track_request_before_callbacks( $result, $handler, $request ) {
		$this->set_time_metric_header( 'Before-Callbacks' );
		return $result;
	}

	/**
	 * Sets tracking header for rest_request_after_callbacks.
	 *
	 * @param mixed            $result  Response to replace the requested version with. Can be anything
	 *                                  a normal endpoint can return, or null to not hijack the request.
	 * @param array            $handler Route handler used for the request.
	 * @param \WP_REST_Request $request Request used to generate the response.
	 *
	 * @return null
	 */
	public function track_request_after_callbacks( $result, $handler, $request ) {
		$this->set_time_metric_header( 'After-Callbacks' );
		return $result;
	}
	/**
	 * Forwards request to API client with taking care of API_Exception.
	 *
	 * @param string $api_method - API method name.
	 * @param array  $args - API method args.
	 *
	 * @return WP_Error|mixed - Method result of WP_Error in case of API_Exception.
	 */
	public function forward_request( $api_method, $args ) {
		try {
			$response = call_user_func_array( [ $this->api_client, $api_method ], $args );
		} catch ( API_Exception $e ) {
			$response = new WP_Error( $e->get_error_code(), $e->getMessage() );
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

	/**
	 * Sets a time tracking header for server response.
	 *
	 * @param string $metric_name Metric name to use in the header.
	 * @return void
	 */
	private function set_time_metric_header( $metric_name ) {
		header(
			sprintf(
				'X-WCPay-Frontend-%s-Timestamp: %0.4f',
				$metric_name,
				microtime( true )
			)
		);
	}
}
