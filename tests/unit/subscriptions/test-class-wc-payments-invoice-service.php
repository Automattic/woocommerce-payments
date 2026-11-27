<?php
/**
 * Class WC_Payments_Invoice_Service_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Constants\Currency_Code;
use WCPay\Constants\Order_Mode;
use WCPay\Core\Server\Request\Get_Intention;
use WCPay\Exceptions\API_Exception;
use WCPay\Exceptions\Connection_Exception;

/**
 * WC_Payments_Invoice_Service_Test unit tests.
 */
class WC_Payments_Invoice_Service_Test extends WCPAY_UnitTestCase {

	const PENDING_INVOICE_ID_KEY             = '_wcpay_pending_invoice_id';
	const ORDER_INVOICE_ID_KEY               = '_wcpay_billing_invoice_id';
	const SUBSCRIPTION_ID_META_KEY           = '_wcpay_subscription_id';
	const SUBSCRIPTION_ITEM_ID_META_KEY      = '_wcpay_subscription_item_id';
	const SUBSCRIPTION_DISCOUNT_IDS_META_KEY = '_wcpay_subscription_discount_ids';

	/**
	 * Mock WC_Payments_API_Client.
	 *
	 * @var WC_Payments_API_Client&MockObject
	 */
	private $mock_api_client;

	/**
	 * Mock WC_Payments_Order_Service.
	 *
	 * @var WC_Payments_Order_Service&MockObject
	 */
	private $mock_order_service;

	/**
	 * Invoice Service under test.
	 * @var WC_Payments_Invoice_Service
	 */
	private $invoice_service;

	/**
	 * Pre-test setup
	 */
	public function set_up() {
		parent::set_up();

		$this->mock_api_client    = $this->createMock( WC_Payments_API_Client::class );
		$this->mock_order_service = $this->createMock( WC_Payments_Order_Service::class );
		$this->invoice_service    = new WC_Payments_Invoice_Service( $this->mock_api_client, $this->mock_order_service );
	}

	/**
	 * Removes retries scheduled by a test so they do not leak into the next one.
	 */
	public function tear_down() {
		as_unschedule_all_actions( WC_Payments_Invoice_Service::RECORD_INVOICE_PAYMENT_RETRY_HOOK );
		parent::tear_down();
	}

	/**
	 * Tests for WC_Payments_Invoice_Service::mark_pending_invoice_for_subscription()
	 */
	public function test_mark_pending_invoice_for_subscription() {
		$mock_subscription = new WC_Subscription();
		$invoice_id        = 'in_123abc';

		$this->invoice_service->mark_pending_invoice_for_subscription( $mock_subscription, $invoice_id );
		$this->assertEquals( $invoice_id, $mock_subscription->get_meta( self::PENDING_INVOICE_ID_KEY, true ) );
	}

	/**
	 * Tests for WC_Payments_Invoice_Service::mark_pending_invoice_paid_for_subscription()
	 */
	public function test_mark_pending_invoice_paid_for_subscription() {
		$mock_subscription = new WC_Subscription();
		$mock_subscription->update_meta_data( self::PENDING_INVOICE_ID_KEY, 'in_123abc' );

		$this->invoice_service->mark_pending_invoice_paid_for_subscription( $mock_subscription );
		$this->assertEquals( '', $mock_subscription->get_meta( self::PENDING_INVOICE_ID_KEY, true ) );
	}

	/**
	 * Tests for WC_Payments_Invoice_Service::get_pending_invoice_id()
	 */
	public function test_get_pending_invoice_id() {
		$mock_subscription = new WC_Subscription();

		$this->assertEquals( '', $this->invoice_service->get_pending_invoice_id( $mock_subscription ) );

		$mock_subscription->update_meta_data( self::PENDING_INVOICE_ID_KEY, 'in_test123' );
		$this->assertEquals( 'in_test123', $this->invoice_service->get_pending_invoice_id( $mock_subscription ) );
	}

	/**
	 * Tests for WC_Payments_Invoice_Service::set_pending_invoice_id()
	 */
	public function test_set_pending_invoice_id() {
		$mock_subscription = new WC_Subscription();

		PHPUnit_Utils::call_method(
			$this->invoice_service,
			'set_pending_invoice_id',
			[ $mock_subscription, 'in_test_abc' ]
		);
		$this->assertEquals( 'in_test_abc', $mock_subscription->get_meta( self::PENDING_INVOICE_ID_KEY, true ) );
	}

	/**
	 * Tests for WC_Payments_Invoice_Service::get_order_invoice_id()
	 */
	public function test_get_order_invoice_id() {
		$mock_order = WC_Helper_Order::create_order();

		$this->assertEquals( '', $this->invoice_service->get_order_invoice_id( $mock_order ) );

		$mock_order->update_meta_data( self::ORDER_INVOICE_ID_KEY, 'in_foo' );
		$this->assertEquals( 'in_foo', $this->invoice_service->get_order_invoice_id( $mock_order ) );
	}

	/**
	 * Tests for WC_Payments_Invoice_Service::does_invoice_payment_match_order_total()
	 *
	 * @dataProvider provider_does_invoice_payment_match_order_total
	 *
	 * @param float  $order_total      The order total.
	 * @param string $order_currency   The order currency.
	 * @param mixed  $amount_paid      The invoice's amount_paid.
	 * @param string $invoice_currency The invoice's currency.
	 * @param bool   $expected         Whether the invoice should match the order.
	 */
	public function test_does_invoice_payment_match_order_total( $order_total, $order_currency, $amount_paid, $invoice_currency, $expected ) {
		$order = $this->create_order_with_two_lines( $order_total, $order_currency );

		$this->assertSame( $expected, WC_Payments_Invoice_Service::does_invoice_payment_match_order_total( $order, $amount_paid, $invoice_currency ) );
	}

	/**
	 * Invoices compared with an order of two lines, in a store priced with two decimals.
	 *
	 * @return array
	 */
	public function provider_does_invoice_payment_match_order_total(): array {
		return [
			'exact match'                     => [ 50, 'USD', 5000, 'usd', true ],
			'a cent per line below'           => [ 50, 'USD', 4998, 'usd', true ],
			'a cent per line above'           => [ 50, 'USD', 5002, 'usd', true ],
			'beyond a cent per line below'    => [ 50, 'USD', 4997, 'usd', false ],
			'beyond a cent per line above'    => [ 50, 'USD', 5003, 'usd', false ],
			'zero-decimal currency'           => [ 50, 'JPY', 50, 'jpy', true ],
			'zero-decimal currency, in cents' => [ 50, 'JPY', 5000, 'jpy', false ],
			'different currency'              => [ 50, 'CAD', 5000, 'usd', false ],
			'missing currency'                => [ 50, 'USD', 5000, '', false ],
			// A free order is within the allowance of 0, so only the guard rejects a missing amount.
			'missing amount'                  => [ 0, 'USD', null, 'usd', false ],
			'non-numeric amount'              => [ 0, 'USD', 'free', 'usd', false ],
		];
	}

