<?php
/**
 * Class WC_Payment_Gateway_WCPay_Subscriptions_Payment_Method_Order_Note_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\Exceptions\API_Exception;

/**
 * WC_Payment_Gateway_WCPay unit tests.
 */
class WC_Payment_Gateway_WCPay_Subscriptions_Payment_Method_Order_Note_Test extends WP_UnitTestCase {
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
	 * Mock WC_Payments_Order_Service.
	 *
	 * @var WC_Payments_Order_Service|PHPUnit_Framework_MockObject_MockObject
	 */
	private $mock_order_service;

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

		$this->mock_order_service = $this->getMockBuilder( 'WC_Payments_Order_Service' )
			->disableOriginalConstructor()
			->getMock();

		$this->wcpay_gateway = new \WC_Payment_Gateway_WCPay(
			$this->mock_api_client,
			$this->wcpay_account,
			$this->mock_customer_service,
			$this->mock_token_service,
			$this->mock_action_scheduler_service,
			$this->mock_session_rate_limiter,
			$this->mock_order_service
		);

		$this->renewal_order = WC_Helper_Order::create_order( self::USER_ID );

		// subscription order.
		$this->subscription = new WC_Subscription();
		$this->subscription->set_last_order( $this->renewal_order->get_id() );

		$this->last4digits = [
			1 => '1122',
			2 => '3344',
			3 => '5566',
		];

		// first payment token as the failed / old payment.
		$this->token1 = WC_Helper_Token::create_token( self::PAYMENT_METHOD_ID, self::USER_ID );
		$this->token1->set_last4( $this->last4digits[1] );
		$this->token1->save();

		// second payment token as the new payment.
		$this->token2 = WC_Helper_Token::create_token( self::PAYMENT_METHOD_ID, self::USER_ID );
		$this->token2->set_last4( $this->last4digits[2] );
		$this->token2->save();

		// third payment token as the new payment. not saved to the order.
		$this->token3 = WC_Helper_Token::create_token( self::PAYMENT_METHOD_ID, self::USER_ID );
		$this->token3->set_last4( $this->last4digits[3] );
		$this->token3->save();

		$this->post_payment_token_parameter  = 'wc-' . WC_Payment_Gateway_WCPay::GATEWAY_ID . '-payment-token';
		$this->post_payment_method_parameter = 'wcpay-payment-method';

