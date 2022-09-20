<?php
/**
 * Class WCPay_Multi_Currency_Frontend_Prices_Tests
 *
 * @package WooCommerce\Payments\Tests
 */

/**
 * WCPay\MultiCurrency\FrontendPrices unit tests.
 */
class WCPay_Multi_Currency_Frontend_Prices_Tests extends WCPAY_UnitTestCase {
	/**
	 * Mock WCPay\MultiCurrency\Compatibility.
	 *
	 * @var WCPay\MultiCurrency\Compatibility|PHPUnit_Framework_MockObject_MockObject
	 */
	private $mock_compatibility;

	/**
	 * Mock WCPay\MultiCurrency\MultiCurrency.
	 *
	 * @var WCPay\MultiCurrency\MultiCurrency|PHPUnit_Framework_MockObject_MockObject
	 */
	private $mock_multi_currency;

	/**
	 * WCPay\MultiCurrency\FrontendPrices instance.
	 *
	 * @var WCPay\MultiCurrency\FrontendPrices
	 */
	private $frontend_prices;

	/**
	 * Pre-test setup
	 */
	public function set_up() {
		parent::set_up();

		$this->mock_compatibility  = $this->createMock( WCPay\MultiCurrency\Compatibility::class );
		$this->mock_multi_currency = $this->createMock( WCPay\MultiCurrency\MultiCurrency::class );

		$this->frontend_prices = new WCPay\MultiCurrency\FrontendPrices( $this->mock_multi_currency, $this->mock_compatibility );
	}

