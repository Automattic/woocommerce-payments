<?php
/**
 * Class WC_Payment_Gateway_WCPay_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\Payment_Methods\CC_Payment_Gateway;

/**
 * WC_Payment_Gateway_WCPay unit tests.
 */
class WC_Payment_Gateway_WCPay_Subscriptions_Process_Payment_Test extends WP_UnitTestCase {
	const USER_ID           = 1;
	const CUSTOMER_ID       = 'cus_mock';
	const PAYMENT_METHOD_ID = 'pm_mock';
	const CHARGE_ID         = 'ch_mock';
	const SETUP_INTENT_ID   = 'si_mock';
	const PAYMENT_INTENT_ID = 'pi_mock';
	const TOKEN_REQUEST_KEY = 'wc-' . CC_Payment_Gateway::GATEWAY_ID . '-payment-token';

	/**
	 * System under test.
	 *
	 * @var WC_Payment_Gateway_WCPay_Subscriptions_Compat
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
	 * WC_Payments_Account instance.
	 *
	 * @var WC_Payments_Account
	 */
	private $wcpay_account;

	/**
	 * Setup intent to be used during tests.
	 *
	 * @var array
	 */
	private $setup_intent = [
		'id'            => self::SETUP_INTENT_ID,
		'status'        => 'succeeded',
		'client_secret' => 'test_client_secret',
	];

	/**
	 * Payment intent to be used during tests.
	 *
	 * @var WC_Payments_API_Intention
	 */
	private $payment_intent;

	/**
	 * Token to be used during the tests.
	 *
	 * @var WC_Payment_Token
	 */
	private $token;

	public function setUp() {
		parent::setUp();

		wp_set_current_user( self::USER_ID );

		$this->payment_intent = new WC_Payments_API_Intention(
			self::PAYMENT_INTENT_ID,
			1500,
			'usd',
			'cus_12345',
			'pm_12345',
			new DateTime(),
			'succeeded',
			self::CHARGE_ID,
			''
		);

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

		$this->mock_action_scheduler_service = $this->getMockBuilder( 'WC_Payments_Action_Scheduler_Service' )
			->disableOriginalConstructor()
			->getMock();

		$this->mock_wcpay_gateway = $this->getMockBuilder( '\WC_Payment_Gateway_WCPay_Subscriptions_Compat' )
			->setConstructorArgs(
				[
					$this->mock_api_client,
					$this->wcpay_account,
					$this->mock_customer_service,
					$this->mock_token_service,
					$this->mock_action_scheduler_service,
				]
			)
			->setMethods(
				[
					'get_return_url',
					'mark_payment_complete_for_order',
					'get_level3_data_from_order', // To avoid needing to mock the order items.
					'add_token_to_order',
				]
			)
			->getMock();

		$this->mock_customer_service
			->expects( $this->once() )
			->method( 'get_customer_id_by_user_id' )
			->with( get_current_user_id() )
			->willReturn( self::CUSTOMER_ID );

		$this->mock_customer_service
			->expects( $this->once() )
			->method( 'update_customer_for_user' )
			->willReturn( self::CUSTOMER_ID );

		$this->token = WC_Helper_Token::create_token( self::PAYMENT_METHOD_ID, self::USER_ID );

		$_POST = [
			'wcpay-payment-method' => self::PAYMENT_METHOD_ID,
		];
	}

	public function test_new_card_subscription() {
		$order = WC_Helper_Order::create_order( self::USER_ID );

		$this->mock_wcs_order_contains_subscription( true );

		$this->mock_api_client
			->expects( $this->once() )
			->method( 'create_and_confirm_intention' )
			->with( $this->anything(), $this->anything(), self::PAYMENT_METHOD_ID, self::CUSTOMER_ID, $this->anything(), true, $this->anything(), $this->anything(), false )
			->willReturn( $this->payment_intent );

		$this->mock_token_service
			->expects( $this->once() )
			->method( 'add_payment_method_to_user' )
			->with( self::PAYMENT_METHOD_ID, $order->get_user() )
			->willReturn( $this->token );

		// Expect add token to order to be called, so it can be reused in renewals.
		$this->mock_wcpay_gateway
			->expects( $this->once() )
			->method( 'add_token_to_order' )
			->with(
				$this->callback( $this->match_order_id( $order->get_id() ) ),
				$this->token
			);

		$result       = $this->mock_wcpay_gateway->process_payment( $order->get_id() );
		$result_order = wc_get_order( $order->get_id() );

		$this->assertEquals( 'processing', $result_order->get_status() );
		$this->assertEquals( 'success', $result['result'] );
	}

