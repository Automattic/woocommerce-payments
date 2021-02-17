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

		$this->wcpay_gateway = new \WC_Payment_Gateway_WCPay_Subscriptions_Compat(
			$this->mock_api_client,
			$this->wcpay_account,
			$this->mock_customer_service,
			$this->mock_token_service,
			$this->mock_action_scheduler_service
		);
	}

	public function test_update_payment_method_order_note_non_wcpay() {
		$subscription = new WC_Subscription();

		$old_payment_method_title = 'old_payment_method_title';
		$old_payment_method       = 'non-wc-pay';
		$new_payment_method_title = 'new_payment_method_title';
		$new_payment_method       = 'non-wc-pay';

		$filtered_old_payment_method_title = (string) apply_filters( 'woocommerce_subscription_note_old_payment_method_title', $old_payment_method_title, $old_payment_method, $subscription );
		$filtered_new_payment_method_title = (string) apply_filters( 'woocommerce_subscription_note_new_payment_method_title', $new_payment_method_title, $new_payment_method, $subscription );

		$this->assertEquals( $filtered_old_payment_method_title, $old_payment_method_title );
		$this->assertEquals( $filtered_new_payment_method_title, $new_payment_method_title );
	}

	public function test_update_payment_method_order_note_failed_renewal_using_saved_payment() {
		$last4_1 = '1122';
		$last4_2 = '3344';
		$last4_3 = '5566';

		$renewal_order = WC_Helper_Order::create_order( self::USER_ID );

		// subscription order.
		$subscription = new WC_Subscription();
		$subscription->set_last_order( $renewal_order->get_id() );

		// first payment token as the failed / old payment.
		$token1 = WC_Helper_Token::create_token( self::PAYMENT_METHOD_ID, self::USER_ID );
		$token1->set_last4( $last4_1 );
		$token1->save();

		// second payment token as the new payment.
		$token2 = WC_Helper_Token::create_token( self::PAYMENT_METHOD_ID, self::USER_ID );
		$token2->set_last4( $last4_2 );
		$token2->save();

		// add token to renewal order.
		$renewal_order->add_payment_token( $token1 );
		$renewal_order->add_payment_token( $token2 );

		// case 1: same payment method, different last 4, new payment is saved. expect old and new title to be modified.
		$old_payment_method       = WC_Payment_Gateway_WCPay::GATEWAY_ID;
		$new_payment_method       = WC_Payment_Gateway_WCPay::GATEWAY_ID;
		$old_payment_method_title = 'cc';
		$new_payment_method_title = 'cc';
		$subscription->update_meta_data( '_old_payment_method', $old_payment_method );
		$subscription->update_meta_data( '_old_payment_method_title', $old_payment_method_title );
		$subscription->set_payment_method( $new_payment_method );
		$subscription->set_payment_method_title( $new_payment_method_title );

		$post_payment_token_parameter  = 'wc-' . WC_Payment_Gateway_WCPay::GATEWAY_ID . '-payment-token';
		$post_payment_method_parameter = 'wcpay-payment-method';

		$_POST[ $post_payment_token_parameter ] = $token2->get_id();

		$old_payment_method_title_modified = (string) apply_filters( 'woocommerce_subscription_note_old_payment_method_title', $old_payment_method_title, $old_payment_method, $subscription );
		$new_payment_method_title_modified = (string) apply_filters( 'woocommerce_subscription_note_new_payment_method_title', $new_payment_method_title, $new_payment_method, $subscription );
		$this->assertContains( $last4_1, $old_payment_method_title_modified );
		$this->assertContains( $last4_2, $new_payment_method_title_modified );

		// case 2: same payment method, different last 4, new payment is *not* saved. expect api call called once and both old and new title to be modified.
		$payment_method_id                       = 'test-payment-method-id';
		$_POST[ $post_payment_token_parameter ]  = '';
		$_POST[ $post_payment_method_parameter ] = $payment_method_id;
		$this->mock_api_client
			->expects( $this->once() )
			->method( 'get_payment_method' )
			->with( $payment_method_id )
			->willReturn(
				[
					'card' => [
						'last4' => $last4_3,
					],
				]
			);
		$old_payment_method_title_modified = (string) apply_filters( 'woocommerce_subscription_note_old_payment_method_title', $old_payment_method_title, $old_payment_method, $subscription );
		$new_payment_method_title_modified = (string) apply_filters( 'woocommerce_subscription_note_new_payment_method_title', $new_payment_method_title, $new_payment_method, $subscription );
		$this->assertContains( $last4_1, $old_payment_method_title_modified );
		$this->assertContains( $last4_3, $new_payment_method_title_modified );

		// case 4: different payment method. expect both old and new title not modified.
		$old_payment_method = WC_Payment_Gateway_WCPay::GATEWAY_ID;
		$new_payment_method = 'non-wc-pay';
		$subscription->update_meta_data( '_old_payment_method', $old_payment_method );
		$subscription->set_payment_method( $new_payment_method );
		$old_payment_method_title_modified = (string) apply_filters( 'woocommerce_subscription_note_old_payment_method_title', $old_payment_method_title, $old_payment_method, $subscription );
		$new_payment_method_title_modified = (string) apply_filters( 'woocommerce_subscription_note_new_payment_method_title', $new_payment_method_title, $new_payment_method, $subscription );
		$this->assertContains( $last4_1, $old_payment_method_title_modified );
		$this->assertEquals( $new_payment_method_title, $new_payment_method_title_modified );
	}

	/**
//	public function test_update_payment_method_order_note_failed_renewal_using_new_payment() {
//	}
//
//	public function test_update_payment_method_order_note_failed_renewal_using_new_to_be_saved_payment() {
//	}
//
//	public function test_update_payment_method_order_note_for_subscriptions_order_using_new_payment() {
//	}
//
//	public function test_update_payment_method_order_note_for_subscriptions_order_using_saved_payment() {
//	}
//
//	public function test_update_payment_method_order_note() {
//		// prepare values and objects to test.
//		$last4_1 = '1122';
//		$last4_2 = '3344';
//		$last4_3 = '5566';
//
//		$renewal_order = WC_Helper_Order::create_order( self::USER_ID );
//
//		// first payment token as the failed / old payment.
//		$token1 = WC_Helper_Token::create_token( self::PAYMENT_METHOD_ID, self::USER_ID );
//		$token1->set_last4( $last4_1 );
//		$token1->save();
//
//		// second payment token as the new payment.
//		$token2 = WC_Helper_Token::create_token( self::PAYMENT_METHOD_ID, self::USER_ID );
//		$token2->set_last4( $last4_2 );
//		$token2->save();
//
//		// add token to renewal order.
//		$renewal_order->add_payment_token( $token1 );
//		$renewal_order->add_payment_token( $token2 );
//
//		// subscription order.
//		$subscription = new WC_Subscription();
//		$subscription->set_last_order( $renewal_order->get_id() );
//
//		// case 1: same payment method, different last 4, new payment is saved. expect old and new title to be modified.
//		$old_payment_method       = WC_Payment_Gateway_WCPay::GATEWAY_ID;
//		$new_payment_method       = WC_Payment_Gateway_WCPay::GATEWAY_ID;
//		$old_payment_method_title = 'cc';
//		$new_payment_method_title = 'cc';
//		$subscription->update_meta_data( '_old_payment_method', $old_payment_method );
//		$subscription->update_meta_data( '_old_payment_method_title', $old_payment_method_title );
//		$subscription->set_payment_method( $new_payment_method );
//		$subscription->set_payment_method_title( $new_payment_method_title );
//
//		$post_payment_token_parameter  = 'wc-' . WC_Payment_Gateway_WCPay::GATEWAY_ID . '-payment-token';
//		$post_payment_method_parameter = 'wcpay-payment-method';
//
//		$_POST[ $post_payment_token_parameter ] = $token2->get_id();
//
//		$old_payment_method_title_modified = (string) apply_filters( 'woocommerce_subscription_note_old_payment_method_title', $old_payment_method_title, $old_payment_method, $subscription );
//		$new_payment_method_title_modified = (string) apply_filters( 'woocommerce_subscription_note_new_payment_method_title', $new_payment_method_title, $new_payment_method, $subscription );
//		$this->assertContains( $last4_1, $old_payment_method_title_modified );
//		$this->assertContains( $last4_2, $new_payment_method_title_modified );
//
//		// case 2: same payment method, different last 4, new payment is *not* saved. expect api call called once and both old and new title to be modified.
//		$payment_method_id                       = 'test-payment-method-id';
//		$_POST[ $post_payment_token_parameter ]  = '';
//		$_POST[ $post_payment_method_parameter ] = $payment_method_id;
//		$this->mock_api_client
//			->expects( $this->once() )
//			->method( 'get_payment_method' )
//			->with( $payment_method_id )
//			->willReturn(
//				[
//					'card' => [
//						'last4' => $last4_3,
//					],
//				]
//			);
//		$old_payment_method_title_modified = (string) apply_filters( 'woocommerce_subscription_note_old_payment_method_title', $old_payment_method_title, $old_payment_method, $subscription );
//		$new_payment_method_title_modified = (string) apply_filters( 'woocommerce_subscription_note_new_payment_method_title', $new_payment_method_title, $new_payment_method, $subscription );
//		$this->assertContains( $last4_1, $old_payment_method_title_modified );
//		$this->assertContains( $last4_3, $new_payment_method_title_modified );
//
//		// case 3: non wcpay. expect both old and new title not modified.
//		$old_payment_method = 'non-wc-pay';
//		$new_payment_method = 'non-wc-pay';
//		$subscription->update_meta_data( '_old_payment_method', $old_payment_method );
//		$subscription->set_payment_method( $new_payment_method );
//		$old_payment_method_title_modified = (string) apply_filters( 'woocommerce_subscription_note_old_payment_method_title', $old_payment_method_title, $old_payment_method, $subscription );
//		$new_payment_method_title_modified = (string) apply_filters( 'woocommerce_subscription_note_new_payment_method_title', $new_payment_method_title, $new_payment_method, $subscription );
//		$this->assertEquals( $old_payment_method_title, $old_payment_method_title_modified );
//		$this->assertEquals( $new_payment_method_title, $new_payment_method_title_modified );
//
//		// case 4: different payment method. expect both old and new title not modified.
//		$old_payment_method = WC_Payment_Gateway_WCPay::GATEWAY_ID;
//		$new_payment_method = 'non-wc-pay';
//		$subscription->update_meta_data( '_old_payment_method', $old_payment_method );
//		$subscription->set_payment_method( $new_payment_method );
//		$old_payment_method_title_modified = (string) apply_filters( 'woocommerce_subscription_note_old_payment_method_title', $old_payment_method_title, $old_payment_method, $subscription );
//		$new_payment_method_title_modified = (string) apply_filters( 'woocommerce_subscription_note_new_payment_method_title', $new_payment_method_title, $new_payment_method, $subscription );
//		$this->assertEquals( $old_payment_method_title, $old_payment_method_title_modified );
//		$this->assertEquals( $new_payment_method_title, $new_payment_method_title_modified );
//	}
	 */

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
