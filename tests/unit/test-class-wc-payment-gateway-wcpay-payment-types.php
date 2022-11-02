<?php
/**
 * Class WC_Payment_Gateway_WCPay_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\Session_Rate_Limiter;
use WCPay\Fraud_Prevention\Fraud_Prevention_Service;

/**
 * WC_Payment_Gateway_WCPay unit tests.
 */
class WC_Payment_Gateway_WCPay_Payment_Types extends WCPAY_UnitTestCase {
	/**
	 * System under test.
	 *
	 * @var WC_Payment_Gateway_WCPay
	 */
	private $mock_wcpay_gateway;

	/**
	 * Mock WC_Payments_Customer_Service.
	 *
	 * @var WC_Payments_Customer_Service|PHPUnit_Framework_MockObject_MockObject
	 */
	private $mock_customer_service;

	/**
	 * Mock WC_Payments_Token_Service.
	 *
	 * @var WC_Payments_Token_Service|PHPUnit_Framework_MockObject_MockObject
	 */
	private $mock_token_service;

	/**
	 * Mock WC_Payments_API_Client.
	 *
	 * @var WC_Payments_API_Client|PHPUnit_Framework_MockObject_MockObject
	 */
	private $mock_api_client;

	/**
	 * Mock WC_Payments_Action_Scheduler_Service.
	 *
	 * @var WC_Payments_Action_Scheduler_Service|PHPUnit_Framework_MockObject_MockObject
	 */
	private $mock_action_scheduler_service;

	/**
	 * Mock Session_Rate_Limiter.
	 *
	 * @var Session_Rate_Limiter|PHPUnit_Framework_MockObject_MockObject
	 */
	private $mock_rate_limiter;


	/**
	 * Mock WC_Payments_Account.
	 *
	 * @var WC_Payments_Account|PHPUnit_Framework_MockObject_MockObject
	 */
	private $mock_wcpay_account;

	/**
	 * Token to be used during the tests.
	 *
	 * @var WC_Payment_Token
	 */
	private $token;

	const USER_ID           = 1;
	const PAYMENT_METHOD_ID = 'pm_mock';

	/**
	 * Pre-test setup
	 */
	public function set_up() {
		parent::set_up();

		// Arrange: Mock WC_Payments_API_Client so we can configure the
		// return value of create_and_confirm_intention().
		// Note that we cannot use createStub here since it's not defined in PHPUnit 6.5.
		$this->mock_api_client = $this->getMockBuilder( 'WC_Payments_API_Client' )
			->disableOriginalConstructor()
			->setMethods( [ 'create_and_confirm_intention', 'get_payment_method', 'request_with_level3_data', 'is_server_connected' ] )
			->getMock();

		// Arrange: Mock WC_Payments_Account instance to use later.
		$this->mock_wcpay_account = $this->createMock( WC_Payments_Account::class );

		// Arrange: Mock WC_Payments_Customer_Service so its methods aren't called directly.
		$this->mock_customer_service = $this->getMockBuilder( 'WC_Payments_Customer_Service' )
			->disableOriginalConstructor()
			->getMock();

		// Arrange: Mock WC_Payments_Customer_Service so its methods aren't called directly.
		$this->mock_token_service = $this->getMockBuilder( 'WC_Payments_Token_Service' )
			->disableOriginalConstructor()
			->getMock();

		// Arrange: Mock WC_Payments_Action_Scheduler_Service so its methods aren't called directly.
		$this->mock_action_scheduler_service = $this->getMockBuilder( 'WC_Payments_Action_Scheduler_Service' )
			->disableOriginalConstructor()
			->getMock();

		$this->token = WC_Helper_Token::create_token( self::PAYMENT_METHOD_ID, self::USER_ID );
		$this->mock_token_service
			->expects( $this->any() )
			->method( 'add_payment_method_to_user' )
			->willReturn( $this->token );

		$this->mock_rate_limiter = $this->createMock( Session_Rate_Limiter::class );

		$this->mock_order_service = $this->createMock( WC_Payments_Order_Service::class );

		$payments_settings = new WC_Payments_Gateway_WCPay_Settings( $this->mock_wcpay_account );

		// Arrange: Mock WC_Payment_Gateway_WCPay so that some of its methods can be
		// mocked, and their return values can be used for testing.
		$this->mock_wcpay_gateway = $this->getMockBuilder( 'WC_Payment_Gateway_WCPay' )
			->setConstructorArgs(
				[
					$this->mock_api_client,
					$this->mock_wcpay_account,
					$this->mock_customer_service,
					$this->mock_token_service,
					$this->mock_action_scheduler_service,
					$this->mock_rate_limiter,
					$this->mock_order_service,
					$payments_settings,
				]
			)
			->setMethods(
				[
					'get_return_url',
					'mark_payment_complete_for_order',
					'get_level3_data_from_order', // To avoid needing to mock the order items.
				]
			)
			->getMock();

		// Arrange: Define a $_POST array which includes the payment method,
		// so that get_payment_method_from_request() does not throw error.
		$_POST = [
			'wcpay-payment-method' => 'pm_mock',
		];
	}