	public function test_new_card_zero_dollar_subscription() {
		$order = WC_Helper_Order::create_order( self::USER_ID, 0 );

		$this->mock_wcs_order_contains_subscription( true );

		$this->mock_api_client
			->expects( $this->once() )
			->method( 'create_and_confirm_setup_intent' )
			->with( self::PAYMENT_METHOD_ID, self::CUSTOMER_ID )
			->willReturn( $this->setup_intent );

		$this->mock_token_service
			->expects( $this->once() )
			->method( 'add_payment_method_to_user' )
			->with( self::PAYMENT_METHOD_ID, $order->get_user() )
			->willReturn( $this->token );

		// Expect add token to order to be called, so it can be reused in renewals.
		$this->mock_wcpay_gateway
			->expects( $this->once() )
			->method( 'add_token_to_order' )
			->with(
				$this->callback( $this->match_order_id( $order->get_id() ) ),
				$this->token
			);

		$result       = $this->mock_wcpay_gateway->process_payment( $order->get_id() );
		$result_order = wc_get_order( $order->get_id() );

		$this->assertEquals( 'processing', $result_order->get_status() );
		$this->assertEquals( 'success', $result['result'] );
	}

	public function test_new_card_is_added_before_status_update() {
		$order = WC_Helper_Order::create_order( self::USER_ID, 0 );

		$this->mock_wcs_order_contains_subscription( true );

		$this->mock_api_client
			->expects( $this->once() )
			->method( 'create_and_confirm_setup_intent' )
			->with( self::PAYMENT_METHOD_ID, self::CUSTOMER_ID )
			->willReturn( $this->setup_intent );

		$this->mock_token_service
			->expects( $this->once() )
			->method( 'add_payment_method_to_user' )
			->with( self::PAYMENT_METHOD_ID, $order->get_user() )
			->willReturn( $this->token );

		// Expect add token to order to be called, so it can be reused in renewals.
		$this->mock_wcpay_gateway
			->expects( $this->once() )
			->method( 'add_token_to_order' )
			->with(
				$this->logicalAnd(
					$this->callback( $this->match_order_id( $order->get_id() ) ),
					$this->callback( $this->match_order_status( 'pending' ) )
				),
				$this->token
			);

		$result = $this->mock_wcpay_gateway->process_payment( $order->get_id() );
	}

	public function test_saved_card_subscription() {
		$order = WC_Helper_Order::create_order( self::USER_ID );

		$_POST = [
			'payment_method'        => WC_Payment_Gateway_WCPay::GATEWAY_ID,
			self::TOKEN_REQUEST_KEY => $this->token->get_id(),
		];

		$this->mock_wcs_order_contains_subscription( true );

		$this->mock_api_client
			->expects( $this->once() )
			->method( 'create_and_confirm_intention' )
			->with( $this->anything(), $this->anything(), self::PAYMENT_METHOD_ID, self::CUSTOMER_ID, $this->anything(), false, $this->anything(), $this->anything(), false )
			->willReturn( $this->payment_intent );

		$this->mock_token_service
			->expects( $this->never() )
			->method( 'add_payment_method_to_user' );

		// Expect add token to order to be called, so it can be reused in renewals.
		$this->mock_wcpay_gateway
			->expects( $this->once() )
			->method( 'add_token_to_order' )
			->with(
				$this->callback( $this->match_order_id( $order->get_id() ) ),
				$this->token
			);

		$result       = $this->mock_wcpay_gateway->process_payment( $order->get_id() );
		$result_order = wc_get_order( $order->get_id() );

		$this->assertEquals( 'processing', $result_order->get_status() );
		$this->assertEquals( 'success', $result['result'] );
	}

