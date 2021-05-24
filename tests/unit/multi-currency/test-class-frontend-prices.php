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
			[ 'woocommerce_package_rates', 'get_shipping_rates_prices' ],
			[ 'woocommerce_coupon_get_amount', 'get_coupon_amount' ],
			[ 'woocommerce_coupon_get_minimum_amount', 'get_coupon_min_max_amount' ],
			[ 'woocommerce_coupon_get_maximum_amount', 'get_coupon_min_max_amount' ],
		];
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

	public function test_get_shipping_rates_prices_converts_rates() {
		$this->mock_multi_currency
			->method( 'get_price' )
			->withConsecutive( [ 10.0, 'shipping' ], [ 0.0, 'shipping' ] )
			->willReturnOnConsecutiveCalls( 25.0, 0.0 );

		$flat_rate_method       = new WC_Shipping_Rate();
		$free_method            = new WC_Shipping_Rate();
		$flat_rate_method->cost = '10.0';
		$free_method->cost      = '0.0';

		$base_shipping_rates = [
			'shipping_rate_1' => $flat_rate_method,
			'shipping_rate_2' => $free_method,
		];

		$shipping_rates = $this->frontend_prices->get_shipping_rates_prices( $base_shipping_rates );

		$this->assertSame( 25.0, $shipping_rates['shipping_rate_1']->cost );
		$this->assertSame( 0.0, $shipping_rates['shipping_rate_2']->cost );
	}

	public function test_get_shipping_rates_prices_converts_taxes() {
		$this->mock_multi_currency
			->method( 'get_price' )
			->withConsecutive( [ 1.0, 'tax' ], [ 2.0, 'tax' ] )
			->willReturnOnConsecutiveCalls( 2.5, 5.0 );

		$flat_rate_method        = new WC_Shipping_Rate();
		$flat_rate_method->taxes = [ '1.0', '2.0' ];

		$shipping_rates = $this->frontend_prices->get_shipping_rates_prices( [ 'shipping_rate_1' => $flat_rate_method ] );

		$this->assertSame( [ 2.5, 5.0 ], $shipping_rates['shipping_rate_1']->taxes );
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

	public function test_get_coupon_min_max_amount_converts_amount() {
		$this->mock_multi_currency->method( 'get_price' )->with( 5.0, 'coupon_min_max' )->willReturn( 12.5 );

		$this->assertSame( 12.5, $this->frontend_prices->get_coupon_min_max_amount( '5.0' ) );
	}
}
