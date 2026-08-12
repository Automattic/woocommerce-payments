<?php
/**
 * These tests make assertions against class WC_Payments_Express_Checkout_Button_Helper.
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\Constants\Currency_Code;
use WCPay\Duplicate_Payment_Prevention_Service;
use WCPay\Duplicates_Detection_Service;
use WCPay\Payment_Methods\UPE_Payment_Method;
use WCPay\PaymentMethods\Configs\Definitions\CardDefinition;
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
		WC_Subscriptions::wcs_cart_contains_renewal( null );
		WC_Subscriptions::wcs_cart_contains_resubscribe( null );
		WC_Subscriptions::wcs_cart_contains_switches( null );
		WC_Subscriptions::set_wcs_order_contains_subscription( null );
		WC_Subscriptions::wcs_order_contains_renewal( null );
		unset( $GLOBALS['wp']->query_vars['order-pay'] );
		WC_Subscriptions_Product::$is_subscription = true;
		WC_Subscriptions_Product::$trial_length    = 0;
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
		$mock_payment_method           = $this->createMock( UPE_Payment_Method::class );
		$mock_payment_method->method( 'get_id' )->willReturn( CardDefinition::get_id() );

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

	public function test_has_subscription_product_on_cart() {
		WC_Subscriptions_Product::$is_subscription = true;
		WC_Subscriptions_Cart::set_cart_contains_subscription( true );

		$helper = $this->getMockBuilder( WC_Payments_Express_Checkout_Button_Helper::class )
			->setConstructorArgs( [ $this->mock_wcpay_gateway, $this->mock_wcpay_account ] )
			->onlyMethods( [ 'is_product', 'is_cart', 'is_checkout' ] )
			->getMock();

		$helper->method( 'is_product' )->willReturn( false );
		$helper->method( 'is_cart' )->willReturn( true );
		$helper->method( 'is_checkout' )->willReturn( false );

		$this->assertTrue( $helper->has_subscription_product() );

		WC_Subscriptions_Cart::set_cart_contains_subscription( false );
	}

	public function test_has_subscription_product_on_product_page_with_no_subscription_product() {
		WC_Subscriptions_Product::$is_subscription = false;
		WC_Subscriptions_Cart::set_cart_contains_subscription( true );

		$helper = $this->getMockBuilder( WC_Payments_Express_Checkout_Button_Helper::class )
			->setConstructorArgs( [ $this->mock_wcpay_gateway, $this->mock_wcpay_account ] )
			->onlyMethods( [ 'is_product', 'is_cart', 'is_checkout' ] )
			->getMock();

		$helper->method( 'is_product' )->willReturn( true );
		$helper->method( 'is_cart' )->willReturn( false );
		$helper->method( 'is_checkout' )->willReturn( false );

		$this->assertFalse( $helper->has_subscription_product() );

		WC_Subscriptions_Cart::set_cart_contains_subscription( false );
	}

	public function test_has_subscription_product_on_product_page_with_subscription_product() {
		WC_Subscriptions_Product::$is_subscription = true;
		WC_Subscriptions_Cart::set_cart_contains_subscription( true );

		$helper = $this->getMockBuilder( WC_Payments_Express_Checkout_Button_Helper::class )
			->setConstructorArgs( [ $this->mock_wcpay_gateway, $this->mock_wcpay_account ] )
			->onlyMethods( [ 'is_product', 'is_cart', 'is_checkout' ] )
			->getMock();

		$helper->method( 'is_product' )->willReturn( true );
		$helper->method( 'is_cart' )->willReturn( false );
		$helper->method( 'is_checkout' )->willReturn( false );

		$this->assertTrue( $helper->has_subscription_product() );

		WC_Subscriptions_Cart::set_cart_contains_subscription( false );
	}

	public function test_has_subscription_product_on_cart_with_renewal() {
		WC_Subscriptions_Cart::set_cart_contains_subscription( false );
		WC_Subscriptions::wcs_cart_contains_renewal(
			function () {
				return true;
			}
		);

		$helper = $this->getMockBuilder( WC_Payments_Express_Checkout_Button_Helper::class )
			->setConstructorArgs( [ $this->mock_wcpay_gateway, $this->mock_wcpay_account ] )
			->onlyMethods( [ 'is_product', 'is_cart', 'is_checkout' ] )
			->getMock();

		$helper->method( 'is_product' )->willReturn( false );
		$helper->method( 'is_cart' )->willReturn( true );
		$helper->method( 'is_checkout' )->willReturn( false );

		$this->assertTrue( $helper->has_subscription_product() );

		WC_Subscriptions::wcs_cart_contains_renewal( null );
	}

	public function test_has_subscription_product_on_checkout_with_resubscribe() {
		WC_Subscriptions_Cart::set_cart_contains_subscription( false );
		WC_Subscriptions::wcs_cart_contains_resubscribe(
			function () {
				return true;
			}
		);

		$helper = $this->getMockBuilder( WC_Payments_Express_Checkout_Button_Helper::class )
			->setConstructorArgs( [ $this->mock_wcpay_gateway, $this->mock_wcpay_account ] )
			->onlyMethods( [ 'is_product', 'is_cart', 'is_checkout' ] )
			->getMock();

		$helper->method( 'is_product' )->willReturn( false );
		$helper->method( 'is_cart' )->willReturn( false );
		$helper->method( 'is_checkout' )->willReturn( true );

		$this->assertTrue( $helper->has_subscription_product() );

		WC_Subscriptions::wcs_cart_contains_resubscribe( null );
	}

	public function test_has_subscription_product_on_checkout_with_switch() {
		WC_Subscriptions_Cart::set_cart_contains_subscription( false );
		WC_Subscriptions::wcs_cart_contains_switches(
			function () {
				return true;
			}
		);

		$helper = $this->getMockBuilder( WC_Payments_Express_Checkout_Button_Helper::class )
			->setConstructorArgs( [ $this->mock_wcpay_gateway, $this->mock_wcpay_account ] )
			->onlyMethods( [ 'is_product', 'is_cart', 'is_checkout' ] )
			->getMock();

		$helper->method( 'is_product' )->willReturn( false );
		$helper->method( 'is_cart' )->willReturn( false );
		$helper->method( 'is_checkout' )->willReturn( true );

		$this->assertTrue( $helper->has_subscription_product() );

		WC_Subscriptions::wcs_cart_contains_switches( null );
	}

	public function test_has_subscription_product_on_cart_with_no_subscription_variants() {
		WC_Subscriptions_Cart::set_cart_contains_subscription( false );

		$helper = $this->getMockBuilder( WC_Payments_Express_Checkout_Button_Helper::class )
			->setConstructorArgs( [ $this->mock_wcpay_gateway, $this->mock_wcpay_account ] )
			->onlyMethods( [ 'is_product', 'is_cart', 'is_checkout' ] )
			->getMock();

		$helper->method( 'is_product' )->willReturn( false );
		$helper->method( 'is_cart' )->willReturn( true );
		$helper->method( 'is_checkout' )->willReturn( false );

		$this->assertFalse( $helper->has_subscription_product() );
	}

	public function test_named_cart_context_reads_cart_state_without_page_context() {
		WC_Subscriptions_Cart::set_cart_contains_subscription( true );

		// No is_cart()/is_checkout() stubbing: the point is that this works with no page context.
		$this->assertSame( 'off_session', $this->system_under_test->get_setup_future_usage( 'cart' ) );
	}

	public function test_named_cart_context_detects_renewal_carts() {
		WC_Subscriptions_Cart::set_cart_contains_subscription( false );
		WC_Subscriptions::wcs_cart_contains_renewal(
			function () {
				return true;
			}
		);

		$this->assertSame( 'off_session', $this->system_under_test->get_setup_future_usage( 'cart' ) );
	}

	public function test_named_cart_context_is_null_for_a_plain_cart() {
		WC_Subscriptions_Cart::set_cart_contains_subscription( false );

		$this->assertNull( $this->system_under_test->get_setup_future_usage( 'cart' ) );
	}

	public function test_get_setup_future_usage_is_off_session_for_a_subscription_cart() {
		WC_Subscriptions_Cart::set_cart_contains_subscription( true );

		$helper = $this->make_helper_for_context( 'cart' );

		$this->assertSame( 'off_session', $helper->get_setup_future_usage() );
	}

	public function test_get_setup_future_usage_is_null_for_a_plain_cart() {
		WC_Subscriptions_Cart::set_cart_contains_subscription( false );

		$helper = $this->make_helper_for_context( 'cart' );

		$this->assertNull( $helper->get_setup_future_usage() );
	}

	public function test_get_setup_future_usage_is_off_session_on_a_subscription_product_page() {
		WC_Subscriptions_Product::$is_subscription = true;
		WC_Subscriptions_Cart::set_cart_contains_subscription( false );

		$helper = $this->make_helper_for_context( 'product' );

		$this->assertSame( 'off_session', $helper->get_setup_future_usage() );
	}

	/**
	 * Counterpart to the test above. The subscription-product stub defaults to true, so
	 * without this the product branch would assert `off_session` for every product page
	 * and pin nothing.
	 */
	public function test_get_setup_future_usage_is_null_on_a_non_subscription_product_page() {
		WC_Subscriptions_Product::$is_subscription = false;
		WC_Subscriptions_Cart::set_cart_contains_subscription( false );

		$helper = $this->make_helper_for_context( 'product' );

		$this->assertNull( $helper->get_setup_future_usage() );
	}

	/**
	 * The Store API cart endpoint carries no page context, so it names the context instead.
	 * Without that, is_cart()/is_checkout() are both false and the cart would look plain.
	 */
	public function test_get_setup_future_usage_honours_a_named_cart_context_with_no_page_context() {
		WC_Subscriptions_Cart::set_cart_contains_subscription( true );

		$this->assertSame( 'off_session', $this->system_under_test->get_setup_future_usage( 'cart' ) );
	}

	public function test_get_setup_future_usage_filter_can_declare_off_session() {
		WC_Subscriptions_Cart::set_cart_contains_subscription( false );

		$seen_context = null;
		$filter       = function ( $value, $context ) use ( &$seen_context ) {
			$seen_context = $context;
			return 'off_session';
		};
		add_filter( 'wcpay_express_checkout_setup_future_usage', $filter, 10, 2 );

		$actual = $this->system_under_test->get_setup_future_usage( 'cart' );

		remove_filter( 'wcpay_express_checkout_setup_future_usage', $filter, 10 );

		$this->assertSame( 'off_session', $actual );
		$this->assertSame( 'cart', $seen_context );
	}

	public function test_get_setup_future_usage_filter_can_suppress_off_session() {
		WC_Subscriptions_Cart::set_cart_contains_subscription( true );

		$filter = function () {
			return null;
		};
		add_filter( 'wcpay_express_checkout_setup_future_usage', $filter );

		$actual = $this->system_under_test->get_setup_future_usage( 'cart' );

		remove_filter( 'wcpay_express_checkout_setup_future_usage', $filter );

		$this->assertNull( $actual );
	}

	/**
	 * Paying an existing order runs with an empty cart, so the cart and product predicates
	 * both report false while the gateway still saves the payment method for the
	 * subscription on the order.
	 */
	public function test_get_setup_future_usage_reads_the_order_for_pay_for_order() {
		WC_Subscriptions_Cart::set_cart_contains_subscription( false );
		WC_Subscriptions::set_wcs_order_contains_subscription(
			function () {
				return true;
			}
		);
		$this->set_order_pay_endpoint( WC_Helper_Order::create_order() );

		$actual = $this->system_under_test->get_setup_future_usage( 'pay_for_order' );

		$this->assertSame( 'off_session', $actual );
	}

	public function test_get_setup_future_usage_is_null_for_a_pay_for_order_without_a_subscription() {
		WC_Subscriptions_Cart::set_cart_contains_subscription( false );
		$this->set_order_pay_endpoint( WC_Helper_Order::create_order() );

		$actual = $this->system_under_test->get_setup_future_usage( 'pay_for_order' );

		$this->assertNull( $actual );
	}

	/**
	 * The order-pay page carries the order in a query var. `get_current_order()` reads the
	 * admin globals, where `$post` is the checkout page on the front end — so resolving
	 * through it returns no order and the subscription goes undetected.
	 */
	public function test_pay_for_order_resolves_the_order_from_the_query_var() {
		$order = WC_Helper_Order::create_order();

		WC_Subscriptions_Cart::set_cart_contains_subscription( false );
		WC_Subscriptions::set_wcs_order_contains_subscription(
			function ( $order_id ) use ( $order ) {
				// Answers only for this exact order, so resolving the checkout page — or
				// nothing at all — cannot pass this.
				return (int) $order_id === $order->get_id();
			}
		);
		$this->set_order_pay_endpoint( $order );

		$this->assertSame( 'off_session', $this->system_under_test->get_setup_future_usage( 'pay_for_order' ) );
	}

	/**
	 * A renewal order carries the renewed subscription by reference rather than as a
	 * subscription line item, so `wcs_order_contains_subscription()` alone misses it.
	 */
	public function test_get_setup_future_usage_recognises_a_renewal_order() {
		WC_Subscriptions_Cart::set_cart_contains_subscription( false );
		WC_Subscriptions::set_wcs_order_contains_subscription(
			function () {
				return false;
			}
		);
		WC_Subscriptions::wcs_order_contains_renewal(
			function () {
				return true;
			}
		);

		$this->set_order_pay_endpoint( WC_Helper_Order::create_order() );

		$actual = $this->system_under_test->get_setup_future_usage( 'pay_for_order' );

		$this->assertSame( 'off_session', $actual );
	}

	/**
	 * Off the order-pay endpoint there is no order to read, and the subscription
	 * predicates must not be handed a non-order in its place.
	 */
	public function test_get_setup_future_usage_is_null_when_no_order_resolves() {
		WC_Subscriptions_Cart::set_cart_contains_subscription( false );
		WC_Subscriptions::set_wcs_order_contains_subscription(
			function () {
				return true;
			}
		);
		WC_Subscriptions::wcs_order_contains_renewal(
			function () {
				return true;
			}
		);

		$actual = $this->system_under_test->get_setup_future_usage( 'pay_for_order' );

		$this->assertNull( $actual );
	}

	/**
	 * The handler calls `get_setup_future_usage()` with no argument, so the shipped value
	 * comes from `get_button_context()`. Passing the context by hand in every other test
	 * leaves that wiring unpinned for the branch this PR added.
	 */
	public function test_get_setup_future_usage_resolves_pay_for_order_with_no_argument() {
		WC_Subscriptions_Cart::set_cart_contains_subscription( false );
		WC_Subscriptions::set_wcs_order_contains_subscription(
			function () {
				return true;
			}
		);
		$this->set_order_pay_endpoint( WC_Helper_Order::create_order() );

		$helper = $this->make_helper_for_context( 'pay_for_order' );

		$this->assertSame( 'off_session', $helper->get_setup_future_usage() );
	}

	/**
	 * Every client consumer gates on truthiness while the server infers from `null !==`, so
	 * a filter returning anything other than 'off_session' or null has the two sides
	 * disagreeing about the same payment.
	 *
	 * @dataProvider provider_non_canonical_filter_returns
	 *
	 * @param mixed $returned What the filter hands back.
	 */
	public function test_get_setup_future_usage_normalises_non_canonical_filter_returns( $returned ) {
		WC_Subscriptions_Cart::set_cart_contains_subscription( false );

		$filter = function () use ( $returned ) {
			return $returned;
		};
		add_filter( 'wcpay_express_checkout_setup_future_usage', $filter );

		$actual = $this->system_under_test->get_setup_future_usage( 'cart' );

		remove_filter( 'wcpay_express_checkout_setup_future_usage', $filter );

		$this->assertNull( $actual );
	}

	/**
	 * Data provider for the filter normalisation test.
	 *
	 * @return array
	 */
	public function provider_non_canonical_filter_returns() {
		return [
			'__return_false' => [ false ],
			'__return_true'  => [ true ],
			'empty string'   => [ '' ],
			'on_session'     => [ 'on_session' ],
			'zero'           => [ 0 ],
		];
	}

	/**
	 * Puts a real order on the order-pay endpoint, the way the front end does. Resolution
	 * is deliberately left unmocked — mocking it is what hid the order going unresolved
	 * outside the admin.
	 *
	 * @param WC_Order $order Order being paid.
	 */
	private function set_order_pay_endpoint( WC_Order $order ) {
		global $wp;

		$wp->query_vars['order-pay'] = (string) $order->get_id();
	}

	/**
	 * Builds a helper whose page context is pinned, so context-dependent predicates
	 * can be exercised off an actual request.
	 *
	 * @param bool $is_product  Whether to report a product page.
	 * @param bool $is_cart     Whether to report the cart page.
	 * @param bool $is_checkout Whether to report the checkout page.
	 *
	 * @return WC_Payments_Express_Checkout_Button_Helper|MockObject
	 */
	private function make_helper_for_context( string $context ) {
		$helper = $this->getMockBuilder( WC_Payments_Express_Checkout_Button_Helper::class )
			->setConstructorArgs( [ $this->mock_wcpay_gateway, $this->mock_wcpay_account ] )
			->onlyMethods( [ 'is_product', 'is_cart', 'is_checkout', 'is_pay_for_order_page' ] )
			->getMock();

		$helper->method( 'is_product' )->willReturn( 'product' === $context );
		$helper->method( 'is_cart' )->willReturn( 'cart' === $context );
		// The order-pay page is part of checkout, so `is_checkout()` is true there too.
		$helper->method( 'is_checkout' )->willReturn( in_array( $context, [ 'checkout', 'pay_for_order' ], true ) );
		$helper->method( 'is_pay_for_order_page' )->willReturn( 'pay_for_order' === $context );

		return $helper;
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

	public function test_get_quantity_preserves_decimal_on_decimal_stores() {
		// Stores that sell in fractional units (e.g. fabric by the metre) swap the
		// default integer stock-amount filter for a float one; mirror that here so
		// wc_stock_amount() keeps the fraction instead of truncating it.
		remove_filter( 'woocommerce_stock_amount', 'intval' );
		add_filter( 'woocommerce_stock_amount', 'floatval' );

		try {
			$_POST['qty'] = '0.25';

			$result = $this->system_under_test->get_quantity();
		} finally {
			remove_filter( 'woocommerce_stock_amount', 'floatval' );
			add_filter( 'woocommerce_stock_amount', 'intval' );
			unset( $_POST['qty'] );
		}

		$this->assertEqualsWithDelta( 0.25, $result, 0.0001 );
	}

	public function test_get_quantity_preserves_decimal_from_woopay_quantity_key() {
		// WooPay posts the quantity as `quantity`; it must be preserved the same way.
		remove_filter( 'woocommerce_stock_amount', 'intval' );
		add_filter( 'woocommerce_stock_amount', 'floatval' );

		try {
			$_POST['quantity'] = '0.25';

			$result = $this->system_under_test->get_quantity();
		} finally {
			remove_filter( 'woocommerce_stock_amount', 'floatval' );
			add_filter( 'woocommerce_stock_amount', 'intval' );
			unset( $_POST['quantity'] );
		}

		$this->assertEqualsWithDelta( 0.25, $result, 0.0001 );
	}

	public function test_get_quantity_returns_integer_on_default_stores() {
		try {
			$_POST['qty'] = '3';

			$result = $this->system_under_test->get_quantity();
		} finally {
			unset( $_POST['qty'] );
		}

		$this->assertSame( 3, $result );
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

		$helper = $this->getMockBuilder( WC_Payments_Express_Checkout_Button_Helper::class )
			->setConstructorArgs( [ $this->mock_wcpay_gateway, $this->mock_wcpay_account ] )
			->onlyMethods( [ 'get_enabled_express_checkout_methods_for_context' ] )
			->getMock();

		$helper->method( 'get_enabled_express_checkout_methods_for_context' )->willReturn( [ 'payment_request' ] );

		$this->assertTrue( $helper->should_show_express_checkout_button() );

		remove_filter( 'woocommerce_is_checkout', '__return_true' );
	}

	public function test_should_show_express_checkout_button_for_pay_for_order_without_billing_email() {
		global $wp;
		global $wp_query;

		$this->mock_wcpay_account
			->method( 'is_stripe_connected' )
			->willReturn( true );
		WC_Payments::mode()->dev();
		$_GET['pay_for_order'] = true;

		// Order created without a billing email (e.g. by the merchant). The email is captured
		// from the wallet at payment time, so the button should still be offered.
		$order = WC_Helper_Order::create_order( 1, 100 );
		$order->set_billing_email( '' );
		$order->save();
		$order_id             = $order->get_id();
		$wp->query_vars       = [ 'order-pay' => strval( $order_id ) ];
		$wp_query->query_vars = [ 'order-pay' => strval( $order_id ) ];

		add_filter( 'woocommerce_is_checkout', '__return_true' );

		$helper = $this->getMockBuilder( WC_Payments_Express_Checkout_Button_Helper::class )
			->setConstructorArgs( [ $this->mock_wcpay_gateway, $this->mock_wcpay_account ] )
			->onlyMethods( [ 'get_enabled_express_checkout_methods_for_context' ] )
			->getMock();

		$helper->method( 'get_enabled_express_checkout_methods_for_context' )->willReturn( [ 'payment_request' ] );

		$this->assertTrue( $helper->should_show_express_checkout_button() );

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

		$helper = $this->getMockBuilder( WC_Payments_Express_Checkout_Button_Helper::class )
			->setConstructorArgs( [ $this->mock_wcpay_gateway, $this->mock_wcpay_account ] )
			->onlyMethods( [ 'get_enabled_express_checkout_methods_for_context' ] )
			->getMock();

		$helper->method( 'get_enabled_express_checkout_methods_for_context' )->willReturn( [ 'payment_request' ] );

		$this->assertTrue( $helper->should_show_express_checkout_button() );

		remove_filter( 'woocommerce_is_checkout', '__return_true' );
		remove_filter( 'wc_tax_enabled', '__return_true' );
		remove_filter( 'pre_option_woocommerce_tax_display_cart', [ $this, '__return_incl' ] );
	}

	public function test_should_not_show_express_checkout_button_when_no_methods_enabled_at_location() {
		$this->mock_wcpay_account
			->method( 'is_stripe_connected' )
			->willReturn( true );

		WC_Payments::mode()->dev();

		add_filter( 'woocommerce_is_checkout', '__return_true' );

		// Clear all express checkout methods from the checkout location.
		$this->mock_wcpay_gateway->update_option( 'express_checkout_checkout_methods', [] );

		// Without mocking get_enabled_express_checkout_methods_for_context, it should
		// return empty because no methods are enabled at the checkout location, causing
		// should_show_express_checkout_button to return false.
		$this->assertFalse( $this->system_under_test->should_show_express_checkout_button() );

		remove_filter( 'woocommerce_is_checkout', '__return_true' );

		// Restore for other tests.
		$this->mock_wcpay_gateway->update_option( 'express_checkout_checkout_methods', [ 'payment_request', 'woopay' ] );
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

	public function test_get_methods_enabled_at_current_location_returns_raw_location_settings() {
		// Unlike get_enabled_express_checkout_methods_for_context(), this reads
		// the location settings verbatim — no currency or availability gating.
		$this->mock_wcpay_gateway->update_option( 'express_checkout_cart_methods', [ 'payment_request', 'amazon_pay' ] );
		$this->mock_wcpay_gateway->update_option( 'express_checkout_checkout_methods', [ 'payment_request' ] );

		$helper = $this->getMockBuilder( WC_Payments_Express_Checkout_Button_Helper::class )
			->setConstructorArgs( [ $this->mock_wcpay_gateway, $this->mock_wcpay_account ] )
			->onlyMethods( [ 'is_product', 'is_cart', 'is_checkout', 'is_pay_for_order_page' ] )
			->getMock();

		$helper->method( 'is_product' )->willReturn( false );
		$helper->method( 'is_cart' )->willReturn( true );
		$helper->method( 'is_checkout' )->willReturn( false );
		$helper->method( 'is_pay_for_order_page' )->willReturn( false );

		$this->assertSame(
			[ 'payment_request', 'amazon_pay' ],
			$helper->get_methods_enabled_at_current_location()
		);
	}

	public function test_get_methods_enabled_at_current_location_returns_empty_without_context() {
		$helper = $this->getMockBuilder( WC_Payments_Express_Checkout_Button_Helper::class )
			->setConstructorArgs( [ $this->mock_wcpay_gateway, $this->mock_wcpay_account ] )
			->onlyMethods( [ 'is_product', 'is_cart', 'is_checkout', 'is_pay_for_order_page' ] )
			->getMock();

		$helper->method( 'is_product' )->willReturn( false );
		$helper->method( 'is_cart' )->willReturn( false );
		$helper->method( 'is_checkout' )->willReturn( false );
		$helper->method( 'is_pay_for_order_page' )->willReturn( false );

		$this->assertSame( [], $helper->get_methods_enabled_at_current_location() );
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
		return Currency_Code::EURO;
	}

	/**
	 * @return string
	 */
	public function return_usd_currency() {
		return Currency_Code::UNITED_STATES_DOLLAR;
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
				'gateway_available'    => true,
				'tax_on_billing'       => false,
				'expected'             => false,
			],
			'gateway not available' => [
				'feature_flag_enabled' => true,
				'gateway_available'    => false,
				'tax_on_billing'       => false,
				'expected'             => false,
			],
			'tax based on billing'  => [
				'feature_flag_enabled' => true,
				'gateway_available'    => true,
				'tax_on_billing'       => true,
				'expected'             => false,
			],
			'all conditions met'    => [
				'feature_flag_enabled' => true,
				'gateway_available'    => true,
				'tax_on_billing'       => false,
				'expected'             => true,
			],
		];
	}

	/**
	 * @dataProvider can_use_amazon_pay_provider
	 */
	public function test_can_use_amazon_pay( $feature_flag_enabled, $gateway_available, $tax_on_billing, $expected ) {
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
		$mock_amazon_pay_gateway->method( 'is_available_for_express_checkout' )->willReturn( $gateway_available );
		$this->set_payment_gateway_map( [ 'amazon_pay' => $mock_amazon_pay_gateway ] );

		$mock_account = $this->createMock( WC_Payments_Account::class );
		$mock_account->method( 'get_account_country' )->willReturn( 'US' );
		$mock_account->method( 'get_cached_account_data' )->willReturn( [ 'country' => 'US' ] );

		WC_Payments::set_account_service( $mock_account );

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
		$this->set_payment_gateway_map( $original_gateway_map );
		if ( $original_account_service ) {
			WC_Payments::set_account_service( $original_account_service );
		}
		WC_Payments::set_database_cache( $original_cache );
		remove_filter( 'wc_tax_enabled', '__return_true' );
		remove_filter( 'wc_tax_enabled', '__return_false' );
		delete_option( 'woocommerce_tax_based_on' );
	}

	public function test_can_use_amazon_pay_returns_false_when_express_checkout_in_payment_methods_enabled() {
		$original_gateway = WC_Payments::get_gateway();

		WC_Payments::mode()->dev();
		update_option( WC_Payments_Features::WCPAY_DYNAMIC_CHECKOUT_PLACE_ORDER_BUTTON_FLAG_NAME, '1' );
		$this->mock_wcpay_gateway->update_option( 'express_checkout_in_payment_methods', 'yes' );
		WC_Payments::set_gateway( $this->mock_wcpay_gateway );

		$result = $this->system_under_test->can_use_amazon_pay();

		$this->assertFalse( $result );

		WC_Payments::set_gateway( $original_gateway );
		delete_option( WC_Payments_Features::WCPAY_DYNAMIC_CHECKOUT_PLACE_ORDER_BUTTON_FLAG_NAME );
		WC_Payments::mode()->live();
	}

	public function test_should_show_express_checkout_button_returns_false_when_express_checkout_in_payment_methods_enabled() {
		$original_gateway = WC_Payments::get_gateway();

		WC_Payments::mode()->dev();
		update_option( WC_Payments_Features::WCPAY_DYNAMIC_CHECKOUT_PLACE_ORDER_BUTTON_FLAG_NAME, '1' );
		$this->mock_wcpay_gateway->update_option( 'express_checkout_in_payment_methods', 'yes' );
		WC_Payments::set_gateway( $this->mock_wcpay_gateway );

		$result = $this->system_under_test->should_show_express_checkout_button();

		$this->assertFalse( $result );

		WC_Payments::set_gateway( $original_gateway );
		delete_option( WC_Payments_Features::WCPAY_DYNAMIC_CHECKOUT_PLACE_ORDER_BUTTON_FLAG_NAME );
		WC_Payments::mode()->live();
	}

	public function test_is_express_checkout_method_enabled_at_maps_pay_for_order_to_checkout() {
		// Set up checkout methods only - pay_for_order should use these.
		$this->mock_wcpay_gateway->update_option( 'express_checkout_checkout_methods', [ 'payment_request', 'amazon_pay' ] );
		$this->mock_wcpay_gateway->update_option( 'express_checkout_cart_methods', [] );
		$this->mock_wcpay_gateway->update_option( 'express_checkout_product_methods', [] );

		// Test that pay_for_order location uses checkout settings.
		$this->assertTrue(
			$this->system_under_test->is_express_checkout_method_enabled_at( 'pay_for_order', 'payment_request' ),
			'pay_for_order location should use checkout settings for payment_request'
		);
		$this->assertTrue(
			$this->system_under_test->is_express_checkout_method_enabled_at( 'pay_for_order', 'amazon_pay' ),
			'pay_for_order location should use checkout settings for amazon_pay'
		);

		// Test that other locations still work correctly.
		$this->assertTrue(
			$this->system_under_test->is_express_checkout_method_enabled_at( 'checkout', 'payment_request' ),
			'checkout location should still work'
		);
		$this->assertFalse(
			$this->system_under_test->is_express_checkout_method_enabled_at( 'cart', 'payment_request' ),
			'cart location should return false when not configured'
		);
	}

	public function test_should_not_show_express_checkout_button_with_zero_total_and_no_trial_subscription() {
		// Set up a zero-total cart without trial subscriptions.
		WC()->cart->empty_cart();
		$product = new WC_Product_Simple();
		$product->set_props(
			[
				'name'          => 'Free Product',
				'regular_price' => 0,
				'price'         => 0,
				'virtual'       => true,
			]
		);
		$product->save();
		WC()->cart->add_to_cart( $product->get_id(), 1 );
		WC()->cart->calculate_totals();

		$this->mock_wcpay_account
			->method( 'is_stripe_connected' )
			->willReturn( true );
		WC_Payments::mode()->dev();

		$helper = $this->getMockBuilder( WC_Payments_Express_Checkout_Button_Helper::class )
			->setConstructorArgs( [ $this->mock_wcpay_gateway, $this->mock_wcpay_account ] )
			->onlyMethods( [ 'is_product', 'is_cart', 'is_checkout', 'is_pay_for_order_page' ] )
			->getMock();

		$helper->method( 'is_product' )->willReturn( false );
		$helper->method( 'is_cart' )->willReturn( true );
		$helper->method( 'is_checkout' )->willReturn( false );
		$helper->method( 'is_pay_for_order_page' )->willReturn( false );

		$this->assertFalse( $helper->should_show_express_checkout_button() );
	}

	public function test_is_product_purchasable_returns_false_when_no_product() {
		$helper = $this->getMockBuilder( WC_Payments_Express_Checkout_Button_Helper::class )
			->setConstructorArgs( [ $this->mock_wcpay_gateway, $this->mock_wcpay_account ] )
			->onlyMethods( [ 'get_product' ] )
			->getMock();
		$helper->method( 'get_product' )->willReturn( null );

		// No resolvable product (e.g. off a product page, where get_product()
		// returns null) -> not purchasable. Callers still guard with is_product(),
		// so this never gates the cart or checkout.
		$this->assertFalse( $helper->is_product_purchasable() );
	}

	public function test_is_product_purchasable_returns_true_for_purchasable_in_stock_product() {
		$product = WC_Helper_Product::create_simple_product();
		$product->set_stock_status( 'instock' );
		$product->save();

		$helper = $this->getMockBuilder( WC_Payments_Express_Checkout_Button_Helper::class )
			->setConstructorArgs( [ $this->mock_wcpay_gateway, $this->mock_wcpay_account ] )
			->onlyMethods( [ 'is_product', 'get_product' ] )
			->getMock();
		$helper->method( 'is_product' )->willReturn( true );
		$helper->method( 'get_product' )->willReturn( $product );

		$this->assertTrue( $helper->is_product_purchasable() );
	}

	public function test_is_product_purchasable_returns_false_for_out_of_stock_product() {
		$product = WC_Helper_Product::create_simple_product();
		$product->set_stock_status( 'outofstock' );
		$product->save();

		$helper = $this->getMockBuilder( WC_Payments_Express_Checkout_Button_Helper::class )
			->setConstructorArgs( [ $this->mock_wcpay_gateway, $this->mock_wcpay_account ] )
			->onlyMethods( [ 'is_product', 'get_product' ] )
			->getMock();
		$helper->method( 'is_product' )->willReturn( true );
		$helper->method( 'get_product' )->willReturn( $product );

		$this->assertFalse( $helper->is_product_purchasable() );
	}

	public function test_is_product_purchasable_returns_true_for_backorder_product() {
		$product = WC_Helper_Product::create_simple_product();
		$product->set_manage_stock( true );
		$product->set_stock_quantity( 0 );
		$product->set_backorders( 'yes' );
		$product->save();

		$helper = $this->getMockBuilder( WC_Payments_Express_Checkout_Button_Helper::class )
			->setConstructorArgs( [ $this->mock_wcpay_gateway, $this->mock_wcpay_account ] )
			->onlyMethods( [ 'is_product', 'get_product' ] )
			->getMock();
		$helper->method( 'is_product' )->willReturn( true );
		$helper->method( 'get_product' )->willReturn( $product );

		// is_in_stock() returns true for backorder products, so they remain purchasable.
		$this->assertTrue( $helper->is_product_purchasable() );
	}

	public function test_is_product_purchasable_returns_false_for_non_purchasable_product() {
		$product = WC_Helper_Product::create_simple_product();
		$product->set_regular_price( '' );
		$product->set_price( '' );
		$product->save();

		$helper = $this->getMockBuilder( WC_Payments_Express_Checkout_Button_Helper::class )
			->setConstructorArgs( [ $this->mock_wcpay_gateway, $this->mock_wcpay_account ] )
			->onlyMethods( [ 'is_product', 'get_product' ] )
			->getMock();
		$helper->method( 'is_product' )->willReturn( true );
		$helper->method( 'get_product' )->willReturn( $product );

		// A product with no price is not purchasable.
		$this->assertFalse( $helper->is_product_purchasable() );
	}

	/**
	 * Data provider for get_product() shortcode-parsing tests.
	 *
	 * Each entry is [ post_content_template, use_sku ].
	 * "use_sku" tells the test whether to substitute the product ID or its SKU.
	 */
	public function get_product_shortcode_syntaxes_provider(): array {
		return [
			'double-quoted id'           => [ '[product_page id="%s"]', false ],
			'single-quoted id'           => [ "[product_page id='%s']", false ],
			'unquoted id'                => [ '[product_page id=%s]', false ],
			'extra attributes after id'  => [ '[product_page id="%s" show_title="false"]', false ],
			'extra attributes before id' => [ '[product_page show_title="false" id="%s"]', false ],
			'sku double-quoted'          => [ '[product_page sku="%s"]', true ],
			'sku single-quoted'          => [ "[product_page sku='%s']", true ],
			'sku unquoted'               => [ '[product_page sku=%s]', true ],
		];
	}

	/**
	 * Creates a product with a known SKU plus a page embedding it via the given shortcode
	 * syntax, then navigates to that page. Returns the product and its SKU.
	 *
	 * @param string $shortcode_template Template with a single %s placeholder.
	 * @param bool   $use_sku            Substitute the SKU instead of the product ID.
	 * @return array{0: WC_Product, 1: string}
	 */
	private function go_to_page_embedding_product( string $shortcode_template, bool $use_sku ): array {
		$product = WC_Helper_Product::create_simple_product();
		$sku     = 'test-sku-' . $product->get_id();
		$product->set_sku( $sku );
		$product->save();

		$this->go_to_page_with_content( sprintf( $shortcode_template, $use_sku ? $sku : $product->get_id() ) );

		return [ $product, $sku ];
	}

	/**
	 * Puts the main query back to the unparsed state a request has on `init`.
	 *
	 * WordPress 6.0's test teardown resets $wp_query but not $wp_the_query, so an earlier
	 * test's go_to() otherwise leaves the main query pointing at its page for the rest of
	 * the run. Mirrors what go_to() itself does.
	 *
	 * @return void
	 */
	private function reset_main_query() {
		unset( $GLOBALS['wp_query'], $GLOBALS['wp_the_query'] );

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Resetting the main query, as go_to() does.
		$GLOBALS['wp_the_query'] = new WP_Query();
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Resetting the main query, as go_to() does.
		$GLOBALS['wp_query'] = $GLOBALS['wp_the_query'];
	}

	/**
	 * Publishes a page with the given content and navigates to it.
	 *
	 * @param string $content The page content.
	 * @return int The page ID.
	 */
	private function go_to_page_with_content( string $content ): int {
		$page_id = wp_insert_post(
			[
				'post_title'   => 'Test page',
				'post_content' => $content,
				'post_status'  => 'publish',
				'post_type'    => 'page',
			]
		);

		$this->go_to( get_permalink( $page_id ) );

		return $page_id;
	}

	/**
	 * @dataProvider get_product_shortcode_syntaxes_provider
	 */
	public function test_get_product_from_product_page_shortcode( string $shortcode_template, bool $use_sku ) {
		list( $product ) = $this->go_to_page_embedding_product( $shortcode_template, $use_sku );

		$resolved = $this->system_under_test->get_product();

		$this->assertInstanceOf( WC_Product::class, $resolved );
		$this->assertSame( $product->get_id(), $resolved->get_id() );
	}

	/**
	 * The express checkout markup is emitted from `woocommerce_after_add_to_cart_form`, which
	 * fires inside the shortcode's own WP_Query loop — so by then the `$post` and `$wp_query`
	 * globals point at the embedded product rather than the host page. The helper has to keep
	 * recognising the page there, or the buttons are never rendered.
	 *
	 * The `sku` attribute is what exposes this: WooCommerce queries it via `meta_query` with no
	 * post ID, so the inner query never derives `is_singular` the way the `id` attribute does.
	 *
	 * @dataProvider get_product_shortcode_syntaxes_provider
	 */
	public function test_get_product_while_product_page_shortcode_renders( string $shortcode_template, bool $use_sku ) {
		list( $product, $sku ) = $this->go_to_page_embedding_product( $shortcode_template, $use_sku );

		$observed = [];
		add_action(
			'woocommerce_after_add_to_cart_form',
			function () use ( &$observed ) {
				$resolved               = $this->system_under_test->get_product();
				$observed['is_product'] = $this->system_under_test->is_product();
				$observed['product_id'] = $resolved instanceof WC_Product ? $resolved->get_id() : null;
			}
		);

		// The single-product template renders reviews, and the default test theme ships no
		// comments.php.
		$this->setExpectedDeprecated( 'Theme without comments.php' );

		// Render through WooCommerce's real shortcode rather than emulating its query, so this
		// keeps testing the actual conditions the button markup is emitted under.
		ob_start();
		do_shortcode( sprintf( $shortcode_template, $use_sku ? $sku : $product->get_id() ) );
		ob_end_clean();

		$this->assertArrayHasKey( 'is_product', $observed, 'woocommerce_after_add_to_cart_form did not fire.' );
		$this->assertTrue( $observed['is_product'] );
		$this->assertSame( $product->get_id(), $observed['product_id'] );
	}

	/**
	 * is_product() and get_product() are public, so anything hooked early — a third-party
	 * plugin on `init`, say — can reach them before WordPress has parsed the request. The
	 * answer at that point is meaningless, and caching it would silently disable express
	 * checkout for the rest of the request.
	 */
	public function test_early_call_does_not_pin_the_shortcode_context() {
		$this->reset_main_query();

		$this->assertFalse( $this->system_under_test->is_product() );
		$this->assertNull( $this->system_under_test->get_product() );

		list( $product ) = $this->go_to_page_embedding_product( '[product_page sku="%s"]', true );

		$this->assertTrue( $this->system_under_test->is_product() );
		$this->assertSame( $product->get_id(), $this->system_under_test->get_product()->get_id() );
	}

	/**
	 * wc_get_product_id_by_sku() is an uncached direct database query, and a single page
	 * render asks the helper for the page context dozens of times.
	 */
	public function test_repeated_calls_look_the_sku_up_once() {
		list( $product ) = $this->go_to_page_embedding_product( '[product_page sku="%s"]', true );

		// Counted from here so the uniqueness check WooCommerce runs when the fixture saves
		// its SKU isn't mistaken for one of the helper's lookups.
		$lookups = 0;
		add_filter(
			'woocommerce_get_product_id_by_sku',
			function ( $id ) use ( &$lookups ) {
				++$lookups;
				return $id;
			}
		);

		for ( $i = 0; $i < 5; $i++ ) {
			$this->system_under_test->is_product();
			$this->system_under_test->get_product();
		}

		$this->assertSame( 1, $lookups );
		$this->assertSame( $product->get_id(), $this->system_under_test->get_product()->get_id() );
	}

	/**
	 * The global product is only the right answer while the button markup is being emitted.
	 * Anywhere else it can be left over from an archive, cross-sell or [products] loop.
	 */
	public function test_unrelated_global_product_does_not_override_the_shortcode_product() {
		list( $product ) = $this->go_to_page_embedding_product( '[product_page id="%s"]', false );

		$GLOBALS['product'] = WC_Helper_Product::create_simple_product();

		$resolved = $this->system_under_test->get_product();
		unset( $GLOBALS['product'] );

		$this->assertSame( $product->get_id(), $resolved->get_id() );
	}

	/**
	 * do_shortcode() leaves [[product_page]] alone and prints it literally, so no product
	 * is ever rendered and no express checkout button belongs on the page.
	 */
	public function test_escaped_shortcode_is_not_treated_as_a_product_page() {
		$product = WC_Helper_Product::create_simple_product();
		$this->go_to_page_with_content( '[[product_page id="' . $product->get_id() . '"]]' );

		$this->assertFalse( $this->system_under_test->is_product() );
		$this->assertNull( $this->system_under_test->get_product() );
	}

	/**
	 * A page documenting the shortcode alongside a live one is the shape that catches a parser
	 * skipping the escaping: the escaped tag has to be passed over and the real one behind it
	 * still resolve, which is what do_shortcode() renders.
	 */
	public function test_escaped_shortcode_does_not_shadow_a_real_one_after_it() {
		$escaped = WC_Helper_Product::create_simple_product();
		$real    = WC_Helper_Product::create_simple_product();

		$this->go_to_page_with_content(
			sprintf( '[[product_page id="%d"]] [product_page id="%d"]', $escaped->get_id(), $real->get_id() )
		);

		$this->assertTrue( $this->system_under_test->is_product() );
		$this->assertSame( $real->get_id(), $this->system_under_test->get_product()->get_id() );
	}

	/**
	 * is_product() answers "does this page embed the shortcode", which it has to keep doing
	 * when the shortcode names a product that no longer exists — it is public, and callers
	 * gate their own product handling on get_product() returning something.
	 */
	public function test_shortcode_naming_a_missing_product_is_still_a_product_page() {
		$this->go_to_page_with_content( '[product_page id="999999"]' );

		$this->assertTrue( $this->system_under_test->is_product() );
		$this->assertNull( $this->system_under_test->get_product() );
	}

	public function test_should_not_show_express_checkout_button_when_product_not_purchasable() {
		$this->mock_wcpay_account->method( 'is_stripe_connected' )->willReturn( true );
		WC_Payments::mode()->dev();

		// A supported, priced product that is out of stock: every other gate in
		// should_show_express_checkout_button() passes, so the purchasability gate
		// is the only thing that makes it return false — exercising the real
		// is_product_purchasable() through the full flow.
		$product = WC_Helper_Product::create_simple_product();
		$product->set_stock_status( 'outofstock' );
		$product->save();

		$helper = $this->getMockBuilder( WC_Payments_Express_Checkout_Button_Helper::class )
			->setConstructorArgs( [ $this->mock_wcpay_gateway, $this->mock_wcpay_account ] )
			->onlyMethods( [ 'is_product', 'get_product', 'get_enabled_express_checkout_methods_for_context' ] )
			->getMock();
		$helper->method( 'is_product' )->willReturn( true );
		$helper->method( 'get_product' )->willReturn( $product );
		$helper->method( 'get_enabled_express_checkout_methods_for_context' )->willReturn( [ 'payment_request' ] );

		$this->assertFalse( $helper->should_show_express_checkout_button() );
	}
}
