<?php
/**
 * Class WCPay_Multi_Currency_Backend_Currencies_Tests
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\MultiCurrency\BackendCurrencies;
use WCPay\MultiCurrency\MultiCurrency;

/**
 * BackendCurrencies unit tests.
 */
class WCPay_Multi_Currency_Backend_Currencies_Tests extends WP_UnitTestCase {
	/**
	 * Mock WC_Payments_Localization_Service.
	 *
	 * @var WC_Payments_Localization_Service|PHPUnit_Framework_MockObject_MockObject
	 */
	private $mock_localization_service;

	/**
	 * Mock MultiCurrency.
	 *
	 * @var MultiCurrency|PHPUnit_Framework_MockObject_MockObject
	 */
	private $mock_multi_currency;

	/**
	 * BackendCurrencies instance.
	 *
	 * @var BackendCurrencies
	 */
	private $backend_currencies;

	public function setUp() {
		parent::setUp();

		$this->mock_localization_service = $this->createMock( WC_Payments_Localization_Service::class );
		$this->mock_multi_currency       = $this->createMock( MultiCurrency::class );

		// Mock admin part.
		set_current_screen( 'edit-post' );

		$this->backend_currencies = new BackendCurrencies( $this->mock_multi_currency, $this->mock_localization_service );
	}

	public function tearDown() {
		remove_all_filters( 'wcpay_multi_currency_currency_settings' );
		set_current_screen( 'front' );

		parent::tearDown();
	}

	/**
	 * @dataProvider woocommerce_filter_provider
	 */
	public function test_registers_woocommerce_filter( $filter, $function_name ) {
		$this->assertGreaterThan(
			10,
			has_filter( $filter, [ $this->backend_currencies, $function_name ] ),
			"Filter '$filter' was not registered with '$function_name' with a priority higher than the default"
		);
	}

	/**
	 * @dataProvider woocommerce_filter_provider
	 */
	public function test_doesnt_register_woocommerce_filter_on_frontend( $filter, $function_name ) {

		$this->tearDown();
		$this->backend_currencies = new BackendCurrencies( $this->mock_multi_currency, $this->mock_localization_service );

		$this->assertFalse(
			has_filter( $filter, [ $this->backend_currencies, $function_name ] ),
			"Filter '$filter' was registered with '$function_name' on a frontend page"
		);
	}

	public function woocommerce_filter_provider() {
		return [
			[ 'wc_price_args', 'build_wc_price_args' ],
		];
	}

	public function test_get_price_currency_with_no_currency_argument() {
		$store_currency = get_option( 'woocommerce_currency' );
		$this->assertSame( $store_currency, $this->backend_currencies->get_price_currency( [] ) );
	}

	public function test_get_price_currency_with_a_currency_argument() {
		$this->assertSame( 'AUD', $this->backend_currencies->get_price_currency( [ 'currency' => 'AUD' ] ) );
	}

	public function test_get_price_decimals_returns_num_decimals() {
		$this->mock_localization_service->method( 'get_currency_format' )->with( 'EUR' )->willReturn( [ 'num_decimals' => 3 ] );

		$this->assertEquals( 3, $this->backend_currencies->get_price_decimals( 'EUR' ) );
	}

	public function test_is_zero_decimal_currency_returns_true_with_zero_decimal_currency() {
		$this->mock_localization_service->method( 'get_currency_format' )->with( 'JPY' )->willReturn( [ 'num_decimals' => 0 ] );

		$this->assertTrue( $this->backend_currencies->is_zero_decimal_currency( 'JPY' ) );
	}

	public function test_is_zero_decimal_currency_returns_false_with_non_zero_decimal_currency() {
		$this->mock_localization_service->method( 'get_currency_format' )->with( 'EUR' )->willReturn( [ 'num_decimals' => 3 ] );

		$this->assertFalse( $this->backend_currencies->is_zero_decimal_currency( 'EUR' ) );
	}

	public function test_get_price_decimal_separator_returns_decimal_sep() {
		$this->mock_localization_service->method( 'get_currency_format' )->with( 'EUR' )->willReturn( [ 'decimal_sep' => '.' ] );

		$this->assertEquals( '.', $this->backend_currencies->get_price_decimal_separator( 'EUR' ) );
	}

	public function test_get_price_thousand_separator_returns_thousand_sep() {
		$this->mock_localization_service->method( 'get_currency_format' )->with( 'EUR' )->willReturn( [ 'thousand_sep' => ',' ] );

		$this->assertEquals( ',', $this->backend_currencies->get_price_thousand_separator( 'EUR' ) );
	}

	public function test_get_woocommerce_price_format_returns_format_for_currency_pos() {
		$this->mock_localization_service->method( 'get_currency_format' )->with( 'EUR' )->willReturn( [ 'currency_pos' => 'left' ] );

		$this->assertEquals( '%1$s%2$s', $this->backend_currencies->get_woocommerce_price_format( 'EUR' ) );
	}

	/**
	 * @dataProvider currency_format_provider
	 */
	public function test_get_woocommerce_price_format_outputs_right_format( $currency_pos, $expected_format ) {

		$this->mock_localization_service->method( 'get_currency_format' )->with( 'EUR' )->willReturn( [ 'currency_pos' => $currency_pos ] );

		$this->assertEquals( $expected_format, $this->backend_currencies->get_woocommerce_price_format( 'EUR' ) );
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
}
