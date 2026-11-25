<?php
/**
 * These tests make assertions against class WC_Payments_Express_Checkout_Button_Display_Handler.
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Duplicate_Payment_Prevention_Service;
use WCPay\Duplicates_Detection_Service;
use WCPay\Payment_Methods\UPE_Payment_Method;
use WCPay\PaymentMethods\Configs\Definitions\CardDefinition;
use WCPay\Session_Rate_Limiter;
use WCPay\WooPay\WooPay_Utilities;

/**
 * WC_Payments_Express_Checkout_Button_Display_Handler_Test class.
 */
class WC_Payments_Express_Checkout_Button_Display_Handler_Test extends WCPAY_UnitTestCase {
	private const ORDER_BILLING_EMAIL  = 'guest-6691@example.com';
	private const INSIDE_GRACE_PERIOD  = MINUTE_IN_SECONDS;
	private const OUTSIDE_GRACE_PERIOD = 15 * MINUTE_IN_SECONDS;
	private const WRONG_ORDER_KEY      = 'wc_order_not-this-one';

	/**
	 * Mock WC_Payments_API_Client.
	 *
	 * @var WC_Payments_API_Client|MockObject
	 */
	private $mock_api_client;

	/**
	 * WooPay Button Handler instance.
	 *
	 * @var WC_Payments_Express_Checkout_Button_Display_Handler
	 */
	private $express_checkout_button_display_handler;

	/**
	 * WooPay Button Handler mock instance.
	 *
	 * @var WC_Payments_WooPay_Button_Handler|MockObject
	 */
	private $mock_woopay_button_handler;

	/**
	 * WC_Payments_Account instance.
	 *
	 * @var WC_Payments_Account|MockObject
	 */
	private $mock_wcpay_account;

	/**
	 * Used to get the settings.
	 *
	 * @var WC_Payment_Gateway_WCPay|MockObject
	 */
	private $mock_wcpay_gateway;

	/**
	 * WooPay_Utilities instance.
	 *
	 * @var WooPay_Utilities|MockObject
	 */
	private $mock_woopay_utilities;

	/**
	 * Express Checkout ECE Button Handler mock instance.
	 *
	 * @var WC_Payments_Express_Checkout_Button_Handler|MockObject
	 */
	private $mock_express_checkout_ece_button_handler;

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
	 * Sets up things all tests need.
	 */
	public function set_up() {
		parent::set_up();

		$this->mock_api_client = $this->getMockBuilder( 'WC_Payments_API_Client' )
			->disableOriginalConstructor()
			->setMethods(
				[
					'is_server_connected',
				]
			)
			->getMock();
		$this->mock_api_client->expects( $this->any() )->method( 'is_server_connected' )->willReturn( true );
		$this->mock_wcpay_account = $this->createMock( WC_Payments_Account::class );

		$this->mock_wcpay_gateway = $this->make_wcpay_gateway();

		$this->mock_woopay_utilities = $this->getMockBuilder( WooPay_Utilities::class )
			->disableOriginalConstructor()
			->setMethods( [ 'is_country_available' ] )
			->getMock();

		$this->mock_express_checkout_helper = $this->getMockBuilder( WC_Payments_Express_Checkout_Button_Helper::class )
			->setConstructorArgs(
				[
					$this->mock_wcpay_gateway,
					$this->mock_wcpay_account,
				]
			)
			->setMethods(
				[
					'is_checkout',
					'should_show_express_checkout_button',
				]
			)
			->getMock();

		$this->mock_express_checkout_ajax_handler = $this->getMockBuilder( WC_Payments_Express_Checkout_Ajax_Handler::class )
			->setConstructorArgs(
				[
					$this->mock_express_checkout_helper,
				]
			)
			->getMock();

		$this->mock_woopay_button_handler = $this->getMockBuilder( WC_Payments_WooPay_Button_Handler::class )
			->setConstructorArgs(
				[
					$this->mock_wcpay_account,
					$this->mock_wcpay_gateway,
					$this->mock_woopay_utilities,
					$this->mock_express_checkout_helper,
				]
			)
			->setMethods(
				[
					'is_woopay_enabled',
					'should_show_woopay_button',
				]
			)
			->getMock();

		$this->mock_express_checkout_ece_button_handler = $this->getMockBuilder( WC_Payments_Express_Checkout_Button_Handler::class )
			->setConstructorArgs(
				[
					$this->mock_wcpay_account,
					$this->mock_wcpay_gateway,
					$this->mock_express_checkout_helper,
					$this->mock_express_checkout_ajax_handler,
				]
			)
			->setMethods(
				[
					'should_show_express_checkout_button',
				]
			)
			->getMock();

		$this->express_checkout_button_display_handler = new WC_Payments_Express_Checkout_Button_Display_Handler(
			$this->mock_wcpay_gateway,
			$this->mock_woopay_button_handler,
			$this->mock_express_checkout_ece_button_handler,
			$this->mock_express_checkout_ajax_handler,
			$this->mock_express_checkout_helper
		);
		$this->express_checkout_button_display_handler->init();

		add_filter(
			'woocommerce_available_payment_gateways',
			function () {
				return [ 'woocommerce_payments' => $this->mock_wcpay_gateway ];
			}
		);
	}

