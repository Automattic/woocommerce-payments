<?php
/**
 * Class WCPay_Multi_Currency_Frontend_Currencies_Tests
 *
 * @package WooCommerce\Payments\Tests
 */

/**
 * WCPay\Multi_Currency\Frontend_Currencies unit tests.
 */
class WCPay_Multi_Currency_Frontend_Currencies_Tests extends WP_UnitTestCase {
	/**
	 * Mock WCPay\Multi_Currency\Multi_Currency.
	 *
	 * @var WCPay\Multi_Currency\Multi_Currency|PHPUnit_Framework_MockObject_MockObject
	 */
	private $mock_multi_currency;

	/**
	 * WCPay\Multi_Currency\Frontend_Currencies instance.
	 *
	 * @var WCPay\Multi_Currency\Frontend_Currencies
	 */
	private $frontend_currencies;

	public function setUp() {
		parent::setUp();

		$this->mock_multi_currency = $this->createMock( WCPay\Multi_Currency\Multi_Currency::class );

		$this->frontend_currencies = new WCPay\Multi_Currency\Frontend_Currencies( $this->mock_multi_currency );
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
		];
	}

	public function test_get_woocommerce_currency() {
		$current_currency = new WCPay\Multi_Currency\Currency( 'USD' );
		$this->mock_multi_currency->method( 'get_selected_currency' )->willReturn( $current_currency );

		$this->assertSame( 'USD', $this->frontend_currencies->get_woocommerce_currency() );
	}

	public function test_get_price_decimals_returns_default_settings() {
		$current_currency = new WCPay\Multi_Currency\Currency( 'RANDOM_CODE' );
		$this->mock_multi_currency->method( 'get_selected_currency' )->willReturn( $current_currency );

		$this->assertEquals( 2, $this->frontend_currencies->get_price_decimals() );
	}

	public function test_get_price_decimals_returns_currency_settings() {
		$current_currency = new WCPay\Multi_Currency\Currency( 'JPY' );
		$this->mock_multi_currency->method( 'get_selected_currency' )->willReturn( $current_currency );

		$this->assertEquals( 0, $this->frontend_currencies->get_price_decimals() );
	}

	public function test_get_price_decimals_returns_filtered_settings() {
		$current_currency = new WCPay\Multi_Currency\Currency( 'JPY' );
		$this->mock_multi_currency->method( 'get_selected_currency' )->willReturn( $current_currency );
		$this->mock_currency_settings( 'jpy', [ 'num_decimals' => 1 ] );

		$this->assertEquals( 1, $this->frontend_currencies->get_price_decimals() );
	}

	public function test_get_price_decimal_separator_returns_default_settings() {
		$current_currency = new WCPay\Multi_Currency\Currency( 'RANDOM_CODE' );
		$this->mock_multi_currency->method( 'get_selected_currency' )->willReturn( $current_currency );

		$this->assertEquals( '.', $this->frontend_currencies->get_price_decimal_separator() );
	}

	public function test_get_price_decimal_separator_returns_currency_settings() {
		$current_currency = new WCPay\Multi_Currency\Currency( 'BRL' );
		$this->mock_multi_currency->method( 'get_selected_currency' )->willReturn( $current_currency );

		$this->assertEquals( ',', $this->frontend_currencies->get_price_decimal_separator() );
	}

	public function test_get_price_decimal_separator_returns_filtered_settings() {
		$current_currency = new WCPay\Multi_Currency\Currency( 'BRL' );
		$this->mock_multi_currency->method( 'get_selected_currency' )->willReturn( $current_currency );
		$this->mock_currency_settings( 'brl', [ 'decimal_sep' => '/' ] );

		$this->assertEquals( '/', $this->frontend_currencies->get_price_decimal_separator() );
	}

	public function test_get_price_thousand_separator_returns_default_settings() {
		$current_currency = new WCPay\Multi_Currency\Currency( 'RANDOM_CODE' );
		$this->mock_multi_currency->method( 'get_selected_currency' )->willReturn( $current_currency );

		$this->assertEquals( ',', $this->frontend_currencies->get_price_thousand_separator() );
	}

	public function test_get_price_thousand_separator_returns_currency_settings() {
		$current_currency = new WCPay\Multi_Currency\Currency( 'BRL' );
		$this->mock_multi_currency->method( 'get_selected_currency' )->willReturn( $current_currency );

		$this->assertEquals( '.', $this->frontend_currencies->get_price_thousand_separator() );
	}

	public function test_get_price_thousand_separator_returns_filtered_settings() {
		$current_currency = new WCPay\Multi_Currency\Currency( 'BRL' );
		$this->mock_multi_currency->method( 'get_selected_currency' )->willReturn( $current_currency );
		$this->mock_currency_settings( 'brl', [ 'thousand_sep' => '/' ] );

		$this->assertEquals( '/', $this->frontend_currencies->get_price_thousand_separator() );
	}

	public function test_get_woocommerce_price_format_returns_default_settings() {
		$current_currency = new WCPay\Multi_Currency\Currency( 'RANDOM_CODE' );
		$this->mock_multi_currency->method( 'get_selected_currency' )->willReturn( $current_currency );

		$this->assertEquals( '%1$s%2$s', $this->frontend_currencies->get_woocommerce_price_format() );
	}

	public function test_get_woocommerce_price_format_returns_currency_settings() {
		$current_currency = new WCPay\Multi_Currency\Currency( 'HUF' );
		$this->mock_multi_currency->method( 'get_selected_currency' )->willReturn( $current_currency );

		$this->assertEquals( '%2$s&nbsp;%1$s', $this->frontend_currencies->get_woocommerce_price_format() );
	}

	public function test_get_woocommerce_price_format_returns_filtered_settings() {
		$current_currency = new WCPay\Multi_Currency\Currency( 'HUF' );
		$this->mock_multi_currency->method( 'get_selected_currency' )->willReturn( $current_currency );
		$this->mock_currency_settings( 'huf', [ 'currency_pos' => 'left_space' ] );

		$this->assertEquals( '%1$s&nbsp;%2$s', $this->frontend_currencies->get_woocommerce_price_format() );
	}

	/**
	 * @dataProvider currency_format_provider
	 */
	public function test_get_woocommerce_price_format_outputs_right_format( $currency_pos, $expected_format ) {
		$current_currency = new WCPay\Multi_Currency\Currency( 'USD' );
		$this->mock_multi_currency->method( 'get_selected_currency' )->willReturn( $current_currency );

		$this->mock_currency_settings( 'usd', [ 'currency_pos' => $currency_pos ] );
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

	private function mock_currency_settings( $currency_code, $currency_settings ) {
		add_filter(
			'wcpay_multi_currency_' . $currency_code . '_settings',
			function () use ( $currency_settings ) {
				return $currency_settings;
			}
		);
	}
}
