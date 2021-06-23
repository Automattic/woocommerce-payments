<?php
/**
 * Class Utils
 *
 * @package WooCommerce\Payments\Utils
 */

namespace WCPay\Multi_Currency;

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
	 * Returns the customer country.
	 *
	 * @return string The country code.
	 */
	public function get_customer_country(): string {
		$customer = WC()->customer;
		if ( $customer && ! empty( $customer->get_billing_country() ) ) {
			return $customer->get_billing_country();
		}

		return wc_get_customer_default_location()['country'];
	}
}
