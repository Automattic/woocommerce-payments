<?php
/**
 * These tests make assertions against class WC_Payments_Payment_Request_Button_Handler.
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\Duplicate_Payment_Prevention_Service;
use WCPay\Payment_Methods\CC_Payment_Method;
use WCPay\Session_Rate_Limiter;

/**
 * WC_Payments_Payment_Request_Button_Handler_Test class.
 */
class WC_Payments_Payment_Request_Button_Handler_Test extends WCPAY_UnitTestCase {
	const SHIPPING_ADDRESS = [
		'country'   => 'US',
		'state'     => 'CA',
		'postcode'  => '94110',
		'city'      => 'San Francisco',
		'address_1' => '60 29th Street',
		'address_2' => '#343',
	];

	/**
	 * Mock WC_Payments_API_Client.
	 *
	 * @var WC_Payments_API_Client
	 */
	private $mock_api_client;

	/**
	 * Payment request instance.
	 *
	 * @var WC_Payments_Payment_Request_Button_Handler
	 */
	private $pr;

	/**
	 * WC_Payments_Account instance.
	 *
	 * @var WC_Payments_Account
	 */
	private $mock_wcpay_account;

	/**
	 * Test product to add to the cart
	 * @var WC_Product_Simple
	 */
	private $simple_product;

	/**
	 * Test shipping zone.
	 *
	 * @var WC_Shipping_Zone
	 */
	private $zone;

	/**
	 * Flat rate shipping method instance id
	 *
	 * @var int
	 */
	private $flat_rate_id;

	/**
	 * Flat rate shipping method instance id
	 *
	 * @var int
	 */
	private $local_pickup_id;

	/**
	 * Used to get the settings.
	 *
	 * @var WC_Payment_Gateway_WCPay
	 */
	private $mock_wcpay_gateway;

	/**
	 * Express Checkout Helper instance.
	 *
	 * @var WC_Payments_Express_Checkout_Button_Helper
	 */
	private $express_checkout_helper;

	/**
	 * Sets up things all tests need.
	 */
	public function set_up() {
		parent::set_up();
		add_filter( 'pre_option_woocommerce_tax_based_on', [ $this, '__return_base' ] );

		$this->mock_api_client = $this->getMockBuilder( 'WC_Payments_API_Client' )
			->disableOriginalConstructor()
			->setMethods(
				[
					'get_account_data',
					'is_server_connected',
					'capture_intention',
					'cancel_intention',
					'get_payment_method',
				]
			)
			->getMock();
		$this->mock_api_client->expects( $this->any() )->method( 'is_server_connected' )->willReturn( true );
		$this->mock_wcpay_account = $this->createMock( WC_Payments_Account::class );

		$this->mock_wcpay_gateway = $this->make_wcpay_gateway();

		$this->express_checkout_helper = new WC_Payments_Express_Checkout_Button_Helper( $this->mock_wcpay_account );

		$this->pr = new WC_Payments_Payment_Request_Button_Handler( $this->mock_wcpay_account, $this->mock_wcpay_gateway, $this->express_checkout_helper );

		$this->simple_product = WC_Helper_Product::create_simple_product();

		WC_Helper_Shipping::delete_simple_flat_rate();
		$zone = new WC_Shipping_Zone();
		$zone->set_zone_name( 'Worldwide' );
		$zone->set_zone_order( 1 );
		$zone->save();

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

		$this->flat_rate_id = $zone->add_shipping_method( 'flat_rate' );
		self::set_shipping_method_cost( $this->flat_rate_id, '5' );

		$this->local_pickup_id = $zone->add_shipping_method( 'local_pickup' );
		self::set_shipping_method_cost( $this->local_pickup_id, '1' );

		$this->zone = $zone;

		WC()->session->init();
		WC()->cart->add_to_cart( $this->simple_product->get_id(), 1 );
		$this->pr->update_shipping_method( [ self::get_shipping_option_rate_id( $this->flat_rate_id ) ] );
		WC()->cart->calculate_totals();
	}

