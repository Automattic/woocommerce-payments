<?php
/**
 * Class WCPay_Multi_Currency_Frontend_Prices_Tests
 *
 * @package WooCommerce\Payments\Tests
 */

/**
 * WCPay\Multi_Currency\Frontend_Prices unit tests.
 */
class WCPay_Multi_Currency_Frontend_Prices_Tests extends WP_UnitTestCase {
	/**
	 * Mock WCPay\Multi_Currency\Multi_Currency.
	 *
	 * @var WCPay\Multi_Currency\Multi_Currency|PHPUnit_Framework_MockObject_MockObject
	 */
	private $mock_multi_currency;

	/**
	 * WCPay\Multi_Currency\Frontend_Prices instance.
	 *
	 * @var WCPay\Multi_Currency\Frontend_Prices
	 */
	private $frontend_prices;

	/**
	 * Pre-test setup
	 */
	public function setUp() {
		parent::setUp();

		$this->mock_multi_currency = $this->createMock( WCPay\Multi_Currency\Multi_Currency::class );

		$this->frontend_prices = new WCPay\Multi_Currency\Frontend_Prices( $this->mock_multi_currency );
	}

	/**
	 * @dataProvider woocommerce_filter_provider
	 */
	public function test_registers_woocommerce_filter( $filter, $function_name ) {
		$this->assertGreaterThan(
			10,
			has_filter( $filter, [ $this->frontend_prices, $function_name ] ),
			"Filter '$filter' was not registered with '$function_name' with a priority higher than the default"
		);
	}

	public function woocommerce_filter_provider() {
		return [
			[ 'woocommerce_product_get_price', 'get_product_price' ],
			[ 'woocommerce_product_get_regular_price', 'get_product_price' ],
			[ 'woocommerce_product_get_sale_price', 'get_product_price' ],
			[ 'woocommerce_product_variation_get_price', 'get_product_price' ],
			[ 'woocommerce_product_variation_get_regular_price', 'get_product_price' ],
			[ 'woocommerce_product_variation_get_sale_price', 'get_product_price' ],
			[ 'woocommerce_variation_prices', 'get_variation_price_range' ],
			[ 'woocommerce_get_variation_prices_hash', 'add_exchange_rate_to_variation_prices_hash' ],
			[ 'init', 'register_shipping_filters' ],
			[ 'woocommerce_coupon_get_amount', 'get_coupon_amount' ],
			[ 'woocommerce_coupon_get_minimum_amount', 'get_coupon_min_max_amount' ],
			[ 'woocommerce_coupon_get_maximum_amount', 'get_coupon_min_max_amount' ],
		];
	}

	public function test_registers_woocommerce_filters_for_shipping_methods() {
		// Add a flat and free shipping method to the default zone.
		$default_zone_free_method = \WC_Shipping_Zones::get_zone( 0 )->add_shipping_method( 'free_shipping' );
		$default_zone_flat_method = \WC_Shipping_Zones::get_zone( 0 )->add_shipping_method( 'flat_rate' );

		// Create a new shipping zone and shipping method.
		$new_zone             = new WC_Shipping_Zone();
		$new_zone_free_method = $new_zone->add_shipping_method( 'free_shipping' );
		$new_zone_flat_method = $new_zone->add_shipping_method( 'flat_rate' );

		$this->frontend_prices->register_shipping_filters();

		$this->assertGreaterThan( 10, has_filter( 'option_woocommerce_free_shipping_' . $default_zone_free_method . '_settings', [ $this->frontend_prices, 'get_shipping_method_settings' ] ) );
		$this->assertGreaterThan( 10, has_filter( 'option_woocommerce_flat_rate_' . $default_zone_flat_method . '_settings', [ $this->frontend_prices, 'get_shipping_method_settings' ] ) );
		$this->assertGreaterThan( 10, has_filter( 'option_woocommerce_free_shipping_' . $new_zone_free_method . '_settings', [ $this->frontend_prices, 'get_shipping_method_settings' ] ) );
		$this->assertGreaterThan( 10, has_filter( 'option_woocommerce_flat_rate_' . $new_zone_flat_method . '_settings', [ $this->frontend_prices, 'get_shipping_method_settings' ] ) );
	}

	public function test_get_product_price_returns_empty_price() {
		$this->assertSame( '', $this->frontend_prices->get_product_price( '' ) );
	}

	public function test_get_product_price_converts_prices() {
		$this->mock_multi_currency->method( 'get_price' )->with( 10.0, 'product' )->willReturn( 25.0 );
		$this->assertSame( 25.0, $this->frontend_prices->get_product_price( 10.0 ) );
	}

