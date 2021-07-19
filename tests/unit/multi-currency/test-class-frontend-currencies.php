<?php
/**
 * Class WCPay_Multi_Currency_Frontend_Currencies_Tests
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\MultiCurrency\Currency;
use WCPay\MultiCurrency\FrontendCurrencies;
use WCPay\MultiCurrency\MultiCurrency;
use WCPay\MultiCurrency\Locale;

/**
 * FrontendCurrencies unit tests.
 */
class WCPay_Multi_Currency_Frontend_Currencies_Tests extends WP_UnitTestCase {
	/**
	 * Mock Locale.
	 *
	 * @var Locale|PHPUnit_Framework_MockObject_MockObject
	 */
	private $mock_locale;

	/**
	 * Mock MultiCurrency.
	 *
	 * @var MultiCurrency|PHPUnit_Framework_MockObject_MockObject
	 */
	private $mock_multi_currency;

	/**
	 * FrontendCurrencies instance.
	 *
	 * @var FrontendCurrencies
	 */
	private $frontend_currencies;

	public function setUp() {
		parent::setUp();

		$this->mock_locale         = $this->createMock( Locale::class );
		$this->mock_multi_currency = $this->createMock( MultiCurrency::class );

		$this->frontend_currencies = new FrontendCurrencies( $this->mock_multi_currency, $this->mock_locale );
	}

	public function tearDown() {
		remove_all_filters( 'wcpay_multi_currency_currency_settings' );

		parent::tearDown();
	}

	/**
	 * @dataProvider woocommerce_filter_provider
	 */
	public function test_registers_woocommerce_filter( $filter, $function_name ) {
		$this->assertGreaterThan(
			10,
			has_filter( $filter, [ $this->frontend_currencies, $function_name ] ),
			"Filter '$filter' was not registered with '$function_name' with a priority higher than the default"
		);
	}

	public function woocommerce_filter_provider() {
		return [
			[ 'woocommerce_currency', 'get_woocommerce_currency' ],
			[ 'wc_get_price_decimals', 'get_price_decimals' ],
			[ 'wc_get_price_decimal_separator', 'get_price_decimal_separator' ],
			[ 'wc_get_price_thousand_separator', 'get_price_thousand_separator' ],
			[ 'woocommerce_price_format', 'get_woocommerce_price_format' ],
			[ 'woocommerce_cart_hash', 'add_currency_to_cart_hash' ],
		];
	}

	public function test_get_woocommerce_currency() {
		$current_currency = new Currency( 'USD' );
		$this->mock_multi_currency->method( 'get_selected_currency' )->willReturn( $current_currency );

		$this->assertSame( 'USD', $this->frontend_currencies->get_woocommerce_currency() );
	}

	public function test_get_price_decimals_returns_default_settings() {
		$current_currency = new Currency( 'RANDOM_CODE' );
		$this->mock_multi_currency->method( 'get_selected_currency' )->willReturn( $current_currency );
		$this->mock_locale
			->expects( $this->once() )
			->method( 'get_currency_format' )
			->willReturn( $this->mock_get_currency_format( $current_currency ) );

		$this->assertEquals( 2, $this->frontend_currencies->get_price_decimals() );
	}

	public function test_get_price_decimals_returns_currency_default_settings() {
		$current_currency = new Currency( 'JPY' );
		$this->mock_multi_currency->method( 'get_selected_currency' )->willReturn( $current_currency );
		$this->mock_locale
			->expects( $this->once() )
			->method( 'get_currency_format' )
			->willReturn( $this->mock_get_currency_format( $current_currency ) );

		$this->assertEquals( 0, $this->frontend_currencies->get_price_decimals() );
	}

	public function test_get_price_decimals_returns_currency_settings_by_locale() {
		$current_currency = new Currency( 'HUF' );
		$this->mock_multi_currency->method( 'get_selected_currency' )->willReturn( $current_currency );
		$this->mock_locale->method( 'get_user_locale' )->willReturn( 'hu_HU' );
		$this->mock_locale
			->expects( $this->once() )
			->method( 'get_currency_format' )
			->willReturn( $this->mock_get_currency_format( $current_currency ) );

		$this->assertEquals( 0, $this->frontend_currencies->get_price_decimals() );
	}

	public function test_get_price_decimals_returns_filtered_settings() {
		$current_currency = new Currency( 'JPY' );
		$this->mock_multi_currency->method( 'get_selected_currency' )->willReturn( $current_currency );
		$this->mock_locale
			->expects( $this->once() )
			->method( 'get_currency_format' )
			->willReturn( $this->mock_get_currency_format( $current_currency ) );
		$this->mock_currency_format( 'jpy', [ 'num_decimals' => 1 ] );

		$this->assertEquals( 1, $this->frontend_currencies->get_price_decimals() );
	}

	public function test_get_price_decimal_separator_returns_default_settings() {
		$current_currency = new Currency( 'RANDOM_CODE' );
		$this->mock_multi_currency->method( 'get_selected_currency' )->willReturn( $current_currency );
		$this->mock_locale
			->expects( $this->once() )
			->method( 'get_currency_format' )
			->willReturn( $this->mock_get_currency_format( $current_currency ) );

		$this->assertEquals( '.', $this->frontend_currencies->get_price_decimal_separator() );
	}

