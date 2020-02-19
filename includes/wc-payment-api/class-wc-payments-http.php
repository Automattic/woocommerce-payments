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
	 * @return array HTTP response on success.
	 * @throws WC_Payments_Http_Exception - If not connected or request failed.
	 */
	public function remote_request( $args, $body = null, $is_site_specific = true ) {
		$args['blog_id'] = Jetpack_Options::get_option( 'id' );
		$args['user_id'] = JETPACK_MASTER_USER;

		if ( $is_site_specific ) {
			$args['url'] = sprintf( $args['url'], $args['blog_id'] );
		}

		// Make sure we're not sendign requests if Jetpack is not connected.
		if ( ! self::is_connected() ) {
			throw new WC_Payments_Http_Exception( __( 'Jetpack is not connected', 'woocommerce-payments' ) );
		}

		return self::make_request( $args, $body );
	}

	/**
	 * Makes a request through Jetpack.
	 *
	 * @param array  $args - The arguments passed to Jetpack.
	 * @param string $body - The body passed to the HTTP request.
	 *
	 * @return array HTTP response on success.
	 * @throws WC_Payments_Http_Exception - If request returns WP_Error.
	 */
	private static function make_request( $args, $body ) {
		$response = null;
		// TODO: Either revamp this auth before releasing WCPay, or properly check that Jetpack is installed & connected.
		if ( class_exists( 'Automattic\Jetpack\Connection\Client' ) ) {
			$response = Automattic\Jetpack\Connection\Client::remote_request( $args, $body );
		} else {
			$response = Jetpack_Client::remote_request( $args, $body );
		}

		if ( is_wp_error( $response ) ) {
			$message = sprintf(
				// translators: %1: original error message.
				__( 'Http request failed. Reason: %1$s', 'woocommerce-payments' ),
				$response->get_error_message()
			);
			throw new WC_Payments_Http_Exception( $message, 500 );
		}

		return $response;
	}

	/**
	 * Checks if Jetpack is connected.
	 *
	 * Checks if connection is authenticated in the same way as Jetpack_Client or Jetpack Connection Client does.
	 *
	 * @return bool true if Jetpack connection has access token.
	 */
	public static function is_connected() {
		// TODO - Remove/update when Jetpack Connection package is all we need.
		return ( class_exists( 'Automattic\Jetpack\Connection\Client' ) && ( new Automattic\Jetpack\Connection\Manager() )->get_access_token() )
			|| ( class_exists( 'Jetpack_Client' ) && Jetpack_Data::get_access_token() );
	}
}
