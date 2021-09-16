<?php
/**
 * Class Utils
 *
 * @package WooCommerce\Payments\Utils
 */

namespace WCPay\MultiCurrency;

defined( 'ABSPATH' ) || exit;

/**
 * Class that controls Multi Currency Utils.
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
	 * Checks if is a REST API request and the HTTP referer matches admin url.
	 *
	 * @return boolean
	 */
	public static function is_admin_api_request(): bool {
		return 0 === stripos( wp_get_referer(), admin_url() ) && WC()->is_rest_api_request();
	}
}
