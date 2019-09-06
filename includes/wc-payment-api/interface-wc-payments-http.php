<?php
/**
 * WC_Payments_Http interface.
 *
 * @package WooCommerce\Payments
 */

/**
 * An interface used by WC_Payments_Api_Client for making HTTP requests to various types of WooCommerce Payments
 * servers.
 */
interface WC_Payments_Http {
	/**
	 * Make a request to the Payments server
	 *
	 * @param array  $args - The arguments to passed to the remote server.
	 * @param string $body - The body passed on to the HTTP request.
	 *
	 * @return WP_Error|array HTTP response on success.
	 */
	public function remote_request( $args, $body = null );
}
