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
class WC_Payments_Http {

	/**
	 * Sends a remote request through Jetpack (?).
	 *
	 * @param array  $args             - The arguments to passed to Jetpack.
	 * @param string $body             - The body passed on to the HTTP request.
	 * @param bool   $is_site_specific - If true, the site ID will be included in the request url.
	 *
	 * @return WP_Error|array HTTP response on success.
	 */
	public function remote_request( $args, $body = null, $is_site_specific = true ) {
		$args['blog_id'] = Jetpack_Options::get_option( 'id' );
		$args['user_id'] = Automattic\Jetpack\Connection\Manager::JETPACK_MASTER_USER;

		if ( $is_site_specific ) {
			$args['url'] = sprintf( $args['url'], $args['blog_id'] );
		}

		return Automattic\Jetpack\Connection\Client::remote_request( $args, $body );
	}
}