	public function test_get_variation_price_range_converts_non_empty_prices() {
		$this->mock_multi_currency
			->method( 'get_price' )
			->withConsecutive( [ 10.0, 'product' ], [ 12.0, 'product' ], [ 6.0, 'product' ], [ 8.0, 'product' ] )
			->willReturnOnConsecutiveCalls( 25.0, 30.0, 15.0, 20.0 );

		$base_variation_prices = [
			'price'      => [
				1 => '10.0',
				2 => '12.0',
			],
			'sale_price' => [
				1 => '6.0',
				2 => '8.0',
			],
		];

		$this->assertSame(
			[
				'price'      => [
					1 => 25.0,
					2 => 30.0,
				],
				'sale_price' => [
					1 => 15.0,
					2 => 20.0,
				],
			],
			$this->frontend_prices->get_variation_price_range( $base_variation_prices )
		);
	}

	public function test_get_variation_price_range_skips_empty_prices() {
		$base_variation_prices = [
			'sale_price' => [
				1 => '',
				2 => '',
			],
		];

		$this->assertSame(
			[
				'sale_price' => [
					1 => '',
					2 => '',
				],
			],
			$this->frontend_prices->get_variation_price_range( $base_variation_prices )
		);
	}

	public function test_exchange_rate_is_added_to_prices_hash() {
		$this->mock_multi_currency->method( 'get_price' )->with( 1.0, 'product' )->willReturn( 2.5 );

		$this->assertSame(
			[ 'existing_item', 2.5 ],
			$this->frontend_prices->add_exchange_rate_to_variation_prices_hash( [ 'existing_item' ] )
		);
	}

	public function test_get_coupon_amount_returns_empty_amount() {
		$this->assertSame( '', $this->frontend_prices->get_coupon_amount( '', null ) );
	}

	public function test_get_coupon_amount_returns_percent_coupon_amount() {
		$percent_coupon = new WC_Coupon();
		$percent_coupon->set_discount_type( 'percent' );

		$this->assertSame( '10', $this->frontend_prices->get_coupon_amount( '10', $percent_coupon ) );
	}

	public function test_get_coupon_amount_converts_fixed_cart_amount() {
		$this->mock_multi_currency->method( 'get_price' )->with( 10.0, 'coupon' )->willReturn( 25.0 );

		$fixed_cart_coupon = new WC_Coupon();
		$fixed_cart_coupon->set_discount_type( 'fixed_cart' );

		$this->assertSame( 25.0, $this->frontend_prices->get_coupon_amount( '10', $fixed_cart_coupon ) );
	}

	public function test_get_coupon_min_max_amount_returns_empty_amount() {
		$this->assertSame( '', $this->frontend_prices->get_coupon_min_max_amount( '' ) );
	}

	public function test_get_coupon_min_max_amount_converts_amount_as_product() {
		$this->mock_multi_currency->method( 'get_price' )->with( 5.0, 'product' )->willReturn( 12.5 );

		$this->assertSame( 12.5, $this->frontend_prices->get_coupon_min_max_amount( '5.0' ) );
	}

	public function test_get_shipping_method_settings_returns_empty_min_amount() {
		$this->assertSame( [ 'key' => 'value' ], $this->frontend_prices->get_shipping_method_settings( [ 'key' => 'value' ] ) );
	}

	public function test_get_shipping_method_settings_returns_zero_min_amount() {
		$this->assertSame( [ 'min_amount' => '0' ], $this->frontend_prices->get_shipping_method_settings( [ 'min_amount' => '0' ] ) );
	}

	public function test_get_shipping_method_settings_converts_min_amount_as_product() {
		$this->mock_multi_currency->method( 'get_price' )->with( 5.0, 'product' )->willReturn( 12.5 );

		$this->assertSame( [ 'min_amount' => 12.5 ], $this->frontend_prices->get_shipping_method_settings( [ 'min_amount' => '5.0' ] ) );
	}

	public function test_get_shipping_method_settings_returns_empty_cost() {
		$this->assertSame( [ 'key' => 'value' ], $this->frontend_prices->get_shipping_method_settings( [ 'key' => 'value' ] ) );
	}

	public function test_get_shipping_method_settings_returns_zero_cost() {
		$this->assertSame( [ 'cost' => '0' ], $this->frontend_prices->get_shipping_method_settings( [ 'cost' => '0' ] ) );
	}

	public function test_get_shipping_method_settings_converts_cost_as_shipping() {
		$this->mock_multi_currency->method( 'get_price' )->with( 5.0, 'shipping' )->willReturn( 12.5 );

		$this->assertSame( [ 'cost' => 12.5 ], $this->frontend_prices->get_shipping_method_settings( [ 'cost' => '5.0' ] ) );
	}
}
