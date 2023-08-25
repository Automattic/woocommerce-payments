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
	 * @param string|null $transaction_date Transaction date to format.
	 * @param string|null $user_timezone         User's timezone passed from client.
	 *
	 * @return string|null The formatted transaction date as per timezone.
	 */
	public static function format_transaction_date_by_timezone( $transaction_date, $user_timezone ) {
		if ( is_null( $transaction_date ) || is_null( $user_timezone ) ) {
			return $transaction_date;
		}

		// Get blog timezone.
		$blog_time = new DateTime( $transaction_date );
		$blog_time->setTimezone( new DateTimeZone( wp_timezone_string() ) );

		// Get local timezone.
		$local_time = new DateTime( $transaction_date );
		$local_time->setTimezone( new DateTimeZone( $user_timezone ) );

		// Compute time difference in minutes.
		$time_difference = ( strtotime( $local_time->format( 'Y-m-d H:i:s' ) ) - strtotime( $blog_time->format( 'Y-m-d H:i:s' ) ) ) / 60;

		// Shift date by time difference.
		$formatted_date = new DateTime( $transaction_date );
		date_modify( $formatted_date, $time_difference . 'minutes' );

		return $formatted_date->format( 'Y-m-d H:i:s' );
	}

}
