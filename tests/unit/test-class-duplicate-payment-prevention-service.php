<?php
/**
 * Class WC_Payment_Gateway_WCPay_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\Constants\Intent_Status;
use WCPay\Constants\Order_Status;
use WCPay\Core\Server\Request\Get_Intention;
use WCPay\Duplicate_Payment_Prevention_Service;
use WCPay\Exceptions\Process_Payment_Exception;

/**
 * WCPay\Duplicate_Payment_Prevention_Service unit tests.
 */
class Duplicate_Payment_Prevention_Service_Test extends WCPAY_UnitTestCase {
	/**
	 * System under test.
	 *
	 * @var Duplicate_Payment_Prevention_Service
	 */
	private $service;

	/**
	 * Order service mock.
	 *
	 * @var WC_Payments_Order_Service
	 */
	private $mock_order_service;

	/**
	 * Gateway mock.
	 *
	 * @var WC_Payment_Gateway_WCPay
	 */
	private $mock_gateway;

	/**
	 * Pre-test setup
	 */
	public function set_up() {
		parent::set_up();

		$this->mock_order_service = $this->createMock( WC_Payments_Order_Service::class );
		$this->mock_gateway       = $this->createMock( WC_Payment_Gateway_WCPay::class );
		$this->service            = new Duplicate_Payment_Prevention_Service();
		$this->service->init( $this->mock_gateway, $this->mock_order_service );
	}

	public function test_check_session_order_redirect_to_previous_order() {
		$same_cart_hash = 'FAKE_SAME_CART_HASH';
		$return_url     = 'https://example.com';

		// Arrange the order saved in the session.
		$session_order = WC_Helper_Order::create_order();
		$session_order->set_cart_hash( $same_cart_hash );
		$session_order->set_status( Order_Status::COMPLETED );
		$session_order->save();
		WC()->session->set(
			Duplicate_Payment_Prevention_Service::SESSION_KEY_PROCESSING_ORDER,
			$session_order->get_id()
		);

		// Arrange the order is being processed.
		$current_order = WC_Helper_Order::create_order();
		$current_order->set_cart_hash( $same_cart_hash );
		$current_order->save();
		$current_order_id = $current_order->get_id();

		// Arrange the redirect URL.
		$this->mock_gateway
			->expects( $this->once() )
			->method( 'get_return_url' )
			->willReturn( $return_url );

		// Act: process the order but redirect to the previous/session paid order.
		$result = $this->service->check_against_session_processing_order( $current_order );

		// Assert: the result of check_against_session_processing_order.
		$this->assertSame( 'success', $result['result'] );
		$this->assertStringContainsString( $return_url, $result['redirect'] );

		// Assert: the behaviors of check_against_session_processing_order.
		$notes = wc_get_order_notes( [ 'order_id' => $session_order->get_id() ] );
		$this->assertStringContainsString(
			'WooCommerce Payments: detected and deleted order ID ' . $current_order_id,
			$notes[0]->content
		);
		$this->assertSame( Order_Status::TRASH, wc_get_order( $current_order_id )->get_status() );
		$this->assertSame(
			null,
			WC()->session->get( Duplicate_Payment_Prevention_Service::SESSION_KEY_PROCESSING_ORDER )
		);
	}

	public function test_maybe_update_session_processing_order_stores_order() {
		// Arrange the order saved in the session.
		WC()->session->set(
			Duplicate_Payment_Prevention_Service::SESSION_KEY_PROCESSING_ORDER,
			null
		);

		// Arrange the order is being processed.
		$current_order    = WC_Helper_Order::create_order();
		$current_order_id = $current_order->get_id();

		// Act: call maybe_update_session_processing_order to store the order in session.
		$this->service->maybe_update_session_processing_order( $current_order_id );

		// Assert: maybe_update_session_processing_order takes action and its value is kept.
		$this->assertSame(
			$current_order_id,
			WC()->session->get( Duplicate_Payment_Prevention_Service::SESSION_KEY_PROCESSING_ORDER )
		);

		// Destroy the session value after running test.
		WC()->session->set(
			Duplicate_Payment_Prevention_Service::SESSION_KEY_PROCESSING_ORDER,
			null
		);
	}

