<?php
/**
 * These tests make assertions against class WC_Payments_Platform_Checkout_Button_Handler.
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\Session_Rate_Limiter;

/**
 * WC_Payments_Platform_Checkout_Button_Handler_Test class.
 */
class WC_Payments_Platform_Checkout_Button_Handler_Test extends WCPAY_UnitTestCase {
	/**
	 * Mock WC_Payments_API_Client.
	 *
	 * @var WC_Payments_API_Client
	 */
	private $mock_api_client;

	/**
	 * Payment request instance.
	 *
	 * @var WC_Payments_Platform_Checkout_Button_Handler
	 */
	private $pr;

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
					'get_setup_intent',
					'get_payment_method',
					'refund_charge',
				]
			)
			->getMock();
		$this->mock_api_client->expects( $this->any() )->method( 'is_server_connected' )->willReturn( true );
		$this->mock_wcpay_account = $this->createMock( WC_Payments_Account::class );

		$this->mock_wcpay_gateway = $this->make_wcpay_gateway();

		$this->pr = new WC_Payments_Payment_Request_Button_Handler( $this->mock_wcpay_account, $this->mock_wcpay_gateway );

		$simple_product = WC_Helper_Product::create_simple_product();

		WC()->session->init();
		WC()->cart->add_to_cart( $simple_product->get_id(), 1 );
		WC()->cart->calculate_totals();
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

		return new WC_Payment_Gateway_WCPay(
			$this->mock_api_client,
			$this->mock_wcpay_account,
			$mock_customer_service,
			$mock_token_service,
			$mock_action_scheduler_service,
			$mock_rate_limiter,
			$mock_order_service
		);
	}
}
