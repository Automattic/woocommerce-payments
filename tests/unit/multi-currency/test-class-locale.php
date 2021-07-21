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

	public function test_get_user_locale_returns_default_locale() {
		$this->assertSame( 'en_US', $this->locale->get_user_locale() );
	}

	public function test_get_user_locale_returns_filtered_locale() {
		$this->mock_locale( 'pt_BR' );

		$this->assertSame( 'pt_BR', $this->locale->get_user_locale() );
	}

	public function test_get_user_locale_returns_user_locale() {
		$this->mock_locale( 'pt_BR' ); // Make sure filtered locale is ignored.

		wp_set_current_user( 1 );
		wp_get_current_user()->locale = 'en_GB';

		$this->assertSame( 'en_GB', $this->locale->get_user_locale() );
	}

	public function test_transient_data_set() {
		$this->assertTrue( is_array( get_transient( 'wcpay_multi_currency_currency_format' ) ) );
		$this->assertTrue( is_array( get_transient( 'wcpay_multi_currency_locale_info' ) ) );
	}

	public function test_get_currency_by_customer_location_returns_currency_code() {
		update_option( 'wcpay_multi_currency_enable_auto_currency', 'yes' );
		update_option( 'woocommerce_default_customer_address', 'geolocation' );
		add_filter(
			'woocommerce_geolocate_ip',
			function() {
				return 'CA';
			}
		);
		$this->assertSame( 'CAD', $this->locale->get_currency_by_customer_location() );
	}

	public function test_get_currency_by_customer_location_returns_null() {
		update_option( 'woocommerce_default_customer_address', 'geolocation' );
		add_filter(
			'woocommerce_geolocate_ip',
			function() {
				return '';
			}
		);
		add_filter(
			'woocommerce_customer_default_location',
			function() {
				return '';
			}
		);
		$this->assertSame( null, $this->locale->get_currency_by_customer_location() );
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
				'en_GB'   => [
					'currency_pos' => 'left',
					'thousand_sep' => ',',
					'decimal_sep'  => '.',
					'num_decimals' => 2,
				],
				'en_GG'   => [
					'currency_pos' => 'left',
					'thousand_sep' => ',',
					'decimal_sep'  => '.',
					'num_decimals' => 2,
				],
				'en_IM'   => [
					'currency_pos' => 'left',
					'thousand_sep' => ',',
					'decimal_sep'  => '.',
					'num_decimals' => 2,
				],
				'en_JE'   => [
					'currency_pos' => 'left',
					'thousand_sep' => ',',
					'decimal_sep'  => '.',
					'num_decimals' => 2,
				],
				'ga_GB'   => [
					'currency_pos' => 'left',
					'thousand_sep' => ',',
					'decimal_sep'  => '.',
					'num_decimals' => 2,
				],
				'default' => [
					'currency_pos' => 'left',
					'thousand_sep' => ',',
					'decimal_sep'  => '.',
					'num_decimals' => 2,
				],
				'cy_GB'   => [
					'currency_pos' => 'left',
					'thousand_sep' => ',',
					'decimal_sep'  => '.',
					'num_decimals' => 2,
				],
				'gd_GB'   => [
					'currency_pos' => 'left',
					'thousand_sep' => ',',
					'decimal_sep'  => '.',
					'num_decimals' => 2,
				],
				'gv_IM'   => [
					'currency_pos' => 'left',
					'thousand_sep' => ',',
					'decimal_sep'  => '.',
					'num_decimals' => 2,
				],
			],
			'JPY' => [
				'default' => [
					'currency_pos' => 'left',
					'thousand_sep' => ',',
					'decimal_sep'  => '.',
					'num_decimals' => 0,
				],
				'ja_JP'   => [
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