	/**
	 * Clean up after each test.
	 *
	 * @return void
	 */
	public function tear_down() {
		remove_all_filters( 'woocommerce_available_payment_gateways' );
		wp_set_current_user( 0 );

		parent::tear_down();
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
		$mock_payment_method           = $this->createMock( UPE_Payment_Method::class );

		return new WC_Payment_Gateway_WCPay(
			$this->mock_api_client,
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

	public function test_display_express_checkout_buttons_all_enabled() {
		$this->mock_woopay_button_handler
			->method( 'should_show_woopay_button' )
			->willReturn( true );

		$this->mock_express_checkout_helper
			->method( 'should_show_express_checkout_button' )
			->willReturn( true );

		$this->mock_express_checkout_helper
			->method( 'is_checkout' )
			->willReturn( false );

		ob_start();
		$this->express_checkout_button_display_handler->display_express_checkout_buttons();

		$this->assertStringContainsString( 'wcpay-woopay-button', ob_get_contents() );
		$this->assertStringContainsString( 'wcpay-express-checkout-element', ob_get_contents() );
		$this->assertStringNotContainsString( 'wcpay-express-checkout-button-separator', ob_get_contents() );
		ob_end_clean();
	}

	public function test_display_express_checkout_buttons_all_disabled() {
		$this->mock_woopay_button_handler
			->method( 'should_show_woopay_button' )
			->willReturn( false );

		$this->mock_express_checkout_helper
			->method( 'is_checkout' )
			->willReturn( false );

		ob_start();
		$this->express_checkout_button_display_handler->display_express_checkout_buttons();

		$this->assertStringNotContainsString( 'wcpay-express-checkout-wrapper', ob_get_contents() );
		ob_end_clean();
	}

	public function test_display_express_checkout_buttons_on_cart_block_page_with_cart() {
		WC()->cart->add_to_cart( WC_Helper_Product::create_simple_product()->get_id() );

		try {
			$output = $this->render_express_checkout_buttons_on_cart_block_page();
		} finally {
			WC()->cart->empty_cart();
		}

		$this->assertStringContainsString( 'wcpay-woopay-button', $output );
	}

	public function test_display_express_checkout_buttons_on_cart_block_page_without_cart() {
		// The Add to Cart with Options block fires the display hook when the REST API renders a page with a Cart block, and REST requests have no cart.
		$cart      = WC()->cart;
		WC()->cart = null;

		try {
			$output = $this->render_express_checkout_buttons_on_cart_block_page();
		} finally {
			WC()->cart = $cart;
		}

		$this->assertSame( '', $output );
	}

	/**
	 * Renders the express checkout buttons on a Cart block page through the real WooPay handler and helper,
	 * with WooPay and Apple Pay / Google Pay enabled on the cart page.
	 *
	 * @return string The rendered markup.
	 */
	private function render_express_checkout_buttons_on_cart_block_page(): string {
		$this->mock_wcpay_account->method( 'is_stripe_connected' )->willReturn( true );
		$this->mock_woopay_utilities->method( 'is_country_available' )->willReturn( true );
		$this->mock_wcpay_gateway->update_option( 'express_checkout_cart_methods', [ 'payment_request', 'woopay' ] );
		$helper = $this->getMockBuilder( WC_Payments_Express_Checkout_Button_Helper::class )
			->setConstructorArgs( [ $this->mock_wcpay_gateway, $this->mock_wcpay_account ] )
			->onlyMethods( [ 'get_enabled_express_checkout_methods_for_context' ] )
			->getMock();
		$helper->method( 'get_enabled_express_checkout_methods_for_context' )->willReturn( [ 'payment_request' ] );
		$woopay_button_handler = $this->getMockBuilder( WC_Payments_WooPay_Button_Handler::class )
			->setConstructorArgs( [ $this->mock_wcpay_account, $this->mock_wcpay_gateway, $this->mock_woopay_utilities, $helper ] )
			->onlyMethods( [ 'is_woopay_enabled' ] )
			->getMock();
		$woopay_button_handler->method( 'is_woopay_enabled' )->willReturn( true );
		$display_handler = new WC_Payments_Express_Checkout_Button_Display_Handler(
			$this->mock_wcpay_gateway,
			$woopay_button_handler,
			$this->mock_express_checkout_ece_button_handler,
			$this->mock_express_checkout_ajax_handler,
			$helper
		);
		WC_Payments::mode()->dev();
		$GLOBALS['post'] = $this->factory->post->create_and_get( [ 'post_content' => '<!-- wp:woocommerce/cart /-->' ] ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		ob_start();
		try {
			$display_handler->display_express_checkout_buttons();
			return (string) ob_get_contents();
		} finally {
			ob_end_clean();
			unset( $GLOBALS['post'] );
			WC_Payments::mode()->live();
		}
	}

	public function test_display_express_checkout_buttons_only_woopay() {
		$this->mock_woopay_button_handler
			->method( 'should_show_woopay_button' )
			->willReturn( true );

		$this->mock_express_checkout_helper
			->method( 'is_checkout' )
			->willReturn( false );

		ob_start();
		$this->express_checkout_button_display_handler->display_express_checkout_buttons();

		$this->assertStringContainsString( 'wcpay-woopay-button', ob_get_contents() );
		$this->assertStringNotContainsString( 'wcpay-express-checkout-button-separator', ob_get_contents() );
		ob_end_clean();
	}

	public function test_pay_for_order_config_gives_a_new_guest_order_its_billing_email() {
		$this->skip_without_woocommerce_email_verification();

		// WooCommerce shows a guest order's pay page without asking for the email in its first minutes.
		$order = $this->create_guest_order( self::INSIDE_GRACE_PERIOD );

		$this->assertSame( 'guest-6691@example.com', $this->get_pay_for_order_js_config( $order )['billing_email'] );
	}

	public function test_pay_for_order_config_gives_the_billing_email_when_the_merchant_turns_email_verification_off() {
		$this->skip_without_woocommerce_email_verification();

		$order = $this->create_guest_order( self::OUTSIDE_GRACE_PERIOD );
		add_filter( 'woocommerce_order_email_verification_required', '__return_false' );

		try {
			$billing_email = $this->get_pay_for_order_js_config( $order )['billing_email'];
		} finally {
			remove_filter( 'woocommerce_order_email_verification_required', '__return_false' );
		}

		$this->assertSame( 'guest-6691@example.com', $billing_email );
	}

	public function test_pay_for_order_config_keeps_the_billing_email_back_while_woocommerce_asks_the_visitor_for_it() {
		$order = $this->create_guest_order( self::OUTSIDE_GRACE_PERIOD );

		$this->assertSame( '', $this->get_pay_for_order_js_config( $order )['billing_email'] );
	}

	public function test_pay_for_order_config_gives_the_billing_email_to_a_store_manager() {
		$order = $this->create_guest_order( self::OUTSIDE_GRACE_PERIOD );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'shop_manager' ] ) );

		$this->assertSame( 'guest-6691@example.com', $this->get_pay_for_order_js_config( $order )['billing_email'] );
	}

