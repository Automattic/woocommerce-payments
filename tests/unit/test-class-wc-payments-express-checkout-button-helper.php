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
 * WC_Payments_Payment_Request_Button_Handler_Test class.
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
	 * Express Checkout Helper instance.
	 *
	 * @var WC_Payments_Express_Checkout_Button_Helper
	 */
	private $express_checkout_helper;

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
	private $mock_express_checkout_helper;

	/**
	 * Express Checkout Ajax Handler instance.
	 *
	 * @var WC_Payments_Express_Checkout_Ajax_Handler
	 */
	private $mock_express_checkout_ajax_handler;

	/**
	 * Express Checkout ECE Button Handler instance.
	 *
	 * @var WC_Payments_Express_Checkout_Button_Handler
	 */
	private $mock_express_checkout_ece_button_handler;

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

		$this->mock_express_checkout_helper       = new WC_Payments_Express_Checkout_Button_Helper( $this->mock_wcpay_gateway, $this->mock_wcpay_account );
		$this->mock_express_checkout_ajax_handler = $this->getMockBuilder( WC_Payments_Express_Checkout_Ajax_Handler::class )
			->setConstructorArgs(
				[
					$this->mock_express_checkout_helper,
				]
			)
			->getMock();

		$this->mock_ece_button_helper = $this->getMockBuilder( WC_Payments_Express_Checkout_Button_Helper::class )
			->setConstructorArgs(
				[
					$this->mock_wcpay_gateway,
					$this->mock_wcpay_account,
				]
			)
			->getMock();

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
		$this->mock_express_checkout_helper->update_shipping_method( [ self::get_shipping_option_rate_id( $this->flat_rate_id ) ] );
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

		return new WC_Payment_Gateway_WCPay(
			$mock_api_client,
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
			$this->createMock( WC_Payments_Fraud_Service::class ),
			$this->createMock( Duplicates_Detection_Service::class )
		);
	}

	public function test_common_get_button_settings() {
		$this->assertEquals(
			[
				'type'   => 'buy',
				'theme'  => 'dark',
				'height' => '48',
				'radius' => '',
			],
			$this->mock_express_checkout_helper->get_common_button_settings()
		);
	}

	public function test_cart_prices_include_tax_with_tax_disabled() {
		add_filter( 'wc_tax_enabled', '__return_false' );
		$this->assertTrue( $this->mock_express_checkout_helper->cart_prices_include_tax() );
	}

	public function test_cart_prices_include_tax_with_tax_enabled_and_display_incl() {
		add_filter( 'wc_tax_enabled', '__return_true' ); // reset in tear_down.
		add_filter( 'pre_option_woocommerce_tax_display_cart', [ $this, '__return_incl' ] ); // reset in tear_down.

		$this->assertTrue( $this->mock_express_checkout_helper->cart_prices_include_tax() );
	}

	public function test_cart_prices_include_tax_with_tax_enabled_and_display_excl() {
		add_filter( 'wc_tax_enabled', '__return_true' ); // reset in tear_down.
		add_filter( 'pre_option_woocommerce_tax_display_cart', [ $this, '__return_excl' ] ); // reset in tear_down.

		$this->assertFalse( $this->mock_express_checkout_helper->cart_prices_include_tax() );
	}

	public function test_get_total_label() {
		$this->mock_wcpay_account->method( 'get_statement_descriptor' )
			->willReturn( 'Google Pay' );

		$result = $this->mock_express_checkout_helper->get_total_label();

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

		$result = $this->mock_express_checkout_helper->get_total_label();

		$this->assertEquals( 'Google Pay (via WooPayments)', $result );

		remove_all_filters( 'wcpay_payment_request_total_label_suffix' );
	}

	public function test_filter_cart_needs_shipping_address_returns_false() {
		sleep( 1 );
		$this->zone->delete_shipping_method( $this->flat_rate_id );
		$this->zone->delete_shipping_method( $this->local_pickup_id );

		WC_Subscriptions_Cart::set_cart_contains_subscription( true );

		$this->mock_ece_button_helper
			->method( 'is_product' )
			->willReturn( true );

		$this->mock_express_checkout_ece_button_handler = new WC_Payments_Express_Checkout_Button_Handler(
			$this->mock_wcpay_account,
			$this->mock_wcpay_gateway,
			$this->mock_ece_button_helper,
			$this->mock_express_checkout_ajax_handler
		);

		$this->assertFalse( $this->mock_express_checkout_ece_button_handler->filter_cart_needs_shipping_address( true ) );
	}

	public function test_filter_cart_needs_shipping_address_returns_true() {
		WC_Subscriptions_Cart::set_cart_contains_subscription( true );

		$this->mock_ece_button_helper
			->method( 'is_product' )
			->willReturn( true );

		$this->mock_express_checkout_ece_button_handler = new WC_Payments_Express_Checkout_Button_Handler(
			$this->mock_wcpay_account,
			$this->mock_wcpay_gateway,
			$this->mock_ece_button_helper,
			$this->mock_express_checkout_ajax_handler
		);

		$this->assertTrue( $this->mock_express_checkout_ece_button_handler->filter_cart_needs_shipping_address( true ) );
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
}