	/**
	 * @dataProvider provider_process_payment_check_session_and_continue_processing
	 */
	public function test_check_session_without_redirection( string $session_order_cart_hash, string $session_order_status, string $current_order_cart_hash ) {
		// Arrange the order saved in the session.
		$session_order = WC_Helper_Order::create_order();
		$session_order->set_cart_hash( $session_order_cart_hash );
		$session_order->set_status( $session_order_status );
		$session_order->save();
		WC()->session->set(
			Duplicate_Payment_Prevention_Service::SESSION_KEY_PROCESSING_ORDER,
			$session_order->get_id()
		);

		// Arrange the order is being processed.
		$current_order = WC_Helper_Order::create_order();
		$current_order->set_cart_hash( $current_order_cart_hash );
		$current_order->save();

		// Act.
		$result = $this->service->check_against_session_processing_order( $current_order );

		// Assert: no redirect was generated.
		$this->assertNull( $result );
	}

	public function provider_process_payment_check_session_and_continue_processing() {
		return [
			'Different cart hash with session order status completed'   => [ 'SESSION_ORDER_HASH', Order_Status::COMPLETED, 'CURRENT_ORDER_HASH' ],
			'Different cart hash  with session order status processing' => [ 'SESSION_ORDER_HASH', Order_Status::PROCESSING, 'CURRENT_ORDER_HASH' ],
			'Same cart hash with session order status pending'          => [ 'SAME_CART_HASH', Order_Status::PENDING, 'SAME_CART_HASH' ],
			'Same cart hash with session order status cancelled'        => [ 'SAME_CART_HASH', Order_Status::CANCELLED, 'SAME_CART_HASH' ],
		];
	}

	/**
	 * @dataProvider provider_test_check_payment_intent_attached_to_order_succeeded_with_invalid_intent_id_returns_null
	 * @param ?string $invalid_intent_id An invalid payment intent ID. If no intent id is set, this can be null.
	 */
	public function test_check_payment_intent_attached_to_order_succeeded_with_invalid_intent_id_returns_null( $invalid_intent_id ) {
		// Arrange order.
		$order = WC_Helper_Order::create_order();
		$order->update_meta_data( '_intent_id', $invalid_intent_id );
		$order->save();

		// Assert: get_intent is not called.
		$this->mock_wcpay_request( Get_Intention::class, 0 );

		// Act: process the order.
		$result = $this->service->check_payment_intent_attached_to_order_succeeded( $order );

		// Assert: No redirect was generated.
		$this->assertNull( $result );
	}

	public function provider_test_check_payment_intent_attached_to_order_succeeded_with_invalid_intent_id_returns_null(): array {
		return [
			'No intent_id is attached'   => [ null ],
			'A setup intent is attached' => [ 'seti_possible_for_a_subscription_id' ],
		];
	}

	/**
	 * The attached PaymentIntent has invalid info (status or order_id) with the order, so payment_process continues.
	 *
	 * @dataProvider provider_check_payment_intent_attached_to_order_succeeded_with_invalid_data_returns_null
	 * @param  string  $attached_intent_id Attached intent ID to the order.
	 * @param  string  $attached_intent_status Attached intent status.
	 * @param  bool  $same_order_id True when the intent meta order_id is exactly the current processing order_id. False otherwise.
	 */
	public function test_check_payment_intent_attached_to_order_succeeded_with_invalid_data_returns_null(
		string $attached_intent_id,
		string $attached_intent_status,
		bool $same_order_id
	) {
		// Arrange order.
		$order = WC_Helper_Order::create_order();
		$order->update_meta_data( '_intent_id', $attached_intent_id );
		$order->save();

		$order_id = $order->get_id();

		// Arrange mock get_intent.
		$meta_order_id   = $same_order_id ? $order_id : $order_id - 1;
		$attached_intent = WC_Helper_Intention::create_intention(
			[
				'id'       => $attached_intent_id,
				'status'   => $attached_intent_status,
				'metadata' => [ 'order_id' => $meta_order_id ],
			]
		);
		$this->mock_wcpay_request( Get_Intention::class, 1, $attached_intent_id )
			->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( $attached_intent );

		// Act: process the order.
		$result = $this->service->check_payment_intent_attached_to_order_succeeded( $order );
		$this->assertNull( $result );
	}

