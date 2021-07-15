<?php
/**
 * Class Country_Flags_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\MultiCurrency\CountryFlags;

/**
 * Class CountryFlags tests.
 */
class Country_Flags_Test extends WP_UnitTestCase {
	public function test_get_by_country_returns_emoji_flag() {
		$this->assertEquals( CountryFlags::get_by_country( 'US' ), '🇺🇸' );
	}

	public function test_get_by_country_returns_empty_string() {
		$this->assertEquals( CountryFlags::get_by_country( 'ZZ' ), '' );
	}

	public function test_get_by_currency_returns_placeholder() {
		$this->assertEquals( CountryFlags::get_by_currency( 'RANDOM' ), '💵' );
	}

	public function test_get_by_currency_returns_emoji_flag() {
		$this->assertEquals( CountryFlags::get_by_currency( 'EUR' ), '🇪🇺' );
	}

	/**
	 * @dataProvider get_by_currency_exceptions_provider
	 */
	public function test_get_by_currency_returns_emoji_flag_for_exceptions( $currency, $flag ) {
		$this->assertEquals( CountryFlags::get_by_currency( $currency ), $flag );
	}

	public function get_by_currency_exceptions_provider() {
		return [
			[ 'ANG', '💵' ],
			[ 'BTC', '🏴‍☠️' ],
			[ 'XAF', '💵' ],
			[ 'XCD', '💵' ],
			[ 'XOF', '💵' ],
			[ 'XPF', '💵' ],
		];
	}
}
