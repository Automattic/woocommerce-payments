<?php
/**
 * Class WCPay_Multi_Currency_Locale_Tests
 *
 * @package WooCommerce\Payments\Tests
 */

/**
 * WCPay\MultiCurrency\Locale unit tests.
 */
class WCPay_Multi_Currency_Locale_Tests extends WP_UnitTestCase {
	/**
	 * WCPay\MultiCurrency\Locale instance.
	 *
	 * @var WCPay\MultiCurrency\Locale
	 */
	private $locale;

	/**
	 * Pre-test setup
	 */
	public function setUp() {
		parent::setUp();

		$this->locale = new WCPay\MultiCurrency\Locale();
	}

	public function tearDown() {
		wp_set_current_user( 0 );
		remove_all_filters( 'locale' );
	}

	public function test_get_currency_format_returns_false() {
		$this->assertFalse( $this->locale->get_currency_format( 'FFF' ) );
	}

	public function test_get_currency_format_returns_single_locale_correctly() {
		$expected = $this->mock_get_currency_format( 'JPY' );
		$this->assertSame( $expected, $this->locale->get_currency_format( 'JPY' ) );
	}

	public function test_get_currency_format_returns_multiple_locale_correctly() {
		$expected = $this->mock_get_currency_format( 'GBP' );
		$this->assertSame( $expected, $this->locale->get_currency_format( 'GBP' ) );
	}

	public function test_get_user_locale_country_returns_default_locale_country() {
		$this->assertSame( 'US', $this->locale->get_user_locale_country() );
	}

	public function test_get_user_locale_country_returns_filtered_locale_country() {
		$this->mock_locale( 'pt_BR' );

		$this->assertSame( 'BR', $this->locale->get_user_locale_country() );
	}

	public function test_get_user_locale_country_returns_user_locale_country() {
		$this->mock_locale( 'pt_BR' ); // Make sure filtered locale is ignored.

		wp_set_current_user( 1 );
		wp_get_current_user()->locale = 'en_GB';

		$this->assertSame( 'GB', $this->locale->get_user_locale_country() );
	}

	public function test_transient_data_set() {
		$this->assertTrue( is_array( get_transient( 'wcpay_multi_currency_locale_data' ) ) );
	}

	private function mock_locale( $locale ) {
		add_filter(
			'locale',
			function () use ( $locale ) {
				return $locale;
			}
		);
	}

	private function mock_get_currency_format( $currency_code ) {
		// Formats are based on what's returned from load_locale_data.
		$currency_format = [
			'GBP' => [
				'GB' => [
					'currency_pos' => 'left',
					'thousand_sep' => ',',
					'decimal_sep'  => '.',
					'num_decimals' => 2,
				],
				'GG' => [
					'currency_pos' => 'left',
					'thousand_sep' => ',',
					'decimal_sep'  => '.',
					'num_decimals' => 2,
				],
				'IM' => [
					'currency_pos' => 'left',
					'thousand_sep' => ',',
					'decimal_sep'  => '.',
					'num_decimals' => 2,
				],
				'JE' => [
					'currency_pos' => 'left',
					'thousand_sep' => ',',
					'decimal_sep'  => '.',
					'num_decimals' => 2,
				],
			],
			'JPY' => [
				'JP' => [
					'currency_pos' => 'left',
					'thousand_sep' => ',',
					'decimal_sep'  => '.',
					'num_decimals' => 0,
				],
			],
		];
		if ( ! empty( $currency_format[ $currency_code ] ) ) {
			return $currency_format[ $currency_code ];
		}
		return false;
	}
}