	public function test_get_price_decimal_separator_returns_currency_default_settings() {
		$current_currency = new Currency( 'BRL' );
		$this->mock_multi_currency->method( 'get_selected_currency' )->willReturn( $current_currency );
		$this->mock_locale
			->expects( $this->once() )
			->method( 'get_currency_format' )
			->willReturn( $this->mock_get_currency_format( $current_currency ) );

		$this->assertEquals( ',', $this->frontend_currencies->get_price_decimal_separator() );
	}

	public function test_get_price_decimal_separator_returns_currency_settings_by_locale() {
		$current_currency = new Currency( 'EUR' );
		$this->mock_multi_currency->method( 'get_selected_currency' )->willReturn( $current_currency );
		$this->mock_locale->method( 'get_user_locale' )->willReturn( 'nl_NL' );
		$this->mock_locale
			->expects( $this->once() )
			->method( 'get_currency_format' )
			->willReturn( $this->mock_get_currency_format( $current_currency ) );

		$this->assertEquals( ',', $this->frontend_currencies->get_price_decimal_separator() );
	}

	public function test_get_price_decimal_separator_returns_filtered_settings() {
		$current_currency = new Currency( 'BRL' );
		$this->mock_multi_currency->method( 'get_selected_currency' )->willReturn( $current_currency );
		$this->mock_locale
			->expects( $this->once() )
			->method( 'get_currency_format' )
			->willReturn( $this->mock_get_currency_format( $current_currency ) );
		$this->mock_currency_format( 'brl', [ 'decimal_sep' => '/' ] );

		$this->assertEquals( '/', $this->frontend_currencies->get_price_decimal_separator() );
	}

	public function test_get_price_thousand_separator_returns_default_settings() {
		$current_currency = new Currency( 'RANDOM_CODE' );
		$this->mock_multi_currency->method( 'get_selected_currency' )->willReturn( $current_currency );
		$this->mock_locale
			->expects( $this->once() )
			->method( 'get_currency_format' )
			->willReturn( $this->mock_get_currency_format( $current_currency ) );

		$this->assertEquals( ',', $this->frontend_currencies->get_price_thousand_separator() );
	}

	public function test_get_price_thousand_separator_returns_currency_default_settings() {
		$current_currency = new Currency( 'BRL' );
		$this->mock_multi_currency->method( 'get_selected_currency' )->willReturn( $current_currency );
		$this->mock_locale
			->expects( $this->once() )
			->method( 'get_currency_format' )
			->willReturn( $this->mock_get_currency_format( $current_currency ) );

		$this->assertEquals( '.', $this->frontend_currencies->get_price_thousand_separator() );
	}

	public function test_get_price_thousand_separator_returns_currency_settings_by_locale() {
		$current_currency = new Currency( 'EUR' );
		$this->mock_multi_currency->method( 'get_selected_currency' )->willReturn( $current_currency );
		$this->mock_locale->method( 'get_user_locale' )->willReturn( 'nl_BE' );
		$this->mock_locale
			->expects( $this->once() )
			->method( 'get_currency_format' )
			->willReturn( $this->mock_get_currency_format( $current_currency ) );

		$this->assertEquals( '.', $this->frontend_currencies->get_price_thousand_separator() );
	}

	public function test_get_price_thousand_separator_returns_filtered_settings() {
		$current_currency = new Currency( 'BRL' );
		$this->mock_multi_currency->method( 'get_selected_currency' )->willReturn( $current_currency );
		$this->mock_locale
			->expects( $this->once() )
			->method( 'get_currency_format' )
			->willReturn( $this->mock_get_currency_format( $current_currency ) );
		$this->mock_currency_format( 'brl', [ 'thousand_sep' => '/' ] );

		$this->assertEquals( '/', $this->frontend_currencies->get_price_thousand_separator() );
	}

	public function test_get_woocommerce_price_format_returns_default_settings() {
		$current_currency = new Currency( 'RANDOM_CODE' );
		$this->mock_multi_currency->method( 'get_selected_currency' )->willReturn( $current_currency );
		$this->mock_locale
			->expects( $this->once() )
			->method( 'get_currency_format' )
			->willReturn( $this->mock_get_currency_format( $current_currency ) );

		$this->assertEquals( '%1$s%2$s', $this->frontend_currencies->get_woocommerce_price_format() );
	}

	public function test_get_woocommerce_price_format_returns_currency_default_settings() {
		$current_currency = new Currency( 'HUF' );
		$this->mock_multi_currency->method( 'get_selected_currency' )->willReturn( $current_currency );
		$this->mock_locale
			->expects( $this->once() )
			->method( 'get_currency_format' )
			->willReturn( $this->mock_get_currency_format( $current_currency ) );

		$this->assertEquals( '%2$s%1$s', $this->frontend_currencies->get_woocommerce_price_format() );
	}

