<?php
/**
 * Class WCPay_Multi_Currency_WooCommerceNameYourPrice_Tests
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\MultiCurrency\Compatibility\WooCommerceNameYourPrice;
use WCPay\MultiCurrency\MultiCurrency;
use WCPay\MultiCurrency\Utils;
use WCPay\MultiCurrency\Currency;

/**
 * WCPay\MultiCurrency\Compatibility\WooCommerceNameYourPrice unit tests.
 */
class WCPay_Multi_Currency_WooCommerceNameYourPrice_Tests extends WCPAY_UnitTestCase {

	const NYP_CURRENCY = '_wcpay_multi_currency_nyp_currency';

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
	 * WCPay\MultiCurrency\Compatibility\WooCommerceNameYourPrice instance.
	 *
	 * @var WCPay\MultiCurrency\Compatibility\WooCommerceNameYourPrice
	 */
	private $woocommerce_nyp;

	/**
	 * WC_Payments_Localization_Service.
	 *
	 * @var WC_Payments_Localization_Service
	 */
	private $localization_service;

	/**
	 * Pre-test setup
	 */
	public function setUp(): void {
		parent::setUp();

		// Create the class instances needed for testing.
		$this->mock_multi_currency  = $this->createMock( MultiCurrency::class );
		$this->mock_utils           = $this->createMock( Utils::class );
		$this->woocommerce_nyp      = new WooCommerceNameYourPrice( $this->mock_multi_currency, $this->mock_utils );
		$this->localization_service = new WC_Payments_Localization_Service();

		// Set is_nyp to return false by default.
		$this->set_is_nyp( false );
	}

	// Tests the return values for get_nyp_prices.
	public function test_get_nyp_prices() {
		// Arrange: Set the expected price and set up the mock method.
		$price = 12.34;
		$this->mock_multi_currency
			->expects( $this->once() )
			->method( 'get_price' )
			->with( $price )
			->willReturn( $price * 2 );

		// Assert: Check that null is returned if null is passed.
		$this->assertEquals( null, $this->woocommerce_nyp->get_nyp_prices( null ) );

		// Assert: Check that the expected value is returned based on the price.
		$this->assertEquals( $price * 2, $this->woocommerce_nyp->get_nyp_prices( $price ) );
	}

	// Unmodified cart item should be returned if `is_nyp` returns false.
	public function test_add_initial_currency_returns_unmodified_cart_item_is_nyp_false() {
		// Arrange: Set up the cart item, set is_nyp to return false.
		$cart_item = [
			'nyp' => 'test',
		];
		$this->set_is_nyp( false );

		// Assert: Confirm the cart_item has been returned unmodified.
		$this->assertSame( $cart_item, $this->woocommerce_nyp->add_initial_currency( $cart_item, 42, null ) );
	}

	// Unmodified cart item should be returned if `nyp` is not in the cart item.
	public function test_add_initial_currency_returns_unmodified_cart_item_if_nyp_not_in_cart_item() {
		// Arrange: Set up the cart item, set is_nyp to return true.
		$cart_item = [];
		$this->set_is_nyp( true );

		// Assert: Confirm the cart_item has been returned unmodified.
		$this->assertSame( $cart_item, $this->woocommerce_nyp->add_initial_currency( $cart_item, 42, null ) );
	}

	// Check to make sure the proper elements are added to the cart_item array.
	public function test_add_initial_currency_returns_modified_cart_item() {
		// Arrange: Set up the currency used for the test.
		$currency = new Currency( $this->localization_service, 'EUR', 2.0 );

		// Arrange: Set up the cart_item and expected cart_item, set is_nyp to return true.
		$nyp_value          = 12.34;
		$cart_item          = [
			'nyp' => $nyp_value,
		];
		$expected_cart_item = [
			'nyp'          => $nyp_value,
			'nyp_currency' => $currency->get_code(),
			'nyp_original' => $nyp_value,
		];
		$this->set_is_nyp( true );

		// Arrange: Set up the get_selected_currency method mock.
		$this->mock_multi_currency
			->expects( $this->once() )
			->method( 'get_selected_currency' )
			->willReturn( $currency );

		// Assert: Check that the expected cart_item is returned.
		$this->assertSame( $expected_cart_item, $this->woocommerce_nyp->add_initial_currency( $cart_item, 42, null ) );
	}

