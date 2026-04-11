<?php
/**
 * Class Utils
 *
 * @package WooCommerce\Payments\Utils
 */

namespace WCPay\MultiCurrency;

defined( 'ABSPATH' ) || exit;

/**
 * Class that controls Multi-Currency Utils.
 */
class Utils {
	/**
	 * Checks backtrace calls to see if a certain call has been made.
	 *
	 * @param array $calls Array of the calls to check for.
	 *
	 * @return bool True if found, false if not.
	 */
	public function is_call_in_backtrace( array $calls ): bool {
		$backtrace = wp_debug_backtrace_summary( null, 0, false ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
		foreach ( $calls as $call ) {
			if ( in_array( $call, $backtrace, true ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Checks the query_vars array for a particular pagename and variable to be set.
	 *
	 * @param array $pages Array of the pagenames to check for.
	 * @param array $vars Array of the vars to check for.
	 *
	 * @return bool True if found, false if not.
	 */
	public function is_page_with_vars( array $pages, array $vars ): bool {
		global $wp;

		if ( $wp->query_vars && isset( $wp->query_vars['pagename'] ) && in_array( $wp->query_vars['pagename'], $pages, true ) ) {
			foreach ( $vars as $var ) {
				if ( isset( $wp->query_vars[ $var ] ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Checks if is a REST API request and the HTTP referer matches admin url.
	 *
	 * @return boolean
	 */
	public static function is_admin_api_request(): bool {
		return 0 === stripos( wp_get_referer(), admin_url() ) && WC()->is_rest_api_request() && ! self::is_store_api_request();
	}


	/**
	 * Writes the session into the client cookie.
	 *
	 * @param bool $set Should the session cookie be set.
	 *
	 * @return void
	 */
	public static function set_customer_session_cookie( bool $set ) {
		WC()->session->set_customer_session_cookie( $set );
	}

	/**
	 * Returns true if the request is a store REST API request.
	 *
	 * @return bool
	 */
	public static function is_store_api_request() {
		if ( function_exists( 'WC' ) && method_exists( WC(), 'is_store_api_request' ) ) {
			return WC()->is_store_api_request();
		}
		// The logic below is sourced from `WC()->is_store_api_request()`.
		if ( empty( $_SERVER['REQUEST_URI'] ) ) {
			return false;
		}
		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		return false !== strpos( $_SERVER['REQUEST_URI'], trailingslashit( rest_get_url_prefix() ) . 'wc/store/' );
	}

	/**
	 * Determine if the request that's currently being processed is a Store API batch request.
	 *
	 * @return bool True if the request is a Store API batch request, false otherwise.
	 */
	public static function is_store_batch_request(): bool {
		// @TODO We should move to a more robust way of getting to the route, like WC is doing in the StoreAPI library. https://github.com/woocommerce/woocommerce/blob/9ac48232a944baa2dbfaa7dd47edf9027cca9519/plugins/woocommerce/src/StoreApi/Authentication.php#L15-L15
		if ( isset( $_REQUEST['rest_route'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$rest_route = sanitize_text_field( $_REQUEST['rest_route'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.NonceVerification
		} else {
			// Extract the request path from the request URL.
			$url_parts    = wp_parse_url( esc_url_raw( $_SERVER['REQUEST_URI'] ?? '' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash
			$request_path = ! empty( $url_parts['path'] ) ? rtrim( $url_parts['path'], '/' ) : '';
			// Remove the REST API prefix from the request path to end up with the route.
			$rest_route = str_replace( trailingslashit( rest_get_url_prefix() ), '', $request_path );
		}

		// Bail early if the rest route is empty.
		if ( empty( $rest_route ) ) {
			return false;
		}

		return 1 === preg_match( '@^\/wc\/store(\/v[\d]+)?\/batch@', $rest_route );
	}
}
