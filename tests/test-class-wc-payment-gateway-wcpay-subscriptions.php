<?php
/**
 * Class WC_Payment_Gateway_WCPay_Test
 *
 * @package WooCommerce\Payments\Tests
 */

/**
 * WC_Payment_Gateway_WCPay unit tests.
 */
class WC_Payment_Gateway_WCPay_Subscriptions_Test extends WP_UnitTestCase {
	const USER_ID           = 1;
	const CUSTOMER_ID       = 'cus_mock';
	const PAYMENT_METHOD_ID = 'pm_mock';
	const CHARGE_ID         = 'ch_mock';
	const PAYMENT_INTENT_ID = 'pi_mock';

	/**
	 * System under test.
	 *
	 * @var WC_Payment_Gateway_WCPay_Subscriptions_Compat
	 */
	private $wcpay_gateway;

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
	 * WC_Payments_Account instance.
	 *
	 * @var WC_Payments_Account
	 */
	private $wcpay_account;

	public function setUp() {
		parent::setUp();

		wp_set_current_user( self::USER_ID );

		$this->mock_api_client = $this->getMockBuilder( 'WC_Payments_API_Client' )
			->disableOriginalConstructor()
			->getMock();

		$this->wcpay_account = new WC_Payments_Account( $this->mock_api_client );

		$this->mock_customer_service = $this->getMockBuilder( 'WC_Payments_Customer_Service' )
			->disableOriginalConstructor()
			->getMock();

		$this->mock_token_service = $this->getMockBuilder( 'WC_Payments_Token_Service' )
			->disableOriginalConstructor()
			->getMock();

		$this->wcpay_gateway = new \WC_Payment_Gateway_WCPay_Subscriptions_Compat(
			$this->mock_api_client,
			$this->wcpay_account,
			$this->mock_customer_service,
			$this->mock_token_service
		);
	}

	public function test_add_token_to_order_should_add_token_to_subscriptions() {
		$original_order = WC_Helper_Order::create_order( self::USER_ID );
		$subscriptions  = [
			WC_Helper_Order::create_order( self::USER_ID ),
			WC_Helper_Order::create_order( self::USER_ID ),
			WC_Helper_Order::create_order( self::USER_ID ),
		];
		$orders         = array_merge( [ $original_order ], $subscriptions );

		$this->mock_wcs_get_subscriptions_for_order( $subscriptions );

		$token = WC_Helper_Token::create_token( 'new_payment_method', self::USER_ID );

		$this->wcpay_gateway->add_token_to_order( $original_order, $token );

		foreach ( $orders as $order ) {
			$payment_methods = $order->get_payment_tokens();
			$this->assertNotFalse( end( $payment_methods ) );
			$this->assertEquals( $token->get_id(), end( $payment_methods ) );
		}
	}

	public function test_add_token_to_order_should_add_to_end_of_array() {
		$order = WC_Helper_Order::create_order( self::USER_ID );
		$this->mock_wcs_get_subscriptions_for_order( [] );
		$tokens = [
			WC_Helper_Token::create_token( 'new_payment_method_1', self::USER_ID ),
			WC_Helper_Token::create_token( 'new_payment_method_2', self::USER_ID ),
		];

		foreach ( $tokens as $token ) {
			$this->wcpay_gateway->add_token_to_order( $order, $token );
			$payment_methods = $order->get_payment_tokens();
			$this->assertNotFalse( end( $payment_methods ) );
			$this->assertEquals( $token->get_id(), end( $payment_methods ) );
		}
		$this->assertCount( count( $tokens ), $order->get_payment_tokens() );
	}

	public function test_add_token_to_order_should_add_existing_tokens() {
		$order = WC_Helper_Order::create_order( self::USER_ID );
		$this->mock_wcs_get_subscriptions_for_order( [] );
		$tokens = [
			WC_Helper_Token::create_token( 'new_payment_method_1', self::USER_ID ),
			WC_Helper_Token::create_token( 'new_payment_method_2', self::USER_ID ),
		];
		$tokens = array_merge( $tokens, $tokens );

		foreach ( $tokens as $token ) {
			$this->wcpay_gateway->add_token_to_order( $order, $token );
			$payment_methods = $order->get_payment_tokens();
			$this->assertNotFalse( end( $payment_methods ) );
			$this->assertEquals( $token->get_id(), end( $payment_methods ) );
		}
		$this->assertCount( count( $tokens ), $order->get_payment_tokens() );
	}

