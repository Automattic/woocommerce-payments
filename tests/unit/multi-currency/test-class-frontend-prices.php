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
		$this->mock_compatibility->method( 'should_convert_shipping_amount' )->willReturn( true );
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
		$this->mock_compatibility->method( 'should_convert_shipping_amount' )->willReturn( true );
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

	public function test_convert_shipping_method_rate_cost_skips_conversion_on_compatibility() {
		$shipping_method = new \WC_Shipping_Flat_Rate();
		$this->mock_compatibility
			->method( 'should_convert_shipping_amount' )
			->with( $shipping_method )
			->willReturn( false );
		$this->mock_multi_currency->expects( $this->never() )->method( 'get_price' );

		$args = $this->frontend_prices->convert_shipping_method_rate_cost( [ 'cost' => '10' ], $shipping_method );

		$this->assertSame( '10', $args['cost'] );
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

	/**
	 * @dataProvider checkout_cart_producer_provider
	 */
	public function test_cart_order_produces_saved_valuation( $store_api, $hpos, $request_handler = false, $charm_pricing = false ) {
		$this->mock_multi_currency->method( 'get_default_currency' )->willReturn( new WCPay\MultiCurrency\Currency( $this->localization_service, 'EUR' ) );
		$selected = new WCPay\MultiCurrency\Currency( $this->localization_service, 'GBP', 0.85 );
		$selected->set_rounding( $charm_pricing ? '0.50' : '0.00' );
		$selected->set_charm( $charm_pricing ? -0.10 : 0 );
		$this->mock_multi_currency->method( 'get_selected_currency' )->willReturn( $selected );
		$pricing = $this->getMockBuilder( WCPay\MultiCurrency\MultiCurrency::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'get_selected_currency' ] )
			->getMock();
		$pricing->method( 'get_selected_currency' )->willReturn( $selected );
		$localization = new \ReflectionProperty( WCPay\MultiCurrency\MultiCurrency::class, 'localization_service' );
		$localization->setAccessible( true );
		$localization->setValue( $pricing, $this->localization_service );
		$this->mock_multi_currency->method( 'get_price' )->willReturnCallback( [ $pricing, 'get_price' ] );
		$this->mock_compatibility->method( 'should_convert_product_price' )->willReturn( true );
		$currency_filter = static function () {
			return 'GBP';
		};
		$storage_filter  = static function () use ( $hpos ) {
			return $hpos ? 'yes' : 'no';
		};
		add_filter( 'pre_option_woocommerce_custom_orders_table_enabled', $storage_filter );
		add_filter( 'woocommerce_currency', $currency_filter );
		$product = new \WC_Product_Simple();
		$product->set_name( 'Synthetic checkout valuation' );
		$product->set_regular_price( '42.35' );
		$product->set_virtual( true );
		$product->save();
		$order             = null;
		$original_gateways = WC()->payment_gateways()->payment_gateways;
		$legacy            = new \Automattic\WooCommerce\StoreApi\Legacy();
		$original_session  = clone WC()->session;
		$original_customer = clone WC()->customer;
		$suppress_mail     = static function () {
			return true;
		};
		$remember_order    = static function ( $created_order ) use ( &$order ) {
			$order = $created_order;
		};
		add_filter( 'pre_wp_mail', $suppress_mail );
		add_action( 'woocommerce_store_api_checkout_order_created', $remember_order );
		try {
			$this->assertSame( $hpos, \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() );
			WC()->cart->empty_cart();
			$this->assertNotFalse( WC()->cart->add_to_cart( $product->get_id() ) );
			WC()->cart->calculate_totals();
			if ( $request_handler ) {
				$legacy->init();
				$gateway                                   = new \WC_Gateway_BACS();
				$gateway->enabled                          = 'yes';
				WC()->payment_gateways()->payment_gateways = [ 'bacs' => $gateway ];
				$schema                                    = \Automattic\WooCommerce\StoreApi\StoreApi::container()->get( \Automattic\WooCommerce\StoreApi\SchemaController::class );
				$route                                     = new \Automattic\WooCommerce\StoreApi\Routes\V1\Checkout( $schema, $schema->get( 'checkout' ) );
				$request                                   = new \WP_REST_Request( 'POST', '/wc/store/v1/checkout' );
				$request->set_header( 'Nonce', wp_create_nonce( 'wc_store_api' ) );
				$request->set_body_params(
					[
						'billing_address' => [
							'first_name' => 'Synthetic',
							'last_name'  => 'Checkout',
							'address_1'  => '1 Test Street',
							'city'       => 'Cambridge',
							'state'      => '',
							'postcode'   => 'CB24 1AB',
							'country'    => 'GB',
							'email'      => 'checkout-fixture@example.test',
							'phone'      => '01234567890',
						],
						'payment_method'  => 'bacs',
						'payment_data'    => [],
						'extensions'      => [],
					]
				);
				$response = $route->get_response( $request );
				$this->assertSame( 200, $response->get_status(), wp_json_encode( $response->get_data() ) );
				$order_id = $response->get_data()['order_id'];
				$order    = wc_get_order( $order_id );
			} elseif ( $store_api ) {
				$controller = new \Automattic\WooCommerce\StoreApi\Utilities\OrderController();
				$order      = $controller->create_order_from_cart();
				$this->assertSame( 'checkout-draft', $order->get_status() );
				$this->assertFalse( $order->meta_exists( '_wcpay_checkout_sales_snapshot' ) );
				$order->update_status( 'pending' );
				$order_id = $order->get_id();
			} else {
				$order_id = WC()->checkout()->create_order(
					[
						'payment_method' => 'cod',
						'billing_email'  => 'checkout-fixture@example.test',
					]
				);
				$this->assertIsInt( $order_id );
				$order = wc_get_order( $order_id );
			}
			$this->assertSame( 'GBP', $order->get_currency( 'edit' ) );
			$this->assertSame( $charm_pricing ? '35.90' : '36.00', $order->get_total( 'edit' ) );
			$this->assertSame( '42.35', wc_get_product( $product->get_id() )->get_regular_price( 'edit' ) );
			$this->assertSame( '0.85', $order->get_meta( '_wcpay_multi_currency_order_valuation_rate', true ) );
			$this->assertSame( $request_handler, $order->meta_exists( '_wcpay_checkout_sales_snapshot' ) );
			// Exercise the checkout-ready boundary after the actual order producer saves its totals.
			if ( $request_handler ) {
				$this->assertSame( 'on-hold', $order->get_status() );
			} elseif ( $store_api ) {
				// phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingHookComment -- Exercise the existing Store API boundary.
				do_action( 'woocommerce_store_api_checkout_order_processed', $order );
			} else {
				// phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingHookComment -- Exercise the existing classic boundary.
				do_action( 'woocommerce_checkout_order_processed', $order_id, [], $order );
			}
			$result = ( new \WCPay\Internal\Service\CheckoutSalesSnapshot() )->read( wc_get_order( $order_id ), 'EUR' );
			$this->assertSame( 'qualified', $result['state'] );
			$this->assertSame( $charm_pricing ? 4224 : 4235, $result['total_minor'] );
			if ( $request_handler && getenv( 'WCPAY_TEST_CUSTOMER_HISTORY_INTEGRATION' ) ) {
				$history_class      = \Automattic\WooCommerce\Internal\Admin\Orders\MetaBoxes\CustomerHistory::class;
				$admin              = self::factory()->user->create( [ 'role' => 'administrator' ] );
				$previous_user      = get_current_user_id();
				$previous_settings  = $GLOBALS['_updated_user_settings'] ?? null;
				$reporting_currency = static function () {
					return 'EUR';
				};
				$reader             = new \WCPay\Internal\Service\CheckoutSalesSnapshot();
				$native             = null;
				$partial_refund     = null;
				$final_refund       = null;
				$unrelated          = null;
				$reader->init_hooks();
				add_filter( 'woocommerce_currency', $reporting_currency, 100 );
				try {
					wp_set_current_user( $admin );
					update_user_option( $admin, 'user-settings', 'wc_history_sales_policy=checkout', false );
					unset( $GLOBALS['_updated_user_settings'] );
					$method = new \ReflectionMethod( $history_class, 'get_customer_history' );
					$method->setAccessible( true );
					$history = $method->invoke( new $history_class(), wc_get_order( $order_id ) );
					$this->assertTrue( $history['history_available'] );
					$this->assertTrue( $history['amounts_available'] );
					$this->assertSame( 1, $history['orders_count'] );
					$this->assertSame( 42.35, $history['total_spend'] );
					$this->assertSame( 42.35, $history['avg_order_value'] );
					$native = new \WC_Order();
					$native->set_currency( 'EUR' );
					$native->set_total( 10 );
					$native->set_billing_email( $order->get_billing_email() );
					$native->set_status( 'processing' );
					$native->save();
					$history = $method->invoke( new $history_class(), wc_get_order( $order_id ) );
					$this->assertTrue( $history['amounts_available'] );
					$this->assertSame( 2, $history['orders_count'] );
					$this->assertSame( 52.35, $history['total_spend'] );
					// Status transitions must change canonical membership without rewriting valuation.
					$checkout_snapshot = $order->get_meta( '_wcpay_checkout_sales_snapshot', true );
					foreach ( [
						'pending'    => false,
						'failed'     => false,
						'cancelled'  => false,
						'on-hold'    => true,
						'processing' => true,
						'completed'  => true,
					] as $status => $included ) {
						$order->set_status( $status );
						$order->save();
						$current = wc_get_order( $order_id );
						$history = $method->invoke( new $history_class(), $current );
						$this->assertTrue( $history['history_available'], $status );
						$this->assertTrue( $history['amounts_available'], $status );
						$this->assertSame( $included ? 2 : 1, $history['orders_count'], $status );
						$this->assertSame( $included ? 52.35 : 10.0, $history['total_spend'], $status );
						$this->assertSame( $checkout_snapshot, $current->get_meta( '_wcpay_checkout_sales_snapshot', true ), $status );
					}
					$partial_refund = wc_create_refund(
						[
							'order_id'       => $order_id,
							'amount'         => 18,
							'refund_payment' => false,
						]
					);
					$this->assertInstanceOf( \WC_Order_Refund::class, $partial_refund );
					$history = $method->invoke( new $history_class(), wc_get_order( $order_id ) );
					$this->assertTrue( $history['amounts_available'] );
					$this->assertSame( 2, $history['orders_count'] );
					$this->assertEqualsWithDelta( 31.17, $history['total_spend'], 0.000001 );
					// Once assigned to a registered customer, cohort membership follows the ID.
					$order->set_customer_id( $admin );
					$order->save();
					$native->set_customer_id( $admin );
					$native->set_billing_email( 'changed-address@example.test' );
					$native->save();
					$unrelated = new \WC_Order();
					$unrelated->set_currency( 'EUR' );
					$unrelated->set_total( 100 );
					$unrelated->set_billing_email( $order->get_billing_email() );
					$unrelated->set_status( 'processing' );
					$unrelated->save();
					$history = $method->invoke( new $history_class(), wc_get_order( $order_id ) );
					$this->assertTrue( $history['history_available'] );
					$this->assertTrue( $history['amounts_available'] );
					$this->assertSame( 2, $history['orders_count'] );
					$this->assertEqualsWithDelta( 31.17, $history['total_spend'], 0.000001 );
					$final_refund = wc_create_refund(
						[
							'order_id'       => $order_id,
							'amount'         => 18,
							'refund_payment' => false,
						]
					);
					$this->assertInstanceOf( \WC_Order_Refund::class, $final_refund );
					$history = $method->invoke( new $history_class(), wc_get_order( $order_id ) );
					$this->assertTrue( $history['amounts_available'] );
					$this->assertSame( 2, $history['orders_count'] );
					$this->assertSame( 10.0, $history['total_spend'] );
					$order->set_total( '42.00' );
					$order->save();
					$history = $method->invoke( new $history_class(), wc_get_order( $order_id ) );
					$this->assertTrue( $history['history_available'] );
					$this->assertFalse( $history['amounts_available'] );
					$this->assertSame( 2, $history['orders_count'] );
				} finally {
					$order->set_customer_id( 0 );
					$order->save();
					if ( $unrelated ) {
						$unrelated->delete( true ); }
					if ( $final_refund instanceof \WC_Order_Refund ) {
						$final_refund->delete( true ); }
					if ( $partial_refund instanceof \WC_Order_Refund ) {
						$partial_refund->delete( true ); }
					if ( $native ) {
						$native->delete( true ); }
					remove_filter( 'woocommerce_currency', $reporting_currency, 100 );
					remove_filter( 'woocommerce_customer_history_sales_valuation', [ $reader, 'provide_customer_history' ], 10 );
					wp_set_current_user( $previous_user );
					// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restore WordPress user-setting cache after this integration fixture.
					$GLOBALS['_updated_user_settings'] = $previous_settings;
					wp_delete_user( $admin );
				}
			}
			$snapshot = wc_get_order( $order_id )->get_meta( '_wcpay_checkout_sales_snapshot', true );
			$this->frontend_prices->capture_sales_snapshot( wc_get_order( $order_id ) );
			$reloaded = wc_get_order( $order_id );
			$this->assertCount( 1, $reloaded->get_meta( '_wcpay_checkout_sales_snapshot', false ) );
			$this->assertSame( $snapshot, $reloaded->get_meta( '_wcpay_checkout_sales_snapshot', true ) );
			$order->set_total( '42.00' );
			$order->save();
			$this->frontend_prices->capture_sales_snapshot( wc_get_order( $order_id ) );
			$reloaded = wc_get_order( $order_id );
			$this->assertSame( $snapshot, $reloaded->get_meta( '_wcpay_checkout_sales_snapshot', true ) );
			$this->assertSame( 'stale', ( new \WCPay\Internal\Service\CheckoutSalesSnapshot() )->read( $reloaded, 'EUR' )['state'] );
		} finally {
			remove_action( 'woocommerce_rest_checkout_process_payment_with_context', [ $legacy, 'process_legacy_payment' ], 999 );
			WC()->payment_gateways()->payment_gateways = $original_gateways;
			remove_filter( 'pre_wp_mail', $suppress_mail );
			remove_action( 'woocommerce_store_api_checkout_order_created', $remember_order );
			WC()->cart->empty_cart();
			remove_filter( 'woocommerce_currency', $currency_filter );
			if ( $order ) {
				$order->delete( true ); }
			$product->delete( true );
			WC()->session  = $original_session;
			WC()->customer = $original_customer;
			remove_filter( 'pre_option_woocommerce_custom_orders_table_enabled', $storage_filter );
		}
	}

	public function checkout_cart_producer_provider() {
		return [
			'classic-hpos'   => [ false, true ],
			'classic-cpt'    => [ false, false ],
			'store-api-hpos' => [ true, true ],
			'store-api-cpt'  => [ true, false ],
			'request-hpos'   => [ true, true, true ],
			'request-cpt'    => [ true, false, true ],
			'charm-hpos'     => [ true, true, false, true ],
			'charm-cpt'      => [ true, false, false, true ],
		];
	}

	public function test_store_api_hook_captures_checkout_snapshot() {
		$this->mock_multi_currency->method( 'get_selected_currency' )->willReturn( new WCPay\MultiCurrency\Currency( $this->localization_service, 'GBP', 0.85 ) );
		$this->mock_multi_currency->method( 'get_default_currency' )->willReturn( new WCPay\MultiCurrency\Currency( $this->localization_service, 'EUR' ) );
		$order = wc_create_order();
		$order->set_currency( 'GBP' );
		$order->set_total( '36.00' );
		$order->update_meta_data( '_wcpay_multi_currency_order_default_currency', 'EUR' );
		$order->update_meta_data( '_wcpay_multi_currency_order_exchange_rate', '0.85' );
		$order->update_meta_data( '_wcpay_multi_currency_order_valuation_rate', '0.85' );
		$order->update_meta_data( '_wcpay_multi_currency_order_rate_currency', 'GBP' );
		$order->save();
		$this->assertSame( 99, has_action( 'woocommerce_store_api_checkout_order_processed', [ $this->frontend_prices, 'capture_sales_snapshot' ] ) );
		// phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingHookComment -- Exercise the existing Store API checkout boundary.
		do_action( 'woocommerce_store_api_checkout_order_processed', $order );
		$snapshot = wc_get_order( $order->get_id() )->get_meta( '_wcpay_checkout_sales_snapshot', true );
		$this->assertSame( 'checkout', $snapshot['basis'] );
		$this->assertSame( '36.00', $snapshot['inputs']['total'] );
		$this->assertSame( hash( 'sha256', wp_json_encode( $snapshot['inputs'] ) ), $snapshot['fingerprint'] );
	}

	public function test_checkout_snapshot_rejects_unbound_or_changed_rate_currency() {
		$order = wc_create_order();
		$order->set_currency( 'GBP' );
		$order->set_total( '36.00' );
		$order->update_meta_data( '_wcpay_multi_currency_order_default_currency', 'EUR' );
		$order->update_meta_data( '_wcpay_multi_currency_order_exchange_rate', '0.85' );
		$order->update_meta_data( '_wcpay_multi_currency_order_valuation_rate', '0.85' );
		foreach ( [ '', 'USD' ] as $rate_currency ) {
			$order->update_meta_data( '_wcpay_multi_currency_order_rate_currency', $rate_currency );
			$order->save();
			$this->frontend_prices->capture_sales_snapshot( $order );
			$this->assertFalse( $order->meta_exists( '_wcpay_checkout_sales_snapshot' ) );
		}
	}

	public function test_checkout_snapshot_rejects_malformed_metadata() {
		$this->mock_multi_currency->method( 'get_selected_currency' )->willReturn( new WCPay\MultiCurrency\Currency( $this->localization_service, 'GBP', 0.85 ) );
		$this->mock_multi_currency->method( 'get_default_currency' )->willReturn( new WCPay\MultiCurrency\Currency( $this->localization_service, 'EUR' ) );
		$order = wc_create_order();
		$order->set_currency( 'GBP' );
		$order->set_total( '36.00' );
		$order->update_meta_data( '_wcpay_multi_currency_order_default_currency', 'EUR' );
		$order->update_meta_data( '_wcpay_multi_currency_order_exchange_rate', '0.85' );
		$order->update_meta_data( '_wcpay_multi_currency_order_valuation_rate', '0.85' );
		$order->update_meta_data( '_wcpay_multi_currency_order_rate_currency', 'GBP' );
		$order->save();
		$this->frontend_prices->capture_sales_snapshot( $order );
		$valid  = $order->get_meta( '_wcpay_checkout_sales_snapshot', true );
		$reader = new \WCPay\Internal\Service\CheckoutSalesSnapshot();
		foreach ( [ 'version', 'basis', 'captured_at', 'fingerprint', 'rate', 'precision', 'rate_direction' ] as $field ) {
			$invalid = $valid;
			if ( in_array( $field, [ 'rate', 'precision', 'rate_direction' ], true ) ) {
				$invalid['inputs'][ $field ] = [ 'unexpected' ];
				$invalid['fingerprint']      = hash( 'sha256', wp_json_encode( $invalid['inputs'] ) );
			} else {
				$invalid[ $field ] = null;
			}
			$order->update_meta_data( '_wcpay_checkout_sales_snapshot', $invalid );
			$order->save_meta_data();
			$this->assertSame( 'unavailable', $reader->read( $order, 'EUR' )['state'], $field );
		}
	}

	public function test_checkout_refund_valuation_with_hpos() {
		$enable_hpos = static function () {
			return 'yes';
		};
		add_filter( 'pre_option_woocommerce_custom_orders_table_enabled', $enable_hpos );
		try {
			$this->assertTrue( \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() );
			$this->test_checkout_snapshot_preserves_original_inputs();
		} finally {
			remove_filter( 'pre_option_woocommerce_custom_orders_table_enabled', $enable_hpos );
		}
	}

	public function test_checkout_refund_valuation_with_cpt() {
		$enable_hpos = static function () {
			return 'no';
		};
		add_filter( 'pre_option_woocommerce_custom_orders_table_enabled', $enable_hpos );
		try {
			$this->assertFalse( \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() );
			$this->test_checkout_snapshot_preserves_original_inputs();
		} finally {
			remove_filter( 'pre_option_woocommerce_custom_orders_table_enabled', $enable_hpos );
		}
	}

	public function test_checkout_snapshot_preserves_original_inputs() {
		$this->mock_multi_currency->method( 'get_selected_currency' )->willReturn( new WCPay\MultiCurrency\Currency( $this->localization_service, 'GBP', 0.85 ) );
		$this->mock_multi_currency->method( 'get_default_currency' )->willReturn( new WCPay\MultiCurrency\Currency( $this->localization_service, 'EUR' ) );
		$order = wc_create_order();
		$order->set_currency( 'GBP' );
		$order->set_total( '36.00' );
		$order->update_meta_data( '_wcpay_multi_currency_order_default_currency', 'EUR' );
		$order->update_meta_data( '_wcpay_multi_currency_order_exchange_rate', '0.85' );
		$order->update_meta_data( '_wcpay_multi_currency_order_valuation_rate', '0.85' );
		$order->update_meta_data( '_wcpay_multi_currency_order_rate_currency', 'GBP' );
		$order->save();
		$this->frontend_prices->capture_classic_sales_snapshot( $order->get_id(), [], $order );
		$snapshot = $order->get_meta( '_wcpay_checkout_sales_snapshot', true );
		$this->assertSame( '36.00', $snapshot['inputs']['total'] );
		$this->assertSame( 'GBP', $snapshot['inputs']['currency'] );
		$this->assertSame( 'EUR', $snapshot['inputs']['reporting_currency'] );
		$this->assertSame( '0.85', $snapshot['inputs']['rate'] );
		$reader = new \WCPay\Internal\Service\CheckoutSalesSnapshot();
		$this->assertSame( 'qualified', $reader->read( $order, 'EUR' )['state'] );
		$this->assertSame( 4235, $reader->read( $order, 'EUR' )['total_minor'] );
		$request       = [
			'order_id' => $order->get_id(),
			'currency' => 'EUR',
			'policy'   => 'checkout',
		];
		$previous_user = get_current_user_id();
		$user          = self::factory()->user->create( [ 'role' => 'administrator' ] );
		try {
			wp_set_current_user( 0 );
			$this->assertSame( [], $reader->provide_customer_history( [], $request ) );
			wp_set_current_user( $user );
			$reader->init_hooks();
			$this->assertSame( 10, has_filter( 'woocommerce_customer_history_sales_valuation', [ $reader, 'provide_customer_history' ] ) );
			$provided = $reader->provide_customer_history( [], $request );
			$this->assertSame( 4235, $provided['woocommerce_payments']['total_minor'] );
			$this->assertSame( 'EUR', $provided['woocommerce_payments']['currency'] );
			$this->assertSame( 'checkout', $provided['woocommerce_payments']['policy'] );
			$this->assertSame( [], $reader->provide_customer_history( [], array_merge( $request, [ 'policy' => 'settlement' ] ) ) );
		} finally {
			remove_filter( 'woocommerce_customer_history_sales_valuation', [ $reader, 'provide_customer_history' ] );
			wp_set_current_user( $previous_user );
			wp_delete_user( $user );
		}

		$first_refund = wc_create_refund(
			[
				'order_id' => $order->get_id(),
				'amount'   => 18,
			]
		);
		$this->assertInstanceOf( \WC_Order_Refund::class, $first_refund );
		$this->assertSame( 2117, $reader->read_after_refunds( $order, 'EUR' )['total_minor'] );
		global $wpdb;
		$hpos          = \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
		$table         = $hpos ? $wpdb->prefix . 'wc_orders' : $wpdb->posts;
		$status_column = $hpos ? 'status' : 'post_status';
		$id_column     = $hpos ? 'id' : 'ID';
		foreach ( [ 'trash', 'auto-draft' ] as $refund_status ) {
			$this->assertSame( 1, $wpdb->update( $table, [ $status_column => $refund_status ], [ $id_column => $first_refund->get_id() ] ) );
			$this->assertSame( 4235, $reader->read_after_refunds( $order, 'EUR' )['total_minor'] );
		}
		$this->assertSame( 1, $wpdb->update( $table, [ $status_column => 'wc-completed' ], [ $id_column => $first_refund->get_id() ] ) );

		add_filter( 'woocommerce_order_query', '__return_empty_array' );
		try {
			$this->assertSame( 2117, $reader->read_after_refunds( $order, 'EUR' )['total_minor'] );
		} finally {
			remove_filter( 'woocommerce_order_query', '__return_empty_array' );
		}

		$second_refund = wc_create_refund(
			[
				'order_id' => $order->get_id(),
				'amount'   => 18,
			]
		);
		$this->assertInstanceOf( \WC_Order_Refund::class, $second_refund );
		$refunded = $reader->read_after_refunds( $order, 'EUR' );
		$this->assertSame( 0, $refunded['total_minor'] );
		$this->assertSame( [ 2118, 2117 ], array_values( $refunded['refunds_minor'] ) );
		$first_refund->set_amount( 19 );
		$first_refund->save();
		$this->assertSame( 'unavailable', $reader->read_after_refunds( $order, 'EUR' )['state'] );
		$first_refund->set_amount( 18 );
		$first_refund->set_currency( 'USD' );
		$first_refund->save();
		$this->assertSame( 'unavailable', $reader->read_after_refunds( $order, 'EUR' )['state'] );
		$first_refund->set_currency( 'GBP' );
		$first_refund->save();

		$this->assertSame( 'unavailable', $reader->read( $order, 'USD' )['state'] );
		$order->set_total( '42.00' );
		$order->save();
		$this->frontend_prices->capture_sales_snapshot( $order );
		$this->assertSame( $snapshot, wc_get_order( $order->get_id() )->get_meta( '_wcpay_checkout_sales_snapshot', true ) );
		$this->assertSame( 'stale', $reader->read( $order, 'EUR' )['state'] );
		$this->assertSame( 'stale', $reader->read_after_refunds( $order, 'EUR' )['state'] );
		$order->add_meta_data( '_wcpay_checkout_sales_snapshot', $snapshot, false );
		$order->save_meta_data();
		$this->assertSame( 'unavailable', $reader->read( $order, 'EUR' )['state'] );
	}

	public function test_checkout_snapshot_rejects_changed_checkout_context() {
		$selected = new WCPay\MultiCurrency\Currency( $this->localization_service, 'GBP', 0.85 );
		$default  = new WCPay\MultiCurrency\Currency( $this->localization_service, 'EUR' );
		$this->mock_multi_currency->method( 'get_selected_currency' )->willReturn( $selected );
		$this->mock_multi_currency->method( 'get_default_currency' )->willReturn( $default );
		$order = wc_create_order();
		$order->set_currency( 'GBP' );
		$order->set_total( '36.00' );
		$order->save();
		$this->frontend_prices->add_order_meta( $order->get_id(), $order );
		foreach ( [ [ 'GBP', 0.86, 'EUR' ], [ 'GBP', 0.85, 'USD' ], [ 'CAD', 0.85, 'EUR' ] ] as $context ) {
			$selected->code = $context[0];
			$selected->rate = $context[1];
			$default->code  = $context[2];
			$this->frontend_prices->capture_sales_snapshot( $order );
			$this->assertFalse( $order->meta_exists( '_wcpay_checkout_sales_snapshot' ) );
		}
	}

	public function test_checkout_snapshot_uses_reporting_precision() {
		$this->mock_multi_currency->method( 'get_default_currency' )->willReturn( new WCPay\MultiCurrency\Currency( $this->localization_service, 'USD' ) );
		$this->mock_multi_currency->method( 'get_selected_currency' )->willReturn( new WCPay\MultiCurrency\Currency( $this->localization_service, 'JPY', 150 ) );
		$order = wc_create_order();
		$order->set_currency( 'JPY' );
		$order->set_total( '150' );
		$order->save();
		$this->frontend_prices->add_order_meta( $order->get_id(), $order );
		add_filter( 'wc_get_price_decimals', '__return_zero' );
		try {
			$this->frontend_prices->capture_sales_snapshot( $order );
		} finally {
			remove_filter( 'wc_get_price_decimals', '__return_zero' );
		}
		$result = ( new \WCPay\Internal\Service\CheckoutSalesSnapshot() )->read( $order, 'USD' );
		$this->assertSame( 100, $result['total_minor'] );
	}

	public function test_checkout_snapshot_preserves_unrounded_rate() {
		$this->mock_multi_currency->method( 'get_default_currency' )->willReturn( new WCPay\MultiCurrency\Currency( $this->localization_service, 'USD' ) );
		$this->mock_multi_currency->method( 'get_selected_currency' )->willReturn( new WCPay\MultiCurrency\Currency( $this->localization_service, 'GBP', 0.00456789 ) );
		$this->mock_multi_currency->method( 'get_price' )->willReturn( 0.0 );
		$order = wc_create_order();
		$order->set_currency( 'GBP' );
		$order->set_total( '36.00' );
		$order->save();
		$this->frontend_prices->add_order_meta( $order->get_id(), $order );
		$this->frontend_prices->capture_sales_snapshot( $order );
		$result = ( new \WCPay\Internal\Service\CheckoutSalesSnapshot() )->read( wc_get_order( $order->get_id() ), 'USD' );
		$this->assertSame( 'qualified', $result['state'] );
		$this->assertSame( '0.00456789', $result['inputs']['rate'] );
	}

	public function test_saved_rate_binding_uses_selected_currency() {
		$this->mock_multi_currency->method( 'get_default_currency' )->willReturn( new WCPay\MultiCurrency\Currency( $this->localization_service, 'USD' ) );
		$this->mock_multi_currency->method( 'get_selected_currency' )->willReturn( new WCPay\MultiCurrency\Currency( $this->localization_service, 'EUR' ) );
		$this->mock_multi_currency->method( 'get_price' )->willReturn( 0.71 );
		$order = wc_create_order();
		$order->set_currency( 'GBP' );
		$this->frontend_prices->add_order_meta( $order->get_id(), $order );
		$this->assertSame( 'EUR', wc_get_order( $order->get_id() )->get_meta( '_wcpay_multi_currency_order_rate_currency' ) );
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
				function ( $price, $_unused_from_currency, $_unused_to_currency ) {
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