	/**
	 * Tests that does_invoice_payment_match_order_total() allows a whole forint per line in a store priced without decimals.
	 *
	 * @dataProvider provider_does_invoice_payment_match_order_total_without_decimals
	 *
	 * @param float $order_total The order total, in HUF.
	 * @param int   $amount_paid The invoice's amount_paid, in fillér.
	 * @param bool  $expected    Whether the invoice should match the order.
	 */
	public function test_does_invoice_payment_match_order_total_without_decimals( $order_total, $amount_paid, $expected ) {
		update_option( 'woocommerce_currency', Currency_Code::HUNGARIAN_FORINT );
		update_option( 'woocommerce_price_num_decimals', 0 );
		$order = $this->create_order_with_two_lines( $order_total, Currency_Code::HUNGARIAN_FORINT );

		$this->assertSame( $expected, WC_Payments_Invoice_Service::does_invoice_payment_match_order_total( $order, $amount_paid, 'huf' ) );
	}

	/**
	 * Stripe rounds each line to a fillér, while WooCommerce rounds the order total to a forint.
	 *
	 * @return array
	 */
	public function provider_does_invoice_payment_match_order_total_without_decimals(): array {
		return [
			'fractional forint above'        => [ 1000, 100040, true ],
			'fractional forint below'        => [ 1269, 126873, true ],
			'a forint per line'              => [ 1000, 100200, true ],
			'beyond a forint per line'       => [ 1000, 100201, false ],
			'beyond a forint per line below' => [ 1000, 99799, false ],
		];
	}

	/**
	 * Tests that does_invoice_payment_match_order_total() uses the decimals Multi-Currency prices a non-store currency in.
	 */
	public function test_does_invoice_payment_match_order_total_uses_multi_currency_decimals() {
		add_filter(
			'wcpay_huf_format',
			function ( $currency_format ) {
				$currency_format['num_decimals'] = 0;
				return $currency_format;
			}
		);
		$order = $this->create_order_with_two_lines( 1000, Currency_Code::HUNGARIAN_FORINT );

		$this->assertTrue( WC_Payments_Invoice_Service::does_invoice_payment_match_order_total( $order, 100040, 'huf' ) );
	}

	/**
	 * Tests for WC_Payments_Invoice_Service::set_order_invoice_id()
	 */
	public function test_set_order_invoice_id() {
		$mock_order = WC_Helper_Order::create_order();

		$this->invoice_service->set_order_invoice_id( $mock_order, 'in_bar' );
		$this->assertEquals( 'in_bar', $mock_order->get_meta( self::ORDER_INVOICE_ID_KEY, true ) );
	}

	/**
	 * Tests for WC_Payments_Invoice_Service::get_order_id_by_invoice_id()
	 */
	public function test_get_order_id_by_invoice_id() {
		$mock_order = WC_Helper_Order::create_order();

		$mock_order->update_meta_data( self::ORDER_INVOICE_ID_KEY, 'in_invoice123' );
		$mock_order->save();

		$this->assertEquals( $mock_order->get_id(), $this->invoice_service->get_order_id_by_invoice_id( 'in_invoice123' ) );
		$this->assertEquals( 0, $this->invoice_service->get_order_id_by_invoice_id( 'invalid_invoice_id' ) );
	}

	/**
	 * Tests for WC_Payments_Invoice_Service::maybe_record_invoice_payment()
	 */
	public function test_maybe_record_invoice_payment() {
		$invoice_id        = 'in_foo123';
		$mock_order        = WC_Helper_Order::create_order();
		$mock_subscription = new WC_Subscription();

		$mock_subscription->payment_tokens = [ uniqid( 'pm_' ) ];
		$mock_subscription->payment_method = 'woocommerce_payments';
		$mock_subscription->update_meta_data( self::SUBSCRIPTION_ID_META_KEY, 'sub_123abc' );
		$mock_subscription->save();

		// With the following calls to `maybe_record_first_invoice_payment()`, we only expect 2 calls (see Positive Cases) to result in an API call.
		$this->mock_api_client->expects( $this->exactly( 2 ) )
			->method( 'charge_invoice' )
			->with( $invoice_id, [ 'paid_out_of_band' => 'true' ] );

		$mock_subscription->update_meta_data( self::ORDER_INVOICE_ID_KEY, $invoice_id );
		$mock_subscription->save();

		// Positive Cases.
		// First Invoice.
		$this->mock_wcs_get_subscriptions_for_order( [ $mock_subscription ] );
		$this->invoice_service->maybe_record_invoice_payment( $mock_order->get_id() );
		// Manual Renewal.
		$mock_subscription->set_requires_manual_renewal( true );
		$this->invoice_service->maybe_record_invoice_payment( $mock_order->get_id() );
		$this->assertNotTrue( $mock_subscription->is_manual() );

		// Negative Cases.
		// Order contains invoice ID meta.
		$mock_order->update_meta_data( self::ORDER_INVOICE_ID_KEY, $invoice_id );
		$mock_order->save();
		$this->invoice_service->maybe_record_invoice_payment( $mock_order->get_id() );
		// Invalid order ID.
		$this->invoice_service->maybe_record_invoice_payment( 0 );
		// Order isn't related to a subscription.
		$this->mock_wcs_get_subscriptions_for_order( [] );
		$this->invoice_service->maybe_record_invoice_payment( $mock_order->get_id() );
	}

