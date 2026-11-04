<?php
/**
 * Class WCPay_Multi_Currency_Frontend_Prices_Tests
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\Constants\Currency_Code;

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
	 * A `woocommerce_currency` filter standing in for the currency WooCommerce reports.
	 *
	 * @var callable|null
	 */
	private $reported_currency_filter;

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
		if ( $this->reported_currency_filter ) {
			remove_filter( 'woocommerce_currency', $this->reported_currency_filter, 5 );
		}
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
			[ 'woocommerce_order_recalculate_coupons_coupon_object', 'set_order_coupon' ],
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

	/**
	 * @dataProvider order_coupon_currency_provider
	 */
	public function test_get_coupon_amount_converts_coupon_reapplied_to_order_to_the_order_currency( $order_currency, $expected_currency ) {
		$currencies = [
			'USD' => new WCPay\MultiCurrency\Currency( $this->localization_service, 'USD' ),
			'JPY' => new WCPay\MultiCurrency\Currency( $this->localization_service, 'JPY', 150 ),
			'EUR' => new WCPay\MultiCurrency\Currency( $this->localization_service, 'EUR', 0.9 ),
		];
		$this->mock_multi_currency->method( 'get_default_currency' )->willReturn( $currencies['USD'] );
		$this->mock_multi_currency->method( 'get_available_currencies' )->willReturn( $currencies );
		// EUR is supported but disabled, as when the merchant turns a currency off after checkout.
		$this->mock_multi_currency->method( 'get_enabled_currencies' )->willReturn(
			[
				'USD' => $currencies['USD'],
				'JPY' => $currencies['JPY'],
			]
		);
		$this->mock_compatibility->method( 'should_convert_coupon_amount' )->willReturn( true );

		$order = WC_Helper_Order::create_order();
		$order->set_currency( $order_currency );

		$coupon = new WC_Coupon();
		$coupon->set_discount_type( 'fixed_cart' );

		$this->mock_multi_currency
			->expects( $this->once() )
			->method( 'get_price' )
			->with( '10', 'coupon', $this->identicalTo( $currencies[ $expected_currency ] ) )
			->willReturn( 25.0 );

		$this->assertSame( $coupon, $this->frontend_prices->set_order_coupon( $coupon, 'code', new WC_Order_Item_Coupon(), $order ) );
		$this->assertSame( 25.0, $this->frontend_prices->get_coupon_amount( '10', $coupon ) );
	}

	public function order_coupon_currency_provider() {
		return [
			'store currency'       => [ 'USD', 'USD' ],
			'enabled currency'     => [ 'JPY', 'JPY' ],
			'disabled currency'    => [ 'EUR', 'EUR' ],
			'unsupported currency' => [ 'BTC', 'USD' ],
		];
	}

	public function test_get_coupon_amount_does_not_convert_coupon_rebuilt_from_the_order() {
		$this->mock_multi_currency->method( 'get_available_currencies' )->willReturn( [ 'JPY' => new WCPay\MultiCurrency\Currency( $this->localization_service, 'JPY', 150 ) ] );
		$this->mock_multi_currency->expects( $this->never() )->method( 'get_price' );
		$this->mock_compatibility->method( 'should_convert_coupon_amount' )->willReturn( true );

		$order = WC_Helper_Order::create_order();
		$order->set_currency( 'JPY' );

		// A coupon that no longer exists is rebuilt from the order's coupon line and flagged as virtual.
		$coupon = new WC_Coupon();
		$coupon->set_discount_type( 'fixed_cart' );
		$coupon->set_virtual( true );

		$this->frontend_prices->set_order_coupon( $coupon, 'code', new WC_Order_Item_Coupon(), $order );

		$this->assertSame( '1500', $this->frontend_prices->get_coupon_amount( '1500', $coupon ) );
	}

	/**
	 * @dataProvider legacy_coupon_data_provider
	 */
	public function test_legacy_virtual_coupon_is_converted_to_the_order_currency( $coupon_data ) {
		$usd = new WCPay\MultiCurrency\Currency( $this->localization_service, 'USD' );
		$jpy = new WCPay\MultiCurrency\Currency( $this->localization_service, 'JPY', 150 );

		$this->mock_multi_currency->method( 'get_default_currency' )->willReturn( $usd );
		$this->mock_multi_currency->method( 'get_available_currencies' )->willReturn(
			[
				'USD' => $usd,
				'JPY' => $jpy,
			]
		);
		$this->mock_compatibility->method( 'should_convert_coupon_amount' )->willReturn( true );
		$this->mock_multi_currency->method( 'get_price' )->willReturnCallback(
			function ( $price ) {
				return (float) $price * 150;
			}
		);

		$order = WC_Helper_Order::create_order();
		$order->set_currency( 'JPY' );

		// A coupon whose post was deleted, rebuilt from the legacy `coupon_data` line meta, whose amount
		// is the raw store-currency value and so must still be converted to the order's currency.
		$coupon = new WC_Coupon();
		$coupon->set_code( 'legacy5' );
		$coupon->set_discount_type( 'fixed_cart' );
		$coupon->set_amount( 5 );
		$coupon->set_virtual( true );

		$coupon_item = new WC_Order_Item_Coupon();
		$coupon_item->set_code( 'legacy5' );
		$coupon_item->add_meta_data( 'coupon_data', $coupon_data );

		$this->frontend_prices->set_order_coupon( $coupon, 'legacy5', $coupon_item, $order );

		// The 5 USD coupon was worth 750 JPY on this order, so the rebuild must convert it.
		$this->assertSame( 750.0, $this->frontend_prices->get_coupon_amount( '5', $coupon ) );
	}

	public function legacy_coupon_data_provider() {
		$coupon_data = [
			'id'            => 0,
			'code'          => 'legacy5',
			'discount_type' => 'fixed_cart',
			'amount'        => 5,
		];

		return [
			'array'  => [ $coupon_data ],
			'object' => [ (object) $coupon_data ],
		];
	}

	public function test_get_coupon_amount_converts_other_coupons_to_the_selected_currency() {
		$usd = new WCPay\MultiCurrency\Currency( $this->localization_service, 'USD' );
		$this->mock_multi_currency->method( 'get_available_currencies' )->willReturn( [ 'USD' => $usd ] );
		$this->mock_compatibility->method( 'should_convert_coupon_amount' )->willReturn( true );

		$this->frontend_prices->set_order_coupon( new WC_Coupon(), 'code', new WC_Order_Item_Coupon(), WC_Helper_Order::create_order() );

		$this->mock_multi_currency
			->expects( $this->once() )
			->method( 'get_price' )
			->with( '10', 'coupon', null )
			->willReturn( 25.0 );

		$this->assertSame( 25.0, $this->frontend_prices->get_coupon_amount( '10', new WC_Coupon() ) );
	}

	public function test_set_order_coupon_leaves_non_store_api_rest_requests_to_the_selected_currency() {
		$original_uri           = $_SERVER['REQUEST_URI'] ?? null;
		$_SERVER['REQUEST_URI'] = '/wp-json/wc/v3/orders';

		try {
			$usd = new WCPay\MultiCurrency\Currency( $this->localization_service, 'USD' );
			$this->mock_multi_currency->method( 'get_available_currencies' )->willReturn( [ 'USD' => $usd ] );
			$this->mock_compatibility->method( 'should_convert_coupon_amount' )->willReturn( true );

			$coupon = new WC_Coupon();
			$coupon->set_discount_type( 'fixed_cart' );

			// Standard REST order write: keeps the selected-currency path (currency omitted), not the order currency.
			$this->frontend_prices->set_order_coupon( $coupon, 'code', new WC_Order_Item_Coupon(), WC_Helper_Order::create_order() );

			$this->mock_multi_currency
				->expects( $this->once() )
				->method( 'get_price' )
				->with( '10', 'coupon', null )
				->willReturn( 25.0 );

			$this->assertSame( 25.0, $this->frontend_prices->get_coupon_amount( '10', $coupon ) );
		} finally {
			if ( null === $original_uri ) {
				unset( $_SERVER['REQUEST_URI'] );
			} else {
				$_SERVER['REQUEST_URI'] = $original_uri;
			}
		}
	}

	public function test_set_order_coupon_still_converts_store_api_requests_to_the_order_currency() {
		$original_uri           = $_SERVER['REQUEST_URI'] ?? null;
		$_SERVER['REQUEST_URI'] = '/wp-json/wc/store/v1/checkout/1';

		try {
			$currencies = [
				'USD' => new WCPay\MultiCurrency\Currency( $this->localization_service, 'USD' ),
				'JPY' => new WCPay\MultiCurrency\Currency( $this->localization_service, 'JPY', 150 ),
			];
			$this->mock_multi_currency->method( 'get_default_currency' )->willReturn( $currencies['USD'] );
			$this->mock_multi_currency->method( 'get_available_currencies' )->willReturn( $currencies );
			$this->mock_compatibility->method( 'should_convert_coupon_amount' )->willReturn( true );

			$order = WC_Helper_Order::create_order();
			$order->set_currency( 'JPY' );

			$coupon = new WC_Coupon();
			$coupon->set_discount_type( 'fixed_cart' );

			// Store API pay-for-order: still converted to the order's currency.
			$this->mock_multi_currency
				->expects( $this->once() )
				->method( 'get_price' )
				->with( '10', 'coupon', $this->identicalTo( $currencies['JPY'] ) )
				->willReturn( 25.0 );

			$this->assertSame( $coupon, $this->frontend_prices->set_order_coupon( $coupon, 'code', new WC_Order_Item_Coupon(), $order ) );
			$this->assertSame( 25.0, $this->frontend_prices->get_coupon_amount( '10', $coupon ) );
		} finally {
			if ( null === $original_uri ) {
				unset( $_SERVER['REQUEST_URI'] );
			} else {
				$_SERVER['REQUEST_URI'] = $original_uri;
			}
		}
	}

	public function test_recalculate_coupons_uses_the_order_currency_instead_of_the_selected_one() {
		$usd = new WCPay\MultiCurrency\Currency( $this->localization_service, 'USD' );
		$this->mock_multi_currency->method( 'get_available_currencies' )->willReturn( [ 'USD' => $usd ] );
		$this->mock_compatibility->method( 'should_convert_coupon_amount' )->willReturn( true );

		// JPY is selected, at 150 JPY for 1 USD: the selected-currency path (currency omitted) returns
		// 150x and the order-currency path (USD passed explicitly) returns 1x. Asserting a $5 discount
		// proves the order currency is used; a regression to the selected currency would yield $750. The
		// exact currency object passed to get_price is pinned in
		// test_get_coupon_amount_converts_coupon_reapplied_to_order_to_the_order_currency.
		$this->mock_multi_currency
			->method( 'get_price' )
			->willReturnCallback(
				function ( $price, $type, $currency = null ) use ( $usd ) {
					return (float) $price * ( $usd === $currency ? 1 : 150 );
				}
			);

		$coupon = new WC_Coupon();
		$coupon->set_code( 'take5' );
		$coupon->set_discount_type( 'fixed_cart' );
		$coupon->set_amount( 5 );
		$coupon->save();

		$coupon_item = new WC_Order_Item_Coupon();
		$coupon_item->set_code( 'take5' );

		// A USD order for 4 products at 10 USD, plus 10 USD of shipping.
		$order = WC_Helper_Order::create_order();
		$order->add_item( $coupon_item );
		$order->recalculate_coupons();

		$this->assertSame( 'USD', $order->get_currency() );
		$this->assertEquals( 5, $order->get_discount_total() );
		$this->assertEquals( 45, $order->get_total() );
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

	public function test_add_order_meta_skips_order_in_third_party_switcher_currency() {
		// An idle Multi-Currency module leaves the selection on the store currency, while a third-party
		// currency switcher filters the WooCommerce currency and the order is created in it.
		$default_currency = new WCPay\MultiCurrency\Currency( $this->localization_service, Currency_Code::UNITED_STATES_DOLLAR );
		$this->mock_multi_currency->method( 'get_default_currency' )->willReturn( $default_currency );
		$this->mock_multi_currency->method( 'get_selected_currency' )->willReturn( $default_currency );
		$this->mock_multi_currency->method( 'get_price' )->with( 1, 'exchange_rate' )->willReturn( 1.0 );
		$this->report_woocommerce_currency( Currency_Code::EURO );

		$order = wc_create_order();
		$order->set_currency( Currency_Code::EURO );

		$this->frontend_prices->add_order_meta( $order->get_id(), $order );

		// Get the order from the database.
		$order = wc_get_order( $order->get_id() );

		$this->assertFalse( $order->meta_exists( '_wcpay_multi_currency_order_exchange_rate' ) );
		$this->assertFalse( $order->meta_exists( '_wcpay_multi_currency_order_default_currency' ) );
	}

	public function test_add_order_meta_skips_switcher_order_in_currency_also_enabled_in_multi_currency() {
		// Multi-Currency has the currency enabled, but the shopper never selected it there: the
		// third-party switcher set it, and Multi-Currency did not convert the order.
		$default_currency = new WCPay\MultiCurrency\Currency( $this->localization_service, Currency_Code::UNITED_STATES_DOLLAR );
		$this->mock_multi_currency->method( 'get_default_currency' )->willReturn( $default_currency );
		$this->mock_multi_currency->method( 'get_enabled_currencies' )->willReturn(
			[
				Currency_Code::UNITED_STATES_DOLLAR => $default_currency,
				Currency_Code::EURO                 => new WCPay\MultiCurrency\Currency( $this->localization_service, Currency_Code::EURO ),
			]
		);
		$this->mock_multi_currency->method( 'get_selected_currency' )->willReturn( $default_currency );
		$this->mock_multi_currency->method( 'get_price' )->with( 1, 'exchange_rate' )->willReturn( 1.0 );
		$this->report_woocommerce_currency( Currency_Code::EURO );

		$order = wc_create_order();
		$order->set_currency( Currency_Code::EURO );

		$this->frontend_prices->add_order_meta( $order->get_id(), $order );

		// Get the order from the database.
		$order = wc_get_order( $order->get_id() );

		$this->assertFalse( $order->meta_exists( '_wcpay_multi_currency_order_exchange_rate' ) );
		$this->assertFalse( $order->meta_exists( '_wcpay_multi_currency_order_default_currency' ) );
	}

	public function test_add_order_meta_skips_switcher_order_overriding_multi_currency_selection() {
		// The shopper selected GBP in Multi-Currency, but a switcher hooked after it reports EUR,
		// which Multi-Currency also has enabled.
		$default_currency  = new WCPay\MultiCurrency\Currency( $this->localization_service, Currency_Code::UNITED_STATES_DOLLAR );
		$selected_currency = new WCPay\MultiCurrency\Currency( $this->localization_service, Currency_Code::POUND_STERLING );
		$this->mock_multi_currency->method( 'get_default_currency' )->willReturn( $default_currency );
		$this->mock_multi_currency->method( 'get_enabled_currencies' )->willReturn(
			[
				Currency_Code::UNITED_STATES_DOLLAR => $default_currency,
				Currency_Code::POUND_STERLING       => $selected_currency,
				Currency_Code::EURO                 => new WCPay\MultiCurrency\Currency( $this->localization_service, Currency_Code::EURO ),
			]
		);
		$this->mock_multi_currency->method( 'get_selected_currency' )->willReturn( $selected_currency );
		$this->mock_multi_currency->method( 'get_price' )->with( 1, 'exchange_rate' )->willReturn( 0.71 );
		$this->report_woocommerce_currency( Currency_Code::EURO );

		$order = wc_create_order();
		$order->set_currency( Currency_Code::EURO );

		$this->frontend_prices->add_order_meta( $order->get_id(), $order );

		// Get the order from the database.
		$order = wc_get_order( $order->get_id() );

		$this->assertFalse( $order->meta_exists( '_wcpay_multi_currency_order_exchange_rate' ) );
		$this->assertFalse( $order->meta_exists( '_wcpay_multi_currency_order_default_currency' ) );
	}

	public function test_add_order_meta_keeps_explicit_order_currency() {
		// An order created through the REST API or code with an explicit currency, while WooCommerce
		// itself still reports the store currency.
		$default_currency = new WCPay\MultiCurrency\Currency( $this->localization_service, Currency_Code::UNITED_STATES_DOLLAR );
		$this->mock_multi_currency->method( 'get_default_currency' )->willReturn( $default_currency );
		$this->mock_multi_currency->method( 'get_selected_currency' )->willReturn( $default_currency );
		$this->mock_multi_currency->method( 'get_price' )->with( 1, 'exchange_rate' )->willReturn( 1.0 );

		$order = wc_create_order();
		$order->set_currency( Currency_Code::EURO );

		$this->frontend_prices->add_order_meta( $order->get_id(), $order );

		// Get the order from the database.
		$order = wc_get_order( $order->get_id() );

		$this->assertSame( '1', $order->get_meta( '_wcpay_multi_currency_order_exchange_rate' ) );
		$this->assertSame( 'USD', $order->get_meta( '_wcpay_multi_currency_order_default_currency' ) );
	}

	public function test_add_order_meta() {
		// The shopper selected GBP in Multi-Currency, which reports it as the WooCommerce currency.
		$this->mock_multi_currency->method( 'get_default_currency' )->willReturn( new WCPay\MultiCurrency\Currency( $this->localization_service, 'USD' ) );
		$this->mock_multi_currency->method( 'get_selected_currency' )->willReturn( new WCPay\MultiCurrency\Currency( $this->localization_service, Currency_Code::POUND_STERLING ) );
		$this->mock_multi_currency->method( 'get_price' )->with( 1, 'exchange_rate' )->willReturn( 0.71 );
		$this->report_woocommerce_currency( Currency_Code::POUND_STERLING );

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

	/**
	 * Makes WooCommerce report the given currency, as a currency switcher's filter would.
	 *
	 * @param string $currency_code The currency code to report.
	 */
	private function report_woocommerce_currency( string $currency_code ) {
		$this->reported_currency_filter = static function () use ( $currency_code ) {
			return $currency_code;
		};
		add_filter( 'woocommerce_currency', $this->reported_currency_filter, 5 );
	}
}
