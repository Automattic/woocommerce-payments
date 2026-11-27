<?php
/**
 * Class Request_Utils_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\Core\Server\Request\Request_Utils;

/**
 * WCPay\Core\Server\Request\Request_Utils unit tests.
 */
class Request_Utils_Test extends WCPAY_UnitTestCase {

	/**
	 * Store timezone settings to restore after each test.
	 *
	 * @var array
	 */
	private $timezone_options;

	public function set_up() {
		parent::set_up();

		$this->timezone_options = [
			'timezone_string' => get_option( 'timezone_string' ),
			'gmt_offset'      => get_option( 'gmt_offset' ),
		];
	}

	public function tear_down() {
		foreach ( $this->timezone_options as $option => $value ) {
			update_option( $option, $value );
		}

		parent::tear_down();
	}

	/**
	 * @dataProvider provider_store_day_bounds
	 */
	public function test_format_transaction_date_by_timezone_reads_the_bound_in_the_store_timezone( string $store_timezone, string $user_timezone, string $transaction_date, bool $is_upper_bound, string $expected ) {
		$this->set_store_timezone( $store_timezone );

		$this->assertSame(
			$expected,
			Request_Utils::format_transaction_date_by_timezone( $transaction_date, $user_timezone, $is_upper_bound )
		);
	}

	/**
	 * Each case sends a store-day bound the way the client builds it: the wall-clock time in
	 * `user_timezone`, expressed in UTC.
	 *
	 * @return array
	 */
	public function provider_store_day_bounds(): array {
		return [
			'UTC store, start of day'                 => [ 'UTC', '+03:00', '2026-01-14 21:00:00', false, '2026-01-15 00:00:00' ],
			'store offset of the filtered winter day' => [ 'America/New_York', '+03:00', '2026-01-14 21:00:00', false, '2026-01-15 05:00:00' ],
			'store offset of the filtered summer day' => [ 'America/New_York', '+03:00', '2026-07-15 20:59:59', true, '2026-07-16 03:59:59' ],
			'store DST change after the UTC instant'  => [ 'America/New_York', '-10:00', '2026-11-01 10:00:00', false, '2026-11-01 04:00:00' ],
			'store skips midnight'                    => [ 'America/Santiago', '+02:00', '2026-09-05 22:00:00', false, '2026-09-06 04:00:00' ],
			'store repeats the hour before midnight'  => [ 'America/Santiago', '+02:00', '2026-04-04 21:59:59', true, '2026-04-05 03:59:59' ],
			'store repeats midnight'                  => [ 'America/Havana', '+02:00', '2026-10-31 22:00:00', false, '2026-11-01 04:00:00' ],
			'store with a manual UTC offset'          => [ '+05:45', '+02:00', '2026-01-14 22:00:00', false, '2026-01-14 18:15:00' ],
			'store with a manual UTC offset, upper'   => [ '+05:45', '+02:00', '2026-01-15 21:59:59', true, '2026-01-15 18:14:59' ],
			'upper bound in an ordinary hour'         => [ 'America/New_York', '+00:00', '2026-01-15 23:59:59', true, '2026-01-16 04:59:59' ],
		];
	}

	public function test_format_transaction_date_by_timezone_returns_the_date_unchanged_without_a_timezone() {
		$this->set_store_timezone( 'America/New_York' );

		$this->assertSame( '2026-01-15 00:00:00', Request_Utils::format_transaction_date_by_timezone( '2026-01-15 00:00:00', null ) );
		$this->assertNull( Request_Utils::format_transaction_date_by_timezone( null, '+03:00' ) );
	}

	public function test_format_date_range_by_timezone_treats_the_second_date_as_the_upper_bound() {
		$this->set_store_timezone( 'America/Santiago' );

		$this->assertSame(
			[ '2026-04-04 03:00:00', '2026-04-05 03:59:59' ],
			Request_Utils::format_date_range_by_timezone( [ '2026-04-03 22:00:00', '2026-04-04 21:59:59' ], '+02:00' )
		);
	}

	/**
	 * Sets the store timezone the way Settings > General saves it.
	 *
	 * @param string $timezone A timezone name, or a UTC offset such as `+05:45` for a manual offset.
	 */
	private function set_store_timezone( string $timezone ) {
		if ( preg_match( '/^([+-])(\d{2}):(\d{2})$/', $timezone, $matches ) ) {
			update_option( 'timezone_string', '' );
			update_option( 'gmt_offset', ( '-' === $matches[1] ? -1 : 1 ) * ( (int) $matches[2] + (int) $matches[3] / 60 ) );
			return;
		}

		update_option( 'timezone_string', $timezone );
		update_option( 'gmt_offset', 0 );
	}
}