	public function tear_down() {
		parent::tear_down();
		WC()->cart->empty_cart();
		WC()->session->cleanup_sessions();
		$this->zone->delete();
		delete_option( 'woocommerce_woocommerce_payments_settings' );
		remove_filter( 'pre_option_woocommerce_tax_based_on', [ $this, '__return_base' ] );
		remove_filter( 'pre_option_woocommerce_tax_display_cart', [ $this, '__return_excl' ] );
		remove_filter( 'pre_option_woocommerce_tax_display_cart', [ $this, '__return_incl' ] );
		remove_filter( 'pre_option_woocommerce_tax_display_shop', [ $this, '__return_excl' ] );
		remove_filter( 'pre_option_woocommerce_tax_display_shop', [ $this, '__return_incl' ] );
		remove_filter( 'pre_option_woocommerce_prices_include_tax', [ $this, '__return_yes' ] );
		remove_filter( 'pre_option_woocommerce_prices_include_tax', [ $this, '__return_no' ] );
		remove_filter( 'wc_tax_enabled', '__return_true' );
		remove_filter( 'wc_tax_enabled', '__return_false' );
		remove_filter( 'wc_shipping_enabled', '__return_false' );
	}

	public function __return_yes() {
		return 'yes';
	}

	public function __return_no() {
		return 'no';
	}

	public function __return_excl() {
		return 'excl';
	}

	public function __return_incl() {
		return 'incl';
	}

	public function __return_base() {
		return 'base';
	}

	/**
	 * @return WC_Payment_Gateway_WCPay
	 */
	private function make_wcpay_gateway() {
		$mock_customer_service         = $this->createMock( WC_Payments_Customer_Service::class );
		$mock_token_service            = $this->createMock( WC_Payments_Token_Service::class );
		$mock_action_scheduler_service = $this->createMock( WC_Payments_Action_Scheduler_Service::class );
		$mock_rate_limiter             = $this->createMock( Session_Rate_Limiter::class );
		$mock_order_service            = $this->createMock( WC_Payments_Order_Service::class );
		$mock_dpps                     = $this->createMock( Duplicate_Payment_Prevention_Service::class );
		$mock_payment_method           = $this->createMock( CC_Payment_Method::class );

		return new WC_Payment_Gateway_WCPay(
			$this->mock_api_client,
			$this->mock_wcpay_account,
			$mock_customer_service,
			$mock_token_service,
			$mock_action_scheduler_service,
			$mock_payment_method,
			[ 'card' => $mock_payment_method ],
			$mock_rate_limiter,
			$mock_order_service,
			$mock_dpps,
			$this->createMock( WC_Payments_Localization_Service::class ),
			$this->createMock( WC_Payments_Fraud_Service::class )
		);
	}

	/**
	 * Sets shipping method cost
	 *
	 * @param string $instance_id Shipping method instance id
	 * @param string $cost Shipping method cost in USD
	 */
	private static function set_shipping_method_cost( $instance_id, $cost ) {
		$method          = WC_Shipping_Zones::get_shipping_method( $instance_id );
		$option_key      = $method->get_instance_option_key();
		$options         = get_option( $option_key );
		$options['cost'] = $cost;
		update_option( $option_key, $options );
	}

	/**
	 * Composes shipping option object by shipping method instance id.
	 *
	 * @param string $instance_id Shipping method instance id.
	 *
	 * @return array Shipping option.
	 */
	private static function get_shipping_option( $instance_id ) {
		$method = WC_Shipping_Zones::get_shipping_method( $instance_id );

		return [
			'id'     => $method->get_rate_id(),
			'label'  => $method->title,
			'detail' => '',
			'amount' => WC_Payments_Utils::prepare_amount( $method->get_instance_option( 'cost' ) ),
		];
	}

	/**
	 * Retrieves rate id by shipping method instance id.
	 *
	 * @param string $instance_id Shipping method instance id.
	 *
	 * @return string Shipping option instance rate id.
	 */
	private static function get_shipping_option_rate_id( $instance_id ) {
		$method = WC_Shipping_Zones::get_shipping_method( $instance_id );

		return $method->get_rate_id();
	}


	public function test_get_shipping_options_returns_shipping_options() {
		$data = $this->pr->get_shipping_options( self::SHIPPING_ADDRESS );

		$expected_shipping_options = array_map(
			'self::get_shipping_option',
			[ $this->flat_rate_id, $this->local_pickup_id ]
		);

		$this->assertEquals( 'success', $data['result'] );
		$this->assertEquals( $expected_shipping_options, $data['shipping_options'], 'Shipping options mismatch' );
	}

	public function test_get_shipping_options_returns_chosen_option() {
		$data = $this->pr->get_shipping_options( self::SHIPPING_ADDRESS );

		$flat_rate              = $this->get_shipping_option( $this->flat_rate_id );
		$expected_display_items = [
			[
				'label'  => 'Shipping',
				'amount' => $flat_rate['amount'],
			],
		];

		$this->assertEquals( 1500, $data['total']['amount'], 'Total amount mismatch' );
		$this->assertEquals( $expected_display_items, $data['displayItems'], 'Display items mismatch' );
	}