	/**
	 * A non-"already paid" failure with a dead server-side subscription unlinks it instead of rethrowing.
	 *
	 * @dataProvider provider_unbillable_subscription_states
	 *
	 * @param API_Exception|array $get_subscription_result What get_subscription() throws (exception) or returns (array).
	 * @param string|null         $parent_mode             The parent order's saved mode; see set_parent_order_mode().
	 */
	public function test_retry_record_invoice_payment_unlinks_unbillable_subscription_on_failure( $get_subscription_result, ?string $parent_mode ) {
		$invoice_id = 'in_dead123';
		$order      = WC_Helper_Order::create_order();
		$sub        = $this->create_linked_unpaid_subscription( 'sub_dead', $invoice_id );
		$this->set_parent_order_mode( $sub, $parent_mode );

		$this->mock_api_client->method( 'charge_invoice' )
			->willThrowException( new API_Exception( 'Error: The invoice could not be updated', 'invoice_update_failed', 400 ) );

		$get_subscription = $this->mock_api_client->expects( $this->once() )
			->method( 'get_subscription' )
			->with( 'sub_dead' );
		if ( $get_subscription_result instanceof API_Exception ) {
			$get_subscription->willThrowException( $get_subscription_result );
		} else {
			$get_subscription->willReturn( $get_subscription_result );
		}

		// The server already reports the subscription as gone or ended, so there is nothing to cancel.
		$this->mock_api_client->expects( $this->never() )
			->method( 'cancel_subscription' );

		// Must not throw.
		$this->run_retry_attempt( $order, $sub, $invoice_id, 1 );

		$this->assertSame( '', $sub->get_meta( self::SUBSCRIPTION_ID_META_KEY, true ) );
		$this->assertSame( 'sub_dead', $sub->get_meta( '_cancelled' . self::SUBSCRIPTION_ID_META_KEY, true ) );
		$this->assertSame( '', $sub->get_meta( self::ORDER_INVOICE_ID_KEY, true ) );
		$this->assertSame( '', $sub->get_meta( self::PENDING_INVOICE_ID_KEY, true ) );
		$this->assertFalse( WC_Payments_Subscription_Service::is_wcpay_subscription( $sub ) );
	}

	/**
	 * Server states that mean a WooPayments subscription can no longer be billed and must be unlinked. A subscription
	 * found with an ended status is in the current mode, so it is unlinked even when the parent order has no mode.
	 *
	 * @return array<string, array{0: API_Exception|array, 1: string|null}>
	 */
	public function provider_unbillable_subscription_states(): array {
		return [
			'resource_missing, mode matches'             => [ new API_Exception( 'No such subscription', 'resource_missing', 404 ), 'current' ],
			'status canceled, mode matches'              => [ [ 'status' => 'canceled' ], 'current' ],
			'status canceled, no parent order'           => [ [ 'status' => 'canceled' ], null ],
			'status canceled, parent order without mode' => [ [ 'status' => 'canceled' ], '' ],
			'status incomplete_expired, no parent order' => [ [ 'status' => 'incomplete_expired' ], null ],
		];
	}

	/**
	 * Without a saved mode, "not found" is ambiguous: a live subscription in the other test/live mode is reported the
	 * same way. The link is kept and a retry scheduled rather than unlinking and double billing it.
	 *
	 * @dataProvider provider_unknown_subscription_modes
	 *
	 * @param string|null $parent_mode The parent order's saved mode; see set_parent_order_mode().
	 */
	public function test_retry_record_invoice_payment_keeps_link_on_resource_missing_when_mode_is_unknown( ?string $parent_mode ) {
		$invoice_id = 'in_nomode123';
		$order      = WC_Helper_Order::create_order();
		$sub        = $this->create_linked_unpaid_subscription( 'sub_nomode', $invoice_id );
		$this->set_parent_order_mode( $sub, $parent_mode );

		$this->mock_api_client->method( 'charge_invoice' )
			->willThrowException( new API_Exception( 'Error: The invoice could not be updated', 'invoice_update_failed', 400 ) );
		$this->mock_api_client->expects( $this->once() )
			->method( 'get_subscription' )
			->with( 'sub_nomode' )
			->willThrowException( new API_Exception( 'No such subscription', 'resource_missing', 404 ) );

		$this->run_retry_attempt( $order, $sub, $invoice_id, 1 );

		$this->assertSame( 'sub_nomode', $sub->get_meta( self::SUBSCRIPTION_ID_META_KEY, true ) );
		$this->assertSame( '', $sub->get_meta( '_cancelled' . self::SUBSCRIPTION_ID_META_KEY, true ) );
		$this->assertTrue( WC_Payments_Subscription_Service::is_wcpay_subscription( $sub ) );
		$this->assertNotFalse( $this->get_scheduled_retry( $order, $sub, $invoice_id, 2 ) );

		as_unschedule_all_actions( WC_Payments_Invoice_Service::RECORD_INVOICE_PAYMENT_RETRY_HOOK );
	}

	/**
	 * Parent order states that leave a subscription's test/live mode unknown.
	 *
	 * @return array<string, array{0: string|null}>
	 */
	public function provider_unknown_subscription_modes(): array {
		return [
			'no parent order'           => [ null ],
			'parent order without mode' => [ '' ],
		];
	}

	/**
	 * An unknown lookup failure (e.g. 500 or timeout) keeps the link and schedules a retry rather than unlinking a
	 * possibly-live subscription, which could double bill.
	 */
	public function test_retry_record_invoice_payment_keeps_link_when_subscription_status_is_unknown() {
		$invoice_id = 'in_unknown123';
		$order      = WC_Helper_Order::create_order();
		$sub        = $this->create_linked_unpaid_subscription( 'sub_unknown', $invoice_id );

		$this->mock_api_client->method( 'charge_invoice' )
			->willThrowException( new API_Exception( 'Error: The invoice could not be updated', 'invoice_update_failed', 400 ) );
		$this->mock_api_client->expects( $this->once() )
			->method( 'get_subscription' )
			->with( 'sub_unknown' )
			->willThrowException( new API_Exception( 'Server error. Please try again.', 'server_error', 500 ) );
		$this->mock_api_client->expects( $this->never() )
			->method( 'cancel_subscription' );

		$this->run_retry_attempt( $order, $sub, $invoice_id, 1 );

		// Link kept, not unlinked.
		$this->assertSame( 'sub_unknown', $sub->get_meta( self::SUBSCRIPTION_ID_META_KEY, true ) );
		$this->assertTrue( WC_Payments_Subscription_Service::is_wcpay_subscription( $sub ) );

		// The next bounded retry is scheduled.
		$payload = [
			'order_id'        => $order->get_id(),
			'subscription_id' => $sub->get_id(),
			'invoice_id'      => $invoice_id,
			'attempt'         => 2,
		];
		$this->assertNotFalse(
			as_next_scheduled_action(
				WC_Payments_Invoice_Service::RECORD_INVOICE_PAYMENT_RETRY_HOOK,
				[ $payload ],
				WC_Payments_Action_Scheduler_Service::GROUP_ID
			)
		);

		as_unschedule_all_actions( WC_Payments_Invoice_Service::RECORD_INVOICE_PAYMENT_RETRY_HOOK );
	}

