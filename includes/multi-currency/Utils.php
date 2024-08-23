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
	 * The Store API route patterns that should be handled by the WooPay session handler.
	 */
	const STORE_API_ROUTE_PATTERNS = [
		'@^\/wc\/store(\/v[\d]+)?\/cart$@',
		'@^\/wc\/store(\/v[\d]+)?\/cart\/add-item$@',
		'@^\/wc\/store(\/v[\d]+)?\/cart\/remove-item$@',
		'@^\/wc\/store(\/v[\d]+)?\/cart\/apply-coupon$@',
		'@^\/wc\/store(\/v[\d]+)?\/cart\/remove-coupon$@',
		'@^\/wc\/store(\/v[\d]+)?\/cart\/select-shipping-rate$@',
		'@^\/wc\/store(\/v[\d]+)?\/cart\/update-customer$@',
		'@^\/wc\/store(\/v[\d]+)?\/cart\/update-item$@',
		'@^\/wc\/store(\/v[\d]+)?\/cart\/extensions$@',
		'@^\/wc\/store(\/v[\d]+)?\/checkout\/(?P<id>[\d]+)@',
		'@^\/wc\/store(\/v[\d]+)?\/checkout$@',
		'@^\/wc\/store(\/v[\d]+)?\/order\/(?P<id>[\d]+)@',
		// The route below is not a Store API route. However, this REST endpoint is used by WooPay to indirectly reach the Store API.
		// By adding it to this list, we're able to identify the user and load the correct session for this route.
		'@^\/wc\/v3\/woopay\/session$@',
	];

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
	 * Returns true if the request that's currently being processed is a Store API request, false
	 * otherwise.
	 *
	 * @return bool True if request is a Store API request, false otherwise.
	 */
	public static function is_store_api_request(): bool {
		if ( isset( $_REQUEST['rest_route'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$rest_route = sanitize_text_field( $_REQUEST['rest_route'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.NonceVerification
		} else {
			$url_parts    = wp_parse_url( esc_url_raw( $_SERVER['REQUEST_URI'] ?? '' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash
			$request_path = rtrim( $url_parts['path'], '/' );
			$rest_route   = str_replace( trailingslashit( rest_get_url_prefix() ), '', $request_path );
		}

		foreach ( self::STORE_API_ROUTE_PATTERNS as $pattern ) {
			if ( 1 === preg_match( $pattern, $rest_route ) ) {
				return true;
			}
		}

		return false;
	}
}