	public function test_get_shipping_options_keeps_chosen_option() {
		$method_id = self::get_shipping_option_rate_id( $this->local_pickup_id );
		$this->pr->update_shipping_method( [ $method_id ] );

		$data = $this->pr->get_shipping_options( self::SHIPPING_ADDRESS );

		$expected_shipping_options = array_map(
			'self::get_shipping_option',
			[ $this->local_pickup_id, $this->flat_rate_id ]
		);

		$this->assertEquals( 'success', $data['result'] );
		$this->assertEquals( $expected_shipping_options, $data['shipping_options'], 'Shipping options mismatch' );
	}

	public function test_get_button_settings() {
		$this->mock_wcpay_gateway = $this->make_wcpay_gateway();
		$this->pr                 = new WC_Payments_Payment_Request_Button_Handler( $this->mock_wcpay_account, $this->mock_wcpay_gateway, $this->express_checkout_helper );

		$this->assertEquals(
			[
				'type'         => 'buy',
				'theme'        => 'dark',
				'height'       => '48',
				'locale'       => 'en',
				'branded_type' => 'long',
			],
			$this->pr->get_button_settings()
		);
	}

	public function test_multiple_packages_in_cart_not_allowed() {
		// Add fake packages to the cart.
		add_filter(
			'woocommerce_cart_shipping_packages',
			function() {
				return [
					'fake_package_1',
					'fake_package_2',
				];
			}
		);
		$this->mock_wcpay_gateway = $this->make_wcpay_gateway();
		$this->pr                 = new WC_Payments_Payment_Request_Button_Handler( $this->mock_wcpay_account, $this->mock_wcpay_gateway, $this->express_checkout_helper );

		$this->assertFalse( $this->pr->has_allowed_items_in_cart() );
	}

	public function test_get_product_price_returns_simple_price() {
		$this->assertEquals(
			$this->simple_product->get_price(),
			$this->pr->get_product_price( $this->simple_product )
		);
	}

	/**
	 * @dataProvider provide_get_product_tax_tests
	 */
	public function test_get_product_price_returns_price_with_tax( $tax_enabled, $prices_include_tax, $tax_display_shop, $tax_display_cart, $product_price, $expected_price ) {
		$this->simple_product->set_price( $product_price );
		add_filter( 'wc_tax_enabled', $tax_enabled ? '__return_true' : '__return_false' ); // reset in tear_down.
		add_filter( 'pre_option_woocommerce_prices_include_tax', [ $this, "__return_$prices_include_tax" ] ); // reset in tear_down.
		add_filter( 'pre_option_woocommerce_tax_display_shop', [ $this, "__return_$tax_display_shop" ] ); // reset in tear_down.
		add_filter( 'pre_option_woocommerce_tax_display_cart', [ $this, "__return_$tax_display_cart" ] ); // reset in tear_down.
		WC()->cart->calculate_totals();
		$this->assertEquals(
			$expected_price,
			$this->pr->get_product_price( $this->simple_product )
		);
	}

	public function provide_get_product_tax_tests() {
		yield 'Tax Disabled, Price Display Unaffected' => [
			'tax_enabled'        => false,
			'prices_include_tax' => 'no',
			'tax_display_shop'   => 'excl',
			'tax_display_cart'   => 'incl',
			'product_price'      => 10,
			'expected_price'     => 10,
		];

		// base prices DO NOT include tax.

		yield 'Shop: Excl / Cart: Incl, Base Prices Don\'t Include Tax' => [
			'tax_enabled'        => true,
			'prices_include_tax' => 'no',
			'tax_display_shop'   => 'excl',
			'tax_display_cart'   => 'incl',
			'product_price'      => 10,
			'expected_price'     => 11,
		];
		yield 'Shop: Excl / Cart: Excl, Base Prices Don\'t Include Tax' => [
			'tax_enabled'        => true,
			'prices_include_tax' => 'no',
			'tax_display_shop'   => 'excl',
			'tax_display_cart'   => 'excl',
			'product_price'      => 10,
			'expected_price'     => 10,
		];

		yield 'Shop: Incl / Cart: Incl, Base Prices Don\'t Include Tax' => [
			'tax_enabled'        => true,
			'prices_include_tax' => 'no',
			'tax_display_shop'   => 'incl',
			'tax_display_cart'   => 'incl',
			'product_price'      => 10,
			'expected_price'     => 11,
		];
		yield 'Shop: Incl / Cart: Excl, Base Prices Don\'t Include Tax' => [
			'tax_enabled'        => true,
			'prices_include_tax' => 'no',
			'tax_display_shop'   => 'incl',
			'tax_display_cart'   => 'excl',
			'product_price'      => 10,
			'expected_price'     => 10,
		];

		// base prices include tax.

		yield 'Shop: Excl / Cart: Incl, Base Prices Include Tax' => [
			'tax_enabled'        => true,
			'prices_include_tax' => 'yes',
			'tax_display_shop'   => 'excl',
			'tax_display_cart'   => 'incl',
			'product_price'      => 10,
			'expected_price'     => 10,
		];
		yield 'Shop: Excl / Cart: Excl, Base Prices Include Tax' => [
			'tax_enabled'        => true,
			'prices_include_tax' => 'yes',
			'tax_display_shop'   => 'excl',
			'tax_display_cart'   => 'excl',
			'product_price'      => 10,
			'expected_price'     => 9.090909,
		];

		yield 'Shop: Incl / Cart: Incl, Base Prices Include Tax' => [
			'tax_enabled'        => true,
			'prices_include_tax' => 'yes',
			'tax_display_shop'   => 'incl',
			'tax_display_cart'   => 'incl',
			'product_price'      => 10,
			'expected_price'     => 10,
		];
		yield 'Shop: Incl / Cart: Excl, Base Prices Include Tax' => [
			'tax_enabled'        => true,
			'prices_include_tax' => 'yes',
			'tax_display_shop'   => 'incl',
			'tax_display_cart'   => 'excl',
			'product_price'      => 10,
			'expected_price'     => 9.090909,
		];
	}