	public function provider_check_payment_intent_attached_to_order_succeeded_with_invalid_data_returns_null(): array {
		return [
			'Attached PaymentIntent with non-success status - same order_id' => [ 'pi_attached_intent_id', 'requires_action', true ],
			'Attached PaymentIntent - non-success status - different order_id' => [ 'pi_attached_intent_id', 'requires_action', false ],
			'Attached PaymentIntent - success status - different order_id' => [ 'pi_attached_intent_id', 'succeeded', false ],
		];
	}

	/**
	 * @dataProvider provider_check_payment_intent_attached_to_order_succeeded_return_redirection
	 */
	public function test_check_payment_intent_attached_to_order_succeeded_return_redirection( string $intent_successful_status ) {
		$attached_intent_id = 'pi_attached_intent_id';
		$return_url         = 'https://example.com';

		// Arrange the redirect URL.
		$this->mock_gateway
			->expects( $this->once() )
			->method( 'get_return_url' )
			->willReturn( $return_url );

		// Arrange order.
		$order = WC_Helper_Order::create_order();
		$order->update_meta_data( '_intent_id', $attached_intent_id );
		$order->save();
		$order_id = $order->get_id();

		// Arrange mock get_intention.
		$attached_intent = WC_Helper_Intention::create_intention(
			[
				'id'       => $attached_intent_id,
				'status'   => $intent_successful_status,
				'metadata' => [ 'order_id' => $order_id ],
			]
		);

		$this->mock_wcpay_request( Get_Intention::class, 1, $attached_intent_id )
			->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( $attached_intent );

		// Act: process the order but redirect to the order.
		$result = $this->service->check_payment_intent_attached_to_order_succeeded( $order );

		// Assert: the result of check_intent_attached_to_order_succeeded.
		$this->assertSame( 'success', $result['result'] );
		$this->assertStringContainsString( $return_url, $result['redirect'] );
	}

	public function provider_check_payment_intent_attached_to_order_succeeded_return_redirection(): array {
		$ret = [];
		foreach ( Intent_Status::AUTHORIZED_STATUSES as $status ) {
			$ret[ 'Intent status ' . $status ] = [ $status ];
		}

		return $ret;
	}

	/**
	 * Test that when duplicate payment is prevented with amount mismatch,
	 * an exception is thrown to inform the customer.
	 *
	 * This reproduces the issue from WOOPMNT-5519 where admins see misleading order notes
	 * suggesting a new charge was made when duplicate prevention kicked in.
	 */
	public function test_check_payment_intent_attached_to_order_succeeded_with_amount_mismatch() {
		$attached_intent_id = 'pi_attached_intent_id';
		$attached_charge_id = 'ch_attached_charge_id';
		$original_amount    = 1000; // $10.00 in cents.
		$updated_amount     = 1500; // $15.00 in cents.

		// Arrange order that was already paid at $10.
		$order = WC_Helper_Order::create_order();
		$order->update_meta_data( '_intent_id', $attached_intent_id );
		$order->set_total( $original_amount / 100 ); // Original amount.
		$order->save();
		$order_id = $order->get_id();

		// Simulate admin changing the order total (adding items).
		$order->set_total( $updated_amount / 100 ); // Updated amount.
		$order->set_status( 'pending' ); // Admin changed status to add items.
		$order->save();

		// Arrange mock get_intention with the original $10 charge.
		$attached_intent = WC_Helper_Intention::create_intention(
			[
				'id'       => $attached_intent_id,
				'status'   => Intent_Status::SUCCEEDED,
				'metadata' => [ 'order_id' => $order_id ],
				'amount'   => $original_amount,
				'charge'   => [
					'id'     => $attached_charge_id,
					'amount' => $original_amount,
				],
			]
		);

		$this->mock_wcpay_request( Get_Intention::class, 1, $attached_intent_id )
			->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( $attached_intent );

		// Act & Assert: Exception should be thrown when amount mismatch is detected.
		$this->expectException( Process_Payment_Exception::class );
		$this->expectExceptionMessage( 'This order was already paid for' );

		$this->service->check_payment_intent_attached_to_order_succeeded( $order );
	}

