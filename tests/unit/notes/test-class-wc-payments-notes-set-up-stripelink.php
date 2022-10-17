<?php
/**
 * Class WC_Payments_Notes_Set_Up_StripeLink_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\Session_Rate_Limiter;

/**
 * Class WC_Payments_Notes_Set_Up_StripeLink tests.
 */
class WC_Payments_Notes_Set_Up_StripeLink_Test extends WCPAY_UnitTestCase {
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
	 * WC_Payments_Order_Service.
	 *
	 * @var WC_Payments_Order_Service
	 */
	private $order_service;

	/**
	 * Mock WC_Payments_Account.
	 *
	 * @var WC_Payments_Account|PHPUnit_Framework_MockObject_MockObject
	 */
	private $mock_wcpay_account;

	public function set_up() {
		parent::set_up();

		require_once WCPAY_ABSPATH . 'includes/notes/class-wc-payments-notes-set-up-stripelink.php';

		$this->mock_api_client = $this->getMockBuilder( 'WC_Payments_API_Client' )
			->disableOriginalConstructor()
			->getMock();

		$this->mock_wcpay_account = $this->createMock( WC_Payments_Account::class );

		$this->mock_customer_service = $this->getMockBuilder( 'WC_Payments_Customer_Service' )
			->disableOriginalConstructor()
			->getMock();

		$this->mock_token_service = $this->getMockBuilder( 'WC_Payments_Token_Service' )
			->disableOriginalConstructor()
			->getMock();

		$this->mock_action_scheduler_service = $this->getMockBuilder( 'WC_Payments_Action_Scheduler_Service' )
			->disableOriginalConstructor()
			->getMock();

		$this->mock_rate_limiter = $this->createMock( Session_Rate_Limiter::class );

		$this->order_service = new WC_Payments_Order_Service( $this->mock_api_client );

		$this->mock_wcpay_gateway = $this->getMockBuilder( '\WC_Payment_Gateway_WCPay' )
			->setConstructorArgs(
				[
					$this->mock_api_client,
					$this->mock_wcpay_account,
					$this->mock_customer_service,
					$this->mock_token_service,
					$this->mock_action_scheduler_service,
					$this->mock_rate_limiter,
					$this->order_service,
				]
			)
			->setMethods(
				[
					'get_upe_available_payment_methods',
					'get_payment_method_ids_enabled_at_checkout',
				]
			)
			->getMock();
	}

	public function tear_down() {
		delete_option( '_wcpay_feature_upe' );

		parent::tear_down();
	}

	public function test_stripelink_setup_get_note() {
		$this->mock_gateway_data( '1', [ 'card', 'link' ], [ 'card' ] );

		$note = \WC_Payments_Notes_Set_Up_StripeLink::get_note();

		$this->assertSame( 'Increase conversion at checkout', $note->get_title() );
		$this->assertSame( 'Reduce cart abandonment and create a frictionless checkout experience with Link by Stripe. Link autofills your customer’s payment and shipping details so they can check out in just six seconds with the Link optimized experience. That’s 9x faster than shoppers who don’t use Link. Link increases conversion rates by over 7% for logged-in Link customers.', $note->get_content() );
		$this->assertSame( 'info', $note->get_type() );
		$this->assertSame( 'wc-payments-notes-set-up-stripe-link', $note->get_name() );
		$this->assertSame( 'woocommerce-payments', $note->get_source() );

		list( $set_up_action ) = $note->get_actions();
		$this->assertSame( 'wc-payments-notes-set-up-stripe-link', $set_up_action->name );
		$this->assertSame( 'Set up now', $set_up_action->label );
		$this->assertStringStartsWith( 'https://woocommerce.com/document/payments/woocommerce-payments-stripe-link/', $set_up_action->query );
	}

	public function test_stripelink_setup_note_null_when_upe_disabled() {
		$this->mock_gateway_data( '0', [ 'card', 'link' ], [ 'card' ] );

		$note = \WC_Payments_Notes_Set_Up_StripeLink::get_note();

		$this->assertNull( $note );
	}

	public function test_stripelink_setup_note_null_when_link_not_available() {
		$this->mock_gateway_data( '1', [ 'card' ], [ 'card' ] );

		$note = \WC_Payments_Notes_Set_Up_StripeLink::get_note();

		$this->assertNull( $note );
	}

	public function test_stripelink_setup_note_null_when_link_enabled() {
		$this->mock_gateway_data( '1', [ 'card', 'link' ], [ 'card', 'link' ] );

		$note = \WC_Payments_Notes_Set_Up_StripeLink::get_note();

		$this->assertNull( $note );
	}

	public function mock_gateway_data( $upe_enabled = '0', $available_methods, $enabled_methods ) {
		update_option( '_wcpay_feature_upe', $upe_enabled );

		$this->mock_wcpay_gateway
			->expects( $this->any() )
			->method( 'get_upe_available_payment_methods' )
			->willReturn( $available_methods );

		$this->mock_wcpay_gateway
			->expects( $this->any() )
			->method( 'get_payment_method_ids_enabled_at_checkout' )
			->willReturn( $enabled_methods );

		\WC_Payments_Notes_Set_Up_StripeLink::set_gateway( $this->mock_wcpay_gateway );

		WC_Payments_Notes_Set_Up_StripeLink::possibly_add_note();
	}
}