	/**
	 * A bare 404 the server does not attribute to the missing resource (no resource_missing error) is an unknown
	 * failure, not a dead subscription: the link is kept and a retry scheduled, so an infrastructure 404 (a proxy or
	 * gateway) cannot unlink a still-live subscription and double bill it.
	 */
	public function test_retry_record_invoice_payment_keeps_link_on_bare_404_without_resource_missing() {
		$invoice_id = 'in_bare404';
		$order      = WC_Helper_Order::create_order();
		$sub        = $this->create_linked_unpaid_subscription( 'sub_bare404', $invoice_id );

		$this->mock_api_client->method( 'charge_invoice' )
			->willThrowException( new API_Exception( 'Error: The invoice could not be updated', 'invoice_update_failed', 400 ) );
		$this->mock_api_client->expects( $this->once() )
			->method( 'get_subscription' )
			->with( 'sub_bare404' )
			->willThrowException( new API_Exception( 'Not Found', 'wcpay_http_request_failed', 404 ) );
		$this->mock_api_client->expects( $this->never() )
			->method( 'cancel_subscription' );

		$this->run_retry_attempt( $order, $sub, $invoice_id, 1 );

		// Link kept, not unlinked.
		$this->assertSame( 'sub_bare404', $sub->get_meta( self::SUBSCRIPTION_ID_META_KEY, true ) );
		$this->assertTrue( WC_Payments_Subscription_Service::is_wcpay_subscription( $sub ) );

		// The next bounded retry is scheduled instead.
		$payload = [
			'order_id'        => $order->get_id(),
			'subscription_id' => $sub->get_id(),
			'invoice_id'      => $invoice_id,
			'attempt'         => 2,
		];
		$this->assertNotFalse(
			as_next_scheduled_action(
				WC_Payments_Invoice_Service::RECORD_INVOICE_PAYMENT_RETRY_HOOK,
				[ $payload ],
				WC_Payments_Action_Scheduler_Service::GROUP_ID
			)
		);

		as_unschedule_all_actions( WC_Payments_Invoice_Service::RECORD_INVOICE_PAYMENT_RETRY_HOOK );
	}

	/**
	 * When the store's mode no longer matches the subscription's mode, a 404 must not unlink it: the subscription may
	 * still be live in the other mode, so unlinking would hand renewals to WCS and double bill.
	 */
	public function test_retry_record_invoice_payment_keeps_link_on_mode_mismatch() {
		$invoice_id = 'in_mode123';
		$order      = WC_Helper_Order::create_order();

		// Record the parent order in the opposite mode to the current one so the modes do not match.
		$mismatched_mode = WC_Payments::mode()->is_test() ? Order_Mode::PRODUCTION : Order_Mode::TEST;
		$order->update_meta_data( WC_Payments_Order_Service::WCPAY_MODE_META_KEY, $mismatched_mode );
		$order->save();

		$sub = $this->create_linked_unpaid_subscription( 'sub_mode', $invoice_id );
		$sub->set_parent( $order );

		$this->mock_api_client->method( 'charge_invoice' )
			->willThrowException( new API_Exception( 'Error: The invoice could not be updated', 'invoice_update_failed', 400 ) );
		// On a mode mismatch the handler bails before looking the subscription up or cancelling it.
		$this->mock_api_client->expects( $this->never() )->method( 'get_subscription' );
		$this->mock_api_client->expects( $this->never() )->method( 'cancel_subscription' );

		$this->run_retry_attempt( $order, $sub, $invoice_id, 1 );

		// Link kept untouched, and no retry scheduled.
		$this->assertSame( 'sub_mode', $sub->get_meta( self::SUBSCRIPTION_ID_META_KEY, true ) );
		$this->assertTrue( WC_Payments_Subscription_Service::is_wcpay_subscription( $sub ) );
		$this->assertFalse( (bool) as_next_scheduled_action( WC_Payments_Invoice_Service::RECORD_INVOICE_PAYMENT_RETRY_HOOK ) );

		// A durable, merchant-visible note explains why reconciliation was skipped and points at the store's mode.
		$this->assertStringContainsString( 'different WooPayments mode', implode( "\n", $sub->order_notes ) );
	}

	/**
	 * A non-"already paid" failure with a still-live subscription keeps the link and schedules a retry.
	 */
	public function test_retry_record_invoice_payment_schedules_next_retry_for_live_subscription_on_failure() {
		$invoice_id = 'in_live123';
		$order      = WC_Helper_Order::create_order();
		$sub        = $this->create_linked_unpaid_subscription( 'sub_live', $invoice_id );

		$this->mock_api_client->method( 'charge_invoice' )
			->willThrowException( new API_Exception( 'Server error. Please try again.', 'server_error', 500 ) );
		$this->mock_api_client->expects( $this->once() )
			->method( 'get_subscription' )
			->with( 'sub_live' )
			->willReturn( [ 'status' => 'active' ] );
		$this->mock_api_client->expects( $this->never() )
			->method( 'cancel_subscription' );

		$this->run_retry_attempt( $order, $sub, $invoice_id, 1 );

		// Link kept, not unlinked, not suspended.
		$this->assertSame( 'sub_live', $sub->get_meta( self::SUBSCRIPTION_ID_META_KEY, true ) );
		$this->assertTrue( WC_Payments_Subscription_Service::is_wcpay_subscription( $sub ) );

		// The next bounded retry is scheduled.
		$payload = [
			'order_id'        => $order->get_id(),
			'subscription_id' => $sub->get_id(),
			'invoice_id'      => $invoice_id,
			'attempt'         => 2,
		];
		$this->assertNotFalse(
			as_next_scheduled_action(
				WC_Payments_Invoice_Service::RECORD_INVOICE_PAYMENT_RETRY_HOOK,
				[ $payload ],
				WC_Payments_Action_Scheduler_Service::GROUP_ID
			)
		);

		as_unschedule_all_actions( WC_Payments_Invoice_Service::RECORD_INVOICE_PAYMENT_RETRY_HOOK );
	}

