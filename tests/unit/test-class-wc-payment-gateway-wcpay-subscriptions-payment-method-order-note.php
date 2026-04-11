<?php
/**
 * Class WC_Payment_Gateway_WCPay_Subscriptions_Payment_Method_Order_Note_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\Duplicate_Payment_Prevention_Service;
use WCPay\Duplicates_Detection_Service;
use WCPay\Payment_Methods\UPE_Payment_Method;
use WCPay\PaymentMethods\Configs\Definitions\CardDefinition;
use WCPay\Session_Rate_Limiter;

/**
 * WC_Payment_Gateway_WCPay unit tests.
 */
class WC_Payment_Gateway_WCPay_Subscriptions_Payment_Method_Order_Note_Test extends WCPAY_UnitTestCase {
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
	 * Mock WC_Payments_Account.
	 *
	 * @var WC_Payments_Account|PHPUnit_Framework_MockObject_MockObject
	 */
	private $mock_wcpay_account;

	public function tear_down() {
		parent::tear_down();

		// So that the gateway hooks are re-attached for the next test, we need to reset the flag that prevents the integration hooks from being attached.
		$reflection = new \ReflectionClass( $this->wcpay_gateway );
		$property   = $reflection->getProperty( 'has_attached_integration_hooks' );
		$property->setAccessible( true );
		$property->setValue( $this->wcpay_gateway, false );
	}

	public function set_up() {
		parent::set_up();

		wp_set_current_user( self::USER_ID );

		$this->mock_api_client = $this->getMockBuilder( 'WC_Payments_API_Client' )
			->disableOriginalConstructor()
			->getMock();

		$this->mock_wcpay_account = $this->createMock( WC_Payments_Account::class );
		$this->mock_wcpay_account
			->method( 'get_account_default_currency' )
			->willReturn( 'USD' );

		$this->mock_customer_service = $this->getMockBuilder( 'WC_Payments_Customer_Service' )
			->disableOriginalConstructor()
			->getMock();

		$this->mock_token_service = $this->getMockBuilder( 'WC_Payments_Token_Service' )
			->disableOriginalConstructor()
			->getMock();

		$this->mock_action_scheduler_service = $this->getMockBuilder( 'WC_Payments_Action_Scheduler_Service' )
			->disableOriginalConstructor()
			->getMock();

		$this->mock_session_rate_limiter = $this->getMockBuilder( Session_Rate_Limiter::class )
			->disableOriginalConstructor()
			->getMock();

		$this->mock_order_service = $this->getMockBuilder( 'WC_Payments_Order_Service' )
			->disableOriginalConstructor()
			->getMock();

		$mock_dpps = $this->createMock( Duplicate_Payment_Prevention_Service::class );

		$mock_payment_method = $this->getMockBuilder( UPE_Payment_Method::class )
			->setConstructorArgs( [ $this->mock_token_service, CardDefinition::class ] )
			->onlyMethods( [ 'is_subscription_item_in_cart' ] )
			->getMock();

		$this->wcpay_gateway = new \WC_Payment_Gateway_WCPay(
			$this->mock_api_client,
			$this->mock_wcpay_account,
			$this->mock_customer_service,
			$this->mock_token_service,
			$this->mock_action_scheduler_service,
			$mock_payment_method,
			[ 'card' => $mock_payment_method ],
			$this->mock_order_service,
			$mock_dpps,
			$this->createMock( WC_Payments_Localization_Service::class ),
			$this->createMock( WC_Payments_Fraud_Service::class ),
			$this->createMock( Duplicates_Detection_Service::class ),
			$this->mock_session_rate_limiter
		);
		$this->wcpay_gateway->init_hooks();

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
		$this->assertStringContainsString( $this->last4digits[1], $old_payment_method_title_modified );
		$this->assertStringContainsString( $this->last4digits[2], $new_payment_method_title_modified );
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
		$this->assertStringContainsString( $this->last4digits[1], $old_payment_method_title_modified );
		$this->assertStringContainsString( $this->last4digits[3], $new_payment_method_title_modified );
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
		$this->assertStringContainsString( $this->last4digits[1], $old_payment_method_title_modified );
		$this->assertStringContainsString( $this->last4digits[2], $new_payment_method_title_modified );
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
		$this->assertStringContainsString( $this->last4digits[1], $old_payment_method_title_modified );
		$this->assertStringContainsString( $this->last4digits[3], $new_payment_method_title_modified );
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

		$old_payment_method_title_modified = $this->wcpay_gateway->get_specific_old_payment_method_title( $old_payment_method_title, $old_payment_method, $mock_subscription );
		$new_payment_method_title_modified = $this->wcpay_gateway->get_specific_new_payment_method_title( $new_payment_method_title, $new_payment_method, $mock_subscription );
		$this->assertStringContainsString( $this->last4digits[1], $old_payment_method_title_modified );
		$this->assertStringContainsString( $this->last4digits[3], $new_payment_method_title_modified );

		// If previous method tokens are unavailable, payment method title
		// should not change.
		$mock_subscription = $this->createMock( WC_Order::class );
		$mock_subscription->expects( $this->once() )
			->method( 'get_payment_tokens' )
			->will( $this->returnValue( [ $this->token1->get_id() ] ) );

		$old_payment_method_title_modified = $this->wcpay_gateway->get_specific_old_payment_method_title( $old_payment_method_title, $old_payment_method, $mock_subscription );
		$this->assertEquals( $old_payment_method_title, $old_payment_method_title_modified );
	}

