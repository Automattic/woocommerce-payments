<?php
/**
 * These tests make assertions against class WC_Payments_Express_Checkout_Button_Display_Handler.
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Duplicate_Payment_Prevention_Service;
use WCPay\Duplicates_Detection_Service;
use WCPay\Payment_Methods\CC_Payment_Method;
use WCPay\Session_Rate_Limiter;
use WCPay\WooPay\WooPay_Utilities;

/**
 * WC_Payments_WooPay_Button_Handler_Test class.
 */
class WC_Payments_Express_Checkout_Button_Display_Handler_Test extends WCPAY_UnitTestCase {
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
	 * Payment Request Button Handler mock instance.
	 *
	 * @var WC_Payments_Payment_Request_Button_Handler|MockObject
	 */
	private $mock_payment_request_button_handler;

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

		$this->mock_payment_request_button_handler = $this->getMockBuilder( WC_Payments_Payment_Request_Button_Handler::class )
			->setConstructorArgs(
				[
					$this->mock_wcpay_account,
					$this->mock_wcpay_gateway,
					$this->mock_express_checkout_helper,
				]
			)
			->setMethods(
				[
					'should_show_payment_request_button',
					'is_checkout',
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
			$this->mock_payment_request_button_handler,
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
		$this->assertStringNotContainsString( 'wcpay-payment-request-button-separator', ob_get_contents() );
		ob_end_clean();
	}

	public function test_display_express_checkout_buttons_all_disabled() {
		$this->mock_woopay_button_handler
			->method( 'should_show_woopay_button' )
			->willReturn( false );

		$this->mock_payment_request_button_handler
			->method( 'should_show_payment_request_button' )
			->willReturn( false );

		$this->mock_express_checkout_helper
			->method( 'is_checkout' )
			->willReturn( false );

		ob_start();
		$this->express_checkout_button_display_handler->display_express_checkout_buttons();

		$this->assertStringNotContainsString( 'wcpay-payment-request-wrapper', ob_get_contents() );
		ob_end_clean();
	}

	public function test_display_express_checkout_buttons_only_woopay() {
		$this->mock_woopay_button_handler
			->method( 'should_show_woopay_button' )
			->willReturn( true );

		$this->mock_payment_request_button_handler
			->method( 'should_show_payment_request_button' )
			->willReturn( false );

		$this->mock_express_checkout_helper
			->method( 'is_checkout' )
			->willReturn( false );

		ob_start();
		$this->express_checkout_button_display_handler->display_express_checkout_buttons();

		$this->assertStringContainsString( 'wcpay-woopay-button', ob_get_contents() );
		$this->assertStringNotContainsString( 'wcpay-payment-request-button-separator', ob_get_contents() );
		ob_end_clean();
	}

	public function test_display_express_checkout_buttons_only_payment_request() {
		$this->mock_woopay_button_handler
			->method( 'should_show_woopay_button' )
			->willReturn( false );

		$this->mock_payment_request_button_handler
			->method( 'should_show_payment_request_button' )
			->willReturn( true );

		$this->mock_express_checkout_helper
			->method( 'is_checkout' )
			->willReturn( true );

		ob_start();
		$this->express_checkout_button_display_handler->display_express_checkout_buttons();

		$this->assertStringContainsString( 'wcpay-express-checkout-button-separator', ob_get_contents() );
		$this->assertStringContainsString( 'display:none;', ob_get_contents() );
		ob_end_clean();
	}
}