	// Check to see that an unmodified cart item is returned if checks do not return true.
	public function test_convert_cart_currency_returns_unmodified_cart_item() {
		// Arrange: Set up the cart_item.
		$cart_item = [];
		// Assert: Confirm the cart_item is returned unmodified.
		$this->assertSame( $cart_item, $this->woocommerce_nyp->convert_cart_currency( $cart_item, null ) );

		// Arrange: Set up the cart_item.
		$cart_item = [
			'nyp_original' => 12.34,
		];
		// Assert: Confirm the cart_item is returned unmodified.
		$this->assertSame( $cart_item, $this->woocommerce_nyp->convert_cart_currency( $cart_item, null ) );

		// Arrange: Set up the cart_item.
		$cart_item = [
			'nyp_currency' => 'EUR',
		];
		// Assert: Confirm the cart_item is returned unmodified.
		$this->assertSame( $cart_item, $this->woocommerce_nyp->convert_cart_currency( $cart_item, null ) );
	}

	// If the selected currency matches the currency of the item, then it should just return the item.
	public function test_convert_cart_currency_returns_cart_item_with_original_value() {
		// Arrange: Set up the currency used for the test.
		$currency = new Currency( $this->localization_service, 'EUR', 2.0 );

		// Arrange: Set up the cart_item.
		$nyp_value = 12.34;
		$cart_item = [
			'nyp'          => $nyp_value * 2,
			'nyp_currency' => $currency->get_code(),
			'nyp_original' => $nyp_value,
			'data'         => WC_Helper_Product::create_simple_product(),
		];

		// Arrange: Set up the get_selected_currency method mock.
		$this->mock_multi_currency
			->expects( $this->once() )
			->method( 'get_selected_currency' )
			->willReturn( $currency );

		// Act: Attempt to convert the cart item amount.
		$cart_item = $this->woocommerce_nyp->convert_cart_currency( $cart_item, null );

		// Assert: Confirm the cart item currency meta matches the selected currency.
		$this->assertSame( $cart_item['data']->get_meta( WooCommerceNameYourPrice::NYP_CURRENCY ), $currency->get_code() );

		// Assert: Confirm the cart value is unmodified.
		$this->assertEquals( $nyp_value, $cart_item['nyp'] );
	}

	// Convert the amount of the item to the selected currency.
	public function test_convert_cart_currency_returns_cart_item_with_converted_value() {
		// Arrange: Set up the currencies used for the test.
		$item_currency     = new Currency( $this->localization_service, 'EUR', 2.0 );
		$selected_currency = new Currency( $this->localization_service, 'GBP', 0.5 );

		// Arrange: Set up the cart_item.
		$nyp_value = 12.34;
		$cart_item = [
			'nyp'          => $nyp_value * 2,
			'nyp_currency' => $item_currency->get_code(),
			'nyp_original' => $nyp_value,
			'data'         => WC_Helper_Product::create_simple_product(),
		];

		// Arrange: Calculated the expected_value.
		$expected_value = ( $nyp_value / $item_currency->get_rate() ) * $selected_currency->get_rate();

		// Arrange: Set up the mock_multi_currency method mocks.
		$this->mock_multi_currency
			->expects( $this->once() )
			->method( 'get_selected_currency' )
			->willReturn( $selected_currency );

		$this->mock_multi_currency
			->expects( $this->once() )
			->method( 'get_raw_conversion' )
			->with( $nyp_value, $selected_currency->get_code(), $item_currency->get_code() )
			->willReturn( $expected_value );

		// Act: Attempt to convert the cart item amount.
		$cart_item = $this->woocommerce_nyp->convert_cart_currency( $cart_item, null );

		// Assert: Confirm the cart_item nyp_currency value is unmodified.
		$this->assertSame( $cart_item['data']->get_meta( WooCommerceNameYourPrice::NYP_CURRENCY ), $item_currency->get_code() );

		// Assert: Confirm the cart_item value matches the expected value.
		$this->assertEquals( $expected_value, $cart_item['nyp'] );
	}