	public function test_pay_for_order_config_gives_the_billing_email_to_the_customer_who_owns_the_order() {
		$owner_id = self::factory()->user->create( [ 'role' => 'customer' ] );
		$order    = $this->create_guest_order( self::OUTSIDE_GRACE_PERIOD );
		$order->set_customer_id( $owner_id );
		$order->save();
		wp_set_current_user( $owner_id );

		$this->assertSame( 'guest-6691@example.com', $this->get_pay_for_order_js_config( $order )['billing_email'] );
	}

	public function test_pay_for_order_config_is_not_added_for_a_wrong_order_key() {
		// Inside the grace period WooCommerce would not ask for the email, but only after the key passes.
		$order = $this->create_guest_order( self::INSIDE_GRACE_PERIOD );

		$js_config = $this->get_js_config_for_payment_link( $order->get_id(), self::WRONG_ORDER_KEY );

		$this->assertArrayNotHasKey( 'billing_email', $js_config );
		$this->assertArrayNotHasKey( 'order_id', $js_config );
	}

	/**
	 * @dataProvider provide_unusable_order_keys
	 *
	 * @param callable $make_key Returns the key the link carries, given the order.
	 */
	public function test_pay_for_order_config_is_not_added_without_the_exact_order_key( callable $make_key ) {
		// Inside the grace period, so only the key check can keep the email back.
		$order = $this->create_guest_order( self::INSIDE_GRACE_PERIOD );

		$js_config = $this->get_js_config_for_payment_link( $order->get_id(), $make_key( $order ) );

		$this->assertArrayNotHasKey( 'billing_email', $js_config );
		$this->assertArrayNotHasKey( 'order_id', $js_config );
	}

