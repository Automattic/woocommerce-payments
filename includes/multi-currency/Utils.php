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
	 * Whether WordPress has matched the request, or a request batched in it, to a Store API route.
	 *
	 * @var bool
	 */
	private static $matched_store_api_route = false;

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
	 * Checks whether one of the given calls is within the innermost frames of the current backtrace.
	 *
	 * Like is_call_in_backtrace(), but scans at most $limit frames (innermost first) and stops at the
	 * first match. Use it on hot paths to detect a call that always sits a fixed, shallow distance below
	 * the current frame, so the per-call cost stays bounded regardless of overall stack depth.
	 *
	 * @param array $calls Array of the calls to check for, in the Class->method or Class::method form.
	 * @param int   $limit Maximum number of frames to scan.
	 *
	 * @return bool True if one of the calls is found within the scanned frames, false otherwise.
	 */
	public function is_call_in_backtrace_within( array $calls, int $limit ): bool {
		// DEBUG_BACKTRACE_IGNORE_ARGS skips collecting arguments, and $limit caps the frames collected,
		// so this stays cheap enough to run on every price-format call.
		foreach ( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, $limit ) as $frame ) { // phpcs:ignore WordPress.PHP.DevelopmentFunctions
			$name = ( $frame['class'] ?? '' ) . ( $frame['type'] ?? '' ) . $frame['function'];
			if ( in_array( $name, $calls, true ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Counts how many frames in the current backtrace match the given call.
	 *
	 * @param string $call Call string in the form rendered by wp_debug_backtrace_summary (for example Class->method).
	 *
	 * @return int The number of matching frames.
	 */
	public function get_backtrace_call_count( string $call ): int {
		// DEBUG_BACKTRACE_IGNORE_ARGS skips collecting every frame's arguments, which this runs on each
		// price-format call during a recalculation, so the cost stays low.
		$count = 0;
		foreach ( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS ) as $frame ) { // phpcs:ignore WordPress.PHP.DevelopmentFunctions
			if ( isset( $frame['class'], $frame['type'] ) && $call === $frame['class'] . $frame['type'] . $frame['function'] ) {
				++$count;
			}
		}
		return $count;
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
		if ( self::$matched_store_api_route ) {
			return true;
		}
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
	 * Records whether WordPress has matched the request to a Store API route.
	 *
	 * The URL doesn't always name the route that handles the request, so once a Store API route has been
	 * matched, the request is a Store API request whatever its URL looks like.
	 *
	 * @param bool $matched True once a Store API route has been matched.
	 *
	 * @return void
	 */
	public static function set_matched_store_api_route( bool $matched ) {
		self::$matched_store_api_route = $matched;
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
