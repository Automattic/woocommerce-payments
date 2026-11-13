<?php
/**
 * Class WC_Payments_Subscriptions_Event_Handler
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\Core\Server\Request\Get_Charge;
use WCPay\Exceptions\Invalid_Webhook_Data_Exception;

/**
 * WC_Payments_Subscriptions_Event_Handler unit tests.
 */
class WC_Payments_Subscriptions_Event_Handler_Test extends WCPAY_UnitTestCase {

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
	public function set_up() {
		parent::set_up();

		$this->mock_invoice_service      = $this->createMock( WC_Payments_Invoice_Service::class );
		$this->mock_subscription_service = $this->createMock( WC_Payments_Subscription_Service::class );

		$this->subscriptions_event_handler = new WC_Payments_Subscriptions_Event_Handler( $this->mock_invoice_service, $this->mock_subscription_service );

		WC_Subscriptions::set_wcs_get_subscription(
			function ( $_unused_id ) {
				return false;
			}
		);
	}

	/**
	 * Test exception thrown during get_event_property.
	 */
	public function test_get_event_property_exception() {
		$this->expectException( Invalid_Webhook_Data_Exception::class );
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

		$this->expectException( Invalid_Webhook_Data_Exception::class );
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

		WC_Subscriptions::set_wcs_get_subscriptions(
			function ( $_unused_args ) use ( $mock_subscription ) {
				return [ $mock_subscription ];
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

		WC_Subscriptions::set_wcs_get_subscriptions( null );
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

		WC_Subscriptions::set_wcs_get_subscriptions(
			function ( $_unused_args ) use ( $mock_subscription ) {
				return [ $mock_subscription ];
			}
		);

		$this->mock_subscription_service->expects( $this->once() )
			->method( 'suspend_subscription' )
			->with( $mock_subscription )
			->willReturn( null );

		$this->subscriptions_event_handler->handle_invoice_upcoming( $test_body );

		WC_Subscriptions::set_wcs_get_subscriptions( null );
	}

	/**
	 * Test handle_invoice_paid when the incoming webhook belongs to a subscription that doesn't exist.
	 */
	public function test_handle_invoice_paid_exception() {
		$test_body = $this->get_mock_test_body( 'sub_ID_no_invoice_paid', 'cus_test1234', 'ii_testInvoiceID' );

		$this->expectException( Invalid_Webhook_Data_Exception::class );
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
		$mock_subscription->update_meta_data( self::ORDER_INVOICE_ID_KEY, $wcpay_invoiceid );
		$mock_subscription->next_payment = time();

		WC_Subscriptions::set_wcs_get_subscriptions(
			function ( $_unused_args ) use ( $mock_subscription ) {
				return [ $mock_subscription ];
			}
		);

		WC_Subscriptions::wcs_create_renewal_order(
			function ( $_unused_subscription ) {
				return WC_Helper_Order::create_order();
			}
		);

		// The parent order's invoice is already paid, so no renewal order is created or marked paid.
		$this->mock_invoice_service->expects( $this->never() )
			->method( 'set_order_invoice_id' );

		$this->mock_invoice_service->expects( $this->never() )
			->method( 'mark_pending_invoice_paid_for_subscription' );

		$this->subscriptions_event_handler->handle_invoice_paid( $test_body );

		WC_Subscriptions::set_wcs_get_subscriptions( null );
		WC_Subscriptions::wcs_create_renewal_order( null );
	}

	/**
	 * Rejects an invoice missing payment data before creating a renewal order.
	 *
	 * @dataProvider provider_missing_invoice_payment_fields
	 *
	 * @param string $missing_field The missing invoice property.
	 */
	public function test_handle_invoice_paid_rejects_missing_payment_data( string $missing_field ) {
		$test_body         = $this->get_mock_test_body();
		$mock_subscription = new WC_Subscription();
		unset( $test_body['data']['object'][ $missing_field ] );

		WC_Subscriptions::set_wcs_get_subscriptions(
			function ( $_unused_args ) use ( $mock_subscription ) {
				return [ $mock_subscription ];
			}
		);
		WC_Subscriptions::wcs_create_renewal_order(
			function ( $_unused_subscription ) {
				$this->fail( 'A malformed invoice must not create a renewal order.' );
			}
		);

		$this->expectException( Invalid_Webhook_Data_Exception::class );
		$this->expectExceptionMessage( $missing_field . ' not found in array' );

		$this->subscriptions_event_handler->handle_invoice_paid( $test_body );
	}

	/**
	 * Invoice properties needed to match the payment to the order.
	 *
	 * @return array
	 */
	public function provider_missing_invoice_payment_fields(): array {
		return [
			'missing amount'   => [ 'amount_paid' ],
			'missing currency' => [ 'currency' ],
		];
	}

	/**
	 * Ignores the parent invoice before requiring renewal payment data.
	 */
	public function test_handle_invoice_paid_ignores_parent_invoice_without_payment_data() {
		$test_body         = $this->get_mock_test_body();
		$mock_subscription = new WC_Subscription();
		$mock_subscription->update_meta_data( WC_Payments_Invoice_Service::ORDER_INVOICE_ID_KEY, 'ii_test1234' );
		unset( $test_body['data']['object']['amount_paid'], $test_body['data']['object']['currency'] );

		WC_Subscriptions::set_wcs_get_subscriptions(
			function ( $_unused_args ) use ( $mock_subscription ) {
				return [ $mock_subscription ];
			}
		);
		WC_Subscriptions::wcs_create_renewal_order(
			function ( $_unused_subscription ) {
				$this->fail( 'The parent invoice must not create a renewal order.' );
			}
		);
		$this->mock_invoice_service->expects( $this->never() )
			->method( 'mark_pending_invoice_paid_for_subscription' );

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

		WC_Subscriptions::set_wcs_get_subscriptions(
			function ( $_unused_args ) use ( $mock_subscription ) {
				return [ $mock_subscription ];
			}
		);

		WC_Subscriptions::set_wcs_get_subscription(
			function ( $_unused_subscription_id ) use ( $mock_subscription ) {
				return $mock_subscription;
			}
		);

		WC_Subscriptions::wcs_create_renewal_order(
			function ( $_unused_subscription ) use ( $mock_renewal_order ) {
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

		$this->mock_invoice_service->expects( $this->once() )
			->method( 'record_subscription_payment_context' )
			->with( $wcpay_invoiceid )
			->willReturn( [ 'charge' => 'ch_testxxx' ] );

		$this->subscriptions_event_handler->handle_invoice_paid( $test_body );
	}

	/**
	 * Test handle_invoice_paid puts the order on hold when the invoice doesn't match the order total,
	 * and still records the payment so the merchant can refund it.
	 *
	 * @dataProvider handle_invoice_paid_mismatch_provider
	 *
	 * @param string $order_currency The currency of the 50 unit order.
	 * @param int    $amount_paid    The amount the invoice collected, in minor units.
	 * @param string $currency       The currency the invoice was collected in.
	 * @param string $expected_note  The text the order note should contain.
	 */
	public function test_handle_invoice_paid_holds_order_when_amount_does_not_match_order_total( $order_currency, $amount_paid, $currency, $expected_note ) {
		$wcpay_subscription_id = 'sub_invoiceUpcoming';
		$wcpay_customer_id     = 'cus_test1234';
		$wcpay_invoiceid       = 'ii_testInvoiceID';
		$test_body             = $this->get_mock_test_body( $wcpay_subscription_id, $wcpay_customer_id, $wcpay_invoiceid, 'pi_testPaymentIntentID', 1, $amount_paid, $currency );
		$mock_renewal_order    = WC_Helper_Order::create_order();
		$mock_subscription     = new WC_Subscription();
		$mock_renewal_order->set_currency( $order_currency );
		$mock_renewal_order->save();

		WC_Subscriptions::set_wcs_get_subscriptions(
			function ( $_unused_args ) use ( $mock_subscription ) {
				return [ $mock_subscription ];
			}
		);

		WC_Subscriptions::set_wcs_get_subscription(
			function ( $_unused_subscription_id ) use ( $mock_subscription ) {
				return $mock_subscription;
			}
		);

		WC_Subscriptions::wcs_create_renewal_order(
			function ( $_unused_subscription ) use ( $mock_renewal_order ) {
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
			->with( $mock_renewal_order, 'pi_testPaymentIntentID' );

		$this->mock_invoice_service->expects( $this->once() )
			->method( 'record_subscription_payment_context' )
			->with( 'ii_testInvoiceID' )
			->willReturn( [ 'charge' => 'ch_testxxx' ] );

		$this->mock_invoice_service->expects( $this->once() )
			->method( 'update_charge_details' )
			->with( [ 'charge' => 'ch_testxxx' ], $mock_renewal_order->get_id() );

		$this->mock_invoice_service->expects( $this->once() )
			->method( 'update_transaction_details' )
			->with( [ 'charge' => 'ch_testxxx' ], $mock_renewal_order );

		$this->subscriptions_event_handler->handle_invoice_paid( $test_body );

		$stored_order = wc_get_order( $mock_renewal_order->get_id() );
		$notes        = wc_get_order_notes( [ 'order_id' => $stored_order->get_id() ] );

		$this->assertSame( 'on-hold', $stored_order->get_status() );
		$this->assertFalse( $stored_order->is_paid() );
		$this->assertStringContainsString( $expected_note, wp_strip_all_tags( html_entity_decode( $notes[0]->content ) ) );
	}

	/**
	 * Invoices that don't match a 50 unit order.
	 *
	 * @return array
	 */
	public function handle_invoice_paid_mismatch_provider(): array {
		return [
			'amount differs'        => [ 'USD', 1000, 'usd', 'collected $10.00 USD, but the order total is $50.00 USD' ],
			'currency differs'      => [ 'USD', 5000, 'eur', 'collected 50,00 € EUR, but the order total is $50.00 USD' ],
			'zero-decimal currency' => [ 'JPY', 1000, 'jpy', 'collected ¥1,000 JPY, but the order total is ¥50 JPY' ],
		];
	}

	/**
	 * Test a repeated invoice.paid event leaves a held order on hold, with a single note.
	 */
	public function test_handle_invoice_paid_holds_order_once_when_delivered_twice() {
		$wcpay_invoiceid    = 'ii_testInvoiceID';
		$test_body          = $this->get_mock_test_body( 'sub_invoiceUpcoming', 'cus_test1234', $wcpay_invoiceid, 'pi_testPaymentIntentID', 1, 1000 );
		$mock_renewal_order = WC_Helper_Order::create_order();
		$mock_subscription  = new WC_Subscription();
		$mock_renewal_order->update_meta_data( self::ORDER_INVOICE_ID_KEY, $wcpay_invoiceid );
		$mock_renewal_order->save();

		WC_Subscriptions::set_wcs_get_subscriptions(
			function ( $_unused_args ) use ( $mock_subscription ) {
				return [ $mock_subscription ];
			}
		);

		WC_Subscriptions::set_wcs_get_subscription(
			function ( $_unused_subscription_id ) use ( $mock_subscription ) {
				return $mock_subscription;
			}
		);

		$this->mock_invoice_service
			->method( 'record_subscription_payment_context' )
			->willReturn( [ 'charge' => 'ch_testxxx' ] );

		$this->subscriptions_event_handler->handle_invoice_paid( $test_body );
		$this->subscriptions_event_handler->handle_invoice_paid( $test_body );

		$stored_order   = wc_get_order( $mock_renewal_order->get_id() );
		$mismatch_notes = array_filter(
			wc_get_order_notes( [ 'order_id' => $stored_order->get_id() ] ),
			function ( $note ) {
				return false !== strpos( $note->content, 'collected' );
			}
		);

		$this->assertSame( 'on-hold', $stored_order->get_status() );
		$this->assertCount( 1, $mismatch_notes );
	}

	/**
	 * Test handle_invoice_payment_failed() when invalid data is given
	 */
	public function test_invoice_payment_failed_missing_attempts_data() {
		$test_body = $this->get_mock_test_body();

		unset( $test_body['data']['object']['attempt_count'] );

		$this->expectException( Invalid_Webhook_Data_Exception::class );
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

		WC_Subscriptions::set_wcs_get_subscriptions(
			function ( $_unused_args ) use ( $mock_subscription ) {
				return [ $mock_subscription ];
			}
		);

		WC_Subscriptions::wcs_create_renewal_order(
			function ( $_unused_subscription ) use ( $mock_order ) {
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

		WC_Subscriptions::set_wcs_get_subscriptions(
			function ( $_unused_args ) use ( $mock_subscription ) {
				return [ $mock_subscription ];
			}
		);

		WC_Subscriptions::wcs_create_renewal_order(
			function ( $_unused_subscription ) use ( $mock_order ) {
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
	 * Test a late or repeated invoice.payment_failed event leaves an invoice's paid order and its subscription alone.
	 *
	 * @param string $order_status  Status of the invoice's order when the failure event arrives.
	 * @param int    $attempt_count Attempt count carried by the failure event.
	 *
	 * @dataProvider provider_invoice_payment_failed_order_already_paid
	 */
	public function test_invoice_payment_failed_ignored_when_order_already_paid( string $order_status, int $attempt_count ) {
		$wcpay_invoice_id = 'ii_paidInvoiceID';
		$charge_id        = 'ch_paid';
		$test_body        = $this->get_mock_test_body( 'sub_invoicePaid', 'cus_paid1234', $wcpay_invoice_id, 'pi_paid', $attempt_count );

		$test_body['data']['object']['charge'] = $charge_id;

		$renewal_order = WC_Helper_Order::create_order();
		$renewal_order->update_meta_data( self::ORDER_INVOICE_ID_KEY, $wcpay_invoice_id );
		$renewal_order->set_status( $order_status );
		$renewal_order->save();
		$mock_subscription = new WC_Subscription();
		$mock_subscription->update_status( 'active' );

		WC_Subscriptions::set_wcs_get_subscriptions(
			function ( $_unused_args ) use ( $mock_subscription ) {
				return [ $mock_subscription ];
			}
		);

		$this->mock_invoice_service->expects( $this->never() )->method( 'set_order_invoice_id' );
		$this->mock_invoice_service->expects( $this->never() )->method( 'mark_pending_invoice_for_subscription' );
		$this->mock_invoice_service->expects( $this->never() )->method( 'record_subscription_payment_context' );
		$this->mock_wcpay_request( Get_Charge::class, 0, $charge_id );

		$this->subscriptions_event_handler->handle_invoice_payment_failed( $test_body );

		$this->assertSame( 'active', $mock_subscription->get_status() );
		$this->assertSame( $order_status, wc_get_order( $renewal_order->get_id() )->get_status() );

		WC_Subscriptions::set_wcs_get_subscriptions( null );
	}

	/**
	 * Order statuses invoice.paid leaves the invoice's order in.
	 *
	 * @return array
	 */
	public function provider_invoice_payment_failed_order_already_paid(): array {
		return [
			'paid order'                     => [ 'processing', 1 ],
			'completed order'                => [ 'completed', 1 ],
			'order held for amount mismatch' => [ 'on-hold', 1 ],
			'paid order on the last attempt' => [ 'processing', 4 ],
		];
	}

	/**
	 * Test a failed attempt on an invoice whose order the invoice did not pay is still recorded.
	 *
	 * @param string $order_status Status of the invoice's order when the failure event arrives.
	 *
	 * @dataProvider provider_invoice_payment_failed_order_not_paid_by_invoice
	 */
	public function test_invoice_payment_failed_processed_when_order_not_paid_by_invoice( string $order_status ) {
		$wcpay_invoice_id = 'ii_failedInvoiceID';
		$test_body        = $this->get_mock_test_body( 'sub_invoiceFailed', 'cus_failed1234', $wcpay_invoice_id, 'pi_failed', 2 );
		$renewal_order    = WC_Helper_Order::create_order();
		$renewal_order->update_meta_data( self::ORDER_INVOICE_ID_KEY, $wcpay_invoice_id );
		$renewal_order->set_status( $order_status );
		$renewal_order->save();
		$mock_subscription = new WC_Subscription();
		$mock_subscription->update_status( 'active' );

		WC_Subscriptions::set_wcs_get_subscriptions(
			function ( $_unused_args ) use ( $mock_subscription ) {
				return [ $mock_subscription ];
			}
		);

		$this->mock_invoice_service->expects( $this->never() )->method( 'set_order_invoice_id' );
		$this->mock_invoice_service->expects( $this->once() )
			->method( 'mark_pending_invoice_for_subscription' )
			->with( $mock_subscription, 'ii_failedInvoiceID' );

		$this->subscriptions_event_handler->handle_invoice_payment_failed( $test_body );

		$this->assertSame( 'on-hold', $mock_subscription->get_status() );

		WC_Subscriptions::set_wcs_get_subscriptions( null );
	}

	/**
	 * Order statuses in which a failed attempt is still recorded.
	 *
	 * @return array
	 */
	public function provider_invoice_payment_failed_order_not_paid_by_invoice(): array {
		return [
			'failed order'    => [ 'failed' ],
			'pending order'   => [ 'pending' ],
			'cancelled order' => [ 'cancelled' ],
			'refunded order'  => [ 'refunded' ],
		];
	}

	/**
	 * Helper method to generate test event body.
	 *
	 * @param string $subscription_id The WCPay subscription ID.
	 * @param string $customer_id     The WCPay customer ID.
	 * @param string $invoice_id      The WCPay invoice ID.
	 * @param int    $attempt_count   The WCPay attempt count for failed renewals.
	 * @param int    $amount_paid     The amount the invoice collected, in minor units.
	 * @param string $currency        The currency the invoice was collected in.
	 *
	 * @return array
	 */
	private function get_mock_test_body( $subscription_id = 'sub_test1234', $customer_id = 'cust_test1234', $invoice_id = 'ii_test1234', $payment_intent_id = 'pi_test1234', $attempt_count = 1, $amount_paid = 5000, $currency = 'usd' ) {
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
					'amount_paid'    => $amount_paid,
					'currency'       => $currency,
				],
			],
		];
	}
}