	public function tear_down() {
		remove_all_filters( 'wc_tax_enabled' );
		remove_all_filters( 'woocommerce_find_rates' );
		WC()->session->cleanup_sessions();

		parent::tear_down();
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
			[ 'woocommerce_product_get_price', 'get_product_price_string' ],
			[ 'woocommerce_product_get_regular_price', 'get_product_price_string' ],
			[ 'woocommerce_product_get_sale_price', 'get_product_price_string' ],
			[ 'woocommerce_product_variation_get_price', 'get_product_price_string' ],
			[ 'woocommerce_product_variation_get_regular_price', 'get_product_price_string' ],
			[ 'woocommerce_product_variation_get_sale_price', 'get_product_price_string' ],
			[ 'woocommerce_variation_prices', 'get_variation_price_range' ],
			[ 'woocommerce_get_variation_prices_hash', 'add_exchange_rate_to_variation_prices_hash' ],
			[ 'woocommerce_shipping_method_add_rate_args', 'convert_shipping_method_rate_cost' ],
			[ 'init', 'register_free_shipping_filters' ],
			[ 'woocommerce_coupon_get_amount', 'get_coupon_amount' ],
			[ 'woocommerce_coupon_get_minimum_amount', 'get_coupon_min_max_amount' ],
			[ 'woocommerce_coupon_get_maximum_amount', 'get_coupon_min_max_amount' ],
			[ 'woocommerce_new_order', 'add_order_meta' ],
		];
	}

	public function test_registers_woocommerce_filters_for_free_shipping_methods() {
		// Add a free shipping method to the default zone.
		$default_zone_free_method = \WC_Shipping_Zones::get_zone( 0 )->add_shipping_method( 'free_shipping' );

		// Create a new shipping zone and shipping method.
		$new_zone             = new WC_Shipping_Zone();
		$new_zone_free_method = $new_zone->add_shipping_method( 'free_shipping' );

		$this->frontend_prices->register_free_shipping_filters();

		$this->assertGreaterThan( 10, has_filter( 'option_woocommerce_free_shipping_' . $default_zone_free_method . '_settings', [ $this->frontend_prices, 'get_free_shipping_min_amount' ] ) );
		$this->assertGreaterThan( 10, has_filter( 'option_woocommerce_free_shipping_' . $new_zone_free_method . '_settings', [ $this->frontend_prices, 'get_free_shipping_min_amount' ] ) );
	}

	public function test_get_product_price_returns_empty_price() {
		$this->assertSame( '', $this->frontend_prices->get_product_price( '' ) );
	}

	public function test_get_product_price_converts_prices() {
		$mock_product = new WC_Product( 0 );
		$this->mock_compatibility
			->method( 'should_convert_product_price' )
			->with( $mock_product )
			->willReturn( true );
		$this->mock_multi_currency->method( 'get_price' )->with( 10.0, 'product' )->willReturn( 25.0 );
		$this->assertSame( 25.0, $this->frontend_prices->get_product_price( 10.0, $mock_product ) );
	}

	public function test_get_product_price_skips_conversion_on_compatibility() {
		$mock_product = new WC_Product( 0 );
		$this->mock_compatibility
			->method( 'should_convert_product_price' )
			->with( $mock_product )
			->willReturn( false );
		$this->assertSame( 10.0, $this->frontend_prices->get_product_price( 10.0, $mock_product ) );
	}

	public function test_get_variation_price_range_converts_non_empty_prices() {
		$this->mock_compatibility->method( 'should_convert_product_price' )->willReturn( true );
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
					1 => '25',
					2 => '30',
				],
				'sale_price' => [
					1 => '15',
					2 => '20',
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
		$this->mock_compatibility->method( 'should_convert_product_price' )->willReturn( true );
		$this->mock_multi_currency->method( 'get_price' )->with( 1.0, 'product' )->willReturn( 2.5 );

		$this->assertSame(
			[ 'existing_item', 2.5 ],
			$this->frontend_prices->add_exchange_rate_to_variation_prices_hash( [ 'existing_item' ] )
		);
	}

	public function test_convert_shipping_method_rate_cost_for_string_cost() {
		$this->mock_multi_currency
			->expects( $this->once() )
			->method( 'get_price' )
			->with( '10' )
			->willReturn( 25.0 );

		add_filter( 'wc_tax_enabled', '__return_true' );
		add_filter(
			'woocommerce_find_rates',
			function() {
				return [
					1 =>
					[
						'rate'     => 10.0,
						'label'    => 'Tax',
						'shipping' => 'yes',
						'compound' => 'no',
					],
				];
			},
			50,
			2
		);

		WC()->session->init();
		WC()->customer->set_location( 'US', 'CA' );

		$shipping_method             = new \WC_Shipping_Flat_Rate();
		$shipping_method->tax_status = 'taxable';
		$shipping_method->add_rate(
			[
				'cost'  => '10',
				'id'    => 1,
				'label' => 'label',
			]
		);
		$shipping_rate = $shipping_method->rates[1];

		// Cost gets converted and taxes properly calculated based on it.
		$this->assertSame( '25.00', $shipping_rate->cost );
		$this->assertSame( 2.5, $shipping_rate->taxes[1] );
	}

	public function test_convert_shipping_method_rate_cost_for_array_cost() {
		$this->mock_multi_currency
			->expects( $this->once() )
			->method( 'get_price' )
			->with( '11' )
			->willReturn( 25.0 );

		add_filter( 'wc_tax_enabled', '__return_true' );
		add_filter(
			'woocommerce_find_rates',
			function() {
				return [
					1 =>
						[
							'rate'     => 10.0,
							'label'    => 'Tax',
							'shipping' => 'yes',
							'compound' => 'no',
						],
				];
			},
			50,
			2
		);

		WC()->session->init();
		WC()->customer->set_location( 'US', 'CA' );

		$shipping_method             = new \WC_Shipping_Flat_Rate();
		$shipping_method->tax_status = 'taxable';
		$shipping_method->add_rate(
			[
				'cost'  => [ '10', '1' ],
				'id'    => 1,
				'label' => 'label',
			]
		);
		$shipping_rate = $shipping_method->rates[1];

		// Cost gets converted and taxes properly calculated based on it.
		$this->assertSame( '25.00', $shipping_rate->cost );
		$this->assertSame( 2.5, $shipping_rate->taxes[1] );
	}

	public function test_get_coupon_amount_returns_empty_amount() {
		$this->assertSame( '', $this->frontend_prices->get_coupon_amount( '', null ) );
	}

	public function test_get_coupon_amount_returns_percent_coupon_amount() {
		$percent_coupon = new WC_Coupon();
		$percent_coupon->set_discount_type( 'percent' );

		$this->assertSame( '10', $this->frontend_prices->get_coupon_amount( '10', $percent_coupon ) );
	}

	public function test_get_coupon_amount_skips_conversion_on_compatibility() {
		$coupon = new WC_Coupon();
		$this->mock_compatibility
			->method( 'should_convert_coupon_amount' )
			->with( $coupon )
			->willReturn( false );
		$this->assertSame( '10', $this->frontend_prices->get_coupon_amount( '10', $coupon ) );
	}

	public function test_get_coupon_amount_converts_fixed_cart_amount() {
		$this->mock_multi_currency->method( 'get_price' )->with( 10.0, 'coupon' )->willReturn( 25.0 );

		$fixed_cart_coupon = new WC_Coupon();
		$fixed_cart_coupon->set_discount_type( 'fixed_cart' );

		$this->mock_compatibility
			->method( 'should_convert_coupon_amount' )
			->with( $fixed_cart_coupon )
			->willReturn( true );

		$this->assertSame( 25.0, $this->frontend_prices->get_coupon_amount( '10', $fixed_cart_coupon ) );
	}

	public function test_get_coupon_min_max_amount_returns_empty_amount() {
		$this->assertSame( '', $this->frontend_prices->get_coupon_min_max_amount( '' ) );
	}

	public function test_get_coupon_min_max_amount_converts_amount_as_product() {
		$this->mock_multi_currency->method( 'get_price' )->with( 5.0, 'product' )->willReturn( 12.5 );

		$this->assertSame( 12.5, $this->frontend_prices->get_coupon_min_max_amount( '5.0' ) );
	}

	public function test_get_free_shipping_min_amount_returns_empty_amount() {
		$this->assertSame( [ 'key' => 'value' ], $this->frontend_prices->get_free_shipping_min_amount( [ 'key' => 'value' ] ) );
	}

	public function test_get_free_shipping_min_amount_returns_zero_amount() {
		$this->assertSame( [ 'min_amount' => '0' ], $this->frontend_prices->get_free_shipping_min_amount( [ 'min_amount' => '0' ] ) );
	}

	public function test_get_free_shipping_min_amount_converts_amount_as_product() {
		$this->mock_multi_currency->method( 'get_price' )->with( 5.0, 'product' )->willReturn( 12.5 );

		$this->assertSame( [ 'min_amount' => 12.5 ], $this->frontend_prices->get_free_shipping_min_amount( [ 'min_amount' => '5.0' ] ) );
	}

	public function test_add_order_meta_skips_default_currency() {
		$this->mock_multi_currency->method( 'get_default_currency' )->willReturn( new WCPay\MultiCurrency\Currency( 'USD' ) );

		$order = wc_create_order();
		$order->set_currency( 'USD' );

		$this->frontend_prices->add_order_meta( $order->get_id(), $order );

		// Get the order from the database.
		$order = wc_get_order( $order->get_id() );

		$this->assertFalse( $order->meta_exists( '_wcpay_multi_currency_order_exchange_rate' ) );
		$this->assertFalse( $order->meta_exists( '_wcpay_multi_currency_order_default_currency' ) );
	}

	public function test_add_order_meta() {
		$this->mock_multi_currency->method( 'get_default_currency' )->willReturn( new WCPay\MultiCurrency\Currency( 'USD' ) );
		$this->mock_multi_currency->method( 'get_price' )->with( 1, 'exchange_rate' )->willReturn( 0.71 );

		$order = wc_create_order();
		$order->set_currency( 'GBP' );

		$this->frontend_prices->add_order_meta( $order->get_id(), $order );

		// Get the order from the database.
		$order = wc_get_order( $order->get_id() );

		$this->assertSame( '0.71', $order->get_meta( '_wcpay_multi_currency_order_exchange_rate' ) );
		$this->assertSame( 'USD', $order->get_meta( '_wcpay_multi_currency_order_default_currency' ) );
	}
}
