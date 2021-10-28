<?php
/**
 * Class WCPay_Multi_Currency_WooCommerceBookings_Tests
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\MultiCurrency\Compatibility\WooCommerceBookings;
use WCPay\MultiCurrency\Currency;
use WCPay\MultiCurrency\FrontendCurrencies;
use WCPay\MultiCurrency\MultiCurrency;
use WCPay\MultiCurrency\Utils;

/**
 * WCPay\MultiCurrency\Compatibility\WooCommerceBookings unit tests.
 */
class WCPay_Multi_Currency_WooCommerceBookings_Tests extends WP_UnitTestCase {

	/**
	 * Mock WCPay\MultiCurrency\MultiCurrency.
	 *
	 * @var WCPay\MultiCurrency\MultiCurrency|PHPUnit_Framework_MockObject_MockObject
	 */
	private $mock_multi_currency;

	/**
	 * Mock WCPay\MultiCurrency\Utils.
	 *
	 * @var WCPay\MultiCurrency\Utils|PHPUnit_Framework_MockObject_MockObject
	 */
	private $mock_utils;

	/**
	 * Mock WCPay\MultiCurrency\FrontendCurrencies.
	 *
	 * @var WCPay\MultiCurrency\FrontendCurrencies|PHPUnit_Framework_MockObject_MockObject
	 */
	private $mock_frontend_currencies;

	/**
	 * WCPay\MultiCurrency\Compatibility\WooCommerceBookings instance.
	 *
	 * @var WCPay\MultiCurrency\Compatibility\WooCommerceBookings
	 */
	private $woocommerce_bookings;

	/**
	 * Pre-test setup
	 */
	public function setUp() {
		parent::setUp();

		$this->mock_multi_currency      = $this->createMock( MultiCurrency::class );
		$this->mock_utils               = $this->createMock( Utils::class );
		$this->mock_frontend_currencies = $this->createMock( FrontendCurrencies::class );
		$this->woocommerce_bookings     = new WooCommerceBookings( $this->mock_multi_currency, $this->mock_utils, $this->mock_frontend_currencies );
	}

	public function test_get_price_returns_empty_string() {
		$expected = '';
		$this->assertSame( $expected, $this->woocommerce_bookings->get_price( $expected ) );
	}

	public function test_get_price_returns_converted_price() {
		$expected = 42.0;
		$this->mock_multi_currency->method( 'get_price' )->willReturn( $expected );
		$this->assertSame( $expected, $this->woocommerce_bookings->get_price( 12 ) );
	}

	public function test_get_resource_prices_returns_non_array_directly() {
		$expected = 'Not an array.';
		$this->assertSame( $expected, $this->woocommerce_bookings->get_resource_prices( $expected ) );
	}

	public function test_get_resource_prices_returns_converted_prices() {
		$expected = [ 42.0, '' ];
		$prices   = [ 12, '' ];
		$this->mock_multi_currency
			->expects( $this->once() )
			->method( 'get_price' )
			->with( 12 )
			->willReturn( 42.0 );

		$this->assertSame( $expected, $this->woocommerce_bookings->get_resource_prices( $prices ) );
	}

	// If false is passed, it should automatically return false.
	public function test_should_convert_product_price_returns_false_if_false_passed() {
		$this->mock_utils->expects( $this->exactly( 0 ) )->method( 'is_call_in_backtrace' );
		$this->assertFalse( $this->woocommerce_bookings->should_convert_product_price( false ) );
	}

	// If the first two sets of calls are found, it should return false.
	public function test_should_convert_product_price_returns_false_if_cart_calls_found() {
		$first_calls  = [ 'WC_Product_Booking->get_price' ];
		$second_calls = [
			'WC_Cart_Totals->calculate_item_totals',
			'WC_Cart->get_product_price',
			'WC_Cart->get_product_subtotal',
		];
		$this->mock_utils
			->expects( $this->exactly( 2 ) )
			->method( 'is_call_in_backtrace' )
			->withConsecutive( [ $first_calls ], [ $second_calls ] )
			->willReturn( true, true );
		$this->assertFalse( $this->woocommerce_bookings->should_convert_product_price( true ) );
	}

	// If the last set of calls are found, it should return false.
	// This also tests to make sure if the first set of calls is found, but not the second, it continues.
	public function test_should_convert_product_price_returns_false_if_display_calls_found() {
		$first_calls  = [ 'WC_Product_Booking->get_price' ];
		$second_calls = [
			'WC_Cart_Totals->calculate_item_totals',
			'WC_Cart->get_product_price',
			'WC_Cart->get_product_subtotal',
		];
		$third_calls  = [ 'WC_Product_Booking->get_price_html' ];
		$this->mock_utils
			->expects( $this->exactly( 3 ) )
			->method( 'is_call_in_backtrace' )
			->withConsecutive( [ $first_calls ], [ $second_calls ], [ $third_calls ] )
			->willReturn( true, false, true );
		$this->assertFalse( $this->woocommerce_bookings->should_convert_product_price( true ) );
	}

	// If no calls are found, it should return true.
	public function test_should_convert_product_price_returns_true_if_no_calls_found() {
		$first_calls = [ 'WC_Product_Booking->get_price' ];
		$third_calls = [ 'WC_Product_Booking->get_price_html' ];
		$this->mock_utils
			->expects( $this->exactly( 2 ) )
			->method( 'is_call_in_backtrace' )
			->withConsecutive( [ $first_calls ], [ $third_calls ] )
			->willReturn( false, false );
		$this->assertTrue( $this->woocommerce_bookings->should_convert_product_price( true ) );
	}

	public function test_filter_wc_price_args_returns_expected_results() {
		$defaults = [
			'currency'           => '',
			'decimal_separator'  => '',
			'thousand_separator' => '',
			'decimals'           => 0,
			'price_format'       => '',
		];
		$expected = [
			'currency'           => 'CAD',
			'decimal_separator'  => '.',
			'thousand_separator' => ',',
			'decimals'           => 2,
			'price_format'       => '%1$s%2$s',
		];

		$this->mock_multi_currency->method( 'get_selected_currency' )->willReturn( new Currency( $expected['currency'] ) );
		$this->mock_frontend_currencies->method( 'get_price_decimal_separator' )->willReturn( $expected['decimal_separator'] );
		$this->mock_frontend_currencies->method( 'get_price_thousand_separator' )->willReturn( $expected['thousand_separator'] );
		$this->mock_frontend_currencies->method( 'get_price_decimals' )->willReturn( $expected['decimals'] );
		$this->mock_frontend_currencies->method( 'get_woocommerce_price_format' )->willReturn( $expected['price_format'] );

		$this->assertSame( $expected, $this->woocommerce_bookings->filter_wc_price_args( $defaults ) );
	}
}
