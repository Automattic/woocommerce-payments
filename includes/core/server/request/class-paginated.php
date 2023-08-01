<?php
/**
 * Class file for WCPay\Core\Server\Request\Paginated.
 *
 * @package WooCommerce Payments
 */

namespace WCPay\Core\Server\Request;

use DateTime;
use DateTimeZone;
use WCPay\Core\Server\Request;
use WP_REST_Request;

/**
 * Request class for paginated requests.
 */
abstract class Paginated extends Request {


	const DEFAULT_PARAMS = [
		'page'      => 0,
		'pagesize'  => 25,
		'sort'      => 'created',
		'direction' => 'desc',
		'limit'     => 100,
	];

	const IMMUTABLE_PARAMS = [
		'page',
		'pagesize',
		'sort',
		'direction',
		'limit',
	];


	/**
	 * Returns the request's HTTP method.
	 */
	public function get_method(): string {
		return 'GET';
	}

	/**
	 * Used to prepare request from WP Rest data.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return static
	 */
	public static function from_rest_request( $request ) {
		$wcpay_request = static::create();
		// We have to check does the default param exist in parent class and if it doesn't we apply ones from this class.
		$wcpay_request->set_page( (int) ( $request->get_param( 'page' ) ?? static::DEFAULT_PARAMS['page'] ?? self::DEFAULT_PARAMS['page'] ) );
		$wcpay_request->set_page_size( (int) ( $request->get_param( 'pagesize' ) ?? static::DEFAULT_PARAMS['pagesize'] ?? self::DEFAULT_PARAMS['pagesize'] ) );
		$wcpay_request->set_sort_by( (string) ( $request->get_param( 'sort' ) ?? static::DEFAULT_PARAMS['sort'] ?? self::DEFAULT_PARAMS['sort'] ) );
		$wcpay_request->set_sort_direction( (string) ( $request->get_param( 'direction' ) ?? static::DEFAULT_PARAMS['direction'] ?? self::DEFAULT_PARAMS['direction'] ) );

		return $wcpay_request;
	}

	/**
	 * Formats the incoming transaction date as per the blog's timezone.
	 *
	 * @param string|null $transaction_date Transaction date to format.
	 * @param string|null $user_timezone User's timezone passed from client.
	 *
	 * @return string|null The formatted transaction date as per timezone.
	 */
	public static function format_transaction_date_with_timestamp( $transaction_date, $user_timezone ) {
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

	/**
	 * Set filters.
	 *
	 * @param array $filters Filters to set.
	 *
	 * @return void
	 */
	public function set_filters( array $filters ) {

		foreach ( $filters as $key => $value ) {
			// Nullable filters are not needed so we skip them.
			if ( null !== $value ) {
				// Make sure that setter is called for this specific key to preform validations and other things in setter function.
				if ( method_exists( $this, 'set_' . $key ) ) {
					$this->{'set_' . $key}( $value );
				} else {
					$this->set_param( $key, $value );
				}
			}
		}
	}

	/**
	 * Set page.
	 *
	 * @param int $page Page to set.
	 *
	 * @return void
	 */
	public function set_page( int $page ) {
		$this->set_param( 'page', $page );
	}

	/**
	 * Set page size.
	 *
	 * @param int $page_size Page size to set.
	 *
	 * @return void
	 */
	public function set_page_size( int $page_size ) {
		$this->set_param( 'pagesize', $page_size );
	}

	/**
	 * Set sort by.
	 *
	 * @param string $sort Sort value.
	 *
	 * @return void
	 */
	public function set_sort_by( string $sort ) {
		$this->set_param( 'sort', $sort );
	}

	/**
	 * Set sort direction.
	 *
	 * @param string $direction Sort direction.
	 *
	 * @return void
	 */
	public function set_sort_direction( string $direction ) {
		$this->set_param( 'direction', $direction );
	}

}