	// Convert the amount of the item into the default (selected) currency.
	public function test_convert_cart_currency_returns_cart_item_with_converted_value_with_default_currency() {
		// Arrange: Set up the currencies used for the test.
		$item_currency     = new Currency( $this->localization_service, 'EUR', 2.0 );
		$selected_currency = new Currency( $this->localization_service, 'USD', 1 );

		// Arrange: Set up the cart_item.
		$nyp_value = 12.34;
		$cart_item = [
			'nyp'          => $nyp_value * 2,
			'nyp_currency' => $item_currency->get_code(),
			'nyp_original' => $nyp_value,
			'data'         => WC_Helper_Product::create_simple_product(),
		];

		// Arrange: Calculated the expected_value.
		$expected_value = ( $nyp_value / $item_currency->get_rate() ) * $selected_currency->get_rate();

		// Arrange: Set up the mock_multi_currency method mocks.
		$this->mock_multi_currency
			->expects( $this->once() )
			->method( 'get_selected_currency' )
			->willReturn( $selected_currency );

		$this->mock_multi_currency
			->expects( $this->once() )
			->method( 'get_raw_conversion' )
			->with( $nyp_value, $selected_currency->get_code(), $item_currency->get_code() )
			->willReturn( $expected_value );

		// Act: Attempt to convert the cart item amount.
		$cart_item = $this->woocommerce_nyp->convert_cart_currency( $cart_item, null );

		// Assert: Confirm the cart_item nyp_currency value is unmodified.
		$this->assertSame( $cart_item['data']->get_meta( WooCommerceNameYourPrice::NYP_CURRENCY ), $item_currency->get_code() );

		// Assert: Confirm the cart_item value matches the expected value.
		$this->assertEquals( $expected_value, $cart_item['nyp'] );
	}

	// If the method is passed false it should return false.
	public function test_should_convert_product_price_returns_false_when_passed_false() {
		// Assert: Confirm false is returned if false is passed.
		$this->assertFalse( $this->woocommerce_nyp->should_convert_product_price( false, null ) );
	}

	// If the meta value is already set on the product, the method should return false.
	public function test_should_convert_product_price_returns_false_when_product_is_already_converted() {
		// Arrange: Set up the currency used for the test.
		$selected_currency = new Currency( $this->localization_service, 'EUR', 2.0 );

		// Arrange: Set up the product, and add the meta data to it.
		$product = WC_Helper_Product::create_simple_product();
		$product->update_meta_data( WooCommerceNameYourPrice::NYP_CURRENCY, $selected_currency->get_code() );

		// Arrange: Set up the mock_multi_currency method mock.
		$this->mock_multi_currency
			->expects( $this->once() )
			->method( 'get_selected_currency' )
			->willReturn( $selected_currency );

		// Arrange: Set is_nyp to return false.
		$this->set_is_nyp( false );

		// Assert: Confirm false is returned.
		$this->assertFalse( $this->woocommerce_nyp->should_convert_product_price( true, $product ) );
	}

	// If the product is tagged a a nyp product, false should be returned.
	public function test_should_convert_product_price_returns_false_when_product_is_a_nyp_product() {
		// Arrange: Set up the currency and product used for the test.
		$selected_currency = new Currency( $this->localization_service, 'EUR', 2.0 );
		$product           = WC_Helper_Product::create_simple_product();

		// Arrange: Set up the mock_multi_currency method mock.
		$this->mock_multi_currency
			->expects( $this->once() )
			->method( 'get_selected_currency' )
			->willReturn( $selected_currency );

		// Arrange: Set is_nyp to return true.
		$this->set_is_nyp( true );

		// Assert: Confirm false is returned.
		$this->assertFalse( $this->woocommerce_nyp->should_convert_product_price( true, $product ) );
	}

	// If no tests return true, method should return true.
	public function test_should_convert_product_price_returns_true_when_no_matches() {
		// Arrange: Set up the currency and product used for the test.
		$selected_currency = new Currency( $this->localization_service, 'EUR', 2.0 );
		$product           = WC_Helper_Product::create_simple_product();

		// Arrange: Set up the mock_multi_currency method mock.
		$this->mock_multi_currency
			->expects( $this->once() )
			->method( 'get_selected_currency' )
			->willReturn( $selected_currency );

		// Arrange: Set is_nyp to return false.
		$this->set_is_nyp( false );

		// Assert: Confirm true is returned.
		$this->assertTrue( $this->woocommerce_nyp->should_convert_product_price( true, $product ) );
	}

