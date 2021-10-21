<?php
/**
 * Class WC_Payments_Subscriptions_Event_Handler
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\Exceptions\Rest_Request_Exception;

/**
 * WC_Payments_Subscriptions_Event_Handler unit tests.
 */
class WC_Payments_Subscriptions_Event_Handler_Test extends WP_UnitTestCase {

	/**
	 * Subscription meta key used to store WCPay subscription's ID.
	 *
	 * @const string
	 */
	const SUBSCRIPTION_ID_META_KEY = '_wcpay_subscription_id';

	/**
	 * Order Invoice meta key used to store WCPay invoice ID.
	 *
	 * @const string
	 */
	const ORDER_INVOICE_ID_KEY = '_wcpay_billing_invoice_id';

	/**
	 * Mock WC_Payments_Invoice_Service.
	 *
	 * @var WC_Payments_Invoice_Service|MockObject
	 */
	private $mock_invoice_service;

	/**
	 * Mock WC_Payments_Subscription_Service
	 *
	 * @var WC_Payments_Subscription_Service
	 */
	private $mock_subscription_service;

	/**
	 * Mock WC_Payments_Subscriptions_Event_Handler.
	 *
	 * @var WC_Payments_Subscriptions_Event_Handler
	 */
	private $subscriptions_event_handler;

	/**
	 * Pre-test setup.
	 */
	public function setUp() {
		parent::setUp();

		$this->mock_invoice_service      = $this->createMock( WC_Payments_Invoice_Service::class );
		$this->mock_subscription_service = $this->createMock( WC_Payments_Subscription_Service::class );

		$this->subscriptions_event_handler = new WC_Payments_Subscriptions_Event_Handler( $this->mock_invoice_service, $this->mock_subscription_service );

		WC_Subscriptions::set_wcs_get_subscription(
			function ( $id ) {
				return false;
			}
		);
	}

	/**
	 * Test exception thrown during get_event_property.
	 */
	public function test_get_event_property_exception() {
		$this->expectException( Rest_Request_Exception::class );
		$this->expectExceptionMessage( 'garbage not found in array' );

		PHPUnit_Utils::call_method(
			$this->subscriptions_event_handler,
			'get_event_property',
			[ [ 'non_empty_key' => 'non_empty_value' ], 'garbage' ]
		);
	}

	/**
	 * Test exception thrown during handle_invoice_upcoming.
	 */
	public function test_handle_invoice_upcoming_exception() {
		$test_body = $this->get_mock_test_body( 'sub_ID_not_exists', 'cus_test1234' );

		$this->expectException( Rest_Request_Exception::class );
		$this->expectExceptionMessage( 'Cannot find subscription to handle the "invoice.upcoming" event.' );

		$this->subscriptions_event_handler->handle_invoice_upcoming( $test_body );
	}

	/**
	 * Test handle_invoice_upcoming when subscription is active.
	 */
	public function test_handle_invoice_upcoming_active() {
		$wcpay_subscription_id = 'sub_invoiceUpcoming';
		$wcpay_customer_id     = 'cus_test1234';
		$test_body             = $this->get_mock_test_body( $wcpay_subscription_id, $wcpay_customer_id );
		$mock_subscription     = new WC_Subscription();
		$mock_subscription->update_meta_data( self::SUBSCRIPTION_ID_META_KEY, $wcpay_subscription_id );
		$mock_subscription->next_payment = time();

		$mock_wcpay_subscription = [
			'subscription'       => $wcpay_subscription_id,
			'current_period_end' => time() + 10000,
		];

		WC_Subscriptions::set_wcs_get_subscription(
			function ( $id ) use ( $mock_subscription ) {
				return $mock_subscription;
			}
		);

		$this->mock_subscription_service->expects( $this->once() )
			->method( 'get_wcpay_subscription' )
			->with( $mock_subscription )
			->willReturn( $mock_wcpay_subscription );

		$this->mock_subscription_service->expects( $this->once() )
			->method( 'update_dates_to_match_wcpay_subscription' )
			->with( $mock_wcpay_subscription, $mock_subscription )
			->willReturn( null );

		$this->subscriptions_event_handler->handle_invoice_upcoming( $test_body );
	}