	public function test_get_woocommerce_price_format_returns_currency_settings_by_locale() {
		$current_currency = new Currency( 'EUR' );
		$this->mock_multi_currency->method( 'get_selected_currency' )->willReturn( $current_currency );
		$this->mock_locale->method( 'get_user_locale' )->willReturn( 'es_ES' );
		$this->mock_locale
			->expects( $this->once() )
			->method( 'get_currency_format' )
			->willReturn( $this->mock_get_currency_format( $current_currency ) );

		$this->assertEquals( '%2$s&nbsp;%1$s', $this->frontend_currencies->get_woocommerce_price_format() );
	}

	public function test_get_woocommerce_price_format_returns_filtered_settings() {
		$current_currency = new Currency( 'HUF' );
		$this->mock_multi_currency->method( 'get_selected_currency' )->willReturn( $current_currency );
		$this->mock_locale
			->expects( $this->once() )
			->method( 'get_currency_format' )
			->willReturn( $this->mock_get_currency_format( $current_currency ) );
		$this->mock_currency_format( 'huf', [ 'currency_pos' => 'left_space' ] );

		$this->assertEquals( '%1$s&nbsp;%2$s', $this->frontend_currencies->get_woocommerce_price_format() );
	}

	/**
	 * @dataProvider currency_format_provider
	 */
	public function test_get_woocommerce_price_format_outputs_right_format( $currency_pos, $expected_format ) {
		$current_currency = new Currency( 'USD' );
		$this->mock_multi_currency->method( 'get_selected_currency' )->willReturn( $current_currency );
		$this->mock_locale
			->expects( $this->once() )
			->method( 'get_currency_format' )
			->willReturn( $this->mock_get_currency_format( $current_currency ) );

		$this->mock_currency_format( 'usd', [ 'currency_pos' => $currency_pos ] );
		$this->assertEquals( $expected_format, $this->frontend_currencies->get_woocommerce_price_format() );
	}

	public function currency_format_provider() {
		return [
			[ '', '%1$s%2$s' ],
			[ 'random_value', '%1$s%2$s' ],
			[ 'left', '%1$s%2$s' ],
			[ 'right', '%2$s%1$s' ],
			[ 'left_space', '%1$s&nbsp;%2$s' ],
			[ 'right_space', '%2$s&nbsp;%1$s' ],
		];
	}

	public function test_add_currency_to_cart_hash_adds_currency_and_rate() {
		$current_currency = new Currency( 'GBP', 0.71 );
		$this->mock_multi_currency->method( 'get_selected_currency' )->willReturn( $current_currency );

		$this->assertSame(
			md5( 'cart_hashGBP0.71' ),
			$this->frontend_currencies->add_currency_to_cart_hash( 'cart_hash' )
		);
	}

	private function mock_currency_format( $currency_code, $currency_settings ) {
		add_filter(
			'wcpay_multi_currency_' . $currency_code . '_format',
			function () use ( $currency_settings ) {
				return $currency_settings;
			}
		);
	}

	private function mock_get_currency_format( $currency ) {
		$currency_code = $currency->get_code();
		// Formats are based on what's returned from Locale.
		$currency_format = [
			'BRL' => [
				'default' => [
					'currency_pos' => 'left_space',
					'thousand_sep' => '.',
					'decimal_sep'  => ',',
					'num_decimals' => 2,
				],
				// Fake pt_BR to assert the 'default' entry is used.
				'pt_BR'   => [
					'currency_pos' => 'right',
					'thousand_sep' => '-',
					'decimal_sep'  => '_',
					'num_decimals' => 1,
				],
			],
			'GBP' => [
				'en_GB' => [
					'currency_pos' => 'left',
					'thousand_sep' => ',',
					'decimal_sep'  => '.',
					'num_decimals' => 2,
				],
			],
			'EUR' => [
				'nl_BE' => [
					'currency_pos' => 'left_space',
					'thousand_sep' => '.',
					'decimal_sep'  => ',',
					'num_decimals' => 2,
				],
				'de_DE' => [
					'currency_pos' => 'right_space',
					'thousand_sep' => '.',
					'decimal_sep'  => ',',
					'num_decimals' => 2,
				],
				'es_ES' => [
					'currency_pos' => 'right_space',
					'thousand_sep' => '.',
					'decimal_sep'  => ',',
					'num_decimals' => 2,
				],
				'nl_NL' => [
					'currency_pos' => 'left_space',
					'thousand_sep' => '.',
					'decimal_sep'  => ',',
					'num_decimals' => 2,
				],
			],
			'HUF' => [
				// Fake default to assert the locale-specific entry is used.
				'default' => [
					'currency_pos' => 'right',
					'thousand_sep' => '-',
					'decimal_sep'  => '^',
					'num_decimals' => 2,
				],
				'hu_HU'   => [
					'currency_pos' => 'right_space',
					'thousand_sep' => '',
					'decimal_sep'  => ',',
					'num_decimals' => 0,
				],
			],
			'JPY' => [
				// Fake jp_JP to assert the 'default' entry is used.
				'jp_JP'   => [
					'currency_pos' => 'right',
					'thousand_sep' => '^',
					'decimal_sep'  => '-',
					'num_decimals' => 3,
				],
				'default' => [
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