	public function test_saved_card_zero_dollar_subscription() {
		$order = WC_Helper_Order::create_order( self::USER_ID, 0 );

		$_POST = [
			'payment_method'        => WC_Payment_Gateway_WCPay::GATEWAY_ID,
			self::TOKEN_REQUEST_KEY => $this->token->get_id(),
		];

		$this->mock_wcs_order_contains_subscription( true );

		// The card is already saved and there's no payment needed, so no Setup Intent needs to be created.
		$this->mock_api_client
			->expects( $this->never() )
			->method( 'create_and_confirm_setup_intent' );

		// We're not saving a new payment method, so we don't need to add the payment method to
		// a user account.
		$this->mock_token_service
			->expects( $this->never() )
			->method( 'add_payment_method_to_user' );

		// We do need to add the payment method to the order so we can charge it when it's time to
		// renew the order or when the free trial is over.
		$this->mock_wcpay_gateway
			->expects( $this->once() )
			->method( 'add_token_to_order' );

		$result       = $this->mock_wcpay_gateway->process_payment( $order->get_id() );
		$result_order = wc_get_order( $order->get_id() );

		$this->assertEquals( 'processing', $result_order->get_status() );
		$this->assertEquals( 'success', $result['result'] );
	}

	public function test_card_is_saved_when_updating_subscription_payment_method() {
		$order = WC_Helper_Order::create_order( self::USER_ID, 0 );

		$_GET = [ 'change_payment_method' => 10 ];

		$this->mock_wcs_order_contains_subscription( false );

		WC_Subscriptions::set_wcs_is_subscription(
			function ( $order ) {
				return true;
			}
		);

		$this->mock_api_client
			->expects( $this->once() )
			->method( 'create_and_confirm_setup_intent' )
			->with( self::PAYMENT_METHOD_ID, self::CUSTOMER_ID )
			->willReturn( $this->setup_intent );

		$this->mock_token_service
			->expects( $this->once() )
			->method( 'add_payment_method_to_user' )
			->with( self::PAYMENT_METHOD_ID, $order->get_user() )
			->willReturn( $this->token );

		// Expect add token to order to be called, so it can be reused in renewals.
		$this->mock_wcpay_gateway
			->expects( $this->once() )
			->method( 'add_token_to_order' )
			->with(
				$this->callback( $this->match_order_id( $order->get_id() ) ),
				$this->token
			);

		$result       = $this->mock_wcpay_gateway->process_payment( $order->get_id() );
		$result_order = wc_get_order( $order->get_id() );

		$this->assertEquals( 'processing', $result_order->get_status() );
		$this->assertEquals( 'success', $result['result'] );
	}

	public function test_card_is_saved_when_updating_subscription_using_saved_payment_method() {
		$order = WC_Helper_Order::create_order( self::USER_ID, 0 );

		$_POST = [
			'payment_method'        => WC_Payment_Gateway_WCPay::GATEWAY_ID,
			self::TOKEN_REQUEST_KEY => $this->token->get_id(),
		];
		$_GET  = [ 'change_payment_method' => 10 ];

		$this->mock_wcs_order_contains_subscription( false );

		WC_Subscriptions::set_wcs_is_subscription(
			function ( $order ) {
				return true;
			}
		);

		$this->mock_api_client
			->expects( $this->never() )
			->method( 'create_and_confirm_setup_intent' );

		$this->mock_token_service
			->expects( $this->never() )
			->method( 'add_payment_method_to_user' );

		// Expect add token to order to be called, so it can be reused in renewals.
		$this->mock_wcpay_gateway
			->expects( $this->once() )
			->method( 'add_token_to_order' )
			->with(
				$this->callback( $this->match_order_id( $order->get_id() ) ),
				$this->token
			);

		$result       = $this->mock_wcpay_gateway->process_payment( $order->get_id() );
		$result_order = wc_get_order( $order->get_id() );

		$this->assertEquals( 'processing', $result_order->get_status() );
		$this->assertEquals( 'success', $result['result'] );
	}

	private function mock_wcs_order_contains_subscription( $value ) {
		WC_Subscriptions::set_wcs_order_contains_subscription(
			function ( $order ) use ( $value ) {
				return $value;
			}
		);
	}

	private function match_order_id( $order_id ) {
		return function ( $order ) use ( $order_id ) {
			return $order_id === $order->get_id();
		};
	}

	private function match_order_status( $status ) {
		return function ( $order ) use ( $status ) {
			return $status === $order->get_status();
		};
	}
}
