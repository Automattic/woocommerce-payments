<?php
/**
 * Class WC_Payment_Gateway_WCPay_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\Exceptions\API_Exception;

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
	private $mock_session_rate_limiter;

	/**
	 * WC_Payments_Order_Service.
	 *
	 * @var WC_Payments_Order_Service
	 */
	private $order_service;

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

		$this->mock_action_scheduler_service = $this->getMockBuilder( 'WC_Payments_Action_Scheduler_Service' )
			->disableOriginalConstructor()
			->getMock();

		$this->mock_session_rate_limiter = $this->getMockBuilder( 'Session_Rate_Limiter' )
			->disableOriginalConstructor()
			->getMock();

		$this->order_service = new WC_Payments_Order_Service();

		$this->wcpay_gateway = new \WC_Payment_Gateway_WCPay(
			$this->mock_api_client,
			$this->wcpay_account,
			$this->mock_customer_service,
			$this->mock_token_service,
			$this->mock_action_scheduler_service,
			$this->mock_session_rate_limiter,
			$this->order_service
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
					'usd',
					'cus_12345',
					'pm_12345',
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
			->willThrowException( new API_Exception( 'Error', 'error', 500 ) );

		$this->wcpay_gateway->scheduled_subscription_payment( $renewal_order->get_total(), $renewal_order );

		$this->assertEquals( 'failed', $renewal_order->get_status() );
	}

	public function test_scheduled_subscription_payment_fails_when_payment_processing_fails_non_usd() {
		$renewal_order = WC_Helper_Order::create_order( self::USER_ID );

		$token = WC_Helper_Token::create_token( 'new_payment_method', self::USER_ID );
		$renewal_order->add_payment_token( $token );
		$renewal_order->set_currency( 'EUR' );

		$this->mock_customer_service
			->expects( $this->once() )
			->method( 'get_customer_id_by_user_id' )
			->willThrowException( new API_Exception( 'Error', 'error', 500 ) );

		$this->wcpay_gateway->scheduled_subscription_payment( $renewal_order->get_total(), $renewal_order );

		$notes             = wc_get_order_notes(
			[
				'order_id' => $renewal_order->get_id(),
				'limit'    => 1,
			]
		);
		$latest_wcpay_note = $notes[0];

		$this->assertEquals( 'failed', $renewal_order->get_status() );
		$this->assertContains( 'failed', $latest_wcpay_note->content );
		$this->assertContains( wc_price( $renewal_order->get_total(), [ 'currency' => 'EUR' ] ), $latest_wcpay_note->content );
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

	public function test_display_save_payment_method_checkbox_for_subs_cart() {
		WC_Subscriptions_Cart::set_cart_contains_subscription( true );

		$this->assertFalse( $this->wcpay_gateway->display_save_payment_method_checkbox( true ) );
	}

	public function test_display_save_payment_method_checkbox_for_subs_change() {
		WC_Subscriptions_Cart::set_cart_contains_subscription( false );

		$this->mock_wcs_is_subscription( true );

		$_GET = [ 'change_payment_method' => 10 ];
		$this->assertFalse( $this->wcpay_gateway->display_save_payment_method_checkbox( true ) );
	}

	public function test_display_save_payment_method_checkbox_for_returns_display() {
		WC_Subscriptions_Cart::set_cart_contains_subscription( false );

		$this->mock_wcs_is_subscription( false );

		$this->assertTrue( $this->wcpay_gateway->display_save_payment_method_checkbox( true ) );
	}

	public function test_add_subscription_payment_meta_adds_active_token() {
		$subscription = WC_Helper_Order::create_order( self::USER_ID );

		$token = WC_Helper_Token::create_token( self::PAYMENT_METHOD_ID, self::USER_ID );
		$subscription->add_payment_token( $token );

		$payment_meta = $this->wcpay_gateway->add_subscription_payment_meta( [], $subscription );

		$this->assertEquals(
			[
				'wc_order_tokens' => [
					'token' => [
						'label' => 'Saved payment method',
						'value' => strval( $token->get_id() ),
					],
				],
			],
			$payment_meta[ $this->wcpay_gateway->id ]
		);
	}

	public function test_add_subscription_payment_meta_adds_empty_string() {
		$subscription = WC_Helper_Order::create_order( self::USER_ID );

		$payment_meta = $this->wcpay_gateway->add_subscription_payment_meta( [], $subscription );

		$this->assertEquals(
			[
				'wc_order_tokens' => [
					'token' => [
						'label' => 'Saved payment method',
						'value' => '',
					],
				],
			],
			$payment_meta[ $this->wcpay_gateway->id ]
		);
	}

	public function test_validate_subscription_payment_meta_success() {
		$subscription = WC_Helper_Order::create_order( self::USER_ID );
		$token        = WC_Helper_Token::create_token( self::PAYMENT_METHOD_ID, self::USER_ID );

		// The validate method doesn't return/does anything when the payment_meta is valid,
		// but we need to assert something to avoid PHPUnit's risky test warning.
		$this->assertNull(
			$this->wcpay_gateway->validate_subscription_payment_meta(
				$this->wcpay_gateway->id,
				[ 'wc_order_tokens' => [ 'token' => [ 'value' => strval( $token->get_id() ) ] ] ],
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
			$this->wcpay_gateway->id,
			[ 'wc_order_tokens' => [ 'token' => [ 'value' => '' ] ] ],
			$subscription
		);
	}

	public function test_validate_subscription_payment_meta_invalid_token() {
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'The saved payment method selected is invalid or does not exist.' );

		$subscription = WC_Helper_Order::create_order( self::USER_ID );

		$this->wcpay_gateway->validate_subscription_payment_meta(
			$this->wcpay_gateway->id,
			[ 'wc_order_tokens' => [ 'token' => [ 'value' => '158651' ] ] ],
			$subscription
		);
	}

	public function test_validate_subscription_payment_meta_another_user_token() {
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'The saved payment method selected does not belong to this order\'s customer.' );

		$subscription = WC_Helper_Order::create_order( self::USER_ID );
		$token        = WC_Helper_Token::create_token( self::PAYMENT_METHOD_ID, self::USER_ID + 1 );

		$this->wcpay_gateway->validate_subscription_payment_meta(
			$this->wcpay_gateway->id,
			[ 'wc_order_tokens' => [ 'token' => [ 'value' => strval( $token->get_id() ) ] ] ],
			$subscription
		);
	}

	public function test_save_meta_in_order_tokens_adds_token_to_order() {
		$subscription = WC_Helper_Order::create_order( self::USER_ID );
		$token        = WC_Helper_Token::create_token( self::PAYMENT_METHOD_ID, self::USER_ID );

		$this->wcpay_gateway->save_meta_in_order_tokens( $subscription, 'wc_order_tokens', 'token', strval( $token->get_id() ) );
		$subscription_tokens = $subscription->get_payment_tokens();
		$this->assertEquals( $token->get_id(), end( $subscription_tokens ) );
	}

	public function test_save_meta_in_order_tokens_skips_wrong_table_name() {
		$subscription = WC_Helper_Order::create_order( self::USER_ID );
		$token        = WC_Helper_Token::create_token( self::PAYMENT_METHOD_ID, self::USER_ID );

		$this->wcpay_gateway->save_meta_in_order_tokens( $subscription, 'post_meta', 'token', strval( $token->get_id() ) );
		$this->assertCount( 0, $subscription->get_payment_tokens() );
	}

	public function test_save_meta_in_order_tokens_wrong_meta_key() {
		$subscription = WC_Helper_Order::create_order( self::USER_ID );
		$token        = WC_Helper_Token::create_token( self::PAYMENT_METHOD_ID, self::USER_ID );

		$this->wcpay_gateway->save_meta_in_order_tokens( $subscription, 'wc_order_tokens', 'payment_method_id', strval( $token->get_id() ) );
		$this->assertCount( 0, $subscription->get_payment_tokens() );
	}

	public function test_save_meta_in_order_tokens_wrong_token_id() {
		$subscription = WC_Helper_Order::create_order( self::USER_ID );
		$token        = WC_Helper_Token::create_token( self::PAYMENT_METHOD_ID, self::USER_ID );

		$this->wcpay_gateway->save_meta_in_order_tokens( $subscription, 'wc_order_tokens', 'token', strval( $token->get_id() + 200 ) );
		$this->assertCount( 0, $subscription->get_payment_tokens() );
	}

	public function test_render_custom_payment_meta_input() {
		$subscription = WC_Helper_Order::create_order( self::USER_ID );
		$tokens       = [
			WC_Helper_Token::create_token( self::PAYMENT_METHOD_ID . '_1', self::USER_ID ),
			WC_Helper_Token::create_token( self::PAYMENT_METHOD_ID . '_2', self::USER_ID ),
		];
		$subscription->add_payment_token( $tokens[0] );
		$subscription->add_payment_token( $tokens[1] );

		$this->expectOutputString(
			'<select name="field_id" id="field_id"><option value="' . $tokens[0]->get_id() . '" selected>' . $tokens[0]->get_display_name() . '</option><option value="' . $tokens[1]->get_id() . '" >' . $tokens[1]->get_display_name() . '</option></select>'
		);

		$this->wcpay_gateway->render_custom_payment_meta_input( $subscription, 'field_id', strval( $tokens[0]->get_id() ) );
	}

	public function test_render_custom_payment_meta_input_invalid_value() {
		$subscription = WC_Helper_Order::create_order( self::USER_ID );
		$tokens       = [
			WC_Helper_Token::create_token( self::PAYMENT_METHOD_ID . '_1', self::USER_ID ),
			WC_Helper_Token::create_token( self::PAYMENT_METHOD_ID . '_2', self::USER_ID ),
		];
		$subscription->add_payment_token( $tokens[0] );
		$subscription->add_payment_token( $tokens[1] );

		$this->expectOutputString(
			'<select name="field_id" id="field_id"><option value="" selected disabled>Please select a payment method</option><option value="' . $tokens[0]->get_id() . '" >' . $tokens[0]->get_display_name() . '</option><option value="' . $tokens[1]->get_id() . '" >' . $tokens[1]->get_display_name() . '</option></select>'
		);

		$this->wcpay_gateway->render_custom_payment_meta_input( $subscription, 'field_id', 'invalid_value' );
	}

	public function test_render_custom_payment_meta_input_empty_value() {
		$subscription = WC_Helper_Order::create_order( self::USER_ID );
		$tokens       = [
			WC_Helper_Token::create_token( self::PAYMENT_METHOD_ID . '_1', self::USER_ID ),
			WC_Helper_Token::create_token( self::PAYMENT_METHOD_ID . '_2', self::USER_ID ),
		];
		$subscription->add_payment_token( $tokens[0] );
		$subscription->add_payment_token( $tokens[1] );

		$this->expectOutputString(
			'<select name="field_id" id="field_id"><option value="" selected disabled>Please select a payment method</option><option value="' . $tokens[0]->get_id() . '" >' . $tokens[0]->get_display_name() . '</option><option value="' . $tokens[1]->get_id() . '" >' . $tokens[1]->get_display_name() . '</option></select>'
		);

		$this->wcpay_gateway->render_custom_payment_meta_input( $subscription, 'field_id', '' );
	}

	public function test_adds_custom_payment_meta_input_using_filter() {
		$subscription = WC_Helper_Order::create_order( self::USER_ID );
		$subscription->add_payment_token( WC_Helper_Token::create_token( self::PAYMENT_METHOD_ID, self::USER_ID ) );

		$this->wcpay_gateway->add_subscription_payment_meta( [], $subscription );
		$this->assertTrue( has_action( 'woocommerce_subscription_payment_meta_input_' . WC_Payment_Gateway_WCPay::GATEWAY_ID . '_wc_order_tokens_token' ) );
	}

	public function test_adds_custom_payment_meta_input_fallback_until_subs_3_0_7() {
		remove_all_actions( 'woocommerce_admin_order_data_after_billing_address' );

		WC_Subscriptions::$version = '3.0.7';
		new \WC_Payment_Gateway_WCPay(
			$this->mock_api_client,
			$this->wcpay_account,
			$this->mock_customer_service,
			$this->mock_token_service,
			$this->mock_action_scheduler_service,
			$this->mock_session_rate_limiter,
			$this->order_service
		);

		$this->assertTrue( has_action( 'woocommerce_admin_order_data_after_billing_address' ) );
	}

	public function test_does_not_add_custom_payment_meta_input_fallback_for_subs_3_0_8() {
		remove_all_actions( 'woocommerce_admin_order_data_after_billing_address' );

		WC_Subscriptions::$version = '3.0.8';
		new \WC_Payment_Gateway_WCPay(
			$this->mock_api_client,
			$this->wcpay_account,
			$this->mock_customer_service,
			$this->mock_token_service,
			$this->mock_action_scheduler_service,
			$this->mock_session_rate_limiter,
			$this->order_service
		);

		$this->assertFalse( has_action( 'woocommerce_admin_order_data_after_billing_address' ) );
	}

	public function test_add_payment_method_select_to_subscription_edit_when_subscription() {
		$subscription = WC_Helper_Order::create_order( self::USER_ID );
		$this->mock_wcs_is_subscription( true );
		wp_deregister_script( 'WCPAY_SUBSCRIPTION_EDIT_PAGE' );
		wp_dequeue_script( 'WCPAY_SUBSCRIPTION_EDIT_PAGE' );

		$this->wcpay_gateway->add_payment_method_select_to_subscription_edit( $subscription );

		$this->assertTrue( wp_script_is( 'WCPAY_SUBSCRIPTION_EDIT_PAGE', 'registered' ) );
		$this->assertTrue( wp_script_is( 'WCPAY_SUBSCRIPTION_EDIT_PAGE', 'enqueued' ) );
	}

	public function test_add_payment_method_select_to_subscription_edit_when_order() {
		$order = WC_Helper_Order::create_order( self::USER_ID );
		$this->mock_wcs_is_subscription( false );
		wp_deregister_script( 'WCPAY_SUBSCRIPTION_EDIT_PAGE' );
		wp_dequeue_script( 'WCPAY_SUBSCRIPTION_EDIT_PAGE' );

		$this->wcpay_gateway->add_payment_method_select_to_subscription_edit( $order );

		$this->assertFalse( wp_script_is( 'WCPAY_SUBSCRIPTION_EDIT_PAGE', 'registered' ) );
		$this->assertFalse( wp_script_is( 'WCPAY_SUBSCRIPTION_EDIT_PAGE', 'enqueued' ) );
	}

	private function mock_wcs_get_subscriptions_for_order( $subscriptions ) {
		WC_Subscriptions::set_wcs_get_subscriptions_for_order(
			function ( $order ) use ( $subscriptions ) {
				return $subscriptions;
			}
		);
	}

	private function mock_wcs_is_subscription( $return_value ) {
		WC_Subscriptions::set_wcs_is_subscription(
			function ( $order ) use ( $return_value ) {
				return $return_value;
			}
		);
	}
}
