<?php
/**
 * Class file for WCPay\Core\Server\Request\Request_Utils.
 *
 * @package WooCommerce Payments
 */

namespace WCPay\Core\Server\Request;

use DateTime;
use DateTimeZone;

/**
 * WC Request Utils class
 */
class Request_Utils {


	/**
	 * Reads a date filter bound in the store's timezone.
	 *
	 * The bound is a wall-clock time in `$user_timezone`, expressed in UTC. The same wall-clock time is
	 * read in the store timezone, with the store offset in effect on that date, and returned in UTC.
	 * For example, a midnight picked at +03:00 becomes the store's midnight on that day.
	 *
	 * @param string|null $transaction_date Transaction date to format, as `Y-m-d H:i:s` in UTC.
	 * @param string|null $user_timezone    UTC offset or timezone name the bound was built in.
	 * @param bool        $is_upper_bound   Whether the bound is the last second of a range. It is then read as
	 *                                      the second before the next one, so an hour that daylight saving
	 *                                      repeats stays inside the range.
	 *
	 * @return string|null The bound in UTC, or the date unchanged when either argument is null.
	 */
	public static function format_transaction_date_by_timezone( $transaction_date, $user_timezone, bool $is_upper_bound = false ) {
		if ( is_null( $transaction_date ) || is_null( $user_timezone ) ) {
			return $transaction_date;
		}

		$wall_clock = new DateTime( $transaction_date, new DateTimeZone( 'UTC' ) );
		$wall_clock->setTimezone( new DateTimeZone( $user_timezone ) );
		if ( $is_upper_bound ) {
			$wall_clock->modify( '+1 second' );
		}

		$store_time = new DateTime( $wall_clock->format( 'Y-m-d H:i:s' ), wp_timezone() );
		$store_time->setTimezone( new DateTimeZone( 'UTC' ) );
		if ( $is_upper_bound ) {
			$store_time->modify( '-1 second' );
		}

		return $store_time->format( 'Y-m-d H:i:s' );
	}

	/**
	 * Reads a `date_between` filter in the store's timezone.
	 *
	 * @param array       $date_range    Range start at index 0 and range end at index 1, as `Y-m-d H:i:s` in UTC.
	 * @param string|null $user_timezone UTC offset or timezone name the range was built in.
	 *
	 * @return array The range in UTC, with the same keys.
	 */
	public static function format_date_range_by_timezone( array $date_range, $user_timezone ): array {
		foreach ( $date_range as $key => $date ) {
			$date_range[ $key ] = self::format_transaction_date_by_timezone( $date, $user_timezone, 1 === $key );
		}

		return $date_range;
	}
}