	/**
	 * A failure in the order status hook (usually the shopper's checkout request) makes no further server calls: the
	 * link is kept, the first retry is scheduled 10 minutes out and the merchant gets a note. Checking whether the
	 * subscription can still be billed is left to the retry, so a slow server does not hold up checkout twice.
	 */
	public function test_maybe_record_invoice_payment_defers_failure_to_retry_without_server_lookup() {
		$invoice_id = 'in_defer123';
		$order      = WC_Helper_Order::create_order();
		$sub        = $this->create_linked_unpaid_subscription( 'sub_defer', $invoice_id );
		$this->mock_wcs_get_subscriptions_for_order( [ $sub ] );

		$this->mock_api_client->expects( $this->once() )
			->method( 'charge_invoice' )
			->willThrowException( new API_Exception( 'Error: The invoice could not be updated', 'invoice_update_failed', 400 ) );
		$this->mock_api_client->expects( $this->never() )->method( 'get_subscription' );
		$this->mock_api_client->expects( $this->never() )->method( 'cancel_subscription' );

		$before = time();
		$this->invoice_service->maybe_record_invoice_payment( $order->get_id() );

		$this->assertSame( 'sub_defer', $sub->get_meta( self::SUBSCRIPTION_ID_META_KEY, true ) );
		$this->assertTrue( WC_Payments_Subscription_Service::is_wcpay_subscription( $sub ) );
		$scheduled_at = $this->get_scheduled_retry( $order, $sub, $invoice_id, 1 );
		$this->assertGreaterThanOrEqual( $before + 600, $scheduled_at );
		$this->assertLessThanOrEqual( time() + 600, $scheduled_at );
		$this->assertStringContainsString( 'will retry automatically', implode( "\n", $sub->order_notes ) );
	}

	/**
	 * Once the server times out or returns a 5xx, the other subscriptions on the order are handed to the retry without
	 * calling the server again, so an outage costs the shopper one request timeout rather than one per subscription.
	 */
	public function test_maybe_record_invoice_payment_defers_remaining_subscriptions_after_server_failure() {
		$order = WC_Helper_Order::create_order();
		$sub_1 = $this->create_linked_unpaid_subscription( 'sub_outage_1', 'in_outage_1' );
		$sub_2 = $this->create_linked_unpaid_subscription( 'sub_outage_2', 'in_outage_2' );
		$this->mock_wcs_get_subscriptions_for_order( [ $sub_1, $sub_2 ] );

		$this->mock_api_client->expects( $this->once() )
			->method( 'charge_invoice' )
			->with( 'in_outage_1' )
			->willThrowException( new Connection_Exception( 'cURL error 28: Operation timed out', 'wcpay_http_request_failed', 500 ) );
		$this->mock_api_client->expects( $this->never() )->method( 'get_subscription' );

		$this->invoice_service->maybe_record_invoice_payment( $order->get_id() );

		$this->assertNotFalse( $this->get_scheduled_retry( $order, $sub_1, 'in_outage_1', 1 ) );
		$this->assertNotFalse( $this->get_scheduled_retry( $order, $sub_2, 'in_outage_2', 1 ) );
		$this->assertTrue( WC_Payments_Subscription_Service::is_wcpay_subscription( $sub_2 ) );
	}

	/**
	 * A 4xx for one subscription says nothing about the server's health, so the next subscription is still charged.
	 */
	public function test_maybe_record_invoice_payment_keeps_charging_after_client_error() {
		$order = WC_Helper_Order::create_order();
		$sub_1 = $this->create_linked_unpaid_subscription( 'sub_4xx_1', 'in_4xx_1' );
		$sub_2 = $this->create_linked_unpaid_subscription( 'sub_4xx_2', 'in_4xx_2' );
		$this->mock_wcs_get_subscriptions_for_order( [ $sub_1, $sub_2 ] );

		$this->mock_api_client->expects( $this->exactly( 2 ) )
			->method( 'charge_invoice' )
			->willReturnCallback(
				function ( $invoice_id ) {
					if ( 'in_4xx_1' === $invoice_id ) {
						throw new API_Exception( 'Error: The invoice could not be updated', 'invoice_update_failed', 400 );
					}
					return [];
				}
			);

		$this->invoice_service->maybe_record_invoice_payment( $order->get_id() );

		$this->assertNotFalse( $this->get_scheduled_retry( $order, $sub_1, 'in_4xx_1', 1 ) );
		$this->assertFalse( $this->get_scheduled_retry( $order, $sub_2, 'in_4xx_2', 1 ) );
	}

	/**
	 * A failed retry for a subscription that may still be live schedules the next attempt with the backoff delay.
	 *
	 * @dataProvider provider_retry_backoff
	 *
	 * @param int $failed_attempt The retry attempt that fails.
	 * @param int $delay          The expected delay before the next attempt, in seconds.
	 */
	public function test_retry_record_invoice_payment_schedules_next_attempt_with_backoff( int $failed_attempt, int $delay ) {
		$invoice_id = 'in_backoff' . $failed_attempt;
		$order      = WC_Helper_Order::create_order();
		$sub        = $this->create_linked_unpaid_subscription( 'sub_backoff', $invoice_id );

		$this->mock_api_client->method( 'charge_invoice' )
			->willThrowException( new API_Exception( 'Server error. Please try again.', 'server_error', 500 ) );
		$this->mock_api_client->method( 'get_subscription' )
			->willReturn( [ 'status' => 'active' ] );

		$before = time();
		$this->run_retry_attempt( $order, $sub, $invoice_id, $failed_attempt );

		$scheduled_at = $this->get_scheduled_retry( $order, $sub, $invoice_id, $failed_attempt + 1 );
		$this->assertGreaterThanOrEqual( $before + $delay, $scheduled_at );
		$this->assertLessThanOrEqual( time() + $delay, $scheduled_at );
	}

	/**
	 * Backoff delays before the next retry attempt.
	 *
	 * @return array<string, array{0: int, 1: int}>
	 */
	public function provider_retry_backoff(): array {
		return [
			'attempt 1 fails, attempt 2 in 1 hour'   => [ 1, 3600 ],
			'attempt 2 fails, attempt 3 in 6 hours'  => [ 2, 21600 ],
			'attempt 3 fails, attempt 4 in 24 hours' => [ 3, 86400 ],
		];
	}

	/**
	 * An "invoice is already paid" failure is swallowed with no server lookup, unlink, or retry (unchanged behaviour).
	 */
	public function test_maybe_record_invoice_payment_swallows_already_paid() {
		$invoice_id = 'in_paid123';
		$order      = WC_Helper_Order::create_order();
		$sub        = $this->create_linked_unpaid_subscription( 'sub_paid', $invoice_id );
		$this->mock_wcs_get_subscriptions_for_order( [ $sub ] );

		$this->mock_api_client->method( 'charge_invoice' )
			->willThrowException( new API_Exception( 'Error: invoice is already paid', 'invoice_already_paid', 400 ) );
		$this->mock_api_client->expects( $this->never() )->method( 'get_subscription' );
		$this->mock_api_client->expects( $this->never() )->method( 'cancel_subscription' );

		$this->invoice_service->maybe_record_invoice_payment( $order->get_id() );

		$this->assertSame( 'sub_paid', $sub->get_meta( self::SUBSCRIPTION_ID_META_KEY, true ) );
		$this->assertTrue( WC_Payments_Subscription_Service::is_wcpay_subscription( $sub ) );
	}

