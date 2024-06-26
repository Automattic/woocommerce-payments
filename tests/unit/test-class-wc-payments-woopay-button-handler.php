<?php
/**
 * These tests make assertions against class WC_Payments_WooPay_Button_Handler.
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\Duplicate_Payment_Prevention_Service;
use WCPay\Duplicates_Detection_Service;
use WCPay\Payment_Methods\CC_Payment_Method;
use WCPay\Session_Rate_Limiter;
use WCPay\WooPay\WooPay_Utilities;

/**
 * WC_Payments_WooPay_Button_Handler_Test class.
 */
class WC_Payments_WooPay_Button_Handler_Test extends WCPAY_UnitTestCase {
	/**
	 * Mock WC_Payments_API_Client.
	 *
	 * @var WC_Payments_API_Client
	 */
	private $mock_api_client;

	/**
	 * Payment request mock instance.
	 *
	 * @var Mock_WC_Payments_WooPay_Button_Handler
	 */
	private $mock_pr;

	/**
	 * WC_Payments_Account instance.
	 *
	 * @var WC_Payments_Account
	 */
	private $mock_wcpay_account;

	/**
	 * Used to get the settings.
	 *
	 * @var WC_Payment_Gateway_WCPay
	 */
	private $mock_wcpay_gateway;

	/**
	 * WooPay_Utilities instance.
	 *
	 * @var WooPay_Utilities
	 */
	private $mock_woopay_utilities;

	/**
	 * Express Checkout Helper instance.
	 *
	 * @var WC_Payments_Express_Checkout_Button_Helper
	 */
	private $mock_express_checkout_helper;

