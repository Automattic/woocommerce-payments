<?php
/**
 * Class WC_Payment_Gateway_WCPay_Test
 *
 * @package WooCommerce\Payments\Tests
 */

/**
 * WC_Payment_Gateway_WCPay unit tests.
 */
class WC_Payment_Gateway_WCPay_Payment_Types extends WP_UnitTestCase {
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
	 * WC_Payments_Account instance.
	 *
	 * @var WC_Payments_Account
	 */
	private $wcpay_account;

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
	public function setUp() {
		parent::setUp();

		// Arrange: Mock WC_Payments_API_Client so we can configure the
		// return value of create_and_confirm_intention().
		// Note that we cannot use createStub here since it's not defined in PHPUnit 6.5.
		$this->mock_api_client = $this->getMockBuilder( 'WC_Payments_API_Client' )
			->disableOriginalConstructor()
			->setMethods( [ 'create_and_confirm_intention', 'get_payment_method', 'request_with_level3_data', 'is_server_connected' ] )
			->getMock();

		// Arrange: Create new WC_Payments_Account instance to use later.
		$this->wcpay_account = new WC_Payments_Account( $this->mock_api_client );

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

		// Arrange: Mock WC_Payment_Gateway_WCPay so that some of its methods can be
		// mocked, and their return values can be used for testing.
		$this->mock_wcpay_gateway = $this->getMockBuilder( 'WC_Payment_Gateway_WCPay' )
			->setConstructorArgs(
				[
					$this->mock_api_client,
					$this->wcpay_account,
					$this->mock_customer_service,
					$this->mock_token_service,
					$this->mock_action_scheduler_service,
					$this->mock_rate_limiter,
					$this->mock_order_service,
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
	public static function tearDownAfterClass() {
		WC_Subscriptions::set_wcs_order_contains_subscription( null );
		WC_Subscriptions::set_wcs_get_subscriptions_for_order( null );
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

		$intent = new WC_Payments_API_Intention( 'pi_mock', 1500, 'usd', 'cus_1234', 'pm_56789', new DateTime(), 'succeeded', 'ch_mock', 'client_secret_123' );
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

		$this->mock_wcpay_gateway->process_payment( $order->get_id() );
	}

	public function test_initial_subscription_payment() {
		$order = WC_Helper_Order::create_order();
		$this->mock_wcs_order_contains_subscription( true );

		$intent = new WC_Payments_API_Intention( 'pi_mock', 1500, 'usd', 'cus_1234', 'pm_56789', new DateTime(), 'succeeded', 'ch_mock', 'client_secret_123' );
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

		$intent = new WC_Payments_API_Intention( 'pi_mock', 1500, 'usd', 'cus_1234', 'pm_56789', new DateTime(), 'succeeded', 'ch_mock', 'client_secret_123' );
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
}
