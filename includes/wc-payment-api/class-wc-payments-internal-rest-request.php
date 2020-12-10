<?php
/**
 * WC_Payments_Internal_REST_Request class.
 *
 * @package WooCommerce\Payments
 */

use WCPay\Exceptions\API_Exception;

defined( 'ABSPATH' ) || exit;

/**
 * A simple instantiation of the WC_Payments_Http_Interface
 */
class WC_Payments_Internal_REST_Request implements \WC_Payments_Http_Interface {

	/**
	 * Checks if Jetpack is connected
	 *
	 * @return bool true for internal REST requests as Jetpack isn't necessary
	 */
	public function is_connected(): bool {
		return true;
	}

	/**
	 * Starts the Jetpack connection process.
	 * Since no Jetpack for internal REST requests is necessary we can skip ahead to the redirect.
	 *
	 * @param string $redirect - URL to redirect to after the connection process is over.
	 *
	 * @throws API_Exception - Exception thrown on failure.
	 */
	public function start_connection( $redirect ) {
		wp_safe_redirect( add_query_arg( $redirect ) );
		exit;
	}

	/**
	 * Uses `rest_do_request` to dispatch a REST request internally
	 *
	 * @param array  $args             - The arguments to passed to Jetpack.
	 * @param string $body             - The body passed on to the HTTP request.
	 * @param bool   $is_site_specific - If true, the site ID will be included in the request url.
	 *
	 * @return array HTTP response on success.
	 * @throws API_Exception - If no request User ID found.
	 */
	public function remote_request( $args, $body = null, $is_site_specific = true ) {
		$request         = $this->create_rest_request( $args, $body, $is_site_specific );
		$logged_in_user  = get_current_user_id();
		$request_user_id = apply_filters( 'wc_payments_internal_rest_request_user_id', null );
		if ( empty( $request_user_id ) ) {
			throw new API_Exception(
				__( 'Invalid request.', 'woocommerce-payments' ),
				'wcpay_invalid_request',
				400
			);
		}

		// This makes API requests by $request_user_id.
		wp_set_current_user( $request_user_id );

		// Handle REST API request directly.
		$response_data = rest_do_request( $request )->get_data();

		wp_set_current_user( $logged_in_user );
		return $this->expected_format( $response_data );
	}

	/**
	 * Parse the HTTP Request into a WP_REST_Request object.
	 *
	 * @param array  $args             - The arguments to passed to Jetpack.
	 * @param string $body             - The body passed on to the HTTP request.
	 * @param bool   $is_site_specific - If true, the site ID will be included in the request url.
	 *
	 * @return WP_REST_Request.
	 */
	private function create_rest_request( $args, $body = null, $is_site_specific = true ): WP_REST_Request {
		// Routes registered are not site specific so need to remove `sites/%s/` from URL request.
		$path = str_ireplace(
			'sites/%s/',
			'',
			wp_parse_url( $args['url'], PHP_URL_PATH )
		);

		$request = new WP_REST_Request( $args['method'], $path );
		if ( $request->get_method() === 'GET' ) {
			$url          = $args['url'];
			$bits         = wp_parse_url( $url );
			$query_params = [];

			if ( ! empty( $bits['query'] ) ) {
				wp_parse_str( $bits['query'], $query_params );
			}
			$request->set_query_params( $query_params );
		} elseif ( $request->get_method() === 'POST' ) {
			if ( is_string( $body ) ) {
				$request->set_body( $body );
			} else {
				$request->set_body_params( $body );
			}
		}
		foreach ( $args['headers'] as $key => $value ) {
			$request->set_header( $key, $value );
		}
		return $request;
	}

	/**
	 * Return data correctly (as the api-client expects).
	 *
	 * @param mixed $data Response data.
	 * @return array.
	 */
	private function expected_format( $data = null ): array {
		if ( empty( $data ) ) {
			$data = (object) [];
		}
		return [ 'body' => wp_json_encode( $data ) ];
	}

	/**
	 * Static function used to load WC_Payments_Internal_REST_Request.
	 *
	 * @param \WC_Payments_Http_Interface $class  Existing implementation to filter.
	 *
	 * @return \WC_Payments_Http_Interface.
	 */
	public static function load_wc_payments_http( $class ): WC_Payments_Internal_REST_Request {
		return new self();
	}
}
