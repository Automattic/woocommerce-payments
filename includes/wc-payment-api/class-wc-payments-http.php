<?php
/**
 * WC_Payments_Http class.
 *
 * @package WooCommerce\Payments
 */

defined( 'ABSPATH' ) || exit;

use WCPay\Exceptions\API_Exception;
use WCPay\Exceptions\Connection_Exception;
use WCPay\Logger;

/**
 * A wrapper around Jetpack HTTP request library. Necessary to increase
 * the testability of WC_Payments_API_Client, and allow dependency
 * injection.
 */
class WC_Payments_Http implements WC_Payments_Http_Interface {

	/**
	 * Jetpack connection handler.
	 *
	 * @var Automattic\Jetpack\Connection\Manager
	 */
	private $connection_manager;

	/**
	 * WC_Payments_Http constructor.
	 *
	 * @param Automattic\Jetpack\Connection\Manager $connection_manager - Jetpack connection handler.
	 */
	public function __construct( $connection_manager ) {
		$this->connection_manager = $connection_manager;

		add_filter( 'allowed_redirect_hosts', [ $this, 'allowed_redirect_hosts' ] );
	}

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
	public function remote_request( $args, $body = null, $is_site_specific = true, $use_user_token = false ) {
		// Make sure we're not sending requests if Jetpack is not connected.
		if ( ! $this->is_connected() ) {
			Logger::error( 'HTTP_REQUEST_ERROR Site is not connected to WordPress.com' );
			throw new API_Exception(
				__( 'Site is not connected to WordPress.com', 'woocommerce-payments' ),
				'wcpay_wpcom_not_connected',
				409 // HTTP Conflict status code.
			);
		}

		$args['blog_id'] = $this->get_blog_id();

		if ( $use_user_token ) {
			$args['user_id'] = $this->connection_manager->get_connection_owner_id();
		}

		if ( $is_site_specific ) {
			// We expect `url` to include a `%s` placeholder which will allow us inject the blog id.
			$url         = explode( '?', $args['url'], 2 );
			$url[0]      = sprintf( $url[0], $args['blog_id'] );
			$args['url'] = implode( '?', $url );
		}

		return self::make_request( $args, $body );
	}

	/**
	 * Queries the WordPress.com REST API with a user token.
	 *
	 * @param string       $path          REST API path.
	 * @param string       $version       REST API version. Default is `2`.
	 * @param array        $args          Arguments to {@see WP_Http}. Default is `array()`.
	 * @param string|array $body          Body passed to {@see WP_Http}. Default is `null`.
	 * @param string       $base_api_path REST API root. Default is `wpcom`.
	 *
	 * @return array|\WP_Error $response Response data, else WP_Error on failure.
	 */
	public function wpcom_json_api_request_as_user(
		$path,
		$version = '2',
		$args = [],
		$body = null,
		$base_api_path = 'wpcom'
	) {
		return Automattic\Jetpack\Connection\Client::wpcom_json_api_request_as_user( $path, $version, $args, $body, $base_api_path );
	}

	/**
	 * Makes a request through Jetpack.
	 *
	 * @param array  $args - The arguments passed to Jetpack.
	 * @param string $body - The body passed to the HTTP request.
	 *
	 * @return array HTTP response on success.
	 * @throws Connection_Exception - If request returns WP_Error.
	 */
	private static function make_request( $args, $body ) {
		$response = Automattic\Jetpack\Connection\Client::remote_request( $args, $body );

		if ( is_wp_error( $response ) || ! is_array( $response ) ) {
			Logger::error( 'HTTP_REQUEST_ERROR ' . var_export( $response, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
			$message = sprintf(
				// translators: %1: original error message.
				__( 'Http request failed. Reason: %1$s', 'woocommerce-payments' ),
				$response->get_error_message()
			);
			throw new Connection_Exception( $message, 'wcpay_http_request_failed', 500 );
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
	public function is_connected() {
		return $this->connection_manager->is_plugin_enabled() && $this->connection_manager->is_active();
	}

	/**
	 * Checks if the site has an admin who is also a connection owner.
	 *
	 * @return bool True if Jetpack connection has an owner.
	 */
	public function has_connection_owner() {
		return ! empty( $this->connection_manager->get_connection_owner_id() );
	}

	/**
	 * Gets the current WP.com blog ID.
	 *
	 * @return integer Current WPCOM blog ID.
	 */
	public function get_blog_id() {
		return Jetpack_Options::get_option( 'id' );
	}

	/**
	 * Starts the Jetpack connection process. Note that running this function will immediately redirect
	 * to the Jetpack flow, so any PHP code after it will never be executed.
	 *
	 * @param string $redirect - URL to redirect to after the connection process is over.
	 *
	 * @throws API_Exception - Exception thrown on failure.
	 */
	public function start_connection( $redirect ) {
		// Mark the plugin as enabled in case it had been soft-disconnected.
		$this->connection_manager->enable_plugin();

		// Register the site to wp.com.
		if ( ! $this->connection_manager->is_connected() ) {
			$result = $this->connection_manager->register();
			if ( is_wp_error( $result ) ) {
				throw new API_Exception( $result->get_error_message(), 'wcpay_jetpack_register_site_failed', 500 );
			}
		}

		// Redirect the user to the Jetpack user connection flow.
		add_filter( 'jetpack_use_iframe_authorization_flow', '__return_false' );
		// Same logic as in WC-Admin.
		$calypso_env = defined( 'WOOCOMMERCE_CALYPSO_ENVIRONMENT' ) && in_array( WOOCOMMERCE_CALYPSO_ENVIRONMENT, [ 'development', 'wpcalypso', 'horizon', 'stage' ], true ) ? WOOCOMMERCE_CALYPSO_ENVIRONMENT : 'production';
		wp_safe_redirect(
			add_query_arg(
				[
					'from'        => 'woocommerce-payments',
					'calypso_env' => $calypso_env,
				],
				$this->connection_manager->get_authorization_url( null, $redirect )
			)
		);
		exit;
	}

	/**
	 * Filter function to add WP.com to the list of allowed redirect hosts
	 *
	 * @param array $hosts - array of allowed hosts.
	 *
	 * @return array allowed hosts
	 */
	public function allowed_redirect_hosts( $hosts ) {
		$hosts[] = 'jetpack.wordpress.com';
		return $hosts;
	}
}
