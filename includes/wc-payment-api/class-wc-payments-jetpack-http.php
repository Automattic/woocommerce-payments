<?php
/**
 * WC_Payments_Http class.
 *
 * @package WooCommerce\Payments
 */

defined( 'ABSPATH' ) || exit;

/**
 * A wrapper around Jetpack HTTP request library. Necessary to increase
 * the testability of WC_Payments_API_Client, and allow dependency
 * injection.
 */
class WC_Payments_Jetpack_Http implements WC_Payments_Http {

	/**
	 * Sends a remote request through Jetpack (?).
	 *
	 * @param array  $args - The arguments to passed to Jetpack.
	 * @param string $body - The body passed on to the HTTP request.
	 *
	 * @return WP_Error|array HTTP response on success.
	 */
	public function remote_request( $args, $body = null ) {
		$args['blog_id'] = Jetpack_Options::get_option( 'id' );
		$args['user_id'] = JETPACK_MASTER_USER;
		// TODO: Either revamp this auth before releasing WCPay, or properly check that Jetpack is installed & connected.
		return Jetpack_Client::remote_request( $args, $body );
	}
}
