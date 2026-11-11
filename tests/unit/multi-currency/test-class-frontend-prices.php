<?php
/**
 * Class WCPay_Multi_Currency_Frontend_Prices_Tests
 *
 * @package WooCommerce\Payments\Tests
 */

use Automattic\WooCommerce\Currency\ActiveCurrency;
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
	/**
	 * Holds the currency and operation announced for the test, released in tear_down().
	 *
	 * @var \Automattic\WooCommerce\Currency\ActiveCurrencyScope|null
	 */
	private $active_scope = null;

	public function set_up() {
		parent::set_up();
		foreach ( \WC_Shipping_Zones::get_zone( 0 )->get_shipping_methods() as $zone_method ) {
			\WC_Shipping_Zones::get_zone( 0 )->delete_shipping_method( $zone_method->instance_id );
		}
		WCPay_Test_Request_Context::set( 'frontend' );

		$this->mock_compatibility   = $this->createMock( WCPay\MultiCurrency\Compatibility::class );
		$this->mock_multi_currency  = $this->createMock( WCPay\MultiCurrency\MultiCurrency::class );
		$this->localization_service = new WC_Payments_Localization_Service();

		$this->frontend_prices = new WCPay\MultiCurrency\FrontendPrices( $this->mock_multi_currency, $this->mock_compatibility );
		$this->frontend_prices->init_hooks();
	}

	public function tear_down() {
		$this->active_scope = null;
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
			[ 'woocommerce_evaluate_shipping_cost_args', 'convert_shipping_cost_args_to_store_currency' ],
			[ 'woocommerce_shipping_zone_shipping_methods', 'convert_free_shipping_method_min_amount' ],
			[ 'woocommerce_coupon_get_amount', 'get_coupon_amount' ],
			[ 'woocommerce_coupon_get_minimum_amount', 'get_coupon_min_max_amount' ],
			[ 'woocommerce_coupon_get_maximum_amount', 'get_coupon_min_max_amount' ],
			[ 'woocommerce_new_order', 'add_order_meta' ],
		];
	}

	/**
	 * @dataProvider charm_only_to_products_provider
	 */
	public function test_add_currency_to_shipping_packages_adds_marker_to_each_package( $charm_only_to_products, $expected_marker ) {
		$currency = new WCPay\MultiCurrency\Currency( $this->localization_service, Currency_Code::EURO, 0.9 );
		$currency->set_rounding( '0.25' );
		$currency->set_charm( -0.01 );
		$this->mock_multi_currency->method( 'get_selected_currency' )->willReturn( $currency );
		$this->mock_multi_currency->method( 'get_apply_charm_only_to_products' )->willReturn( $charm_only_to_products );

		$packages = [
			0 => [ 'contents' => [ 'a' ] ],
			1 => [ 'contents' => [ 'b' ] ],
		];

		$result = $this->frontend_prices->add_currency_to_shipping_packages( $packages );

		// Marker is a ':'-delimited signature: code, rate, rounding, charm, charm-only flag.
		$this->assertSame( $expected_marker, $result[0]['wcpay_multi_currency'] );
		$this->assertSame( $expected_marker, $result[1]['wcpay_multi_currency'] );
		// Existing package data is preserved.
		$this->assertSame( [ 'a' ], $result[0]['contents'] );
	}

	public function charm_only_to_products_provider() {
		return [
			'charm applies to products only' => [ true, 'EUR:0.9:0.25:-0.01:1' ],
			'charm applies to shipping too'  => [ false, 'EUR:0.9:0.25:-0.01:0' ],
		];
	}

	public function test_add_currency_to_shipping_packages_skips_non_array_and_empty_packages() {
		$currency = new WCPay\MultiCurrency\Currency( $this->localization_service, Currency_Code::EURO, 0.9 );
		$this->mock_multi_currency->method( 'get_selected_currency' )->willReturn( $currency );
		$this->mock_multi_currency->method( 'get_apply_charm_only_to_products' )->willReturn( true );

		// A non-array passed on by an earlier filter is returned untouched.
		$this->assertSame( 'not-an-array', $this->frontend_prices->add_currency_to_shipping_packages( 'not-an-array' ) );

		// A non-array package entry is skipped; array entries still get the marker.
		$result = $this->frontend_prices->add_currency_to_shipping_packages(
			[
				0 => [ 'contents' => [ 'a' ] ],
				1 => 'skip-me',
			]
		);
		$this->assertArrayHasKey( 'wcpay_multi_currency', $result[0] );
		$this->assertSame( 'skip-me', $result[1] );

		// An empty package stays empty, so WooCommerce still discards it instead of rating it.
		$this->assertSame( [ [] ], $this->frontend_prices->add_currency_to_shipping_packages( [ [] ] ) );
	}

	/**
	 * In the store currency the packages must hash the same as where Multi-Currency doesn't filter them. Otherwise each
	 * switch between those contexts throws away the cached shipping rates and asks a live-rate carrier again.
	 */
	public function test_add_currency_to_shipping_packages_leaves_store_currency_packages_unchanged() {
		$usd = new WCPay\MultiCurrency\Currency( $this->localization_service, Currency_Code::UNITED_STATES_DOLLAR );
		$this->mock_multi_currency->method( 'get_selected_currency' )->willReturn( $usd );
		$this->mock_multi_currency->method( 'get_default_currency' )->willReturn( $usd );
		$packages = [ 0 => [ 'contents' => [ 'a' ] ] ];

		$this->assertSame( [ 0 => [ 'contents' => [ 'a' ] ] ], $this->frontend_prices->add_currency_to_shipping_packages( $packages ) );
	}

	public function test_add_currency_to_shipping_packages_runs_last() {
		$this->assertSame(
			PHP_INT_MAX,
			has_filter( 'woocommerce_cart_shipping_packages', [ $this->frontend_prices, 'add_currency_to_shipping_packages' ] )
		);
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

	public function test_convert_shipping_cost_args_converts_cost_to_store_currency() {
		$this->mock_compatibility->method( 'should_convert_shipping_amount' )->willReturn( true );
		$this->use_selected_currency( Currency_Code::POUND_STERLING, 0.8 );
		$method = $this->fee_reading_method();

		$args = $this->frontend_prices->convert_shipping_cost_args_to_store_currency( [ 'cost' => '10' ], '[cost]', $method );

		$this->assertSame( 12.5, $args['cost'] );
		// Outside the shortcode, the selected currency's decimals still apply.
		$this->assertSame( 0, wc_get_price_decimals() );
	}

	public function test_convert_shipping_cost_args_skips_conversion_on_compatibility() {
		$shipping_method = $this->fee_reading_method();
		$this->mock_compatibility
			->method( 'should_convert_shipping_amount' )
			->with( $shipping_method )
			->willReturn( false );
		$this->use_selected_currency( Currency_Code::POUND_STERLING, 0.8 );

		$args = $this->frontend_prices->convert_shipping_cost_args_to_store_currency( [ 'cost' => '10' ], '10', $shipping_method );

		$this->assertSame( '10', $args['cost'] );
		// An opted-out method keeps the selected currency's decimals.
		$this->assertSame( [ 0 ], $this->decimals_seen_by_shipping_fee_shortcode( '[fee]', $shipping_method ) );
	}

	public function test_convert_shipping_cost_args_skips_conversion_in_store_currency() {
		$this->mock_compatibility->method( 'should_convert_shipping_amount' )->willReturn( true );
		$this->use_selected_currency( Currency_Code::UNITED_STATES_DOLLAR, 1.0 );
		$method = $this->fee_reading_method();

		$args = $this->frontend_prices->convert_shipping_cost_args_to_store_currency( [ 'cost' => '10' ], '10', $method );

		$this->assertSame( '10', $args['cost'] );
		$this->assertSame( [ 0 ], $this->decimals_seen_by_shipping_fee_shortcode( '[fee]', $method ) );
	}

	public function test_convert_shipping_cost_args_skips_conversion_for_non_positive_rate() {
		$this->mock_compatibility->method( 'should_convert_shipping_amount' )->willReturn( true );
		$this->use_selected_currency( Currency_Code::POUND_STERLING, 0.0 );
		$method = $this->fee_reading_method();

		$args = $this->frontend_prices->convert_shipping_cost_args_to_store_currency( [ 'cost' => '10' ], '10', $method );

		$this->assertSame( '10', $args['cost'] );
		$this->assertSame( [ 0 ], $this->decimals_seen_by_shipping_fee_shortcode( '[fee]', $method ) );
	}

	public function test_a_later_non_converting_evaluation_resets_store_currency_decimals() {
		$this->mock_compatibility->method( 'should_convert_shipping_amount' )->willReturnOnConsecutiveCalls( true, false );
		$this->use_selected_currency( Currency_Code::POUND_STERLING, 0.8 );
		$method = $this->fee_reading_method();

		$this->frontend_prices->convert_shipping_cost_args_to_store_currency( [ 'cost' => '10' ], '[fee]', $method );
		$this->frontend_prices->convert_shipping_cost_args_to_store_currency( [ 'cost' => '10' ], '[fee]', $method );

		$this->assertSame( [ 0 ], $this->decimals_seen_by_shipping_fee_shortcode( '[fee]', $method ) );
	}

	public function test_fee_shortcode_not_registered_by_a_shipping_method_keeps_selected_currency_format() {
		update_option( 'woocommerce_price_decimal_sep', ',' );
		$this->use_selected_currency( Currency_Code::POUND_STERLING, 0.8 );

		$seen = [];
		add_shortcode(
			'fee',
			function ( $atts ) use ( &$seen ) {
				shortcode_atts( [], $atts, 'fee' );
				$seen[] = wc_get_price_decimal_separator();
				return '';
			}
		);
		do_shortcode( '[fee]' );
		remove_shortcode( 'fee' );

		$this->assertSame( [ '.' ], $seen );
	}

	public function test_fee_shortcode_keeps_decimal_separator_in_store_currency() {
		update_option( 'woocommerce_price_decimal_sep', ',' );
		$this->use_selected_currency( Currency_Code::UNITED_STATES_DOLLAR, 1.0 );

		$this->assertSame( [ '.' ], $this->values_seen_by_shipping_fee_shortcode( '[fee percent="2,5"]', 'wc_get_price_decimal_separator' ) );
	}

	/**
	 * Selects a currency on the mocked MultiCurrency, against a USD store, and stands in for the
	 * selected currency's zero decimals and dot separator that FrontendCurrencies would otherwise report.
	 *
	 * @param string $code The selected currency code.
	 * @param float  $rate Its rate against USD.
	 */
	private function use_selected_currency( string $code, float $rate ) {
		$this->mock_multi_currency->method( 'get_default_currency' )->willReturn( new WCPay\MultiCurrency\Currency( $this->localization_service, Currency_Code::UNITED_STATES_DOLLAR ) );
		$this->mock_multi_currency->method( 'get_selected_currency' )->willReturn( new WCPay\MultiCurrency\Currency( $this->localization_service, $code, $rate ) );
		add_filter(
			'wc_get_price_decimals',
			function () {
				return 0;
			},
			900
		);
		add_filter(
			'wc_get_price_decimal_separator',
			function () {
				return '.';
			},
			900
		);
	}

	/**
	 * Renders a formula's `[fee]` shortcodes the way WC_Shipping_Flat_Rate::evaluate_cost() does, and
	 * returns the decimals each shortcode saw.
	 *
	 * @param string                      $formula The formula to render.
	 * @param \WC_Shipping_Flat_Rate|null $method  A method from fee_reading_method(), such as the one whose
	 *                                             cost was converted. A new one when omitted.
	 *
	 * @return int[]
	 */
	private function decimals_seen_by_shipping_fee_shortcode( string $formula, $method = null ): array {
		return $this->values_seen_by_shipping_fee_shortcode( $formula, 'wc_get_price_decimals', $method );
	}

	/**
	 * Renders a formula's `[fee]` shortcodes the way WC_Shipping_Flat_Rate::evaluate_cost() does, and
	 * returns what the given reader returned inside each shortcode.
	 *
	 * @param string                      $formula The formula to render.
	 * @param callable                    $read    Reads the value, such as wc_get_price_decimals.
	 * @param \WC_Shipping_Flat_Rate|null $method  A method from fee_reading_method(). A new one when omitted.
	 *
	 * @return array
	 */
	private function values_seen_by_shipping_fee_shortcode( string $formula, callable $read, $method = null ): array {
		$method       = $method ?? $this->fee_reading_method();
		$method->read = $read;
		$method->seen = [];

		add_shortcode( 'fee', [ $method, 'fee' ] );
		do_shortcode( $formula );
		remove_shortcode( 'fee' );

		return $method->seen;
	}

	/**
	 * A flat rate whose fee() records what its reader returns inside the shortcode.
	 *
	 * @return \WC_Shipping_Flat_Rate
	 */
	private function fee_reading_method() {
		return new class() extends \WC_Shipping_Flat_Rate {
			/**
			 * @var callable
			 */
			public $read = 'wc_get_price_decimals';

			/**
			 * @var array
			 */
			public $seen = [];

			public function fee( $atts ) {
				// Like WC_Shipping_Flat_Rate::fee(), read the attributes before anything else.
				shortcode_atts( [], $atts, 'fee' );
				$this->seen[] = ( $this->read )();
				return '';
			}
		};
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
		$this->simulate_order_operation( $order_currency );

		$coupon = $this->coupon_held_in( '' );

		if ( null === $expected_currency ) {
			$this->mock_multi_currency->expects( $this->never() )->method( 'get_price' );
			$this->assertSame( '10', $this->frontend_prices->get_coupon_amount( '10', $coupon ) );
			return;
		}

		$this->mock_multi_currency
			->expects( $this->once() )
			->method( 'get_price' )
			->with( 10.0, 'coupon', $this->identicalTo( $currencies[ $expected_currency ] ) )
			->willReturn( 25.0 );

		$this->assertSame( 25.0, $this->frontend_prices->get_coupon_amount( '10', $coupon ) );
	}

	public function order_coupon_currency_provider() {
		return [
			'store currency'       => [ 'USD', null ],
			'enabled currency'     => [ 'JPY', 'JPY' ],
			'disabled currency'    => [ 'EUR', 'EUR' ],
			'unsupported currency' => [ 'BTC', null ],
		];
	}

	/**
	 * The enabled currency carries the merchant's manual rate, while the available one only has the
	 * automatic rate. A coupon re-applied to an order must convert at the rate the shopper paid with.
	 */
	public function test_get_coupon_amount_converts_coupon_reapplied_to_order_with_the_manual_rate() {
		$usd           = new WCPay\MultiCurrency\Currency( $this->localization_service, Currency_Code::UNITED_STATES_DOLLAR );
		$automatic_eur = new WCPay\MultiCurrency\Currency( $this->localization_service, Currency_Code::EURO, 0.88 );
		$manual_eur    = new WCPay\MultiCurrency\Currency( $this->localization_service, Currency_Code::EURO, 0.9 );

		$this->mock_multi_currency->method( 'get_default_currency' )->willReturn( $usd );
		$this->mock_multi_currency->method( 'get_available_currencies' )->willReturn(
			[
				'USD' => $usd,
				'EUR' => $automatic_eur,
			]
		);
		$this->mock_multi_currency->method( 'get_enabled_currencies' )->willReturn(
			[
				'USD' => $usd,
				'EUR' => $manual_eur,
			]
		);
		$this->mock_multi_currency->method( 'get_price' )->willReturnCallback(
			function ( $price, $type, $currency = null ) {
				return round( (float) $price * $currency->get_rate(), 2 );
			}
		);
		$this->mock_compatibility->method( 'should_convert_coupon_amount' )->willReturn( true );
		$this->simulate_order_operation( Currency_Code::EURO );

		// 1.37 USD at the manual 0.9 is 1.23 EUR; the automatic 0.88 would give 1.21 EUR.
		$this->assertSame( 1.23, $this->frontend_prices->get_coupon_amount( '1.37', $this->coupon_held_in( '' ) ) );
	}

	public function test_get_coupon_amount_does_not_convert_a_coupon_already_held_in_the_order_currency() {
		$this->mock_multi_currency->method( 'get_default_currency' )->willReturn( new WCPay\MultiCurrency\Currency( $this->localization_service, 'USD' ) );
		$this->mock_multi_currency->method( 'get_available_currencies' )->willReturn( [ 'JPY' => new WCPay\MultiCurrency\Currency( $this->localization_service, 'JPY', 150 ) ] );
		$this->mock_multi_currency->expects( $this->never() )->method( 'get_price' );
		$this->mock_compatibility->method( 'should_convert_coupon_amount' )->willReturn( true );
		$this->simulate_order_operation( 'JPY' );

		// A coupon rebuilt from the order's own coupon line already holds the order currency.
		$this->assertSame( '1500', $this->frontend_prices->get_coupon_amount( '1500', $this->coupon_held_in( 'JPY' ) ) );
	}

	public function test_get_coupon_amount_converts_a_store_currency_coupon_to_the_order_currency() {
		$usd = new WCPay\MultiCurrency\Currency( $this->localization_service, 'USD' );
		$jpy = new WCPay\MultiCurrency\Currency( $this->localization_service, 'JPY', 150 );
		$this->mock_multi_currency->method( 'get_default_currency' )->willReturn( $usd );
		$this->mock_multi_currency->method( 'get_available_currencies' )->willReturn(
			[
				'USD' => $usd,
				'JPY' => $jpy,
			]
		);
		$this->mock_multi_currency->method( 'get_enabled_currencies' )->willReturn(
			[
				'USD' => $usd,
				'JPY' => $jpy,
			]
		);
		$this->mock_compatibility->method( 'should_convert_coupon_amount' )->willReturn( true );
		$this->mock_multi_currency
			->expects( $this->once() )
			->method( 'get_price' )
			->with( 5.0, 'coupon', $this->identicalTo( $jpy ) )
			->willReturn( 750.0 );
		$this->simulate_order_operation( 'JPY' );

		$this->assertSame( 750.0, $this->frontend_prices->get_coupon_amount( '5', $this->coupon_held_in( '' ) ) );
	}

	public function test_get_coupon_amount_converts_a_coupon_held_in_another_currency_through_the_store_currency() {
		$usd = new WCPay\MultiCurrency\Currency( $this->localization_service, 'USD' );
		$jpy = new WCPay\MultiCurrency\Currency( $this->localization_service, 'JPY', 150 );
		$this->mock_multi_currency->method( 'get_default_currency' )->willReturn( $usd );
		$this->mock_multi_currency->method( 'get_available_currencies' )->willReturn(
			[
				'USD' => $usd,
				'JPY' => $jpy,
			]
		);
		$this->mock_multi_currency->method( 'get_enabled_currencies' )->willReturn(
			[
				'USD' => $usd,
				'JPY' => $jpy,
			]
		);
		$this->mock_compatibility->method( 'should_convert_coupon_amount' )->willReturn( true );
		$this->mock_multi_currency
			->expects( $this->once() )
			->method( 'get_price' )
			->with( 10.0, 'coupon', $this->identicalTo( $usd ) )
			->willReturn( 10.0 );
		$this->simulate_order_operation( 'USD' );

		// 1500 JPY is 10 USD at the 150 rate.
		$this->assertSame( 10.0, $this->frontend_prices->get_coupon_amount( '1500', $this->coupon_held_in( 'JPY' ) ) );
	}

	public function test_get_coupon_amount_converts_other_coupons_to_the_selected_currency() {
		$usd = new WCPay\MultiCurrency\Currency( $this->localization_service, 'USD' );
		$this->mock_multi_currency->method( 'get_available_currencies' )->willReturn( [ 'USD' => $usd ] );
		$this->mock_compatibility->method( 'should_convert_coupon_amount' )->willReturn( true );

		$this->mock_multi_currency
			->expects( $this->once() )
			->method( 'get_price' )
			->with( '10', 'coupon', null )
			->willReturn( 25.0 );

		$this->assertSame( 25.0, $this->frontend_prices->get_coupon_amount( '10', new WC_Coupon() ) );
	}

	public function test_get_coupon_min_max_amount_converts_to_the_order_currency_during_an_order_operation() {
		$usd = new WCPay\MultiCurrency\Currency( $this->localization_service, 'USD' );
		$jpy = new WCPay\MultiCurrency\Currency( $this->localization_service, 'JPY', 150 );
		$this->mock_multi_currency->method( 'get_default_currency' )->willReturn( $usd );
		$this->mock_multi_currency->method( 'get_available_currencies' )->willReturn(
			[
				'USD' => $usd,
				'JPY' => $jpy,
			]
		);
		$this->mock_multi_currency->method( 'get_enabled_currencies' )->willReturn(
			[
				'USD' => $usd,
				'JPY' => $jpy,
			]
		);
		$this->mock_compatibility->method( 'should_convert_coupon_min_max_amount' )->willReturn( true );
		$this->mock_multi_currency
			->expects( $this->once() )
			->method( 'get_price' )
			->with( '5.0', 'product', $this->identicalTo( $jpy ) )
			->willReturn( 750.0 );
		$this->simulate_order_operation( 'JPY' );

		$this->assertSame( 750.0, $this->frontend_prices->get_coupon_min_max_amount( '5.0', new WC_Coupon() ) );
	}

	/**
	 * Sets the currency WooCommerce reports while it operates on an order.
	 */
	private function simulate_order_operation( string $order_currency, string $operation = 'order.recalculate_coupons' ): void {
		$this->active_scope = null;
		$this->active_scope = ActiveCurrency::scope( $order_currency, $operation );
	}

	/**
	 * A coupon that reports the currency its amount is held in, as WooCommerce does for a coupon rebuilt from an order.
	 */
	private function coupon_held_in( string $currency ): WC_Coupon {
		$coupon          = new class() extends WC_Coupon {
			/**
			 * The currency the amount is held in, as WooCommerce reports it.
			 *
			 * @var string
			 */
			public $held_in = '';

			public function get_amount_currency(): string {
				return $this->held_in;
			}
		};
		$coupon->held_in = $currency;
		$coupon->set_discount_type( 'fixed_cart' );

		return $coupon;
	}

	public function test_recalculate_coupons_uses_the_order_currency_instead_of_the_selected_one() {
		$this->simulate_order_operation( 'USD' );
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

	public function test_get_coupon_min_max_amount_skips_conversion_on_compatibility() {
		$coupon = new WC_Coupon();
		$this->mock_compatibility
			->method( 'should_convert_coupon_min_max_amount' )
			->with( $coupon )
			->willReturn( false );
		$this->assertSame( '5.0', $this->frontend_prices->get_coupon_min_max_amount( '5.0', $coupon ) );
	}

	public function test_get_coupon_min_max_amount_converts_amount_as_product() {
		$this->mock_multi_currency->method( 'get_price' )->with( 5.0, 'product' )->willReturn( 12.5 );

		$coupon = new WC_Coupon();
		$this->mock_compatibility
			->method( 'should_convert_coupon_min_max_amount' )
			->with( $coupon )
			->willReturn( true );

		$this->assertSame( 12.5, $this->frontend_prices->get_coupon_min_max_amount( '5.0', $coupon ) );
	}

	/**
	 * The coupon getters pass the coupon to the compatibility check through the registered filters.
	 *
	 * @dataProvider coupon_min_max_getter_provider
	 */
	public function test_coupon_min_max_getters_pass_coupon_to_compatibility( $setter, $getter ) {
		$coupon = new WC_Coupon();
		$coupon->$setter( 5 );

		// WC_Coupon reads the prop twice in the view context: once to check for an empty value, once to return it.
		$this->mock_compatibility
			->expects( $this->atLeastOnce() )
			->method( 'should_convert_coupon_min_max_amount' )
			->with( $coupon )
			->willReturn( false );
		$this->mock_multi_currency
			->expects( $this->never() )
			->method( 'get_price' );

		// Depending on the WooCommerce version, the getter returns the amount as a string or a float.
		$this->assertEquals( 5, $coupon->$getter() );
	}

	public function coupon_min_max_getter_provider() {
		return [
			'minimum amount' => [ 'set_minimum_amount', 'get_minimum_amount' ],
			'maximum amount' => [ 'set_maximum_amount', 'get_maximum_amount' ],
		];
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