	/**
	 * Test handle_invoice_upcoming when subscription is suspended.
	 */
	public function test_handle_invoice_upcoming_suspended() {
		$wcpay_subscription_id = 'sub_invoiceUpcomingSuspended';
		$wcpay_customer_id     = 'cus_test4321';
		$test_body             = $this->get_mock_test_body( $wcpay_subscription_id, $wcpay_customer_id );
		$mock_order            = WC_Helper_Order::create_order();
		$mock_subscription     = new WC_Subscription();
		$mock_subscription->update_meta_data( self::SUBSCRIPTION_ID_META_KEY, $wcpay_subscription_id );
		$mock_subscription->next_payment = 0;
		$mock_subscription->end          = 0;
		$mock_subscription->set_parent( $mock_order );

		$mock_wcpay_subscription = [
			'subscription'       => $wcpay_subscription_id,
			'current_period_end' => time() + 10000,
		];

		WC_Subscriptions::set_wcs_get_subscription(
			function ( $id ) use ( $mock_subscription ) {
				return $mock_subscription;
			}
		);

		$this->mock_subscription_service->expects( $this->once() )
			->method( 'suspend_subscription' )
			->with( $mock_subscription )
			->willReturn( null );

		$this->subscriptions_event_handler->handle_invoice_upcoming( $test_body );
	}

	/**
	 * Test handle_invoice_paid when the incoming webhook belongs to a subscription that doesn't exist.
	 */
	public function test_handle_invoice_paid_exception() {
		$test_body = $this->get_mock_test_body( 'sub_ID_no_invoice_paid', 'cus_test1234', 'ii_testInvoiceID' );

		$this->expectException( Rest_Request_Exception::class );
		$this->expectExceptionMessage( 'Cannot find subscription for the incoming "invoice.paid" event.' );

		$this->subscriptions_event_handler->handle_invoice_paid( $test_body );
	}

	/**
	 * Test handle_invoice_paid
	 */
	public function test_handle_invoice_paid_parent_order() {
		$wcpay_subscription_id = 'sub_invoiceUpcoming';
		$wcpay_customer_id     = 'cus_test1234';
		$wcpay_invoiceid       = 'ii_testInvoiceID';
		$test_body             = $this->get_mock_test_body( $wcpay_subscription_id, $wcpay_customer_id, $wcpay_invoiceid );
		$mock_subscription     = new WC_Subscription();
		$mock_subscription->update_meta_data( self::SUBSCRIPTION_ID_META_KEY, $wcpay_subscription_id );
		$mock_subscription->next_payment = time();

		$this->expectException( Rest_Request_Exception::class );
		$this->expectExceptionMessage( 'Cannot find subscription for the incoming "invoice.paid" event.' );

		$this->subscriptions_event_handler->handle_invoice_paid( $test_body );
	}

	/**
	 * Test handle_invoice_paid
	 */
	public function test_handle_invoice_paid() {
		$wcpay_subscription_id = 'sub_invoiceUpcoming';
		$wcpay_customer_id     = 'cus_test1234';
		$wcpay_invoiceid       = 'ii_testInvoiceID';
		$wcpay_intent_id       = 'pi_testPaymentIntentID';
		$test_body             = $this->get_mock_test_body( $wcpay_subscription_id, $wcpay_customer_id, $wcpay_invoiceid, $wcpay_intent_id );
		$mock_renewal_order    = WC_Helper_Order::create_order();
		$mock_subscription     = new WC_Subscription();

		WC_Subscriptions::set_wcs_get_subscription(
			function ( $id ) use ( $mock_subscription ) {
				return $mock_subscription;
			}
		);

		WC_Subscriptions::wcs_create_renewal_order(
			function ( $subscription ) use ( $mock_renewal_order ) {
				return $mock_renewal_order;
			}
		);

		$this->mock_invoice_service->expects( $this->once() )
			->method( 'set_order_invoice_id' )
			->with( $mock_renewal_order, $wcpay_invoiceid )
			->willReturn( null );

		$this->mock_invoice_service->expects( $this->once() )
			->method( 'mark_pending_invoice_paid_for_subscription' )
			->with( $mock_subscription )
			->willReturn( null );

		$this->mock_invoice_service->expects( $this->once() )
			->method( 'get_and_attach_intent_info_to_order' )
			->with( $mock_renewal_order, $wcpay_intent_id )
			->willReturn( null );

		$this->subscriptions_event_handler->handle_invoice_paid( $test_body );
	}

