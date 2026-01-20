<?php
/**
 * These tests make assertions against class WC_Payments_Express_Checkout_Button_Helper.
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\Duplicate_Payment_Prevention_Service;
use WCPay\Duplicates_Detection_Service;
use WCPay\Payment_Methods\CC_Payment_Method;
use WCPay\Session_Rate_Limiter;

/**
 * WC_Payments_Express_Checkout_Button_Helper_Test class.
 */
class WC_Payments_Express_Checkout_Button_Helper_Test extends WCPAY_UnitTestCase {
	/**
	 * Used to get the settings.
	 *
	 * @var WC_Payment_Gateway_WCPay|MockObject
	 */
	private $mock_wcpay_gateway;

	/**
	 * WC_Payments_Account instance.
	 *
	 * @var WC_Payments_Account
	 */
	private $mock_wcpay_account;

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
	 * Express Checkout Helper instance.
	 *
	 * @var WC_Payments_Express_Checkout_Button_Helper
	 */
	private $system_under_test;

	/**
	 * Test product to add to the cart
	 * @var WC_Product_Simple
	 */
	private $simple_product;

	/**
	 * Sets up things all tests need.
	 */
	public function set_up() {
		parent::set_up();

		$this->mock_wcpay_account = $this->createMock( WC_Payments_Account::class );
		$this->mock_wcpay_gateway = $this->make_wcpay_gateway();

		$this->mock_wcpay_gateway->update_option( 'express_checkout_product_methods', [ 'payment_request', 'woopay' ] );
		$this->mock_wcpay_gateway->update_option( 'express_checkout_cart_methods', [ 'payment_request', 'woopay' ] );
		$this->mock_wcpay_gateway->update_option( 'express_checkout_checkout_methods', [ 'payment_request', 'woopay' ] );

		$this->system_under_test = new WC_Payments_Express_Checkout_Button_Helper( $this->mock_wcpay_gateway, $this->mock_wcpay_account );

		WC_Helper_Shipping::delete_simple_flat_rate();
		$zone = new WC_Shipping_Zone();
		$zone->set_zone_name( 'Worldwide' );
		$zone->set_zone_order( 1 );
		$zone->save();

		$this->flat_rate_id = $zone->add_shipping_method( 'flat_rate' );
		self::set_shipping_method_cost( $this->flat_rate_id, '5' );

		$this->local_pickup_id = $zone->add_shipping_method( 'local_pickup' );
		self::set_shipping_method_cost( $this->local_pickup_id, '1' );

		$this->zone = $zone;

		$this->simple_product = WC_Helper_Product::create_simple_product();

		WC()->session->init();
		WC()->cart->add_to_cart( $this->simple_product->get_id(), 1 );
		WC()->cart->calculate_totals();
	}

	public function tear_down() {
		WC_Subscriptions_Cart::set_cart_contains_subscription( false );
		WC()->cart->empty_cart();
		WC()->session->cleanup_sessions();
		$this->zone->delete();
		remove_filter( 'wc_tax_enabled', '__return_true' );
		remove_filter( 'wc_tax_enabled', '__return_false' );
		remove_filter( 'pre_option_woocommerce_tax_display_cart', [ $this, '__return_excl' ] );
		remove_filter( 'pre_option_woocommerce_tax_display_cart', [ $this, '__return_incl' ] );

		parent::tear_down();
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
		$mock_api_client               = $this->createMock( WC_Payments_API_Client::class );
		$mock_customer_service         = $this->createMock( WC_Payments_Customer_Service::class );
		$mock_token_service            = $this->createMock( WC_Payments_Token_Service::class );
		$mock_action_scheduler_service = $this->createMock( WC_Payments_Action_Scheduler_Service::class );
		$mock_rate_limiter             = $this->createMock( Session_Rate_Limiter::class );
		$mock_order_service            = $this->createMock( WC_Payments_Order_Service::class );
		$mock_dpps                     = $this->createMock( Duplicate_Payment_Prevention_Service::class );
		$mock_payment_method           = $this->createMock( CC_Payment_Method::class );
		$mock_payment_method->method( 'get_id' )->willReturn( CC_Payment_Method::PAYMENT_METHOD_STRIPE_ID );

		return new WC_Payment_Gateway_WCPay(
			$mock_api_client,
			$this->mock_wcpay_account,
			$mock_customer_service,
			$mock_token_service,
			$mock_action_scheduler_service,
			$mock_payment_method,
			[ 'card' => $mock_payment_method ],
			$mock_order_service,
			$mock_dpps,
			$this->createMock( WC_Payments_Localization_Service::class ),
			$this->createMock( WC_Payments_Fraud_Service::class ),
			$this->createMock( Duplicates_Detection_Service::class ),
			$mock_rate_limiter
		);
	}

