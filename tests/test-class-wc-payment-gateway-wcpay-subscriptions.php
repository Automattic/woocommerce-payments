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
	const SETUP_INTENT_ID   = 'seti_mock';

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

	public function test_subscription_payment_method_filter_bypass_other_payment_methods() {
		$subscription              = WC_Helper_Order::create_order( self::USER_ID );
		$payment_method_to_display = $this->wcpay_gateway->maybe_render_subscription_payment_method( 'Via Crypto Currency', $subscription );
		$this->assertEquals( 'Via Crypto Currency', $payment_method_to_display );
	}

	public function test_subscription_payment_method_filter_adds_card_details() {
		$subscription = WC_Helper_Order::create_order( self::USER_ID );
		$subscription->set_payment_method( $this->wcpay_gateway->id );
		$subscription->add_payment_token( WC_Helper_Token::create_token( 'new_payment_method_1', self::USER_ID ) );

		$last_token = WC_Helper_Token::create_token( 'new_payment_method_2', self::USER_ID );
		$subscription->add_payment_token( $last_token );

		$payment_method_to_display = $this->wcpay_gateway->maybe_render_subscription_payment_method( 'Via Credit card', $subscription );
		$this->assertEquals( $last_token->get_display_name(), $payment_method_to_display );
	}

	public function test_add_subscription_payment_meta_adds_active_token() {
		$subscription = WC_Helper_Order::create_order( self::USER_ID );

		$token = WC_Helper_Token::create_token( self::PAYMENT_METHOD_ID, self::USER_ID );
		$subscription->add_payment_token( $token );

		$payment_meta = $this->wcpay_gateway->add_subscription_payment_meta( [], $subscription );

		$this->assertEquals(
			[
				'wc_order_tokens' => [
					'payment_method_id' => [
						'label' => 'Saved payment method ID',
						'value' => self::PAYMENT_METHOD_ID,
					],
				],
			],
			$payment_meta[ WC_Payment_Gateway_WCPay::GATEWAY_ID ]
		);
	}

	public function test_add_subscription_payment_meta_adds_empty_string() {
		$subscription = WC_Helper_Order::create_order( self::USER_ID );

		$payment_meta = $this->wcpay_gateway->add_subscription_payment_meta( [], $subscription );

		$this->assertEquals(
			[
				'wc_order_tokens' => [
					'payment_method_id' => [
						'label' => 'Saved payment method ID',
						'value' => '',
					],
				],
			],
			$payment_meta[ WC_Payment_Gateway_WCPay::GATEWAY_ID ]
		);
	}

	public function test_validate_subscription_payment_meta_success() {
		$subscription = WC_Helper_Order::create_order( self::USER_ID );
		$token        = WC_Helper_Token::create_token( self::PAYMENT_METHOD_ID, self::USER_ID );

		// The validate method doesn't return/does anything when the payment_meta is valid,
		// but we need to assert something to avoid PHPUnit's risky test warning.
		$this->assertNull(
			$this->wcpay_gateway->validate_subscription_payment_meta(
				WC_Payment_Gateway_WCPay::GATEWAY_ID,
				[ 'wc_order_tokens' => [ 'payment_method_id' => [ 'value' => self::PAYMENT_METHOD_ID ] ] ],
				$subscription
			)
		);
	}

	public function test_validate_subscription_payment_meta_skips_wrong_gateway() {
		// The validate method doesn't return/does anything when the payment_meta is valid,
		// but we need to assert something to avoid PHPUnit's risky test warning.
		$this->assertNull(
			$this->wcpay_gateway->validate_subscription_payment_meta(
				'some_random_gateway',
				[],
				null
			)
		);
	}

	public function test_validate_subscription_payment_meta_no_payment_method() {
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'A customer saved payment method was not selected for this order.' );

		$subscription = WC_Helper_Order::create_order( self::USER_ID );

		$this->wcpay_gateway->validate_subscription_payment_meta(
			WC_Payment_Gateway_WCPay::GATEWAY_ID,
			[ 'wc_order_tokens' => [ 'payment_method_id' => [ 'value' => '' ] ] ],
			$subscription
		);
	}

	public function test_validate_subscription_payment_meta_invalid_token() {
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'The saved payment method selected is invalid or does not exist for this customer.' );

		$subscription = WC_Helper_Order::create_order( self::USER_ID );

		$this->wcpay_gateway->validate_subscription_payment_meta(
			WC_Payment_Gateway_WCPay::GATEWAY_ID,
			[ 'wc_order_tokens' => [ 'payment_method_id' => [ 'value' => 'some_random_payment_method' ] ] ],
			$subscription
		);
	}

	public function test_save_meta_in_order_tokens_adds_token_to_order() {
		$subscription = WC_Helper_Order::create_order( self::USER_ID );
		$token        = WC_Helper_Token::create_token( self::PAYMENT_METHOD_ID, self::USER_ID );

		$this->wcpay_gateway->save_meta_in_order_tokens( $subscription, 'wc_order_tokens', 'payment_method_id', self::PAYMENT_METHOD_ID );
		$subscription_tokens = $subscription->get_payment_tokens();
		$this->assertEquals( $token->get_id(), end( $subscription_tokens ) );
	}

	public function test_save_meta_in_order_tokens_skips_wrong_table_name() {
		$subscription = WC_Helper_Order::create_order( self::USER_ID );
		$token        = WC_Helper_Token::create_token( self::PAYMENT_METHOD_ID, self::USER_ID );

		$this->wcpay_gateway->save_meta_in_order_tokens( $subscription, 'post_meta', 'payment_method_id', self::PAYMENT_METHOD_ID );
		$this->assertCount( 0, $subscription->get_payment_tokens() );
	}

	public function test_save_meta_in_order_tokens_wrong_meta_key() {
		$subscription = WC_Helper_Order::create_order( self::USER_ID );
		$token        = WC_Helper_Token::create_token( self::PAYMENT_METHOD_ID, self::USER_ID );

		$this->wcpay_gateway->save_meta_in_order_tokens( $subscription, 'wc_order_tokens', 'token', self::PAYMENT_METHOD_ID );
		$this->assertCount( 0, $subscription->get_payment_tokens() );
	}

	public function test_save_meta_in_order_tokens_wrong_token_id() {
		$subscription = WC_Helper_Order::create_order( self::USER_ID );
		$token        = WC_Helper_Token::create_token( self::PAYMENT_METHOD_ID, self::USER_ID );

		$this->wcpay_gateway->save_meta_in_order_tokens( $subscription, 'wc_order_tokens', 'payment_method_id', 'some_random_payment_method' );
		$this->assertCount( 0, $subscription->get_payment_tokens() );
	}

	private function mock_wcs_get_subscriptions_for_order( $subscriptions ) {
		WCS_Mock::set_wcs_get_subscriptions_for_order(
			function ( $order ) use ( $subscriptions ) {
				return $subscriptions;
			}
		);
	}
}