	public function test_update_failing_payment_method_copies_last_method_from_renewal() {
		$subscription  = WC_Helper_Order::create_order( self::USER_ID );
		$renewal_order = WC_Helper_Order::create_order( self::USER_ID );

		$renewal_order->add_payment_token( WC_Helper_Token::create_token( 'new_payment_method_1', self::USER_ID ) );
		$renewal_order->add_payment_token( WC_Helper_Token::create_token( 'new_payment_method_2', self::USER_ID ) );

		$this->wcpay_gateway->update_failing_payment_method( $subscription, $renewal_order );

		$payment_methods = $subscription->get_payment_tokens();
		$this->assertCount( 1, $payment_methods );
		$token = WC_Payment_Tokens::get( end( $payment_methods ) );
		$this->assertEquals( 'new_payment_method_2', $token->get_token() );
	}

	public function test_update_failing_payment_method_does_not_copy_method_if_renewal_has_no_method() {
		$subscription  = WC_Helper_Order::create_order( self::USER_ID );
		$renewal_order = WC_Helper_Order::create_order( self::USER_ID );

		$this->wcpay_gateway->update_failing_payment_method( $subscription, $renewal_order );

		$this->assertCount( 0, $subscription->get_payment_tokens() );
	}

	public function test_update_failing_payment_method_does_not_copy_method_if_token_does_not_exist() {
		$subscription  = WC_Helper_Order::create_order( self::USER_ID );
		$renewal_order = WC_Helper_Order::create_order( self::USER_ID );

		$token = WC_Helper_Token::create_token( 'new_payment_method', self::USER_ID );
		$renewal_order->add_payment_token( $token );
		$token->delete();

		$this->wcpay_gateway->update_failing_payment_method( $subscription, $renewal_order );

		$this->assertCount( 0, $subscription->get_payment_tokens() );
	}

	public function test_scheduled_subscription_payment() {
		$renewal_order = WC_Helper_Order::create_order( self::USER_ID );

		$token = WC_Helper_Token::create_token( self::PAYMENT_METHOD_ID, self::USER_ID );
		$renewal_order->add_payment_token( $token );

		$this->mock_customer_service
			->expects( $this->once() )
			->method( 'get_customer_id_by_user_id' )
			->with( self::USER_ID )
			->willReturn( self::CUSTOMER_ID );

		$this->mock_customer_service
			->expects( $this->once() )
			->method( 'update_customer_for_user' )
			->willReturn( self::CUSTOMER_ID );

		$this->mock_api_client
			->expects( $this->once() )
			->method( 'create_and_confirm_intention' )
			->with( $this->anything(), $this->anything(), self::PAYMENT_METHOD_ID, self::CUSTOMER_ID, $this->anything(), false, $this->anything(), $this->anything(), true )
			->willReturn(
				new WC_Payments_API_Intention(
					self::PAYMENT_INTENT_ID,
					1500,
					new DateTime(),
					'succeeded',
					self::CHARGE_ID,
					''
				)
			);

		$this->wcpay_gateway->scheduled_subscription_payment( $renewal_order->get_total(), $renewal_order );

		$this->assertEquals( 'processing', $renewal_order->get_status() );
	}

	public function test_scheduled_subscription_payment_fails_when_token_is_missing() {
		$renewal_order = WC_Helper_Order::create_order( self::USER_ID );

		$this->mock_customer_service
			->expects( $this->never() )
			->method( 'get_customer_id_by_user_id' );

		$this->wcpay_gateway->scheduled_subscription_payment( $renewal_order->get_total(), $renewal_order );

		$this->assertEquals( 'failed', $renewal_order->get_status() );
	}

	public function test_scheduled_subscription_payment_fails_when_token_is_invalid() {
		$renewal_order = WC_Helper_Order::create_order( self::USER_ID );

		$token = WC_Helper_Token::create_token( 'new_payment_method', self::USER_ID );
		$renewal_order->add_payment_token( $token );
		$token->delete();

		$this->mock_customer_service
			->expects( $this->never() )
			->method( 'get_customer_id_by_user_id' );

		$this->wcpay_gateway->scheduled_subscription_payment( $renewal_order->get_total(), $renewal_order );

		$this->assertEquals( 'failed', $renewal_order->get_status() );
	}

	public function test_scheduled_subscription_payment_fails_when_payment_processing_fails() {
		$renewal_order = WC_Helper_Order::create_order( self::USER_ID );

		$token = WC_Helper_Token::create_token( 'new_payment_method', self::USER_ID );
		$renewal_order->add_payment_token( $token );

		$this->mock_customer_service
			->expects( $this->once() )
			->method( 'get_customer_id_by_user_id' )
			->willThrowException( new WC_Payments_API_Exception( 'Error', 'error', 500 ) );

		$this->wcpay_gateway->scheduled_subscription_payment( $renewal_order->get_total(), $renewal_order );

		$this->assertEquals( 'failed', $renewal_order->get_status() );
	}

	private function mock_wcs_get_subscriptions_for_order( $subscriptions ) {
		WCS_Mock::set_wcs_get_subscriptions_for_order(
			function ( $order ) use ( $subscriptions ) {
				return $subscriptions;
			}
		);
	}
}