	public function test_common_get_button_settings() {
		$this->assertEquals(
			[
				'type'   => 'default',
				'theme'  => 'dark',
				'height' => '48',
				'radius' => '',
			],
			$this->system_under_test->get_common_button_settings()
		);
	}

	public function test_cart_prices_include_tax_with_tax_disabled() {
		add_filter( 'wc_tax_enabled', '__return_false' );
		$this->assertTrue( $this->system_under_test->cart_prices_include_tax() );
	}

	public function test_cart_prices_include_tax_with_tax_enabled_and_display_incl() {
		add_filter( 'wc_tax_enabled', '__return_true' ); // reset in tear_down.
		add_filter( 'pre_option_woocommerce_tax_display_cart', [ $this, '__return_incl' ] ); // reset in tear_down.

		$this->assertTrue( $this->system_under_test->cart_prices_include_tax() );
	}

	public function test_cart_prices_include_tax_with_tax_enabled_and_display_excl() {
		add_filter( 'wc_tax_enabled', '__return_true' ); // reset in tear_down.
		add_filter( 'pre_option_woocommerce_tax_display_cart', [ $this, '__return_excl' ] ); // reset in tear_down.

		$this->assertFalse( $this->system_under_test->cart_prices_include_tax() );
	}

	public function test_get_total_label() {
		$this->mock_wcpay_account->method( 'get_statement_descriptor' )
			->willReturn( 'Google Pay' );

		$result = $this->system_under_test->get_total_label();

		$this->assertEquals( 'Google Pay (via WooCommerce)', $result );
	}

	public function test_get_total_label_with_filter() {
		$this->mock_wcpay_account->method( 'get_statement_descriptor' )
			->willReturn( 'Google Pay' );

		add_filter(
			'wcpay_payment_request_total_label_suffix',
			function () {
				return ' (via WooPayments)';
			}
		);

		$result = $this->system_under_test->get_total_label();

		$this->assertEquals( 'Google Pay (via WooPayments)', $result );

		remove_all_filters( 'wcpay_payment_request_total_label_suffix' );
	}

	public function test_should_show_express_checkout_button_for_tokenized_ece_with_billing_email() {
		global $wp;
		global $wp_query;

		$this->mock_wcpay_account
			->method( 'is_stripe_connected' )
			->willReturn( true );
		WC_Payments::mode()->dev();
		$_GET['pay_for_order'] = true;

		// Total is 100 USD, which is above both payment methods (Affirm and AfterPay) minimums.
		$order                = WC_Helper_Order::create_order( 1, 100 );
		$order_id             = $order->get_id();
		$wp->query_vars       = [ 'order-pay' => strval( $order_id ) ];
		$wp_query->query_vars = [ 'order-pay' => strval( $order_id ) ];

		add_filter( 'woocommerce_is_checkout', '__return_true' );

		$this->assertTrue( $this->system_under_test->should_show_express_checkout_button() );

		remove_filter( 'woocommerce_is_checkout', '__return_true' );
	}

