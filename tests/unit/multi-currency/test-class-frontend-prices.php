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
	 * WC_Payments_Localization_Service.
	 *
	 * @var WC_Payments_Localization_Service
	 */
	private $localization_service;

	/**
	 * Pre-test setup
	 */
	public function set_up() {
		parent::set_up();

		$this->mock_compatibility   = $this->createMock( WCPay\MultiCurrency\Compatibility::class );
		$this->mock_multi_currency  = $this->createMock( WCPay\MultiCurrency\MultiCurrency::class );
		$this->localization_service = new WC_Payments_Localization_Service();

		$this->frontend_prices = new WCPay\MultiCurrency\FrontendPrices( $this->mock_multi_currency, $this->mock_compatibility );
		$this->frontend_prices->init_hooks();
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
			98,
			has_filter( $filter, [ $this->frontend_prices, $function_name ] ),
			"Filter '$filter' was not registered with '$function_name' with a priority higher than 98"
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
			[ 'woocommerce_shipping_zone_shipping_methods', 'convert_free_shipping_method_min_amount' ],
			[ 'woocommerce_coupon_get_amount', 'get_coupon_amount' ],
			[ 'woocommerce_coupon_get_minimum_amount', 'get_coupon_min_max_amount' ],
			[ 'woocommerce_coupon_get_maximum_amount', 'get_coupon_min_max_amount' ],
			[ 'woocommerce_new_order', 'add_order_meta' ],
		];
	}

	public function test_convert_free_shipping_method_min_amount() {
		// Add multiple shipping methods to the default zone.
		$default_zone = \WC_Shipping_Zones::get_zone( 0 );
		$default_zone->add_shipping_method( 'flat_rate' );
		$free_shipping_id     = $default_zone->add_shipping_method( 'free_shipping' );
		$free_shipping_method = \WC_Shipping_Zones::get_shipping_method( $free_shipping_id );
		$this->assertNotNull( $free_shipping_method );

		// Set a min_amount to be converted.
		$free_shipping_method->instance_settings['min_amount'] = 10.0;
		update_option(
			$free_shipping_method->get_instance_option_key(),
			$free_shipping_method->instance_settings,
			'yes'
		);

		$this->mock_multi_currency
			->expects( $this->once() )
			->method( 'get_price' )
			->with( 10.0, 'product' )
			->willReturn( 25.0 );

		// The filter should be registered when the zone's shipping methods are retrieved.
		$methods = $default_zone->get_shipping_methods();

		$this->assertCount( 2, $methods );

		$test_gateway = $methods[ $free_shipping_id ];
		$this->assertInstanceOf( \WC_Shipping_Free_Shipping::class, $test_gateway );
		$this->assertEquals( 25.0, $test_gateway->min_amount );

		// Make sure it doesn't change the gateway's settings directly.
		$this->assertEquals( 10.0, $test_gateway->instance_settings['min_amount'] );
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
			function () {
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
		$matcher = $this->exactly( 2 );
		$this->mock_multi_currency
			->expects( $matcher )
			->method( 'get_price' )
			->willReturnCallback(
				function ( $price ) use ( $matcher ) {
					switch ( $matcher->getInvocationCount() ) {
						case 1:
							$this->assertEquals( 10.0, $price );
							break;
						case 2:
							$this->assertEquals( 1.0, $price );
							break;
					}
					return $price;
				}
			);

		add_filter( 'wc_tax_enabled', '__return_true' );
		add_filter(
			'woocommerce_find_rates',
			function () {
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
		$this->assertSame( '11.00', $shipping_rate->cost );
		$this->assertSame( 1.1, $shipping_rate->taxes[1] );
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

	public function test_add_order_meta_skips_default_currency() {
		$this->mock_multi_currency->method( 'get_default_currency' )->willReturn( new WCPay\MultiCurrency\Currency( $this->localization_service, 'USD' ) );

		$order = wc_create_order();
		$order->set_currency( 'USD' );

		$this->frontend_prices->add_order_meta( $order->get_id(), $order );

		// Get the order from the database.
		$order = wc_get_order( $order->get_id() );

		$this->assertFalse( $order->meta_exists( '_wcpay_multi_currency_order_exchange_rate' ) );
		$this->assertFalse( $order->meta_exists( '_wcpay_multi_currency_order_default_currency' ) );
	}

	public function test_add_order_meta() {
		$this->mock_multi_currency->method( 'get_default_currency' )->willReturn( new WCPay\MultiCurrency\Currency( $this->localization_service, 'USD' ) );
		$this->mock_multi_currency->method( 'get_price' )->with( 1, 'exchange_rate' )->willReturn( 0.71 );

		$order = wc_create_order();
		$order->set_currency( 'GBP' );

		$this->frontend_prices->add_order_meta( $order->get_id(), $order );

		// Get the order from the database.
		$order = wc_get_order( $order->get_id() );

		$this->assertSame( '0.71', $order->get_meta( '_wcpay_multi_currency_order_exchange_rate' ) );
		$this->assertSame( 'USD', $order->get_meta( '_wcpay_multi_currency_order_default_currency' ) );
	}

	/**
	 * Test that maybe_modify_price_ranges_rest_response() does nothing
	 * if the REST route is not /wc/store/v1/products/collection-data.
	 */
	public function test_maybe_modify_price_ranges_rest_response_non_target_route() {
		$response = new \WP_REST_Response(
			[
				'price_range' => (object) [
					'min_price' => '10',
					'max_price' => '100',
				],
			]
		);
		$server   = $this->createMock( \WP_REST_Server::class );
		$request  = $this->createMock( \WP_REST_Request::class );

		// Simulate a route we don't care about.
		$request->method( 'get_route' )->willReturn( '/wc/store/v1/cart' );

		$modified = $this->frontend_prices->maybe_modify_price_ranges_rest_response( $response, $server, $request );
		$this->assertSame( $response, $modified, 'Response should remain unchanged for non-target routes.' );
	}

	/**
	 * Test that maybe_modify_price_ranges_rest_response() does nothing
	 * if the price_range key is missing or not an object.
	 */
	public function test_maybe_modify_price_ranges_rest_response_missing_or_invalid_price_range() {
		$server  = $this->createMock( \WP_REST_Server::class );
		$request = $this->createMock( \WP_REST_Request::class );
		$request->method( 'get_route' )->willReturn( '/wc/store/v1/products/collection-data' );

		// No price_range at all.
		$response = new \WP_REST_Response( [ 'some_key' => 123 ] );
		$modified = $this->frontend_prices->maybe_modify_price_ranges_rest_response( $response, $server, $request );
		$this->assertSame( $response, $modified, 'Response should be unchanged if price_range is missing.' );

		// price_range is not an object.
		$response->set_data( [ 'price_range' => 'not-an-object' ] );
		$modified = $this->frontend_prices->maybe_modify_price_ranges_rest_response( $response, $server, $request );
		$this->assertSame( $response, $modified, 'Response should be unchanged if price_range is not an object.' );
	}

	/**
	 * Test that maybe_modify_price_ranges_rest_response() does nothing if
	 * the store currency equals the selected currency.
	 */
	public function test_maybe_modify_price_ranges_rest_response_same_currency() {
		// Store currency = USD, selected currency = USD.
		$this->mock_multi_currency
			->method( 'get_default_currency' )
			->willReturn( new \WCPay\MultiCurrency\Currency( $this->localization_service, 'USD' ) );
		$this->mock_multi_currency
			->method( 'get_selected_currency' )
			->willReturn( new \WCPay\MultiCurrency\Currency( $this->localization_service, 'USD' ) );

		$request  = $this->createMock( \WP_REST_Request::class );
		$server   = $this->createMock( \WP_REST_Server::class );
		$response = new \WP_REST_Response(
			[
				'price_range' => (object) [
					'min_price' => '10',
					'max_price' => '100',
				],
			]
		);

		$request->method( 'get_route' )->willReturn( '/wc/store/v1/products/collection-data' );

		$modified = $this->frontend_prices->maybe_modify_price_ranges_rest_response( $response, $server, $request );
		$this->assertSame( $response, $modified, 'Expected no changes if currencies are the same.' );
	}

	/**
	 * Test that maybe_modify_price_ranges_rest_response() converts
	 * min_price and max_price when store and selected currencies differ.
	 */
	public function test_maybe_modify_price_ranges_rest_response_converts_price_range() {
		// Store currency = USD, selected currency = EUR.
		$this->mock_multi_currency
			->method( 'get_default_currency' )
			->willReturn( new \WCPay\MultiCurrency\Currency( $this->localization_service, 'USD' ) );
		$this->mock_multi_currency
			->method( 'get_selected_currency' )
			->willReturn( new \WCPay\MultiCurrency\Currency( $this->localization_service, 'EUR' ) );

		// Mock the get_price() conversion to always multiply by 2.5 for easy checking.
		$this->mock_multi_currency
			->method( 'get_price' )
			->willReturnCallback(
				function ( $value ) {
					return (float) $value * 2.5;
				}
			);

		$request = $this->createMock( \WP_REST_Request::class );
		$server  = $this->createMock( \WP_REST_Server::class );
		$request->method( 'get_route' )->willReturn( '/wc/store/v1/products/collection-data' );

		$response = new \WP_REST_Response(
			[
				'price_range' => (object) [
					'min_price' => '10',
					'max_price' => '100',
				],
			]
		);
		$modified = $this->frontend_prices->maybe_modify_price_ranges_rest_response( $response, $server, $request );
		$data     = $modified->get_data();

		$this->assertSame( '25', $data['price_range']->min_price, 'Expected min_price to be 10 * 2.5' );
		$this->assertSame( '250', $data['price_range']->max_price, 'Expected max_price to be 100 * 2.5' );
	}

	/**
	 * Test that maybe_modify_price_ranges_query_var() does nothing if post_type != 'product'.
	 */
	public function test_maybe_modify_price_ranges_query_var_not_product() {
		$query = [
			'post_type'  => 'page',
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'meta_query' => [
				[
					'key'     => '_price',
					'compare' => '>=',
					'value'   => 10,
				],
			],
		];

		$block = $this->createMock( \WP_Block::class );
		$page  = 1;

		$modified_query = $this->frontend_prices->maybe_modify_price_ranges_query_var( $query, $block, $page );
		$this->assertSame( $query, $modified_query, 'Query should be unchanged when post_type is not product.' );
	}

	/**
	 * Test that maybe_modify_price_ranges_query_var() does nothing if meta_query is missing or not an array.
	 */
	public function test_maybe_modify_price_ranges_query_var_no_meta_query() {
		$query = [ 'post_type' => 'product' ]; // no meta_query at all.
		$block = $this->createMock( \WP_Block::class );
		$page  = 1;

		$modified_query = $this->frontend_prices->maybe_modify_price_ranges_query_var( $query, $block, $page );
		$this->assertSame( $query, $modified_query, 'Query should remain unchanged if meta_query is missing.' );

		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		$query['meta_query'] = 'string_value'; // meta_query is not an array.
		$modified_query      = $this->frontend_prices->maybe_modify_price_ranges_query_var( $query, $block, $page );
		$this->assertSame( $query, $modified_query, 'Query should remain unchanged if meta_query is not an array.' );
	}

	/**
	 * Test that maybe_modify_price_ranges_query_var() does nothing if
	 * the store currency equals the selected currency.
	 */
	public function test_maybe_modify_price_ranges_query_var_same_currency() {
		$this->mock_multi_currency
			->method( 'get_default_currency' )
			->willReturn( new \WCPay\MultiCurrency\Currency( $this->localization_service, 'USD' ) );
		$this->mock_multi_currency
			->method( 'get_selected_currency' )
			->willReturn( new \WCPay\MultiCurrency\Currency( $this->localization_service, 'USD' ) );

		$query = [
			'post_type'  => 'product',
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'meta_query' => [
				[
					'key'     => '_price',
					'compare' => '>=',
					'value'   => 10,
				],
			],
		];

		$block = $this->createMock( \WP_Block::class );
		$page  = 1;

		$modified_query = $this->frontend_prices->maybe_modify_price_ranges_query_var( $query, $block, $page );
		$this->assertSame( $query, $modified_query, 'Expected no changes if the store and selected currencies match.' );
	}

	/**
	 * Test that maybe_modify_price_ranges_query_var() converts _price values
	 * when store and selected currencies differ, applying floor or ceil where needed.
	 */
	public function test_maybe_modify_price_ranges_query_var_converts_price_filters() {
		$this->mock_multi_currency
			->method( 'get_default_currency' )
			->willReturn( new \WCPay\MultiCurrency\Currency( $this->localization_service, 'USD' ) );
		$this->mock_multi_currency
			->method( 'get_selected_currency' )
			->willReturn( new \WCPay\MultiCurrency\Currency( $this->localization_service, 'EUR' ) );

		// Suppose 1 USD = 0.75 EUR, for test convenience.
		// We'll detect floors or ceilings based on 'compare'.
		$this->mock_multi_currency
			->method( 'get_raw_conversion' )
			->willReturnCallback(
				function ( $price, $from_currency, $to_currency ) {
					return (float) $price * 0.75;
				}
			);

		$query = [
			'post_type'  => 'product',
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'meta_query' => [
				[
					'key'     => '_price',
					'compare' => '>=',
					'value'   => 10,
				],
				[
					'key'     => '_price',
					'compare' => '<=',
					'value'   => 100,
				],
				[
					'key'     => '_price',
					'compare' => '>=',
					'value'   => 99.75, // checking an odd decimal.
				],
			],
		];

		$block = $this->createMock( \WP_Block::class );
		$page  = 1;

		$modified_query = $this->frontend_prices->maybe_modify_price_ranges_query_var( $query, $block, $page );

		// We expect:
		// - ">= 10" becomes >= floor(10*0.75) = >= floor(7.5) = >= 7.
		// - "<= 100" becomes <= ceil(100*0.75) = <= ceil(75) = <= 75.
		// - ">= 99.75" => floor(99.75*0.75) => floor(74.8125) => 74.

		$this->assertSame( '7', $modified_query['meta_query'][0]['value'] );
		$this->assertSame( '75', $modified_query['meta_query'][1]['value'] );
		$this->assertSame( '74', $modified_query['meta_query'][2]['value'] );
	}
}