	/**
	 * A successful retry records the payment and converts a manual subscription to automatic, like the sync path.
	 */
	public function test_retry_record_invoice_payment_converts_manual_subscription_on_success() {
		$invoice_id          = 'in_retry123';
		$order               = WC_Helper_Order::create_order();
		$sub                 = $this->create_linked_unpaid_subscription( 'sub_retry', $invoice_id );
		$sub->payment_tokens = [ uniqid( 'pm_' ) ];
		$sub->set_requires_manual_renewal( true );
		$sub->save();

		WC_Subscriptions::set_wcs_get_subscription(
			function ( $_unused_id ) use ( $sub ) {
				return $sub;
			}
		);

		$this->mock_api_client->expects( $this->once() )
			->method( 'charge_invoice' )
			->with( $invoice_id, [ 'paid_out_of_band' => 'true' ] );

		$this->invoice_service->retry_record_invoice_payment(
			[
				'order_id'        => $order->get_id(),
				'subscription_id' => $sub->get_id(),
				'invoice_id'      => $invoice_id,
				'attempt'         => 1,
			]
		);

		$this->assertNotTrue( $sub->is_manual() );
	}

	/**
	 * On the final retry attempt the subscription is left active and linked, with no further retry and no suspension,
	 * which is the bound that stops an endless retry loop. The out-of-band charge is attempted once more and,
	 * when it fails, a durable merchant-visible note records the exhausted state for manual review.
	 */
	public function test_retry_record_invoice_payment_stops_and_keeps_link_on_exhaustion() {
		$invoice_id = 'in_exhaust123';
		$order      = WC_Helper_Order::create_order();
		$sub        = $this->create_linked_unpaid_subscription( 'sub_exhaust', $invoice_id );

		WC_Subscriptions::set_wcs_get_subscription(
			function ( $_unused_id ) use ( $sub ) {
				return $sub;
			}
		);

		// The final attempt must actually try the out-of-band charge again; stubbing it to fail is what drives the
		// exhaustion branch, so assert the call happens rather than letting the test pass without it.
		$this->mock_api_client->expects( $this->once() )
			->method( 'charge_invoice' )
			->with( $invoice_id, [ 'paid_out_of_band' => 'true' ] )
			->willThrowException( new API_Exception( 'Server error. Please try again.', 'server_error', 500 ) );
		$this->mock_api_client->method( 'get_subscription' )
			->with( 'sub_exhaust' )
			->willReturn( [ 'status' => 'active' ] );
		$this->mock_api_client->expects( $this->never() )
			->method( 'cancel_subscription' );

		$this->invoice_service->retry_record_invoice_payment(
			[
				'order_id'        => $order->get_id(),
				'subscription_id' => $sub->get_id(),
				'invoice_id'      => $invoice_id,
				'attempt'         => WC_Payments_Invoice_Service::MAX_RECORD_INVOICE_PAYMENT_RETRIES,
			]
		);

		// Left active and linked, not suspended, and no further retry is scheduled (the loop stops at the bound).
		$this->assertTrue( WC_Payments_Subscription_Service::is_wcpay_subscription( $sub ) );
		$this->assertFalse( (bool) as_next_scheduled_action( WC_Payments_Invoice_Service::RECORD_INVOICE_PAYMENT_RETRY_HOOK ) );

		// A durable, merchant-visible note records the exhausted state for manual review.
		$this->assertStringContainsString( 'could not record the payment for invoice in_exhaust123', implode( "\n", $sub->order_notes ) );
		$this->assertStringContainsString( 'needs manual review', implode( "\n", $sub->order_notes ) );
	}

	/**
	 * A retry for a subscription that has since been cancelled or expired does nothing: the store already cancelled
	 * the WooPayments subscription, so recording the payment or unlinking it would only add a misleading note.
	 *
	 * @dataProvider provider_ended_subscription_statuses
	 *
	 * @param string $status The subscription status when the retry runs.
	 */
	public function test_retry_record_invoice_payment_skips_ended_subscription( string $status ) {
		$invoice_id  = 'in_ended123';
		$order       = WC_Helper_Order::create_order();
		$sub         = $this->create_linked_unpaid_subscription( 'sub_ended', $invoice_id );
		$sub->status = $status;

		$this->mock_api_client->expects( $this->never() )->method( 'charge_invoice' );
		$this->mock_api_client->expects( $this->never() )->method( 'get_subscription' );

		$this->run_retry_attempt( $order, $sub, $invoice_id, 1 );

		$this->assertSame( 'sub_ended', $sub->get_meta( self::SUBSCRIPTION_ID_META_KEY, true ) );
		$this->assertFalse( (bool) as_next_scheduled_action( WC_Payments_Invoice_Service::RECORD_INVOICE_PAYMENT_RETRY_HOOK ) );
		$this->assertSame( [], (array) $sub->order_notes );
	}

	/**
	 * Subscription statuses whose retries are skipped.
	 *
	 * @return array<string, array{0: string}>
	 */
	public function provider_ended_subscription_statuses(): array {
		return [
			'cancelled' => [ 'cancelled' ],
			'expired'   => [ 'expired' ],
		];
	}

	/**
	 * A retry still records the payment while the subscription has a paid period, including on-hold and pending-cancel.
	 *
	 * @dataProvider provider_live_subscription_statuses
	 *
	 * @param string $status The subscription status when the retry runs.
	 */
	public function test_retry_record_invoice_payment_records_payment_for_live_subscription( string $status ) {
		$invoice_id  = 'in_live_status123';
		$order       = WC_Helper_Order::create_order();
		$sub         = $this->create_linked_unpaid_subscription( 'sub_live_status', $invoice_id );
		$sub->status = $status;

		$this->mock_api_client->expects( $this->once() )
			->method( 'charge_invoice' )
			->with( $invoice_id, [ 'paid_out_of_band' => 'true' ] );

		$this->run_retry_attempt( $order, $sub, $invoice_id, 1 );
	}

	/**
	 * Subscription statuses whose retries still record the payment.
	 *
	 * @return array<string, array{0: string}>
	 */
	public function provider_live_subscription_statuses(): array {
		return [
			'active'         => [ 'active' ],
			'on-hold'        => [ 'on-hold' ],
			'pending-cancel' => [ 'pending-cancel' ],
		];
	}

