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
	 * Perform an SQL query to return a list of currencies that have been used at least once for
	 * an order on the store. This also accepts a start date and end date, which can optionally be used
	 * to filter by a particular date range.
	 *
	 * @param ?string $start_date Allows filtering to only show currencies used within a particular range.
	 * @param ?string $end_date   Allows filtering to only show currencies used within a particular range.
	 *
	 * @return array
	 */
	public function get_customer_currencies( $start_date = null, $end_date = null ) {
		global $wpdb;

		$join             = '';
		$start_date_query = '';
		$end_date_query   = '';

		if ( ! is_null( $start_date ) || ! is_null( $end_date ) ) {
			$join = "LEFT JOIN {$wpdb->postmeta} date_lookup ON pm.post_id = date_lookup.post_id AND date_lookup.meta_key = '_paid_date' ";

			if ( ! is_null( $start_date ) ) {
				$start_date_query = "AND date_lookup.meta_value >= {$start_date} ";
			}

			if ( ! is_null( $end_date ) ) {
				$end_date_query .= " AND date_lookup.meta_value <= {$end_date} ";
			}
		}

		$currencies = $wpdb->get_var(
			$wpdb->prepare(
				"
				SELECT
					DISTINCT(meta_value) FROM {$wpdb->postmeta} pm
				%s
				WHERE
					meta_key = '_wcpay_intent_currency'
				%s
				%s;
				",
				$join,
				$start_date_query,
				$end_date_query
			)
		);
		return $currencies;
	}

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
		return 0 === stripos( wp_get_referer(), admin_url() ) && WC()->is_rest_api_request();
	}
}