	/**
	 * Test handle_invoice_payment_failed() when invalid data is given
	 */
	public function test_invoice_payment_failed_missing_attempts_data() {
		$test_body = $this->get_mock_test_body();

		unset( $test_body['data']['object']['attempt_count'] );

		$this->expectException( Rest_Request_Exception::class );
		$this->expectExceptionMessage( 'attempt_count not found in array' );

		$this->subscriptions_event_handler->handle_invoice_payment_failed( $test_body );
	}

	/**
	 * Test handle_invoice_payment_failed() first attempt
	 */
	public function test_invoice_payment_failed() {
		$wcpay_subscription_id = 'sub_invoiceFailed';
		$wcpay_customer_id     = 'cus_failed1234';
		$wcpay_invoice_id      = 'ii_failedInvoiceID';
		$wcpay_intent_id       = 'pi_failed';
		$test_body             = $this->get_mock_test_body( $wcpay_subscription_id, $wcpay_customer_id, $wcpay_invoice_id, $wcpay_intent_id, 1 );
		$mock_order            = WC_Helper_Order::create_order();
		$mock_subscription     = new WC_Subscription();
		$mock_subscription->set_parent( $mock_order );

		WC_Subscriptions::set_wcs_get_subscription(
			function ( $id ) use ( $mock_subscription ) {
				return $mock_subscription;
			}
		);

		WC_Subscriptions::wcs_create_renewal_order(
			function ( $subscription ) use ( $mock_order ) {
				return $mock_order;
			}
		);

		$this->mock_invoice_service->expects( $this->once() )
			->method( 'set_order_invoice_id' )
			->with( $mock_order, $wcpay_invoice_id )
			->willReturn( null );

		$this->mock_invoice_service->expects( $this->once() )
			->method( 'mark_pending_invoice_for_subscription' )
			->with( $mock_subscription, $wcpay_invoice_id )
			->willReturn( null );

		$this->subscriptions_event_handler->handle_invoice_payment_failed( $test_body );

		$this->assertTrue( $mock_subscription->has_status( 'on-hold' ) );
	}

	/**
	 * Test handle_invoice_payment_failed() when max attempts have been reached
	 */
	public function test_invoice_payment_failed_max_attempts() {
		$wcpay_subscription_id = 'sub_invoiceFailed';
		$wcpay_customer_id     = 'cus_failed1234';
		$wcpay_invoice_id      = 'ii_failedInvoiceID';
		$wcpay_intent_id       = 'pi_failed';
		$test_body             = $this->get_mock_test_body( $wcpay_subscription_id, $wcpay_customer_id, $wcpay_invoice_id, $wcpay_intent_id, 4 );
		$mock_order            = WC_Helper_Order::create_order();
		$mock_subscription     = new WC_Subscription();
		$mock_subscription->set_parent( $mock_order );

		WC_Subscriptions::set_wcs_get_subscription(
			function ( $id ) use ( $mock_subscription ) {
				return $mock_subscription;
			}
		);

		WC_Subscriptions::wcs_create_renewal_order(
			function ( $subscription ) use ( $mock_order ) {
				return $mock_order;
			}
		);

		$this->mock_invoice_service->expects( $this->once() )
			->method( 'set_order_invoice_id' )
			->with( $mock_order, $wcpay_invoice_id )
			->willReturn( null );

		$this->mock_invoice_service->expects( $this->once() )
			->method( 'mark_pending_invoice_for_subscription' )
			->with( $mock_subscription, $wcpay_invoice_id )
			->willReturn( null );

		$this->subscriptions_event_handler->handle_invoice_payment_failed( $test_body );

		$this->assertTrue( $mock_subscription->has_status( 'cancelled' ) );
	}

	/**
	 * Helper method to generate test event body.
	 *
	 * @param string $subscription_id The WCPay subscription ID.
	 * @param string $customer_id     The WCPay customer ID.
	 * @param string $invoice_id      The WCPay invoice ID.
	 * @param inte   $attempt_count   The WCPay attempt count for failed renewals.
	 *
	 * @return array
	 */
	private function get_mock_test_body( $subscription_id = 'sub_test1234', $customer_id = 'cust_test1234', $invoice_id = 'ii_test1234', $payment_intent_id = 'pi_test1234', $attempt_count = 1 ) {
		return [
			'data' => [
				'object' => [
					'attempt_count'  => $attempt_count,
					'customer'       => $customer_id,
					'discounts'      => [],
					'id'             => $invoice_id,
					'payment_intent' => $payment_intent_id,
					'lines'          => [
						'data' => [],
					],
					'subscription'   => $subscription_id,
				],
			],
		];
	}
}
