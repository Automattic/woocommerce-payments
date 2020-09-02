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
	const USER_ID = 1;

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
			$this->mock_token_service,
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

		$token = $this->create_saved_payment_method( 'new_payment_method' );

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
			$this->create_saved_payment_method( 'new_payment_method_1' ),
			$this->create_saved_payment_method( 'new_payment_method_2' ),
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
			$this->create_saved_payment_method( 'new_payment_method_1' ),
			$this->create_saved_payment_method( 'new_payment_method_2' ),
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

	private function create_saved_payment_method( $payment_method, $gateway = WC_Payment_Gateway_WCPay::GATEWAY_ID, $user_id = self::USER_ID ) {
		$token = new WC_Payment_Token_CC();
		$token->set_token( $payment_method );
		$token->set_gateway_id( $gateway );
		$token->set_user_id( $user_id );
		$token->set_card_type( 'visa' );
		$token->set_last4( '4242' );
		$token->set_expiry_month( 6 );
		$token->set_expiry_year( 2026 );
		$token->save();

		return WC_Payment_Tokens::get( $token->get_id() );
	}

	private function mock_wcs_get_subscriptions_for_order( $subscriptions ) {
		WCS_Mock::set_wcs_get_subscriptions_for_order(
			function ( $order ) use ( $subscriptions ) {
				return $subscriptions;
			}
		);
	}
}