	public function test_should_show_express_checkout_button_for_non_shipping_but_price_includes_tax() {
		$this->mock_wcpay_account
			->method( 'is_stripe_connected' )
			->willReturn( true );

		WC_Payments::mode()->dev();

		add_filter( 'woocommerce_is_checkout', '__return_true' );
		add_filter( 'wc_shipping_enabled', '__return_false' );
		add_filter( 'wc_tax_enabled', '__return_true' );

		update_option( 'woocommerce_tax_based_on', 'billing' );
		update_option( 'woocommerce_prices_include_tax', 'yes' );

		$this->assertTrue( $this->system_under_test->should_show_express_checkout_button() );

		remove_filter( 'woocommerce_is_checkout', '__return_true' );
		remove_filter( 'wc_tax_enabled', '__return_true' );
		remove_filter( 'pre_option_woocommerce_tax_display_cart', [ $this, '__return_incl' ] );
	}

	public function test_should_not_show_express_checkout_button_for_non_shipping_but_price_does_not_include_tax() {
		$this->mock_wcpay_account
			->method( 'is_stripe_connected' )
			->willReturn( true );

		WC_Payments::mode()->dev();

		add_filter( 'woocommerce_is_checkout', '__return_true' );
		add_filter( 'wc_shipping_enabled', '__return_false' );
		add_filter( 'wc_tax_enabled', '__return_true' );

		update_option( 'woocommerce_tax_based_on', 'billing' );
		update_option( 'woocommerce_prices_include_tax', 'no' );

		$this->assertFalse( $this->system_under_test->should_show_express_checkout_button() );

		remove_filter( 'woocommerce_is_checkout', '__return_true' );
		remove_filter( 'wc_tax_enabled', '__return_true' );
		remove_filter( 'pre_option_woocommerce_tax_display_cart', [ $this, '__return_incl' ] );
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

	public function test_get_enabled_express_checkout_methods_for_context_returns_payment_request_when_enabled_on_product_page() {
		$mock_gateway = $this->createMock( WC_Payment_Gateway_WCPay::class );
		$mock_gateway->method( 'is_payment_request_enabled' )->willReturn( true );
		$mock_gateway->method( 'get_option' )
			->willReturnCallback(
				function ( $option ) {
					if ( 'express_checkout_product_methods' === $option ) {
						return [ 'payment_request' ];
					}
					return null;
				}
			);

		$helper = $this->getMockBuilder( WC_Payments_Express_Checkout_Button_Helper::class )
			->setConstructorArgs( [ $mock_gateway, $this->mock_wcpay_account ] )
			->onlyMethods( [ 'is_product', 'is_cart', 'is_checkout', 'is_pay_for_order_page' ] )
			->getMock();

		$helper->method( 'is_product' )->willReturn( true );
		$helper->method( 'is_cart' )->willReturn( false );
		$helper->method( 'is_checkout' )->willReturn( false );
		$helper->method( 'is_pay_for_order_page' )->willReturn( false );

		$enabled_methods = $helper->get_enabled_express_checkout_methods_for_context();

		$this->assertContains( 'payment_request', $enabled_methods );
		$this->assertNotContains( 'amazon_pay', $enabled_methods );
	}

	public function test_get_enabled_express_checkout_methods_for_context_returns_amazon_pay_when_enabled() {
		add_filter(
			'pre_option__wcpay_feature_amazon_pay',
			function () {
				return '1';
			}
		);

		// is_amazon_pay_enabled() internally checks is_ece_confirmation_tokens_enabled() which reads from cache.
		$mock_cache = $this->createMock( WCPay\Database_Cache::class );
		$mock_cache->method( 'get' )->willReturn( [ 'ece_confirmation_tokens_disabled' => false ] );
		$original_cache = WC_Payments::get_database_cache();
		WC_Payments::set_database_cache( $mock_cache );

		$mock_gateway = $this->createMock( WC_Payment_Gateway_WCPay::class );
		$mock_gateway->method( 'is_payment_request_enabled' )->willReturn( false );
		$mock_gateway->method( 'get_option' )
			->willReturnCallback(
				function ( $option ) {
					if ( 'express_checkout_cart_methods' === $option ) {
						return [ 'amazon_pay' ];
					}
					return null;
				}
			);

		$helper = $this->getMockBuilder( WC_Payments_Express_Checkout_Button_Helper::class )
			->setConstructorArgs( [ $mock_gateway, $this->mock_wcpay_account ] )
			->onlyMethods( [ 'is_product', 'is_cart', 'is_checkout', 'is_pay_for_order_page', 'can_use_amazon_pay' ] )
			->getMock();

		$helper->method( 'is_product' )->willReturn( false );
		$helper->method( 'is_cart' )->willReturn( true );
		$helper->method( 'is_checkout' )->willReturn( false );
		$helper->method( 'is_pay_for_order_page' )->willReturn( false );
		$helper->method( 'can_use_amazon_pay' )->willReturn( true );

		$enabled_methods = $helper->get_enabled_express_checkout_methods_for_context();

		$this->assertContains( 'amazon_pay', $enabled_methods );

		remove_all_filters( 'pre_option__wcpay_feature_amazon_pay' );
		WC_Payments::set_database_cache( $original_cache );
	}

	public function test_get_enabled_express_checkout_methods_for_context_returns_both_when_both_enabled() {
		add_filter(
			'pre_option__wcpay_feature_amazon_pay',
			function () {
				return '1';
			}
		);

		// is_amazon_pay_enabled() internally checks is_ece_confirmation_tokens_enabled() which reads from cache.
		$mock_cache = $this->createMock( WCPay\Database_Cache::class );
		$mock_cache->method( 'get' )->willReturn( [ 'ece_confirmation_tokens_disabled' => false ] );
		$original_cache = WC_Payments::get_database_cache();
		WC_Payments::set_database_cache( $mock_cache );

		$mock_gateway = $this->createMock( WC_Payment_Gateway_WCPay::class );
		$mock_gateway->method( 'is_payment_request_enabled' )->willReturn( true );
		$mock_gateway->method( 'get_option' )
			->willReturnCallback(
				function ( $option ) {
					if ( 'express_checkout_checkout_methods' === $option ) {
						return [ 'payment_request', 'amazon_pay' ];
					}
					return null;
				}
			);

		$helper = $this->getMockBuilder( WC_Payments_Express_Checkout_Button_Helper::class )
			->setConstructorArgs( [ $mock_gateway, $this->mock_wcpay_account ] )
			->onlyMethods( [ 'is_product', 'is_cart', 'is_checkout', 'is_pay_for_order_page', 'can_use_amazon_pay' ] )
			->getMock();

		$helper->method( 'is_product' )->willReturn( false );
		$helper->method( 'is_cart' )->willReturn( false );
		$helper->method( 'is_checkout' )->willReturn( true );
		$helper->method( 'is_pay_for_order_page' )->willReturn( false );
		$helper->method( 'can_use_amazon_pay' )->willReturn( true );

		$enabled_methods = $helper->get_enabled_express_checkout_methods_for_context();

		$this->assertContains( 'payment_request', $enabled_methods );
		$this->assertContains( 'amazon_pay', $enabled_methods );

		remove_all_filters( 'pre_option__wcpay_feature_amazon_pay' );
		WC_Payments::set_database_cache( $original_cache );
	}

	public function test_get_enabled_express_checkout_methods_for_context_returns_empty_when_no_valid_context() {
		$helper = $this->getMockBuilder( WC_Payments_Express_Checkout_Button_Helper::class )
			->setConstructorArgs( [ $this->mock_wcpay_gateway, $this->mock_wcpay_account ] )
			->onlyMethods( [ 'is_product', 'is_cart', 'is_checkout', 'is_pay_for_order_page' ] )
			->getMock();

		$helper->method( 'is_product' )->willReturn( false );
		$helper->method( 'is_cart' )->willReturn( false );
		$helper->method( 'is_checkout' )->willReturn( false );
		$helper->method( 'is_pay_for_order_page' )->willReturn( false );

		$enabled_methods = $helper->get_enabled_express_checkout_methods_for_context();

		$this->assertEmpty( $enabled_methods );
	}

	public function test_get_enabled_express_checkout_methods_for_context_respects_location_settings() {
		$this->mock_wcpay_gateway->update_option( 'express_checkout_cart_methods', [ 'payment_request' ] );
		$this->mock_wcpay_gateway->update_option( 'express_checkout_checkout_methods', [] );

		$helper = $this->getMockBuilder( WC_Payments_Express_Checkout_Button_Helper::class )
			->setConstructorArgs( [ $this->mock_wcpay_gateway, $this->mock_wcpay_account ] )
			->onlyMethods( [ 'is_product', 'is_cart', 'is_checkout', 'is_pay_for_order_page' ] )
			->getMock();

		$helper->method( 'is_product' )->willReturn( false );
		$helper->method( 'is_cart' )->willReturn( false );
		$helper->method( 'is_checkout' )->willReturn( true );
		$helper->method( 'is_pay_for_order_page' )->willReturn( false );

		$enabled_methods = $helper->get_enabled_express_checkout_methods_for_context();

		$this->assertEmpty( $enabled_methods );
	}

	public function test_get_enabled_express_checkout_methods_for_context_excludes_amazon_pay_when_currency_not_supported() {
		add_filter(
			'pre_option__wcpay_feature_amazon_pay',
			function () {
				return '1';
			}
		);

		// is_amazon_pay_enabled() internally checks is_ece_confirmation_tokens_enabled() which reads from cache.
		$mock_cache = $this->createMock( WCPay\Database_Cache::class );
		$mock_cache->method( 'get' )->willReturn( [ 'ece_confirmation_tokens_disabled' => false ] );
		$original_cache = WC_Payments::get_database_cache();
		WC_Payments::set_database_cache( $mock_cache );

		// EUR is not supported for US merchants.
		add_filter( 'woocommerce_currency', [ $this, 'return_eur_currency' ] );

		$mock_account = $this->createMock( WC_Payments_Account::class );
		$mock_account->method( 'get_account_country' )->willReturn( 'US' );
		$mock_account->method( 'get_cached_account_data' )->willReturn( [ 'country' => 'US' ] );

		$mock_gateway = $this->createMock( WC_Payment_Gateway_WCPay::class );
		$mock_gateway->method( 'is_payment_request_enabled' )->willReturn( false );
		$mock_gateway->method( 'get_option' )
			->willReturnCallback(
				function ( $option ) {
					if ( 'express_checkout_cart_methods' === $option ) {
						return [ 'amazon_pay' ];
					}
					return null;
				}
			);

		$helper = $this->getMockBuilder( WC_Payments_Express_Checkout_Button_Helper::class )
			->setConstructorArgs( [ $mock_gateway, $mock_account ] )
			->onlyMethods( [ 'is_product', 'is_cart', 'is_checkout', 'is_pay_for_order_page' ] )
			->getMock();

		$helper->method( 'is_product' )->willReturn( false );
		$helper->method( 'is_cart' )->willReturn( true );
		$helper->method( 'is_checkout' )->willReturn( false );
		$helper->method( 'is_pay_for_order_page' )->willReturn( false );

		$enabled_methods = $helper->get_enabled_express_checkout_methods_for_context();

		$this->assertNotContains( 'amazon_pay', $enabled_methods );

		remove_all_filters( 'pre_option__wcpay_feature_amazon_pay' );
		remove_filter( 'woocommerce_currency', [ $this, 'return_eur_currency' ] );
		WC_Payments::set_database_cache( $original_cache );
	}

	/**
	 * Helper function to return EUR currency.
	 *
	 * @return string
	 */
	public function return_eur_currency() {
		return 'EUR';
	}

	/**
	 * @return string
	 */
	public function return_usd_currency() {
		return 'USD';
	}

	/**
	 * Data provider for can_use_amazon_pay() tests.
	 *
	 * @return array
	 */
	public function can_use_amazon_pay_provider() {
		return [
			'feature flag disabled' => [
				'feature_flag_enabled' => false,
				'gateway_enabled'      => true,
				'has_fees'             => true,
				'tax_on_billing'       => false,
				'currency'             => 'USD',
				'expected'             => false,
			],
			'gateway not enabled'   => [
				'feature_flag_enabled' => true,
				'gateway_enabled'      => false,
				'has_fees'             => true,
				'tax_on_billing'       => false,
				'currency'             => 'USD',
				'expected'             => false,
			],
			'no fees configured'    => [
				'feature_flag_enabled' => true,
				'gateway_enabled'      => true,
				'has_fees'             => false,
				'tax_on_billing'       => false,
				'currency'             => 'USD',
				'expected'             => false,
			],
			'tax based on billing'  => [
				'feature_flag_enabled' => true,
				'gateway_enabled'      => true,
				'has_fees'             => true,
				'tax_on_billing'       => true,
				'currency'             => 'USD',
				'expected'             => false,
			],
			'all conditions met'    => [
				'feature_flag_enabled' => true,
				'gateway_enabled'      => true,
				'has_fees'             => true,
				'tax_on_billing'       => false,
				'currency'             => 'USD',
				'expected'             => true,
			],
		];
	}

	/**
	 * @dataProvider can_use_amazon_pay_provider
	 */
	public function test_can_use_amazon_pay( $feature_flag_enabled, $gateway_enabled, $has_fees, $tax_on_billing, $currency, $expected ) {
		$original_gateway_map     = WC_Payments::get_payment_gateway_map();
		$original_account_service = WC_Payments::get_account_service();
		$original_cache           = WC_Payments::get_database_cache();

		// is_amazon_pay_enabled() internally checks is_ece_confirmation_tokens_enabled() which reads from cache.
		$mock_cache = $this->createMock( WCPay\Database_Cache::class );
		$mock_cache->method( 'get' )->willReturn( [ 'ece_confirmation_tokens_disabled' => false ] );
		WC_Payments::set_database_cache( $mock_cache );

		add_filter(
			'pre_option__wcpay_feature_amazon_pay',
			function () use ( $feature_flag_enabled ) {
				return $feature_flag_enabled ? '1' : '0';
			}
		);

		$mock_amazon_pay_gateway = $this->createMock( WC_Payment_Gateway_WCPay::class );
		$mock_amazon_pay_gateway->method( 'is_enabled' )->willReturn( $gateway_enabled );
		$this->set_payment_gateway_map( [ 'amazon_pay' => $mock_amazon_pay_gateway ] );

		$fees = $has_fees
			? [
				'card'       => [ 'base' => [] ],
				'amazon_pay' => [ 'base' => [] ],
			]
			: [ 'card' => [ 'base' => [] ] ];

		$mock_account = $this->createMock( WC_Payments_Account::class );
		$mock_account->method( 'get_fees' )->willReturn( $fees );
		$mock_account->method( 'get_account_country' )->willReturn( 'US' );
		$mock_account->method( 'get_cached_account_data' )->willReturn( [ 'country' => 'US' ] );

		// AmazonPayDefinition::get_supported_currencies() uses the global account service.
		WC_Payments::set_account_service( $mock_account );

		add_filter(
			'woocommerce_currency',
			function () use ( $currency ) {
				return $currency;
			}
		);

		if ( $tax_on_billing ) {
			add_filter( 'wc_tax_enabled', '__return_true' );
			update_option( 'woocommerce_tax_based_on', 'billing' );
		} else {
			add_filter( 'wc_tax_enabled', '__return_false' );
		}

		$helper = $this->getMockBuilder( WC_Payments_Express_Checkout_Button_Helper::class )
			->setConstructorArgs( [ $this->mock_wcpay_gateway, $mock_account ] )
			->onlyMethods( [ 'is_pay_for_order_page' ] )
			->getMock();
		$helper->method( 'is_pay_for_order_page' )->willReturn( false );

		$result = $helper->can_use_amazon_pay();

		$this->assertSame( $expected, $result );

		remove_all_filters( 'pre_option__wcpay_feature_amazon_pay' );
		remove_all_filters( 'woocommerce_currency' );
		$this->set_payment_gateway_map( $original_gateway_map );
		if ( $original_account_service ) {
			WC_Payments::set_account_service( $original_account_service );
		}
		WC_Payments::set_database_cache( $original_cache );
		remove_filter( 'wc_tax_enabled', '__return_true' );
		remove_filter( 'wc_tax_enabled', '__return_false' );
		delete_option( 'woocommerce_tax_based_on' );
	}
}