	public function test_get_product_price_includes_subscription_sign_up_fee() {
		$mock_product = $this->create_mock_subscription( 'subscription' );

		// We have a helper because we are not loading subscriptions.
		WC_Subscriptions_Product::set_sign_up_fee( 10 );

		$this->assertEquals( 20, $this->pr->get_product_price( $mock_product ) );

		// Restore the sign-up fee after the test.
		WC_Subscriptions_Product::set_sign_up_fee( 0 );
	}

	public function test_get_product_price_includes_variable_subscription_sign_up_fee() {
		$mock_product = $this->create_mock_subscription( 'subscription_variation' );

		// We have a helper because we are not loading subscriptions.
		WC_Subscriptions_Product::set_sign_up_fee( 10 );

		$this->assertEquals( 20, $this->pr->get_product_price( $mock_product ) );

		// Restore the sign-up fee after the test.
		WC_Subscriptions_Product::set_sign_up_fee( 0 );
	}

	public function test_get_product_price_throws_exception_for_products_without_prices() {
		if ( version_compare( WC_VERSION, '6.9.0', '>=' ) ) {
			$this->markTestSkipped( 'This test is useless starting with WooCommerce 6.9.0' );
			return;
		}

		$this->simple_product->set_price( 'a' );

		$this->expectException( WCPay\Exceptions\Invalid_Price_Exception::class );

		$this->pr->get_product_price( $this->simple_product );
	}

	public function test_get_product_price_throws_exception_for_a_non_numeric_signup_fee() {
		$mock_product = $this->create_mock_subscription( 'subscription' );
		WC_Subscriptions_Product::set_sign_up_fee( 'a' );

		$this->expectException( WCPay\Exceptions\Invalid_Price_Exception::class );
		$this->pr->get_product_price( $mock_product );

		// Restore the sign-up fee after the test.
		WC_Subscriptions_Product::set_sign_up_fee( 0 );
	}

	private function create_mock_subscription( $type ) {
		$mock_product = $this->createMock( WC_Subscriptions_Product::class );

		$mock_product
			->expects( $this->once() )
			->method( 'get_price' )
			->willReturn( 10 );

		$mock_product
			->expects( $this->once() )
			->method( 'get_type' )
			->willReturn( $type );

		return $mock_product;
	}