	/**
	 * Runs one scheduled retry of recording the out-of-band payment for a subscription's invoice.
	 *
	 * @param WC_Order        $order        The order being processed.
	 * @param WC_Subscription $subscription The subscription the retry resolves to.
	 * @param string          $invoice_id   The WooPayments invoice ID.
	 * @param int             $attempt      The retry attempt number.
	 */
	private function run_retry_attempt( WC_Order $order, WC_Subscription $subscription, string $invoice_id, int $attempt ) {
		WC_Subscriptions::set_wcs_get_subscription(
			function ( $_unused_id ) use ( $subscription ) {
				return $subscription;
			}
		);

		$this->invoice_service->retry_record_invoice_payment(
			[
				'order_id'        => $order->get_id(),
				'subscription_id' => $subscription->get_id(),
				'invoice_id'      => $invoice_id,
				'attempt'         => $attempt,
			]
		);
	}

	/**
	 * Returns the pending retry action's scheduled timestamp for a payload, or false when none is pending.
	 *
	 * @param WC_Order        $order        The order being processed.
	 * @param WC_Subscription $subscription The subscription.
	 * @param string          $invoice_id   The WooPayments invoice ID.
	 * @param int             $attempt      The retry attempt number.
	 * @return int|bool
	 */
	private function get_scheduled_retry( WC_Order $order, WC_Subscription $subscription, string $invoice_id, int $attempt ) {
		return as_next_scheduled_action(
			WC_Payments_Invoice_Service::RECORD_INVOICE_PAYMENT_RETRY_HOOK,
			[
				[
					'order_id'        => $order->get_id(),
					'subscription_id' => $subscription->get_id(),
					'invoice_id'      => $invoice_id,
					'attempt'         => $attempt,
				],
			],
			WC_Payments_Action_Scheduler_Service::GROUP_ID
		);
	}

	/**
	 * Builds a WCPay (Stripe Billing) subscription linked to a server-side subscription and invoice, in the state
	 * maybe_record_invoice_payment() acts on: the subscription carries the invoice id while the parent order does not.
	 *
	 * @param string $wcpay_subscription_id The server-side subscription ID.
	 * @param string $invoice_id            The server-side invoice ID.
	 * @return WC_Subscription
	 */
	private function create_linked_unpaid_subscription( string $wcpay_subscription_id, string $invoice_id ): WC_Subscription {
		$subscription                 = new WC_Subscription();
		$subscription->payment_method = 'woocommerce_payments';
		$subscription->status         = 'active';
		$subscription->update_meta_data( self::SUBSCRIPTION_ID_META_KEY, $wcpay_subscription_id );
		$subscription->update_meta_data( self::ORDER_INVOICE_ID_KEY, $invoice_id );
		$subscription->update_meta_data( self::PENDING_INVOICE_ID_KEY, $invoice_id );
		$subscription->save();

		return $subscription;
	}

	/**
	 * Gives the subscription a parent order with the given WooPayments mode.
	 *
	 * @param WC_Subscription $subscription The subscription.
	 * @param string|null     $mode         Null for no parent order, '' for a parent order without a saved mode, or
	 *                                      'current' for the store's current mode.
	 */
	private function set_parent_order_mode( WC_Subscription $subscription, ?string $mode ) {
		if ( null === $mode ) {
			return;
		}

		$parent_order = WC_Helper_Order::create_order();
		if ( 'current' === $mode ) {
			$parent_order->update_meta_data(
				WC_Payments_Order_Service::WCPAY_MODE_META_KEY,
				WC_Payments::mode()->is_test() ? Order_Mode::TEST : Order_Mode::PRODUCTION
			);
			$parent_order->save();
		}

		$subscription->set_parent( $parent_order );
	}

	/**
	 * Tests for WC_Payments_Invoice_Service::validate_invoice() with valid data.
	 */
	public function test_validate_invoice_with_valid_data() {
		$mock_order        = WC_Helper_Order::create_order();
		$mock_subscription = new WC_Subscription();
		$mock_subscription->set_parent( $mock_order );
		$mock_subscription->update_meta_data( self::SUBSCRIPTION_ID_META_KEY, [ 'sub_test123' ] );
		$mock_subscription->update_meta_data( self::SUBSCRIPTION_DISCOUNT_IDS_META_KEY, [ 'di_test123' ] );

		foreach ( $mock_order->get_items( [ 'line_item', 'fee', 'shipping' ] ) as $item ) {
			$item->update_meta_data( self::SUBSCRIPTION_ITEM_ID_META_KEY, 'si_test123_' . $item->get_type() );
		}

		$mock_item_data = [
			[
				'subscription_item' => 'si_test123_line_item',
				'quantity'          => 4,
				'price'             =>
					[
						'unit_amount_decimal' => 1000,
						'currency'            => 'usd',
						'recurring'           =>
							[
								'interval'       => 'month',
								'interval_count' => 1,
							],
					],
			],
			[
				'subscription_item' => 'si_test123_shipping',
				'quantity'          => 1,
				'price'             =>
					[
						'unit_amount_decimal' => 1000,
						'currency'            => 'usd',
						'recurring'           =>
							[
								'interval'       => 'month',
								'interval_count' => 1,
							],
					],
			],
		];

		$mock_discount_data = [ 'di_test123' ];

		$this->mock_api_client
			->expects( $this->never() )
			->method( 'update_subscription_item' );

		$this->mock_api_client
			->expects( $this->never() )
			->method( 'update_subscription' );

		$invoice_service = $this->getMockBuilder( WC_Payments_Invoice_Service::class )
			->setConstructorArgs( [ $this->mock_api_client, $this->mock_order_service ] )
			->onlyMethods( [ 'get_recurring_items', 'get_wcpay_item_id' ] )
			->getMock();

		$subscription_items = [];
		foreach ( $mock_order->get_items( [ 'line_item', 'fee', 'shipping' ] ) as $item ) {
			$subscription_items[ $item->get_id() ] = $item;
		}

		$invoice_service
			->expects( $this->once() )
			->method( 'get_recurring_items' )
			->willReturn(
				[
					[
						'quantity'   => 4,
						'price_data' => [
							'unit_amount_decimal' => 1000,
							'currency'            => 'usd',
						],
						'metadata'   => [
							'wc_item_id' => key( $subscription_items ),
						],
					],
					[
						'quantity'   => 1,
						'price_data' => [
							'unit_amount_decimal' => 1000,
							'currency'            => 'usd',
						],
						'metadata'   => [
							'wc_item_id' => array_keys( $subscription_items )[1],
						],
					],
				]
			);

		$invoice_service
			->method( 'get_wcpay_item_id' )
			->willReturnCallback(
				function ( $item ) {
					return 'si_test123_' . $item->get_type();
				}
			);

		$invoice_service->validate_invoice( $mock_item_data, $mock_discount_data, $mock_subscription );
	}