	/**
	 * @dataProvider provider_check_order_already_paid_returns_redirection
	 * @param string $paid_status An order status that counts as paid.
	 */
	public function test_check_order_already_paid_returns_redirection( string $paid_status ) {
		$return_url = 'https://example.com';

		// Arrange the redirect URL.
		$this->mock_gateway
			->expects( $this->once() )
			->method( 'get_return_url' )
			->willReturn( $return_url );

		// Arrange an order that has already been paid.
		$order = WC_Helper_Order::create_order();
		$order->set_status( $paid_status );
		$order->save();

		// Act: attempt to pay for the order a second time.
		$result = $this->service->check_order_already_paid( $order );

		// Assert: the shopper is sent to the order received page instead of being charged.
		$this->assertSame( 'success', $result['result'] );
		$this->assertStringContainsString( $return_url, $result['redirect'] );
		$this->assertStringContainsString( 'wcpay_previous_successful_intent', $result['redirect'] );
	}

	public function provider_check_order_already_paid_returns_redirection(): array {
		return [
			'Order is processing' => [ Order_Status::PROCESSING ],
			'Order is completed'  => [ Order_Status::COMPLETED ],
		];
	}

	/**
	 * @dataProvider provider_check_order_already_paid_returns_null
	 * @param string $unpaid_status An order status that does not count as paid.
	 */
	public function test_check_order_already_paid_returns_null_for_unpaid_order( string $unpaid_status ) {
		// Assert: an unpaid order never short-circuits the payment.
		$this->mock_gateway->expects( $this->never() )->method( 'get_return_url' );

		// Arrange an order that has not been paid.
		$order = WC_Helper_Order::create_order();
		$order->set_status( $unpaid_status );
		$order->save();

		// Act & Assert: processing continues.
		$this->assertNull( $this->service->check_order_already_paid( $order ) );
	}

	public function provider_check_order_already_paid_returns_null(): array {
		return [
			'Order is pending'   => [ Order_Status::PENDING ],
			'Order is failed'    => [ Order_Status::FAILED ],
			'Order is on hold'   => [ Order_Status::ON_HOLD ],
			'Order is cancelled' => [ Order_Status::CANCELLED ],
		];
	}