	/**
	 * Sets up things all tests need.
	 */
	public function set_up() {
		parent::set_up();

		$this->mock_api_client = $this->getMockBuilder( 'WC_Payments_API_Client' )
			->disableOriginalConstructor()
			->setMethods(
				[
					'get_account_data',
					'is_server_connected',
					'capture_intention',
					'cancel_intention',
					'get_intent',
					'create_and_confirm_setup_intent',
					'get_payment_method',
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
					'is_cart',
					'is_checkout',
					'is_product',
					'is_available_at',
				]
			)
			->getMock();

		$this->mock_pr = $this->getMockBuilder( WC_Payments_WooPay_Button_Handler::class )
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
				]
			)
			->getMock();

		$simple_product = WC_Helper_Product::create_simple_product();

		WC()->session->init();
		WC()->cart->add_to_cart( $simple_product->get_id(), 1 );
		WC()->cart->calculate_totals();

		add_filter(
			'woocommerce_available_payment_gateways',
			function () {
				return [ 'woocommerce_payments' => $this->mock_wcpay_gateway ];
			}
		);
	}

	public function tear_down() {
		parent::tear_down();
		WC()->cart->empty_cart();
		WC()->session->cleanup_sessions();
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
			$this->createMock( WC_Payments_Fraud_Service::class ),
			$this->createMock( Duplicates_Detection_Service::class )
		);
	}

	public function test_should_show_woopay_button_all_good_at_cart() {
		$this->mock_woopay_utilities
			->expects( $this->once() )
			->method( 'is_country_available' )
			->willReturn( true );

		$this->mock_pr
			->method( 'is_woopay_enabled' )
			->willReturn( true );

		$this->mock_express_checkout_helper
			->method( 'is_cart' )
			->willReturn( true );

		$this->mock_express_checkout_helper
			->expects( $this->once() )
			->method( 'is_available_at' )
			->with( 'cart' )
			->willReturn( true );

		$this->assertTrue( $this->mock_pr->should_show_woopay_button() );
	}

	public function test_should_show_woopay_button_not_available_at_cart() {
		$this->mock_pr
			->method( 'is_woopay_enabled' )
			->willReturn( true );

		$this->mock_woopay_utilities
			->expects( $this->once() )
			->method( 'is_country_available' )
			->willReturn( true );

		$this->mock_express_checkout_helper
			->method( 'is_cart' )
			->willReturn( true );

		$this->mock_express_checkout_helper
			->expects( $this->once() )
			->method( 'is_available_at' )
			->with( 'cart' )
			->willReturn( false );

		$this->assertFalse( $this->mock_pr->should_show_woopay_button() );
	}

	public function test_should_show_woopay_button_all_good_at_checkout() {
		add_filter( 'wcpay_platform_checkout_button_are_cart_items_supported', '__return_true' );

		$this->mock_pr
			->method( 'is_woopay_enabled' )
			->willReturn( true );

		$this->mock_woopay_utilities
			->expects( $this->once() )
			->method( 'is_country_available' )
			->willReturn( true );

		$this->mock_express_checkout_helper
			->method( 'is_checkout' )
			->willReturn( true );

		$this->mock_express_checkout_helper
			->expects( $this->once() )
			->method( 'is_available_at' )
			->with( 'checkout' )
			->willReturn( true );

		$this->assertTrue( $this->mock_pr->should_show_woopay_button() );
	}

	public function test_should_only_load_common_config_script() {
		// Ensure we are on the cart page.
		$this->mock_express_checkout_helper
			->method( 'is_cart' )
			->willReturn( true );
		// Ensure the express button is not available in the cart page.
		$this->mock_express_checkout_helper
			->expects( $this->any() )
			->method( 'is_available_at' )
			->with( 'cart' )
			->willReturn( false );

		WC_Payments::set_express_checkout_helper( $this->mock_express_checkout_helper );

		// Since the express button is not available in the cart page, the
		// WCPAY_WOOPAY_EXPRESS_BUTTON handle should not be enqueued.
		$this->mock_pr->scripts();

		// Since the express button is not available in the cart page, the
		// WCPAY_WOOPAY_COMMON_CONFIG handle should be enqueued.
		$is_direct_checkout_enabled = true;
		WC_Payments::maybe_enqueue_woopay_common_config_script( $is_direct_checkout_enabled );
		wp_enqueue_scripts();

		$this->assertFalse( wp_script_is( 'WCPAY_WOOPAY_EXPRESS_BUTTON', 'enqueued' ) );
		$this->assertTrue( wp_script_is( 'WCPAY_WOOPAY_COMMON_CONFIG', 'enqueued' ) );

		// Cleanup.
		wp_dequeue_script( 'WCPAY_WOOPAY_COMMON_CONFIG' );
		wp_deregister_script( 'WCPAY_WOOPAY_COMMON_CONFIG' );
	}

	public function test_should_not_load_common_config_script() {
		// Ensure WooPay is enabled.
		$this->mock_pr
			->expects( $this->any() )
			->method( 'is_woopay_enabled' )
			->willReturn( true );
		// Ensure WooPay is available in the given country.
		$this->mock_woopay_utilities
			->expects( $this->once() )
			->method( 'is_country_available' )
			->willReturn( true );
		// Ensure we are on the cart page.
		$this->mock_express_checkout_helper
			->expects( $this->any() )
			->method( 'is_cart' )
			->willReturn( true );
		// Ensure the express button is available in the cart page.
		$this->mock_express_checkout_helper
			->expects( $this->any() )
			->method( 'is_available_at' )
			->with( 'cart', WC_Payments_WooPay_Button_Handler::BUTTON_LOCATIONS )
			->willReturn( true );

		WC_Payments::set_express_checkout_helper( $this->mock_express_checkout_helper );

		// Since the express button is available in the cart page, the
		// WCPAY_WOOPAY_EXPRESS_BUTTON handle should be enqueued.
		$this->mock_pr->scripts();

		// Since the express button is available in the cart page, the
		// WCPAY_WOOPAY_COMMON_CONFIG handle should not be enqueued.
		$is_direct_checkout_enabled = true;
		WC_Payments::maybe_enqueue_woopay_common_config_script( $is_direct_checkout_enabled );
		wp_enqueue_scripts();

		$this->assertTrue( wp_script_is( 'WCPAY_WOOPAY_EXPRESS_BUTTON', 'enqueued' ) );
		$this->assertFalse( wp_script_is( 'WCPAY_WOOPAY_COMMON_CONFIG', 'enqueued' ) );
	}

	public function test_should_show_woopay_button_unsupported_product_at_checkout() {
		add_filter( 'wcpay_platform_checkout_button_are_cart_items_supported', '__return_false' );

		$this->mock_pr
			->method( 'is_woopay_enabled' )
			->willReturn( true );

		$this->mock_woopay_utilities
			->expects( $this->once() )
			->method( 'is_country_available' )
			->willReturn( true );

		$this->mock_express_checkout_helper
			->method( 'is_checkout' )
			->willReturn( true );

		$this->mock_express_checkout_helper
			->expects( $this->once() )
			->method( 'is_available_at' )
			->with( 'checkout' )
			->willReturn( true );

		$this->assertFalse( $this->mock_pr->should_show_woopay_button() );
	}

	public function test_should_show_woopay_button_all_good_at_product() {
		add_filter( 'wcpay_woopay_button_is_product_supported', '__return_true' );

		$this->mock_pr
			->method( 'is_woopay_enabled' )
			->willReturn( true );

		$this->mock_woopay_utilities
			->expects( $this->once() )
			->method( 'is_country_available' )
			->willReturn( true );

		$this->mock_express_checkout_helper
			->method( 'is_product' )
			->willReturn( true );

		$this->mock_express_checkout_helper
			->expects( $this->once() )
			->method( 'is_available_at' )
			->with( 'product' )
			->willReturn( true );

		$this->assertTrue( $this->mock_pr->should_show_woopay_button() );
	}

	public function test_should_show_woopay_button_unsupported_product_at_product() {
		add_filter( 'wcpay_woopay_button_is_product_supported', '__return_false' );

		$this->mock_pr
			->method( 'is_woopay_enabled' )
			->willReturn( true );

		$this->mock_woopay_utilities
			->expects( $this->once() )
			->method( 'is_country_available' )
			->willReturn( true );

		$this->mock_express_checkout_helper
			->method( 'is_product' )
			->willReturn( true );

		$this->mock_express_checkout_helper
			->expects( $this->once() )
			->method( 'is_available_at' )
			->with( 'product' )
			->willReturn( true );

		$this->assertFalse( $this->mock_pr->should_show_woopay_button() );
	}

	public function test_should_show_woopay_button_not_available_at_product() {
		add_filter( 'wcpay_woopay_button_is_product_supported', '__return_true' );

		$this->mock_pr
			->method( 'is_woopay_enabled' )
			->willReturn( true );

		$this->mock_woopay_utilities
			->expects( $this->once() )
			->method( 'is_country_available' )
			->willReturn( true );

		$this->mock_express_checkout_helper
			->method( 'is_product' )
			->willReturn( true );

		$this->mock_express_checkout_helper
			->expects( $this->once() )
			->method( 'is_available_at' )
			->with( 'product' )
			->willReturn( false );

		$this->assertFalse( $this->mock_pr->should_show_woopay_button() );
	}

	public function test_should_show_woopay_button_page_not_supported() {
		$this->mock_woopay_utilities
			->expects( $this->never() )
			->method( 'is_country_available' );

		$this->mock_express_checkout_helper
			->method( 'is_product' )
			->willReturn( false );

		$this->mock_express_checkout_helper
			->method( 'is_cart' )
			->willReturn( false );

		$this->mock_express_checkout_helper
			->method( 'is_checkout' )
			->willReturn( false );

		$this->mock_express_checkout_helper
			->expects( $this->never() )
			->method( 'is_available_at' );

		$this->assertFalse( $this->mock_pr->should_show_woopay_button() );
	}

	public function test_should_show_woopay_button_country_not_supported() {
		$this->mock_pr
			->method( 'is_woopay_enabled' )
			->willReturn( true );

		$this->mock_woopay_utilities
			->expects( $this->once() )
			->method( 'is_country_available' )
			->willReturn( false );

		$this->mock_express_checkout_helper
			->method( 'is_product' )
			->willReturn( true );

		$this->mock_express_checkout_helper
			->method( 'is_cart' )
			->willReturn( true );

		$this->mock_express_checkout_helper
			->method( 'is_checkout' )
			->willReturn( true );

		$this->mock_express_checkout_helper
			->expects( $this->never() )
			->method( 'is_available_at' );

		$this->assertFalse( $this->mock_pr->should_show_woopay_button() );
	}

	public function test_should_show_woopay_button_unavailable_wcpay() {
		add_filter( 'woocommerce_available_payment_gateways', '__return_empty_array' );

		$this->mock_woopay_utilities
			->expects( $this->never() )
			->method( 'is_country_available' );

		$this->mock_express_checkout_helper
			->expects( $this->never() )
			->method( 'is_product' );

		$this->assertFalse( $this->mock_pr->should_show_woopay_button() );
	}

	public function test_should_show_woopay_button_woopay_not_enabled() {
		$this->mock_pr
			->method( 'is_woopay_enabled' )
			->willReturn( false );

		$this->mock_woopay_utilities
			->expects( $this->never() )
			->method( 'is_country_available' );

		$this->mock_express_checkout_helper
			->expects( $this->never() )
			->method( 'is_cart' );

		$this->mock_express_checkout_helper
			->expects( $this->never() )
			->method( 'is_available_at' )
			->with( 'cart' );

		$this->assertFalse( $this->mock_pr->should_show_woopay_button() );
	}

	public function test_get_button_settings() {
		$this->mock_express_checkout_helper
			->method( 'is_product' )
			->willReturn( true );

		$this->assertEquals(
			[
				'type'    => 'buy',
				'theme'   => 'dark',
				'height'  => '48',
				'size'    => 'medium',
				'context' => 'product',
			],
			$this->mock_pr->get_button_settings()
		);
	}
}