	/**
	 * Tests for WC_Payments_Invoice_Service::validate_invoice() with invalid data.
	 */
	public function test_validate_invoice_with_invalid_data() {
		$mock_order        = WC_Helper_Order::create_order();
		$mock_subscription = new WC_Subscription();
		$mock_subscription->set_parent( $mock_order );
		$mock_subscription->update_meta_data( self::SUBSCRIPTION_ID_META_KEY, 'sub_test123' );
		$mock_subscription->update_meta_data( self::SUBSCRIPTION_DISCOUNT_IDS_META_KEY, [ 'di_test123' ] );

		foreach ( $mock_order->get_items( [ 'line_item', 'fee', 'shipping' ] ) as $item ) {
			$item->update_meta_data( self::SUBSCRIPTION_ITEM_ID_META_KEY, 'si_test123_' . $item->get_type() );
		}

		$mock_item_data = [
			[
				'subscription_item' => 'si_test123_line_item',
				'quantity'          => 1,
				'price'             =>
					[
						'unit_amount_decimal' => 1000,
						'currency'            => 'usd',
						'recurring'           =>
							[
								'interval'       => 'month',
								'interval_count' => 1,
							],
					],
			],
			[
				'subscription_item' => 'si_test123_shipping',
				'quantity'          => 1,
				'price'             =>
					[
						'unit_amount_decimal' => 1000,
						'currency'            => 'usd',
						'recurring'           =>
							[
								'interval'       => 'month',
								'interval_count' => 1,
							],
					],
			],
		];

		$mock_discount_data = [ 'di_test456' ];

		$this->mock_api_client
			->expects( $this->once() )
			->method( 'update_subscription_item' )
			->with(
				'si_test123_line_item',
				[
					'quantity' => 4,
				]
			);

		$this->mock_api_client
			->expects( $this->once() )
			->method( 'update_subscription' )
			->with(
				'sub_test123',
				[
					'discounts' => [],
				]
			)
			->willReturn(
				[ 'discounts' => [] ]
			);

		// Create a partial mock of the invoice service.
		$invoice_service = $this->getMockBuilder( WC_Payments_Invoice_Service::class )
			->setConstructorArgs( [ $this->mock_api_client, $this->mock_order_service ] )
			->onlyMethods( [ 'get_recurring_items', 'get_wcpay_item_id' ] )
			->getMock();

		// Create a map of subscription items.
		$subscription_items = [];
		foreach ( $mock_order->get_items( [ 'line_item', 'fee', 'shipping' ] ) as $item ) {
			$subscription_items[ $item->get_id() ] = $item;
		}

		// Mock get_recurring_items to return data that will trigger a quantity mismatch (4 instead of 1).
		$invoice_service
			->expects( $this->once() )
			->method( 'get_recurring_items' )
			->willReturn(
				[
					[
						'quantity'   => 4, // Different quantity than in mock_item_data (1).
						'price_data' => [
							'unit_amount_decimal' => 1000,
							'currency'            => 'usd',
						],
						'metadata'   => [
							'wc_item_id' => key( $subscription_items ), // First item ID (line_item).
						],
					],
					[
						'quantity'   => 1,
						'price_data' => [
							'unit_amount_decimal' => 1000,
							'currency'            => 'usd',
						],
						'metadata'   => [
							'wc_item_id' => array_keys( $subscription_items )[1], // Second item ID.
						],
					],
				]
			);

		// Mock get_wcpay_item_id to return the correct ID based on item type.
		$invoice_service
			->expects( $this->exactly( 2 ) )
			->method( 'get_wcpay_item_id' )
			->willReturnCallback(
				function ( $item ) {
					return 'si_test123_' . $item->get_type();
				}
			);

		$invoice_service->validate_invoice( $mock_item_data, $mock_discount_data, $mock_subscription );
		$this->assertSame( [], $mock_subscription->get_meta( self::SUBSCRIPTION_DISCOUNT_IDS_META_KEY, true ) );
	}

	/**
	 * Tests WC_Payments_Invoice_Service::get_and_attach_intent_info_to_order() with a valid Intention object.
	 */
	public function test_get_and_attach_intent_info_to_order() {
		$mock_order = WC_Helper_Order::create_order();
		$intent_id  = 'pi_paymentIntentID';

		$intent = WC_Helper_Intention::create_intention();

		$request = $this->mock_wcpay_request( Get_Intention::class, 1, $intent_id );

		$request->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( $intent );

		$this->mock_order_service
			->expects( $this->once() )
			->method( 'attach_intent_info_to_order' )
			->willReturn( null );

		$this->invoice_service->get_and_attach_intent_info_to_order( $mock_order, $intent_id );
	}

	/**
	 * Tests WC_Payments_Invoice_Service::get_and_attach_intent_info_to_order() with a thrown exception when retrieving the PaymentIntent.
	 */
	public function test_get_and_attach_intent_info_to_order_with_exception() {
		$mock_order = WC_Helper_Order::create_order();
		$intent_id  = 'pi_paymentIntentID';

		$request = $this->mock_wcpay_request( Get_Intention::class, 1, $intent_id );

		$request->expects( $this->once() )
			->method( 'format_response' )
			->will( $this->throwException( new API_Exception( 'whoops', 'mock_error', 403 ) ) );

		$this->mock_order_service
			->expects( $this->never() )
			->method( 'attach_intent_info_to_order' )
			->willReturn( null );

		$this->invoice_service->get_and_attach_intent_info_to_order( $mock_order, $intent_id );
	}

	/**
	 * Mocks the wcs_order_contains_subscription function return.
	 *
	 * @param bool $value The value to return to wcs_order_contains_subscription() calls.
	 */
	private function mock_wcs_get_subscriptions_for_order( $value ) {
		WC_Subscriptions::set_wcs_get_subscriptions_for_order(
			function ( $_unused_order ) use ( $value ) {
				return $value;
			}
		);
	}

	/**
	 * Creates an order with a product line and a shipping line.
	 *
	 * @param float  $total    The order total.
	 * @param string $currency The order currency.
	 *
	 * @return WC_Order
	 */
	private function create_order_with_two_lines( $total, string $currency ): WC_Order {
		$order = WC_Helper_Order::create_order( 1, $total );
		$order->set_currency( $currency );
		$order->save();

		return $order;
	}
}