	/**
	 * Cleanup after all tests.
	 */
	public static function tear_down_after_class() {
		WC_Subscriptions::set_wcs_order_contains_subscription( null );
		WC_Subscriptions::set_wcs_get_subscriptions_for_order( null );

		parent::tear_down_after_class();
	}

	private function mock_wcs_order_contains_subscription( $value ) {
		WC_Subscriptions::set_wcs_order_contains_subscription(
			function ( $order ) use ( $value ) {
				return $value;
			}
		);
	}

	public function test_single_payment() {
		$order = WC_Helper_Order::create_order();
		$this->mock_wcs_order_contains_subscription( false );

		$intent = WC_Helper_Intention::create_intention();
		$this->mock_api_client
			->expects( $this->once() )
			->method( 'create_and_confirm_intention' )
			->with(
				$this->anything(),
				$this->anything(),
				$this->anything(),
				$this->anything(),
				$this->anything(),
				$this->anything(),
				$this->anything(),
				// Metadata argument.
				$this->callback(
					function( $metadata ) use ( $order ) {
						$this->assertEquals( $metadata['payment_type'], 'single' );
						$this->assertEquals( $metadata['order_key'], $order->get_order_key() );
						return is_array( $metadata );
					}
				)
			)
			->will( $this->returnValue( $intent ) );

		$mock_fraud_prevention = $this->createMock( Fraud_Prevention_Service::class );
		Fraud_Prevention_Service::set_instance( $mock_fraud_prevention );
		$mock_fraud_prevention
			->expects( $this->once() )
			->method( 'is_enabled' )
			->willReturn( false );

		$this->mock_wcpay_gateway->process_payment( $order->get_id() );
	}

	public function test_initial_subscription_payment() {
		$order = WC_Helper_Order::create_order();
		$this->mock_wcs_order_contains_subscription( true );

		$intent = WC_Helper_Intention::create_intention();
		$this->mock_api_client
			->expects( $this->once() )
			->method( 'create_and_confirm_intention' )
			->with(
				$this->anything(),
				$this->anything(),
				$this->anything(),
				$this->anything(),
				$this->anything(),
				$this->anything(),
				$this->anything(),
				// Metadata argument.
				$this->callback(
					function( $metadata ) use ( $order ) {
						$this->assertEquals( $metadata['payment_type'], 'recurring' );
						$this->assertEquals( $metadata['order_key'], $order->get_order_key() );
						return is_array( $metadata );
					}
				)
			)
			->will( $this->returnValue( $intent ) );

		$this->mock_wcpay_gateway->process_payment( $order->get_id() );
	}

	public function test_renewal_subscription_payment() {
		$order = WC_Helper_Order::create_order();
		$this->mock_wcs_order_contains_subscription( true );
		WC_Subscriptions::set_wcs_get_subscriptions_for_order(
			function( $parent_order ) use ( $order ) {
				return $order;
			}
		);
		$order->add_payment_token( $this->token );

		$mock_subscription = new WC_Subscription();

		WC_Subscriptions::set_wcs_get_subscriptions_for_renewal_order(
			function ( $id ) use ( $mock_subscription ) {
				return [ '1' => $mock_subscription ];
			}
		);

		$intent = WC_Helper_Intention::create_intention();
		$this->mock_api_client
			->expects( $this->once() )
			->method( 'create_and_confirm_intention' )
			->with(
				$this->anything(),
				$this->anything(),
				$this->anything(),
				$this->anything(),
				$this->anything(),
				$this->anything(),
				$this->anything(),
				// Metadata argument.
				$this->callback(
					function( $metadata ) use ( $order ) {
						$this->assertEquals( $metadata['payment_type'], 'recurring' );
						$this->assertEquals( $metadata['order_key'], $order->get_order_key() );
						return is_array( $metadata );
					}
				)
			)
			->will( $this->returnValue( $intent ) );

		$this->mock_wcpay_gateway->scheduled_subscription_payment( 100, $order );
	}

	/**
	 * Test the scheduled_subscription_payment method is halted before payment when the renewal order is linked to a WCPay Subscription.
	 */
	public function test_scheduled_subscription_payment_skipped() {
		$order = WC_Helper_Order::create_order();
		$this->mock_wcs_order_contains_subscription( true );
		WC_Subscriptions::set_wcs_get_subscriptions_for_order(
			function( $parent_order ) use ( $order ) {
				return $order;
			}
		);
		$order->add_payment_token( $this->token );

		// Mock a subscription that is a WCPay Subscription.
		$mock_subscription                 = new WC_Subscription();
		$mock_subscription->payment_method = 'woocommerce_payments';

		$mock_subscription->update_meta_data( '_wcpay_subscription_id', 'test_is_wcpay_subscription' );

		WC_Subscriptions::set_wcs_get_subscriptions_for_renewal_order(
			function ( $id ) use ( $mock_subscription ) {
				return [ '1' => $mock_subscription ];
			}
		);

		// Make sure the payment is skipped for WCPay Subscriptions.
		$this->mock_api_client
			->expects( $this->never() )
			->method( 'create_and_confirm_intention' );

		$this->mock_wcpay_gateway->scheduled_subscription_payment( 100, $order );

	}
}