	public function test_edit_in_cart_args() {
		// Arrange: Set up the currency  used for the test.
		$selected_currency = new Currency( $this->localization_service, 'EUR', 2.0 );

		// Arrange: Set up the mock_multi_currency method mock.
		$this->mock_multi_currency
			->expects( $this->once() )
			->method( 'get_selected_currency' )
			->willReturn( $selected_currency );

		// Act: Edit the in cart args.
		$result = $this->woocommerce_nyp->edit_in_cart_args( [], [] );

		// Assert: Confirm that the currency code was added to the arg array.
		$this->assertSame( $selected_currency->get_code(), $result['nyp_currency'] );
	}

	/**
	 * Runs through all the checks in the method returning the initial price until the last one passes all the checks.
	 *
	 * @dataProvider provider_get_initial_price
	 */
	public function test_get_initial_price( $initial_price, $suffix, $request, $get_selected_currency, $get_raw_conversion ) {
		// Arrange: Set the initial expected price and the currencies that may be used.
		$expected_price    = $initial_price;
		$selected_currency = new Currency( $this->localization_service, 'EUR', 2.0 );
		$store_currency    = new Currency( $this->localization_service, 'USD', 1 );

		// Arrange: Set expectations for calls to get_selected_currency method.
		if ( $get_selected_currency ) {
			$this->mock_multi_currency
				->expects( $this->once() )
				->method( 'get_selected_currency' )
				->willReturn( $selected_currency );
		} else {
			$this->mock_multi_currency
				->expects( $this->never() )
				->method( 'get_selected_currency' );
		}

		// Arrange: Set expectations for calls to get_raw_conversion method and update expected price.
		if ( $get_raw_conversion ) {
			$expected_price = $initial_price * ( $selected_currency->get_rate() / $store_currency->get_rate() );

			$this->mock_multi_currency
				->expects( $this->once() )
				->method( 'get_raw_conversion' )
				->with( $initial_price, $selected_currency->get_code(), $store_currency->get_code() )
				->willReturn( $expected_price );
		} else {
			$this->mock_multi_currency
				->expects( $this->never() )
				->method( 'get_raw_conversion' );
		}

		// Arrange: Manually set the request prarameters.
		foreach ( $request as $key => $value ) {
			$_REQUEST[ $key ] = $value;
		}

		// Act: Get the initial price.
		$result = $this->woocommerce_nyp->get_initial_price( $initial_price, '', $suffix );

		// Assert: Confirm the initial price is returned.
		$this->assertSame( $expected_price, $result );
	}

	public function provider_get_initial_price() {
		return [
			'Both requests false - return initial_price'  => [
				'initial_price'         => 10.00,
				'suffix'                => '',
				'request'               => [],
				'get_selected_currency' => false,
				'get_raw_conversion'    => false,
			],
			'First request true - return initial_price'   => [
				'initial_price'         => 10.00,
				'suffix'                => '_suffix',
				'request'               => [
					'nyp_raw_suffix' => 'test',
				],
				'get_selected_currency' => false,
				'get_raw_conversion'    => false,
			],
			'Second request true - return initial_price'  => [
				'initial_price'         => 10.00,
				'suffix'                => '_suffix',
				'request'               => [
					'nyp_currency' => 'test',
				],
				'get_selected_currency' => false,
				'get_raw_conversion'    => false,
			],
			'Both requests true - return initial_price'   => [
				'initial_price'         => 10.00,
				'suffix'                => '_suffix',
				'request'               => [
					'nyp_raw_suffix' => 10.00,
					'nyp_currency'   => 'EUR',
				],
				'get_selected_currency' => true,
				'get_raw_conversion'    => false,
			],
			'Both requests true - return converted price' => [
				'initial_price'         => 10.00,
				'suffix'                => '_suffix',
				'request'               => [
					'nyp_raw_suffix' => 10.00,
					'nyp_currency'   => 'USD',
				],
				'get_selected_currency' => true,
				'get_raw_conversion'    => true,
			],
		];
	}

	/**
	 * Sets up `is_nyp` to return true or false for a test.
	 */
	private function set_is_nyp( $value ) {
		WC_Name_Your_Price_Helpers::is_nyp( is_bool( $value ) ? $value : false );
	}
}
