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
		return 0 === stripos( wp_get_referer(), admin_url() ) && WC()->is_rest_api_request() && ! \WC_Payments_Utils::is_store_api_request();
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
}