		// add token to renewal order.
		$this->renewal_order->add_payment_token( $this->token1 );
		$this->renewal_order->add_payment_token( $this->token2 );
	}

	public function test_non_wcpay_old_and_new_payment_methods() {
		$old_payment_method_title = 'old_payment_method_title';
		$old_payment_method       = 'non-wc-pay';
		$new_payment_method_title = 'new_payment_method_title';
		$new_payment_method       = 'non-wc-pay';

		$filtered_old_payment_method_title = (string) apply_filters( 'woocommerce_subscription_note_old_payment_method_title', $old_payment_method_title, $old_payment_method, $this->subscription );
		$filtered_new_payment_method_title = (string) apply_filters( 'woocommerce_subscription_note_new_payment_method_title', $new_payment_method_title, $new_payment_method, $this->subscription );

		$this->assertEquals( $filtered_old_payment_method_title, $old_payment_method_title );
		$this->assertEquals( $filtered_new_payment_method_title, $new_payment_method_title );
	}

	/**
	 * Case: Same payment method, different last 4, new payment is saved.
	 * expect old and new title to be modified. Renewal order is updated.
	 */
	public function test_failed_renewal_using_saved_payment() {
		$old_payment_method       = WC_Payment_Gateway_WCPay::GATEWAY_ID;
		$new_payment_method       = WC_Payment_Gateway_WCPay::GATEWAY_ID;
		$old_payment_method_title = 'cc';
		$new_payment_method_title = 'cc';

		$this->renewal_order->update_meta_data( '_old_payment_method', $old_payment_method );
		$this->renewal_order->update_meta_data( '_old_payment_method_title', $old_payment_method_title );
		$this->renewal_order->set_payment_method( $new_payment_method );
		$this->renewal_order->set_payment_method_title( $new_payment_method_title );

		$_POST['payment_method']                      = WC_Payment_Gateway_WCPay::GATEWAY_ID;
		$_POST[ $this->post_payment_token_parameter ] = $this->token2->get_id();

		$old_payment_method_title_modified = (string) apply_filters( 'woocommerce_subscription_note_old_payment_method_title', $old_payment_method_title, $old_payment_method, $this->subscription );
		$new_payment_method_title_modified = (string) apply_filters( 'woocommerce_subscription_note_new_payment_method_title', $new_payment_method_title, $new_payment_method, $this->subscription );
		$this->assertContains( $this->last4digits[1], $old_payment_method_title_modified );
		$this->assertContains( $this->last4digits[2], $new_payment_method_title_modified );
	}

	public function test_failed_renewal_using_new_payment_method() {
		$old_payment_method       = WC_Payment_Gateway_WCPay::GATEWAY_ID;
		$new_payment_method       = WC_Payment_Gateway_WCPay::GATEWAY_ID;
		$old_payment_method_title = 'cc';
		$new_payment_method_title = 'cc';

		$payment_method_id                             = 'test-payment-method-id';
		$_POST[ $this->post_payment_token_parameter ]  = '';
		$_POST[ $this->post_payment_method_parameter ] = $payment_method_id;
		$this->mock_api_client
			->expects( $this->once() )
			->method( 'get_payment_method' )
			->with( $payment_method_id )
			->willReturn(
				[
					'card' => [
						'last4' => $this->last4digits[3],
					],
				]
			);
		$old_payment_method_title_modified = (string) apply_filters( 'woocommerce_subscription_note_old_payment_method_title', $old_payment_method_title, $old_payment_method, $this->subscription );
		$new_payment_method_title_modified = (string) apply_filters( 'woocommerce_subscription_note_new_payment_method_title', $new_payment_method_title, $new_payment_method, $this->subscription );
		$this->assertContains( $this->last4digits[1], $old_payment_method_title_modified );
		$this->assertContains( $this->last4digits[3], $new_payment_method_title_modified );
	}

	/**
	 * Case: Same payment method, different last 4, new payment is saved.
	 * expect old and new title to be modified. Subscription order is updated.
	 */
	public function test_subscriptions_order_using_saved_payment() {
		$old_payment_method       = WC_Payment_Gateway_WCPay::GATEWAY_ID;
		$new_payment_method       = WC_Payment_Gateway_WCPay::GATEWAY_ID;
		$old_payment_method_title = 'cc';
		$new_payment_method_title = 'cc';

		$this->subscription->update_meta_data( '_old_payment_method', $old_payment_method );
		$this->subscription->update_meta_data( '_old_payment_method_title', $old_payment_method_title );
		$this->subscription->set_payment_method( $new_payment_method );
		$this->subscription->set_payment_method_title( $new_payment_method_title );

		$_POST['payment_method']                      = WC_Payment_Gateway_WCPay::GATEWAY_ID;
		$_POST[ $this->post_payment_token_parameter ] = $this->token2->get_id();

		$old_payment_method_title_modified = (string) apply_filters( 'woocommerce_subscription_note_old_payment_method_title', $old_payment_method_title, $old_payment_method, $this->subscription );
		$new_payment_method_title_modified = (string) apply_filters( 'woocommerce_subscription_note_new_payment_method_title', $new_payment_method_title, $new_payment_method, $this->subscription );
		$this->assertContains( $this->last4digits[1], $old_payment_method_title_modified );
		$this->assertContains( $this->last4digits[2], $new_payment_method_title_modified );
	}

	/**
	 * Case: same payment method, different last 4, new payment is *not*
	 * saved. expect api call called once and both old and new title to be
	 * modified. Subscription order is updated.
	 */
	public function test_subscriptions_order_using_new_payment_method() {
		$old_payment_method       = WC_Payment_Gateway_WCPay::GATEWAY_ID;
		$new_payment_method       = WC_Payment_Gateway_WCPay::GATEWAY_ID;
		$old_payment_method_title = 'cc';
		$new_payment_method_title = 'cc';

		$payment_method_id                             = 'test-payment-method-id';
		$_POST[ $this->post_payment_token_parameter ]  = '';
		$_POST[ $this->post_payment_method_parameter ] = $payment_method_id;
		$this->mock_api_client
			->expects( $this->once() )
			->method( 'get_payment_method' )
			->with( $payment_method_id )
			->willReturn(
				[
					'card' => [
						'last4' => $this->last4digits[3],
					],
				]
			);
		$old_payment_method_title_modified = (string) apply_filters( 'woocommerce_subscription_note_old_payment_method_title', $old_payment_method_title, $old_payment_method, $this->subscription );
		$new_payment_method_title_modified = (string) apply_filters( 'woocommerce_subscription_note_new_payment_method_title', $new_payment_method_title, $new_payment_method, $this->subscription );
		$this->assertContains( $this->last4digits[1], $old_payment_method_title_modified );
		$this->assertContains( $this->last4digits[3], $new_payment_method_title_modified );
	}

	/**
	 * Case: same payment method, different last 4, new payment is *not*
	 * saved. expect api call called once and both old and new title to be
	 * modified. Subscription order is updated. Payment method is flagged to
	 * be changed.
	 */
	public function test_subscriptions_order_using_new_payment_method_flagged_as_change() {
		$old_payment_method       = WC_Payment_Gateway_WCPay::GATEWAY_ID;
		$new_payment_method       = WC_Payment_Gateway_WCPay::GATEWAY_ID;
		$old_payment_method_title = 'cc';
		$new_payment_method_title = 'cc';

		$payment_method_id                             = 'test-payment-method-id';
		$_POST[ $this->post_payment_token_parameter ]  = '';
		$_POST[ $this->post_payment_method_parameter ] = $payment_method_id;

		// Simulate is_changing_payment_method_for_subscription being true.
		$_GET['change_payment_method'] = 10;
		WC_Subscriptions::set_wcs_is_subscription(
			function ( $order ) {
				return true;
			}
		);

		// is_changing_payment_method_for_subscription flow expects WC_Order
		// instance to create Payment_Information instance from. However our
		// mock WC_Subscription is not inherited from WC_Order, therefore we
		// provide a separate mock instance for this test case.
		$mock_subscription = $this->createMock( WC_Order::class );
		$mock_subscription->expects( $this->once() )
				->method( 'get_payment_tokens' )
				->will( $this->returnValue( [ $this->token1->get_id(), $this->token2->get_id() ] ) );

		$this->mock_api_client
			->expects( $this->once() )
			->method( 'get_payment_method' )
			->with( $payment_method_id )
			->willReturn(
				[
					'card' => [
						'last4' => $this->last4digits[3],
					],
				]
			);

		$old_payment_method_title_modified = (string) apply_filters( 'woocommerce_subscription_note_old_payment_method_title', $old_payment_method_title, $old_payment_method, $mock_subscription );
		$new_payment_method_title_modified = (string) apply_filters( 'woocommerce_subscription_note_new_payment_method_title', $new_payment_method_title, $new_payment_method, $mock_subscription );
		$this->assertContains( $this->last4digits[1], $old_payment_method_title_modified );
		$this->assertContains( $this->last4digits[3], $new_payment_method_title_modified );

		// If previous method tokens are unavailable, payment method title
		// should not change.
		$mock_subscription = $this->createMock( WC_Order::class );
		$mock_subscription->expects( $this->once() )
				->method( 'get_payment_tokens' )
				->will( $this->returnValue( [ $this->token1->get_id() ] ) );

		$old_payment_method_title_modified = (string) apply_filters( 'woocommerce_subscription_note_old_payment_method_title', $old_payment_method_title, $old_payment_method, $mock_subscription );
		$this->assertEquals( $old_payment_method_title, $old_payment_method_title_modified );
	}

	/**
	 * Case: different payment method. expect both old and new title not
	 * modified.
	 */
	public function test_new_payment_method_non_wc_pay() {
		$old_payment_method       = WC_Payment_Gateway_WCPay::GATEWAY_ID;
		$new_payment_method       = WC_Payment_Gateway_WCPay::GATEWAY_ID;
		$old_payment_method_title = 'cc';
		$new_payment_method_title = 'cc';

		$old_payment_method = WC_Payment_Gateway_WCPay::GATEWAY_ID;
		$new_payment_method = 'non-wc-pay';
		$this->subscription->update_meta_data( '_old_payment_method', $old_payment_method );
		$this->subscription->set_payment_method( $new_payment_method );
		$old_payment_method_title_modified = (string) apply_filters( 'woocommerce_subscription_note_old_payment_method_title', $old_payment_method_title, $old_payment_method, $this->subscription );
		$new_payment_method_title_modified = (string) apply_filters( 'woocommerce_subscription_note_new_payment_method_title', $new_payment_method_title, $new_payment_method, $this->subscription );
		$this->assertContains( $this->last4digits[1], $old_payment_method_title_modified );
		$this->assertEquals( $new_payment_method_title, $new_payment_method_title_modified );
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