	/**
	 * Case: different payment method. expect both old and new title not
	 * modified.
	 */
	public function test_new_payment_method_non_wc_pay() {
		$old_payment_method_title = 'cc';
		$new_payment_method_title = 'cc';

		$old_payment_method = WC_Payment_Gateway_WCPay::GATEWAY_ID;
		$new_payment_method = 'non-wc-pay';
		$this->subscription->update_meta_data( '_old_payment_method', $old_payment_method );
		$this->subscription->set_payment_method( $new_payment_method );
		$old_payment_method_title_modified = (string) apply_filters( 'woocommerce_subscription_note_old_payment_method_title', $old_payment_method_title, $old_payment_method, $this->subscription );
		$new_payment_method_title_modified = (string) apply_filters( 'woocommerce_subscription_note_new_payment_method_title', $new_payment_method_title, $new_payment_method, $this->subscription );
		$this->assertStringContainsString( $this->last4digits[1], $old_payment_method_title_modified );
		$this->assertEquals( $new_payment_method_title, $new_payment_method_title_modified );
	}

	public function test_amazon_pay_old_payment_method_displays_email() {
		$old_token = WC_Helper_Token::create_amazon_pay_token( 'pm_amazon_1', self::USER_ID, 'customer@example.com' );

		$newer_token = WC_Helper_Token::create_token( 'pm_card_new', self::USER_ID );
		$newer_token->set_last4( '9999' );
		$newer_token->save();

		$this->renewal_order->add_payment_token( $old_token );
		$this->renewal_order->add_payment_token( $newer_token );

		$old_payment_method_title_modified = (string) apply_filters(
			'woocommerce_subscription_note_old_payment_method_title',
			'Amazon Pay',
			'woocommerce_payments_amazon_pay',
			$this->subscription
		);

		$this->assertStringContainsString( '***omer@example.com', $old_payment_method_title_modified );
		$this->assertStringContainsString( 'Amazon Pay', $old_payment_method_title_modified );
	}

	public function test_amazon_pay_new_saved_payment_method_displays_email() {
		$amazon_pay_gateway_id = 'woocommerce_payments_amazon_pay';

		$new_token = WC_Helper_Token::create_amazon_pay_token( 'pm_amazon_1', self::USER_ID, 'buyer@amazon.com' );

		$this->renewal_order->set_payment_method( $amazon_pay_gateway_id );
		$this->renewal_order->set_payment_method_title( 'Amazon Pay' );

		$_POST['payment_method']                                    = $amazon_pay_gateway_id;
		$_POST[ 'wc-' . $amazon_pay_gateway_id . '-payment-token' ] = $new_token->get_id();

		$new_payment_method_title_modified = (string) apply_filters(
			'woocommerce_subscription_note_new_payment_method_title',
			'Amazon Pay',
			$amazon_pay_gateway_id,
			$this->subscription
		);

		$this->assertStringContainsString( '***uyer@amazon.com', $new_payment_method_title_modified );
		$this->assertStringContainsString( 'Amazon Pay', $new_payment_method_title_modified );
	}

