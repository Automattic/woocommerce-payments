<?php
/**
 * WC_Payments_Http_Interface class.
 *
 * @package WooCommerce\Payments
 */

defined( 'ABSPATH' ) || exit;

/**
 * A wrapper around Jetpack HTTP request library. Necessary to increase
 * the testability of WC_Payments_API_Client, and allow dependency
 * injection.
 */
interface WC_Payments_Http_Interface {

	/**
	 * Sends a remote request through Jetpack.
	 *
	 * @param array  $args             - The arguments to passed to Jetpack.
	 * @param string $body             - The body passed on to the HTTP request.
	 * @param bool   $is_site_specific - If true, the site ID will be included in the request url. Defaults to true.
	 * @param bool   $use_user_token   - If true, the request will be signed with the user token rather than blog token. Defaults to false.
	 *
	 * @return array HTTP response on success.
	 * @throws API_Exception - If not connected or request failed.
	 */
	public function remote_request( $args, $body = null, $is_site_specific = true, $use_user_token = false );

	/**
	 * Checks if Jetpack is connected.
	 *
	 * Checks if connection is authenticated in the same way as Jetpack_Client or Jetpack Connection Client does.
	 *
	 * @return bool true if Jetpack connection has access token.
	 */
	public function is_connected();

	/**
	 * Gets the current WP.com blog ID.
	 *
	 * @return integer Current WPCOM blog ID.
	 */
	public function get_blog_id();

	/**
	 * Starts the Jetpack connection process. Note that running this function will immediately redirect
	 * to the Jetpack flow, so any PHP code after it will never be executed.
	 *
	 * @param string $redirect - URL to redirect to after the connection process is over.
	 *
	 * @throws API_Exception - Exception thrown on failure.
	 */
	public function start_connection( $redirect );
}
