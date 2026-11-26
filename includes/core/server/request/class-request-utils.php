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
	 * Formats the incoming transaction date as per the blog's timezone.
	 *
	 * The date is a wall-clock time in `$user_timezone`, expressed in UTC. The same wall-clock time is read in the
	 * store timezone, using the store offset on that date, and returned in UTC.
	 *
	 * @param string|null $transaction_date Transaction date to format.
	 * @param string|null $user_timezone    User's timezone passed from client.
	 *
	 * @return string|null The formatted transaction date as per timezone.
	 */
	public static function format_transaction_date_by_timezone( $transaction_date, $user_timezone ) {
		if ( is_null( $transaction_date ) || is_null( $user_timezone ) ) {
			return $transaction_date;
		}

		$user_time = new DateTime( $transaction_date, new DateTimeZone( 'UTC' ) );
		$user_time->setTimezone( new DateTimeZone( $user_timezone ) );

		$store_time = new DateTime( $user_time->format( 'Y-m-d H:i:s' ), wp_timezone() );
		$store_time->setTimezone( new DateTimeZone( 'UTC' ) );

		return $store_time->format( 'Y-m-d H:i:s' );
	}
}