	/**
	 * Both checkout surfaces only start payment when `needs_payment()` is true, so a second
	 * request that reaches `process_payment()` at all must have loaded the order while it was
	 * still unpaid. The concurrent request then marks it paid in a separate PHP process, which
	 * leaves this process's caches untouched. Only a read that reaches the database sees it.
	 *
	 * The write here goes straight to the orders table for that reason: saving through WooCommerce
	 * would invalidate the order cache and hide the very staleness this covers.
	 */
	public function test_check_order_already_paid_detects_payment_written_by_a_concurrent_request() {
		global $wpdb;

		$return_url = 'https://example.com';

		$this->mock_gateway
			->expects( $this->once() )
			->method( 'get_return_url' )
			->willReturn( $return_url );

		// Arrange an unpaid order, as process_payment() would have loaded it.
		$order = WC_Helper_Order::create_order();
		$order->set_status( Order_Status::PENDING );
		$order->save();
		$order_id = $order->get_id();

		// Arrange the caches this request would have warmed on the way in.
		wc_get_order( $order_id );

		// Arrange a concurrent request marking the order paid, without touching our caches.
		if ( WC_Payments_Utils::is_hpos_tables_usage_enabled() ) {
			// Mirrors the fallback in the code under test: get_table_for_orders() only exists
			// from WooCommerce 7.9, and the plugin supports 7.6.
			$orders_table = method_exists( \Automattic\WooCommerce\Utilities\OrderUtil::class, 'get_table_for_orders' )
				? \Automattic\WooCommerce\Utilities\OrderUtil::get_table_for_orders()
				: $wpdb->prefix . 'wc_orders';

			$wpdb->update(
				$orders_table,
				[ 'status' => 'wc-processing' ],
				[ 'id' => $order_id ]
			);
		} else {
			$wpdb->update(
				$wpdb->posts,
				[ 'post_status' => 'wc-processing' ],
				[ 'ID' => $order_id ]
			);
		}

		// Assert the premise: without this, the test could pass without reaching the database.
		$this->assertFalse(
			$order->has_status( wc_get_is_paid_statuses() ),
			'The in-memory order should still be stale for this test to be meaningful.'
		);

		// Act: attempt to charge the order.
		$result = $this->service->check_order_already_paid( $order );

		// Assert: the concurrent payment is seen and the second charge is stopped.
		$this->assertSame( 'success', $result['result'] );
		$this->assertStringContainsString( $return_url, $result['redirect'] );
	}

	/**
	 * Deposit and partial-payment extensions add a status that is both paid and still payable,
	 * collecting the balance through pay-for-order. Blocking that would break a legitimate payment.
	 */
	public function test_check_order_already_paid_skips_a_paid_status_the_store_declares_payable() {
		// Assert: a payable status never short-circuits the payment.
		$this->mock_gateway->expects( $this->never() )->method( 'get_return_url' );

		// Arrange a store that treats a paid status as still awaiting payment.
		$declare_payable = function ( $statuses ) {
			$statuses[] = Order_Status::PROCESSING;
			return $statuses;
		};
		add_filter( 'woocommerce_valid_order_statuses_for_payment', $declare_payable );

		// Arrange an order in that status.
		$order = WC_Helper_Order::create_order();
		$order->set_status( Order_Status::PROCESSING );
		$order->save();

		// Act: the balance payment must be allowed through.
		$result = $this->service->check_order_already_paid( $order );

		remove_filter( 'woocommerce_valid_order_statuses_for_payment', $declare_payable );

		// Assert: processing continues so the balance can be collected.
		$this->assertNull( $result );
	}

	/**
	 * Changing a subscription's payment method legitimately re-runs payment processing
	 * against an entity that was already paid once, so it must not be blocked.
	 */
	public function test_check_order_already_paid_skips_subscription_payment_method_change() {
		$this->mock_gateway
			->expects( $this->once() )
			->method( 'is_changing_payment_method_for_subscription' )
			->willReturn( true );
		$this->mock_gateway->expects( $this->never() )->method( 'get_return_url' );

		// Arrange a paid order, which would otherwise be blocked.
		$order = WC_Helper_Order::create_order();
		$order->set_status( Order_Status::PROCESSING );
		$order->save();

		// Act & Assert: processing continues.
		$this->assertNull( $this->service->check_order_already_paid( $order ) );
	}

	public function test_check_order_already_paid_clears_the_session_processing_order() {
		$this->mock_gateway->method( 'get_return_url' )->willReturn( 'https://example.com' );

		// Arrange a paid order that is also the session's processing order.
		$order = WC_Helper_Order::create_order();
		$order->set_status( Order_Status::PROCESSING );
		$order->save();
		WC()->session->set(
			Duplicate_Payment_Prevention_Service::SESSION_KEY_PROCESSING_ORDER,
			$order->get_id()
		);

		// Act: attempt to pay for the order a second time.
		$this->service->check_order_already_paid( $order );

		// Assert: the stale session entry is cleared so it cannot mislead a later submission.
		$this->assertNull(
			WC()->session->get( Duplicate_Payment_Prevention_Service::SESSION_KEY_PROCESSING_ORDER )
		);
	}
}