	/**
	 * @dataProvider provide_get_product_tax_tests
	 */
	public function test_get_product_data_returns_the_same_as_build_display_items_without_shipping( $tax_enabled, $prices_include_tax, $tax_display_shop, $tax_display_cart, $_product_price, $_expected_price ) {
		add_filter( 'wc_tax_enabled', $tax_enabled ? '__return_true' : '__return_false' ); // reset in tear_down.
		add_filter( 'pre_option_woocommerce_prices_include_tax', [ $this, "__return_$prices_include_tax" ] ); // reset in tear_down.
		add_filter( 'pre_option_woocommerce_tax_display_shop', [ $this, "__return_$tax_display_shop" ] ); // reset in tear_down.
		add_filter( 'pre_option_woocommerce_tax_display_cart', [ $this, "__return_$tax_display_cart" ] ); // reset in tear_down.
		add_filter( 'wc_shipping_enabled', '__return_false' ); // reset in tear_down.
		WC()->cart->calculate_totals();
		$build_display_items_result = $this->express_checkout_helper->build_display_items( true );

		$mock_pr = $this->getMockBuilder( WC_Payments_Payment_Request_Button_Handler::class )
			->setConstructorArgs( [ $this->mock_wcpay_account, $this->mock_wcpay_gateway, $this->express_checkout_helper ] )
			->setMethods( [ 'is_product', 'get_product' ] )
			->getMock();

		$mock_pr->method( 'is_product' )
			->willReturn( true );
		$mock_pr->method( 'get_product' )
			->willReturn( $this->simple_product );

		$get_product_data_result = $mock_pr->get_product_data();

		foreach ( $get_product_data_result['displayItems'] as $key => $display_item ) {
			if ( isset( $display_item['pending'] ) ) {
				unset( $get_product_data_result['displayItems'][ $key ]['pending'] );
			}
		}

		$this->assertEquals(
			$get_product_data_result['displayItems'],
			$build_display_items_result['displayItems'],
			'Failed asserting displayItems are the same for get_product_data and build_display_items'
		);
		$this->assertEquals(
			$get_product_data_result['total']['amount'],
			$build_display_items_result['total']['amount'],
			'Failed asserting total amount are the same for get_product_data and build_display_items'
		);
	}

	public function test_should_show_button_for_non_subscription_product() {
		$product = $this->createMock( WC_Product::class );
		WC()->cart->add_to_cart( $product->get_id() );

		$this->assertTrue( $this->pr->should_show_button_for_subscription_product() );
	}

	public function test_should_not_show_button_for_subscription_product_when_no_shipping_methods_enabled() {
		$product = $this->createMock( WC_Subscriptions_Product::class );

		WC()->cart->add_to_cart( $product->get_id() );

		$express_checkout_helper_mock = $this->createMock( WC_Payments_Express_Checkout_Button_Helper::class );
		$express_checkout_helper_mock->method( 'has_any_shipping_method' )->willReturn( false );

		$mock_pr = $this->getMockBuilder( WC_Payments_Payment_Request_Button_Handler::class )
			->setConstructorArgs( [ $this->mock_wcpay_account, $this->mock_wcpay_gateway, $express_checkout_helper_mock ] )
			->setMethods( [ 'has_subscription_product' ] )
			->getMock();

		$mock_pr->method( 'has_subscription_product' )->willReturn( true );

		$this->pr = $mock_pr;

		$this->assertFalse( $this->pr->should_show_button_for_subscription_product() );
	}

	public function test_should_show_button_for_subscription_product_when_shipping_methods_enabled() {
		// Shipping method is enabled by default in the setup method.
		$product = $this->createMock( WC_Subscriptions_Product::class );

		$mock_pr = $this->getMockBuilder( WC_Payments_Payment_Request_Button_Handler::class )
			->setConstructorArgs( [ $this->mock_wcpay_account, $this->mock_wcpay_gateway, $this->express_checkout_helper ] )
			->setMethods( [ 'has_subscription_product' ] )
			->getMock();

		$mock_pr->method( 'has_subscription_product' )->willReturn( true );

		WC()->cart->add_to_cart( $product->get_id() );

		$this->assertTrue( $mock_pr->should_show_button_for_subscription_product() );
	}

	public function test_should_show_button_for_subscription_that_does_not_need_shipping() {
		$product = $this->createMock( WC_Product::class );

		add_filter(
			'woocommerce_cart_item_product',
			function ( $product, $cart_item, $cart_item_key ) {
				$mock_product = $this->getMockBuilder( WC_Product::class )
					->setMethods( [ 'needs_shipping', 'is_type' ] )
					->getMock();
				$mock_product->method( 'needs_shipping' )->willReturn( false );
				$mock_product->method( 'is_type' )->willReturn( 'subscription' );

				return $mock_product;
			},
			10,
			3
		);

		WC()->cart->add_to_cart( $product->get_id() );

		$mock_pr = $this->getMockBuilder( WC_Payments_Payment_Request_Button_Handler::class )
			->setConstructorArgs( [ $this->mock_wcpay_account, $this->mock_wcpay_gateway, $this->express_checkout_helper ] )
			->setMethods( [ 'has_any_shipping_method', 'has_subscription_product' ] )
			->getMock();

		$mock_pr->method( 'has_any_shipping_method' )->willReturn( false );
		$mock_pr->method( 'has_subscription_product' )->willReturn( true );

		$this->assertTrue( $mock_pr->should_show_button_for_subscription_product() );
	}
}