	public function test_switching_from_card_to_amazon_pay() {
		$amazon_pay_gateway_id    = 'woocommerce_payments_amazon_pay';
		$old_payment_method       = WC_Payment_Gateway_WCPay::GATEWAY_ID;
		$old_payment_method_title = 'Credit card';
		$new_payment_method_title = 'Amazon Pay';

		// Old payment is a card (already set up in setUp with token1 and token2).
		$new_token = WC_Helper_Token::create_amazon_pay_token( 'pm_amazon_1', self::USER_ID, 'switcher@test.com' );
		$this->renewal_order->add_payment_token( $new_token );

		$_POST['payment_method']                                    = $amazon_pay_gateway_id;
		$_POST[ 'wc-' . $amazon_pay_gateway_id . '-payment-token' ] = $new_token->get_id();

		$old_payment_method_title_modified = (string) apply_filters(
			'woocommerce_subscription_note_old_payment_method_title',
			$old_payment_method_title,
			$old_payment_method,
			$this->subscription
		);
		$new_payment_method_title_modified = (string) apply_filters(
			'woocommerce_subscription_note_new_payment_method_title',
			$new_payment_method_title,
			$amazon_pay_gateway_id,
			$this->subscription
		);

		// Old should show card last4.
		$this->assertStringContainsString( $this->last4digits[2], $old_payment_method_title_modified );
		// New should show Amazon Pay email.
		$this->assertStringContainsString( '***cher@test.com', $new_payment_method_title_modified );
	}

	public function test_link_old_payment_method_displays_email() {
		$old_token = WC_Helper_Token::create_link_token( 'pm_link_1', self::USER_ID, 'customer@example.com' );

		$newer_token = WC_Helper_Token::create_token( 'pm_card_new', self::USER_ID );
		$newer_token->set_last4( '9999' );
		$newer_token->save();

		$this->renewal_order->add_payment_token( $old_token );
		$this->renewal_order->add_payment_token( $newer_token );

		$old_payment_method_title_modified = (string) apply_filters(
			'woocommerce_subscription_note_old_payment_method_title',
			'Stripe Link',
			WC_Payment_Gateway_WCPay::GATEWAY_ID,
			$this->subscription
		);

		$this->assertStringContainsString( '***omer@example.com', $old_payment_method_title_modified );
		$this->assertStringContainsString( 'Stripe Link', $old_payment_method_title_modified );
	}

	public function test_link_new_saved_payment_method_displays_email() {
		$new_token = WC_Helper_Token::create_link_token( 'pm_link_1', self::USER_ID, 'buyer@link.com' );

		$this->renewal_order->set_payment_method( WC_Payment_Gateway_WCPay::GATEWAY_ID );
		$this->renewal_order->set_payment_method_title( 'Stripe Link' );

		$_POST['payment_method']                      = WC_Payment_Gateway_WCPay::GATEWAY_ID;
		$_POST[ $this->post_payment_token_parameter ] = $new_token->get_id();

		$new_payment_method_title_modified = (string) apply_filters(
			'woocommerce_subscription_note_new_payment_method_title',
			'Stripe Link',
			WC_Payment_Gateway_WCPay::GATEWAY_ID,
			$this->subscription
		);

		$this->assertStringContainsString( '***uyer@link.com', $new_payment_method_title_modified );
		$this->assertStringContainsString( 'Stripe Link', $new_payment_method_title_modified );
	}

	public function test_switching_from_card_to_link() {
		$old_payment_method       = WC_Payment_Gateway_WCPay::GATEWAY_ID;
		$old_payment_method_title = 'Credit card';
		$new_payment_method_title = 'Stripe Link';

		// Old payment is a card (already set up in setUp with token1 and token2).
		$new_token = WC_Helper_Token::create_link_token( 'pm_link_1', self::USER_ID, 'switcher@test.com' );
		$this->renewal_order->add_payment_token( $new_token );

		$_POST['payment_method']                      = WC_Payment_Gateway_WCPay::GATEWAY_ID;
		$_POST[ $this->post_payment_token_parameter ] = $new_token->get_id();

		$old_payment_method_title_modified = (string) apply_filters(
			'woocommerce_subscription_note_old_payment_method_title',
			$old_payment_method_title,
			$old_payment_method,
			$this->subscription
		);
		$new_payment_method_title_modified = (string) apply_filters(
			'woocommerce_subscription_note_new_payment_method_title',
			$new_payment_method_title,
			WC_Payment_Gateway_WCPay::GATEWAY_ID,
			$this->subscription
		);

		// Old should show card last4.
		$this->assertStringContainsString( $this->last4digits[2], $old_payment_method_title_modified );
		// New should show Link email.
		$this->assertStringContainsString( '***cher@test.com', $new_payment_method_title_modified );
	}