	public function provide_unusable_order_keys(): array {
		return [
			'empty key'               => [ fn() => '' ],
			'array key'               => [ fn() => [ 'x' ] ],
			'key in a different case' => [ fn( WC_Order $order ) => strtoupper( $order->get_order_key() ) ],
		];
	}

	public function test_pay_for_order_config_is_not_added_on_the_add_payment_method_page() {
		// Inside the grace period, so the pay page itself would give the email.
		$order = $this->create_guest_order( self::INSIDE_GRACE_PERIOD );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'customer' ] ) );
		$this->open_payment_link_on_add_payment_method_page( $order );

		$js_config = $this->get_pay_for_order_js_config( $order );

		$this->assertArrayNotHasKey( 'billing_email', $js_config );
		$this->assertArrayNotHasKey( 'order_id', $js_config );
	}

	public function test_pay_for_order_config_is_not_added_for_a_deleted_order() {
		$order    = $this->create_guest_order( self::INSIDE_GRACE_PERIOD );
		$order_id = $order->get_id();
		$key      = $order->get_order_key();
		$order->delete( true );

		$js_config = $this->get_js_config_for_payment_link( $order_id, $key );

		$this->assertArrayNotHasKey( 'order_id', $js_config );
	}

	public function test_pay_for_order_config_is_not_added_for_a_refund() {
		$order = WC_Helper_Order::create_order( 0 );
		$order->set_status( 'completed' );
		$order->save();
		$refund = wc_create_refund(
			[
				'order_id' => $order->get_id(),
				'amount'   => 1,
			]
		);

		$js_config = $this->get_js_config_for_payment_link( $refund->get_id(), self::WRONG_ORDER_KEY );

		$this->assertArrayNotHasKey( 'order_id', $js_config );
	}

	public function test_pay_for_order_config_keeps_the_billing_email_back_for_an_order_that_needs_no_payment() {
		$order = $this->create_guest_order( self::INSIDE_GRACE_PERIOD );
		$order->set_status( 'completed' );
		$order->save();

		$this->assertSame( '', $this->get_pay_for_order_js_config( $order )['billing_email'] );
	}

	public function test_pay_for_order_config_keeps_the_billing_email_back_when_the_woocommerce_check_fails() {
		$this->skip_without_woocommerce_email_verification();

		$order = $this->create_guest_order( self::OUTSIDE_GRACE_PERIOD );
		$throw = function () {
			throw new Exception( 'A third-party callback failed.' );
		};
		add_filter( 'woocommerce_order_email_verification_required', $throw );

		try {
			$billing_email = $this->get_pay_for_order_js_config( $order )['billing_email'];
		} finally {
			remove_filter( 'woocommerce_order_email_verification_required', $throw );
		}

		$this->assertSame( '', $billing_email );
	}

	/**
	 * Skips a test that needs WooCommerce's pay-page email verification check, public from WooCommerce 8.6.
	 */
	private function skip_without_woocommerce_email_verification(): void {
		if ( ! is_callable( [ 'Automattic\WooCommerce\Internal\Utilities\Users', 'should_user_verify_order_email' ] ) ) {
			$this->markTestSkipped( 'WooCommerce has no callable pay-page email verification check before 8.6.' );
		}
	}

	/**
	 * Creates a pending guest order with a billing email.
	 *
	 * @param int $age_in_seconds How long ago the order was created.
	 *
	 * @return WC_Order
	 */
	private function create_guest_order( int $age_in_seconds ): WC_Order {
		$order = WC_Helper_Order::create_order( 0 );
		$order->set_billing_email( self::ORDER_BILLING_EMAIL );
		$order->set_status( 'pending' );
		$order->set_date_created( time() - $age_in_seconds );
		$order->save();

		return $order;
	}

	/**
	 * Returns the JS config the order's pay page gets, for a browser session with no email.
	 *
	 * @param WC_Order $order The order whose payment link is opened.
	 *
	 * @return array
	 */
	private function get_pay_for_order_js_config( WC_Order $order ): array {
		return $this->get_js_config_for_payment_link( $order->get_id(), $order->get_order_key() );
	}

	/**
	 * Returns the JS config a payment link's page gets, for a browser session with no email.
	 *
	 * @param int          $order_id The order ID in the payment link.
	 * @param string|array $key      The order key in the payment link; `?key[]=` arrives as an array.
	 *
	 * @return array
	 */
	private function get_js_config_for_payment_link( int $order_id, $key ): array {
		global $wp, $wp_filter;

		$original_hook      = isset( $wp_filter['wcpay_payment_fields_js_config'] ) ? clone $wp_filter['wcpay_payment_fields_js_config'] : null;
		$original_customer  = WC()->session->get( 'customer' );
		$original_get       = $_GET;
		$original_query_var = $wp->query_vars['order-pay'] ?? null;

		$wp->query_vars['order-pay'] = $order_id;
		$_GET['pay_for_order']       = 'true';
		$_GET['key']                 = $key;
		WC()->session->set( 'customer', [ 'email' => '' ] );

		try {
			$this->express_checkout_button_display_handler->add_pay_for_order_params_to_js_config();

			/** This filter is documented in includes/class-wc-payments-checkout.php */
			return apply_filters( 'wcpay_payment_fields_js_config', [] );
		} finally {
			// Drop only the callback this test added; other suites rely on the ones registered at bootstrap.
			if ( null === $original_hook ) {
				unset( $wp_filter['wcpay_payment_fields_js_config'] );
			} else {
				$wp_filter['wcpay_payment_fields_js_config'] = $original_hook; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			}
			$_GET = $original_get;
			if ( null === $original_query_var ) {
				unset( $wp->query_vars['order-pay'] );
			} else {
				$wp->query_vars['order-pay'] = $original_query_var;
			}
			WC()->session->set( 'customer', $original_customer );
		}
	}
}
