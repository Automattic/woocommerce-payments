<?php
/**
 * WC_Payments_Local_Http class.
 *
 * @package WooCommerce\Payments
 */

/**
 * A wrapper around a standard wp_remote_request call which can be injected into an instance of WC_Payments_Api_Client.
 */
class WC_Payments_Local_Http implements WC_Payments_Http {
	/**
	 * Make a request to the Payments server
	 *
	 * @param array  $args - The arguments to passed to Jetpack.
	 * @param string $body - The body passed on to the HTTP request.
	 *
	 * @return WP_Error|array HTTP response on success.
	 */
	public function remote_request( $args, $body = null ) {
		return wp_remote_request( $args['url'], $args );
	}
}