	public function test_switching_from_link_to_card_uses_stripe_link_title_even_when_gateway_says_card() {
		// WC Subscriptions passes the gateway title ("Card") as the default, not "Stripe Link".
		// Our filter should produce "Stripe Link (email)" regardless.
		$old_payment_method       = WC_Payment_Gateway_WCPay::GATEWAY_ID;
		$old_payment_method_title = 'Card';
		$new_payment_method_title = 'Credit card';

		$link_token = WC_Helper_Token::create_link_token( 'pm_link_1', self::USER_ID, 'linker@test.com' );
		$card_token = WC_Helper_Token::create_token( 'pm_card_new', self::USER_ID );
		$card_token->set_last4( '7777' );
		$card_token->save();

		$this->renewal_order->add_payment_token( $link_token );
		$this->renewal_order->add_payment_token( $card_token );

		$_POST['payment_method']                      = WC_Payment_Gateway_WCPay::GATEWAY_ID;
		$_POST[ $this->post_payment_token_parameter ] = $card_token->get_id();

		$old_payment_method_title_modified = (string) apply_filters(
			'woocommerce_subscription_note_old_payment_method_title',
			$old_payment_method_title,
			$old_payment_method,
			$this->subscription
		);

		// Should say "Stripe Link (email)" not "Card (email)".
		$this->assertStringContainsString( 'Stripe Link', $old_payment_method_title_modified );
		$this->assertStringContainsString( '***nker@test.com', $old_payment_method_title_modified );
		$this->assertStringNotContainsString( 'Card (', $old_payment_method_title_modified );
	}

	public function test_switching_from_link_to_card() {
		$old_payment_method       = WC_Payment_Gateway_WCPay::GATEWAY_ID;
		$old_payment_method_title = 'Stripe Link';
		$new_payment_method_title = 'Credit card';

		// Replace setup's CC tokens with Link + CC tokens on the renewal order.
		$link_token = WC_Helper_Token::create_link_token( 'pm_link_1', self::USER_ID, 'linker@test.com' );
		$card_token = WC_Helper_Token::create_token( 'pm_card_new', self::USER_ID );
		$card_token->set_last4( '7777' );
		$card_token->save();

		$this->renewal_order->add_payment_token( $link_token );
		$this->renewal_order->add_payment_token( $card_token );

		$_POST['payment_method']                      = WC_Payment_Gateway_WCPay::GATEWAY_ID;
		$_POST[ $this->post_payment_token_parameter ] = $card_token->get_id();

		$old_payment_method_title_modified = (string) apply_filters(
			'woocommerce_subscription_note_old_payment_method_title',
			$old_payment_method_title,
			$old_payment_method,
			$this->subscription
		);
		$new_payment_method_title_modified = (string) apply_filters(
			'woocommerce_subscription_note_new_payment_method_title',
			$new_payment_method_title,
			WC_Payment_Gateway_WCPay::GATEWAY_ID,
			$this->subscription
		);

		// Old should show Link email.
		$this->assertStringContainsString( '***nker@test.com', $old_payment_method_title_modified );
		// New should show card brand and last4, not "Credit card ending in".
		$this->assertStringContainsString( 'Visa', $new_payment_method_title_modified );
		$this->assertStringContainsString( '7777', $new_payment_method_title_modified );
	}

	/**
	 * Regression test: when $default is "Link" (subscription's stored title) but the
	 * actual token is a CC, the note should show "Visa ending in XXXX" not "Link ending in XXXX".
	 */
	public function test_cc_token_with_link_default_uses_card_brand_not_link() {
		$old_payment_method       = WC_Payment_Gateway_WCPay::GATEWAY_ID;
		$old_payment_method_title = 'Link';

		// Simulate: subscription had Link, but the second-to-last token is a CC
		// (e.g., from a prior card payment before the Link payment was set up).
		$card_token_old = WC_Helper_Token::create_token( 'pm_card_old', self::USER_ID );
		$card_token_old->set_last4( '4242' );
		$card_token_old->set_card_type( 'visa' );
		$card_token_old->save();

		$card_token_new = WC_Helper_Token::create_token( 'pm_card_new', self::USER_ID );
		$card_token_new->set_last4( '8888' );
		$card_token_new->set_card_type( 'mastercard' );
		$card_token_new->save();

		$this->renewal_order->add_payment_token( $card_token_old );
		$this->renewal_order->add_payment_token( $card_token_new );

		$_POST['payment_method']                      = WC_Payment_Gateway_WCPay::GATEWAY_ID;
		$_POST[ $this->post_payment_token_parameter ] = $card_token_new->get_id();

		$old_payment_method_title_modified = (string) apply_filters(
			'woocommerce_subscription_note_old_payment_method_title',
			$old_payment_method_title,
			$old_payment_method,
			$this->subscription
		);

		// Should say "Visa ending in 4242", NOT "Link ending in 4242".
		$this->assertStringContainsString( 'Visa', $old_payment_method_title_modified );
		$this->assertStringContainsString( '4242', $old_payment_method_title_modified );
		$this->assertStringNotContainsString( 'Link', $old_payment_method_title_modified );
	}
}
