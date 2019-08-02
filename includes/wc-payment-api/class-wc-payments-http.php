<?php
/**
 * WC_Payments_Http class.
 *
 * @package WooCommerce\Payments
 */

/**
 * A wrapper around Jetpack HTTP request library. Necessary to increase
 * the testability of WC_Payments_API_Client, and allow dependency
 * injection.
 */
class WC_Payments_Http {

	/**
	 * Sends a remote request through Jetpack (?).
	 *
	 * @param array  $args - The arguments to passed to Jetpack.
	 * @param string $body - The body passed on to the HTTP request.
	 *
	 * @return WC_Payments_Http_Response
	 *
	 * @throws Exception - Thrown if the request returns an error.
	 */
	public function remote_request( $args, $body = null ) {
		$args['blog_id'] = Jetpack_Options::get_option( 'id' );
		$args['user_id'] = JETPACK_MASTER_USER;
		// TODO: Either revamp this auth before releasing WCPay, or properly check that Jetpack is installed & connected.
		$response_array = Jetpack_Client::remote_request( $args, $body );

		if ( is_wp_error( $response_array ) ) {
			throw new Exception( $response_array->get_error_message() );
		}

		$response_code = wp_remote_retrieve_response_code( $response_array );
		$response_body = wp_remote_retrieve_body( $response_array );

		$response = new WC_Payments_Http_Response( $response_code, $response_body );

		return $response;
	}
}
