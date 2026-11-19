<?php
/**
 * Class WC_Payment_Gateway_WCPay_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\Constants\Intent_Status;
use WCPay\Constants\Order_Status;
use WCPay\Core\Server\Request\Cancel_Intention;
use WCPay\Core\Server\Request\Get_Intention;
use WCPay\Duplicate_Payment_Prevention_Service;
use WCPay\Exceptions\API_Exception;
use WCPay\Exceptions\Process_Payment_Exception;

/**
 * WCPay\Duplicate_Payment_Prevention_Service unit tests.
 */
class Duplicate_Payment_Prevention_Service_Test extends WCPAY_UnitTestCase {
	/**
	 * Intent attached to the order by an earlier attempt.
	 */
	const ATTACHED_INTENT_ID = 'pi_attached_intent_id';

	/**
	 * Where the shopper is sent when the order turns out to be paid already.
	 */
	const RETURN_URL = 'https://example.com/order-received';

	/**
	 * A payment WooPay charged and posts with its request.
	 */
	const WOOPAY_INTENT_ID = 'pi_woopay_intent_id';

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

	/**
	 * Post-test teardown
	 */
	public function tear_down() {
		unset( $_POST['platform-checkout-intent'] );
		wc_clear_notices();
		WC()->session->set( Duplicate_Payment_Prevention_Service::SESSION_KEY_PROCESSING_ORDER, null );
		WC()->session->set( Duplicate_Payment_Prevention_Service::SESSION_KEY_PROCESSING_ORDER_OWNER, null );
		parent::tear_down();
	}

	public function test_check_session_order_redirect_to_previous_order() {
		$same_cart_hash = 'FAKE_SAME_CART_HASH';
		$return_url     = 'https://example.com';

		// Arrange the order saved in the session.
		$session_order = WC_Helper_Order::create_order();
		$session_order->set_cart_hash( $same_cart_hash );
		$session_order->set_status( Order_Status::COMPLETED );
		$session_order->save();
		$this->service->maybe_update_session_processing_order( $session_order->get_id() );

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
		$this->assertSame(
			WC()->session->get_customer_id(),
			WC()->session->get( Duplicate_Payment_Prevention_Service::SESSION_KEY_PROCESSING_ORDER_OWNER )
		);

		// Destroy the session value after running test.
		WC()->session->set(
			Duplicate_Payment_Prevention_Service::SESSION_KEY_PROCESSING_ORDER,
			null
		);
	}

	public function test_check_session_order_ignores_processing_order_stored_in_another_session() {
		$same_cart_hash = 'FAKE_SAME_CART_HASH';

		// Arrange a paid processing order whose session ID differs from the current session.
		$session_order = WC_Helper_Order::create_order();
		$session_order->set_cart_hash( $same_cart_hash );
		$session_order->set_status( Order_Status::COMPLETED );
		$session_order->save();
		$this->service->maybe_update_session_processing_order( $session_order->get_id() );
		WC()->session->set( Duplicate_Payment_Prevention_Service::SESSION_KEY_PROCESSING_ORDER_OWNER, 't_another_session' );

		// Arrange the order is being processed.
		$current_order = WC_Helper_Order::create_order();
		$current_order->set_cart_hash( $same_cart_hash );
		$current_order->save();

		// Act.
		$result = $this->service->check_against_session_processing_order( $current_order );

		// Assert: no redirect, and the current order is kept.
		$this->assertNull( $result );
		$this->assertSame( Order_Status::PENDING, wc_get_order( $current_order->get_id() )->get_status() );
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
			'Attached PaymentIntent with non-success status - same order_id' => [ 'pi_attached_intent_id', 'requires_payment_method', true ],
			'Attached PaymentIntent - non-success status - different order_id' => [ 'pi_attached_intent_id', 'requires_action', false ],
			'Attached PaymentIntent - success status - different order_id' => [ 'pi_attached_intent_id', 'succeeded', false ],
		];
	}

	/**
	 * A lookup that fails with an exception, as on a timeout, skips the check: a shopper must be able to retry
	 * when the server is unreachable.
	 */
	public function test_check_payment_intent_attached_to_order_succeeded_skips_the_check_when_the_lookup_throws_an_exception() {
		$order = $this->create_order_with_attached_intent();
		$this->mock_attached_intent_statuses( $order, [ new API_Exception( 'test exception', 'server_error', 500 ) ] );
		$this->mock_order_service
			->expects( $this->never() )
			->method( 'cancel_attempt_replaced_by_new_payment' );

		$this->assertNull( $this->service->check_payment_intent_attached_to_order_succeeded( $order ) );
	}

	/**
	 * A lookup that fails with a PHP error, as a response that is not an intent does, stops the payment: the
	 * attached payment may have gone through, and nothing was read to rule that out.
	 */
	public function test_check_payment_intent_attached_to_order_succeeded_does_not_swallow_an_error_on_the_first_lookup() {
		$order = $this->create_order_with_attached_intent();
		$this->mock_attached_intent_statuses( $order, [ new TypeError( 'test error' ) ] );
		$this->mock_order_service
			->expects( $this->never() )
			->method( 'cancel_attempt_replaced_by_new_payment' );

		$this->expectException( TypeError::class );

		$this->service->check_payment_intent_attached_to_order_succeeded( $order );
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

	/**
	 * When the cancel fails and the attempt is still open, or can no longer be read, it could yet charge the
	 * shopper. The new payment is refused for now, the order keeps its status, and the merchant gets one note
	 * however often the shopper tries.
	 *
	 * @dataProvider provider_attempts_still_open_after_a_failed_cancel
	 *
	 * @param mixed $second_fetch The status the second fetch returns, or the Throwable it throws.
	 */
	public function test_check_payment_intent_attached_to_order_succeeded_refuses_a_new_payment_while_the_attempt_is_open( $second_fetch ) {
		$this->service->init( $this->mock_gateway, $this->order_service_whose_cancel_fails() );
		$order = $this->create_order_with_attached_intent();
		$this->mock_attached_intent_statuses( $order, [ Intent_Status::REQUIRES_ACTION, $second_fetch, Intent_Status::REQUIRES_ACTION, $second_fetch ] );
		// The block checkout takes its message from the gateway's error action.
		$messages_for_the_block_checkout = [];
		add_action(
			'wcpay_update_payment_result_on_error',
			function ( $error ) use ( &$messages_for_the_block_checkout ) {
				$messages_for_the_block_checkout[] = $error->getMessage();
			}
		);

		$result = $this->service->check_payment_intent_attached_to_order_succeeded( $order );
		$this->service->check_payment_intent_attached_to_order_succeeded( $order );

		$notices = wc_get_notices( 'error' );
		$this->assertSame( 'fail', $result['result'] );
		$this->assertStringContainsString( 'earlier payment attempt for this order is still in progress', $notices[0]['notice'] );
		$this->assertStringContainsString( 'earlier payment attempt for this order is still in progress', $messages_for_the_block_checkout[0] );
		$this->assertSame( 'pending', wc_get_order( $order->get_id() )->get_status() );
		$this->assertSame( 1, preg_match_all( '/pi_attached_intent_id.*could not be cancelled, so a new payment was not started/', $this->get_order_notes_text( $order ) ) );
	}

	/**
	 * Second looks at an attempt that leave it able to charge the shopper.
	 *
	 * @return array
	 */
	public function provider_attempts_still_open_after_a_failed_cancel(): array {
		return [
			'still waiting for the shopper'         => [ Intent_Status::REQUIRES_ACTION ],
			'it cannot be read'                     => [ new Exception( 'test exception' ) ],
			'reading it fails without an exception' => [ new Error( 'test error' ) ],
		];
	}

	/**
	 * When the cancel fails because the attempt ended meanwhile, it can no longer charge the shopper, so the
	 * new payment goes ahead without alarming the merchant. A cancelled attempt is detached too: a 3DS result
	 * reported later for a cancelled intent the order names would cancel the order. One that failed at 3DS
	 * stays, as after any decline.
	 *
	 * @dataProvider provider_attempts_ended_after_a_failed_cancel
	 *
	 * @param string $second_status          The status the second fetch returns.
	 * @param string $intent_named_afterward The intent the order names afterwards.
	 */
	public function test_check_payment_intent_attached_to_order_succeeded_goes_ahead_when_the_attempt_ended_meanwhile( string $second_status, string $intent_named_afterward ) {
		$this->service->init( $this->mock_gateway, $this->order_service_whose_cancel_fails() );
		$order = $this->create_order_with_attached_intent();
		$this->mock_attached_intent_statuses( $order, [ Intent_Status::REQUIRES_ACTION, $second_status ] );

		$result = $this->service->check_payment_intent_attached_to_order_succeeded( $order );

		$this->assertNull( $result );
		$this->assertSame( [], wc_get_notices( 'error' ) );
		$this->assertStringNotContainsString( 'could not be cancelled', $this->get_order_notes_text( $order ) );
		$this->assertSame( $intent_named_afterward, wc_get_order( $order->get_id() )->get_meta( '_intent_id' ) );
	}

	/**
	 * Statuses of an attempt that can no longer be completed, and the intent the order names afterwards.
	 *
	 * @return array
	 */
	public function provider_attempts_ended_after_a_failed_cancel(): array {
		return [
			'cancelled, by another request or by a cancel call that errored late' => [ Intent_Status::CANCELED, '' ],
			'failed at 3DS' => [ Intent_Status::REQUIRES_PAYMENT_METHOD, 'pi_attached_intent_id' ],
		];
	}

	/**
	 * WooPay has charged the payment it posts before the store hears of it, so holding the request back can't
	 * prevent a second charge: it only leaves the shopper charged with no order. The request goes ahead, and
	 * the merchant is told once that the earlier payment may still complete.
	 *
	 * @dataProvider provider_attempts_still_open_after_a_failed_cancel
	 *
	 * @param mixed $second_fetch The status the second fetch returns, or the Throwable it throws.
	 */
	public function test_check_payment_intent_attached_to_order_succeeded_does_not_refuse_a_request_that_brings_a_woopay_payment( $second_fetch ) {
		$this->service->init( $this->mock_gateway, $this->order_service_whose_cancel_fails() );
		$order                             = $this->create_order_with_attached_intent();
		$_POST['platform-checkout-intent'] = self::WOOPAY_INTENT_ID;
		$this->mock_attached_intent_statuses( $order, [ Intent_Status::REQUIRES_ACTION, $second_fetch, Intent_Status::REQUIRES_ACTION, $second_fetch ] );
		$errors_for_the_block_checkout = 0;
		add_action(
			'wcpay_update_payment_result_on_error',
			function () use ( &$errors_for_the_block_checkout ) {
				++$errors_for_the_block_checkout;
			}
		);

		$result = $this->service->check_payment_intent_attached_to_order_succeeded( $order );
		$this->service->check_payment_intent_attached_to_order_succeeded( $order );

		$notes = $this->get_order_notes_text( $order );
		$this->assertNull( $result );
		$this->assertSame( [], wc_get_notices( 'error' ) );
		$this->assertSame( 0, $errors_for_the_block_checkout );
		$this->assertSame( 'pi_attached_intent_id', wc_get_order( $order->get_id() )->get_meta( '_intent_id' ) );
		$this->assertSame( 1, preg_match_all( '/pi_attached_intent_id.*could not be cancelled before a WooPay payment/', $notes ) );
		$this->assertStringNotContainsString( 'a new payment was not started', $notes );
	}

	/**
	 * A new payment for an order whose earlier attempt is still waiting for 3DS cancels that attempt at the
	 * server, the order stops naming it, and the new payment goes ahead.
	 */
	public function test_check_payment_intent_attached_to_order_succeeded_cancels_and_detaches_through_the_order_service() {
		$this->use_real_order_service();
		$order = $this->create_order_with_attached_intent();
		$order->update_meta_data( '_intention_status', Intent_Status::REQUIRES_ACTION );
		$order->save();
		$this->mock_attached_intent_statuses( $order, [ Intent_Status::REQUIRES_ACTION ] );
		$this->expect_cancel_of_attached_intent();
		// Once the attempt is cancelled there is nothing left to look at.
		$this->mock_wcpay_request( Get_Intention::class, 0 );

		$result = $this->service->check_payment_intent_attached_to_order_succeeded( $order );

		$this->assertNull( $result );
		$this->assertSame( '', $order->get_meta( '_intent_id' ) );
		$this->assertSame( '', wc_get_order( $order->get_id() )->get_meta( '_intent_id' ) );
		$this->assertSame( '', wc_get_order( $order->get_id() )->get_meta( '_intention_status' ) );
	}

	/**
	 * The cancel fails when the shopper finished the earlier attempt in the meantime. The order is then
	 * settled with that payment instead of being charged again, without alarming the merchant.
	 */
	public function test_check_payment_intent_attached_to_order_succeeded_settles_the_order_when_the_server_refuses_the_cancel() {
		$this->use_real_order_service();
		$this->mock_gateway
			->method( 'get_return_url' )
			->willReturn( self::RETURN_URL );
		$order = $this->create_order_with_attached_intent();
		$this->mock_attached_intent_statuses( $order, [ Intent_Status::REQUIRES_ACTION, Intent_Status::SUCCEEDED ] );
		$this->expect_cancel_of_attached_intent( new API_Exception( 'test exception', 'payment_intent_unexpected_state', 400 ) );

		$result = $this->service->check_payment_intent_attached_to_order_succeeded( $order );

		$order = wc_get_order( $order->get_id() );
		$this->assertSame( 'success', $result['result'] );
		$this->assertStringContainsString( 'wcpay_previous_successful_intent=yes', $result['redirect'] );
		$this->assertSame( 'processing', $order->get_status() );
		$this->assertSame( 'pi_attached_intent_id', $order->get_meta( '_intent_id' ) );
		$this->assertStringNotContainsString( 'could not be cancelled', $this->get_order_notes_text( $order ) );
	}

	/**
	 * The two services together: a cancel the server refuses while the attempt is still open refuses the new
	 * payment, and the order names the attempt again, so the shopper's next try can cancel it.
	 */
	public function test_check_payment_intent_attached_to_order_succeeded_names_the_attempt_again_when_it_refuses() {
		$this->use_real_order_service();
		$order = $this->create_order_with_attached_intent();
		$this->mock_attached_intent_statuses( $order, [ Intent_Status::REQUIRES_ACTION, Intent_Status::REQUIRES_ACTION ] );
		$this->expect_cancel_of_attached_intent( new API_Exception( 'test exception', 'server_error', 500 ) );

		$result = $this->service->check_payment_intent_attached_to_order_succeeded( $order );

		$this->assertSame( 'fail', $result['result'] );
		$this->assertSame( 'pi_attached_intent_id', wc_get_order( $order->get_id() )->get_meta( '_intent_id' ) );
	}

	/**
	 * The two services together: a cancel call that errors although the attempt is cancelled. The order must
	 * not name the cancelled attempt again at any point, not even while it is looked at once more.
	 */
	public function test_check_payment_intent_attached_to_order_succeeded_never_names_an_attempt_again_that_turns_out_cancelled() {
		$this->use_real_order_service();
		$order = $this->create_order_with_attached_intent();
		$order->set_transaction_id( self::ATTACHED_INTENT_ID );
		$order->update_meta_data( '_intention_status', Intent_Status::REQUIRES_ACTION );
		$order->save();
		$this->mock_attached_intent_statuses( $order, [ Intent_Status::REQUIRES_ACTION ] );
		$this->expect_cancel_of_attached_intent( new API_Exception( 'test exception', 'server_error', 500 ) );
		$named_during_the_second_look = null;
		$this->mock_wcpay_request( Get_Intention::class, 1, self::ATTACHED_INTENT_ID )
			->expects( $this->once() )
			->method( 'format_response' )
			->willReturnCallback(
				function () use ( $order, &$named_during_the_second_look ) {
					$named_during_the_second_look = wc_get_order( $order->get_id() )->get_meta( '_intent_id' );

					return WC_Helper_Intention::create_intention(
						[
							'id'       => self::ATTACHED_INTENT_ID,
							'status'   => Intent_Status::CANCELED,
							'metadata' => [ 'order_id' => $order->get_id() ],
						]
					);
				}
			);

		$result = $this->service->check_payment_intent_attached_to_order_succeeded( $order );

		$order = wc_get_order( $order->get_id() );
		$this->assertNull( $result );
		$this->assertSame( '', $named_during_the_second_look );
		$this->assertSame( '', $order->get_meta( '_intent_id' ) );
		$this->assertSame( '', $order->get_meta( '_intention_status' ) );
		$this->assertSame( '', $order->get_transaction_id() );
		$this->assertSame( 1, substr_count( $this->get_order_notes_text( $order ), 'was cancelled because a new payment was started' ) );
	}

	/**
	 * WooPay has charged the shopper already, so a note that can't be added must not fail the request.
	 */
	public function test_check_payment_intent_attached_to_order_succeeded_does_not_fail_a_woopay_request_over_a_note() {
		$this->service->init( $this->mock_gateway, $this->order_service_whose_cancel_fails() );
		$order                             = $this->create_order_with_attached_intent();
		$_POST['platform-checkout-intent'] = self::WOOPAY_INTENT_ID;
		$this->mock_attached_intent_statuses( $order, [ Intent_Status::REQUIRES_ACTION, Intent_Status::REQUIRES_ACTION ] );
		add_filter(
			'woocommerce_new_order_note_data',
			function () {
				throw new RuntimeException( 'test exception' );
			}
		);

		$result = $this->service->check_payment_intent_attached_to_order_succeeded( $order );

		$this->assertNull( $result );
		$this->assertSame( [], wc_get_notices( 'error' ) );
	}

	/**
	 * An intent WooPay created names the store's order by its number, not by an order ID.
	 *
	 * @dataProvider provider_woopay_intent_metadata
	 *
	 * @param bool $paid_on_woopay              Whether the intent says it was paid on WooPay.
	 * @param bool $order_number_is_this_order Whether the intent's order number is this order's.
	 * @param bool $settles                    Whether the intent settles the order.
	 */
	public function test_check_payment_intent_attached_to_order_succeeded_matches_a_woopay_intent_by_order_number( bool $paid_on_woopay, bool $order_number_is_this_order, bool $settles ) {
		$this->mock_gateway
			->method( 'get_return_url' )
			->willReturn( self::RETURN_URL );
		$order    = $this->create_order_with_attached_intent();
		$metadata = [
			'order_number' => $order_number_is_this_order ? $order->get_id() : $order->get_id() + 1,
			'order_id'     => $order->get_id() + 1,
		];
		if ( $paid_on_woopay ) {
			$metadata['paid_on_woopay'] = 'true';
		}
		$this->mock_attached_intent_statuses( $order, [ Intent_Status::SUCCEEDED ], $metadata );

		$result = $this->service->check_payment_intent_attached_to_order_succeeded( $order );

		$this->assertSame( $settles ? 'success' : null, $result['result'] ?? null );
	}

	/**
	 * Metadata of intents WooPay may have created for an order.
	 *
	 * @return array
	 */
	public function provider_woopay_intent_metadata(): array {
		return [
			'paid on WooPay for this order number'      => [ true, true, true ],
			'paid on WooPay for another order number'   => [ true, false, false ],
			'this order number, but not paid on WooPay' => [ false, true, false ],
		];
	}

	/**
	 * Only an attempt still waiting for the shopper is cancelled, on an order the shopper can pay, and never the
	 * payment the request itself brings. A failed order counts, as after a declined 3DS card followed by a new
	 * one, and so does a request posting a WooPay intent that is another payment, or not a string.
	 *
	 * @dataProvider provider_attempts_a_new_payment_cancels_or_leaves
	 *
	 * @param string $intent_status  The attached intent's status.
	 * @param bool   $for_this_order Whether the intent's metadata names the order.
	 * @param string $order_status   The order's status.
	 * @param mixed  $posted_intent  The WooPay intent the request posts.
	 * @param bool   $cancels        Whether the attempt is cancelled.
	 */
	public function test_check_payment_intent_attached_to_order_succeeded_cancels_only_a_replaceable_attempt( string $intent_status, bool $for_this_order, string $order_status, $posted_intent, bool $cancels ) {
		$order = $this->create_order_with_attached_intent();
		$order->set_status( $order_status );
		$_POST['platform-checkout-intent'] = $posted_intent;
		$this->mock_attached_intent_statuses( $order, [ $intent_status ], [ 'order_id' => $for_this_order ? $order->get_id() : $order->get_id() + 1 ] );
		$this->mock_order_service
			->expects( $cancels ? $this->once() : $this->never() )
			->method( 'cancel_attempt_replaced_by_new_payment' )
			->willReturn( true );

		$result = $this->service->check_payment_intent_attached_to_order_succeeded( $order );

		$this->assertNull( $result );
	}

	/**
	 * Attached attempts, and whether a new payment cancels them.
	 *
	 * @return array
	 */
	public function provider_attempts_a_new_payment_cancels_or_leaves(): array {
		return [
			'a declined attempt'                   => [ Intent_Status::REQUIRES_PAYMENT_METHOD, true, Order_Status::FAILED, '', false ],
			'an attempt for another order'         => [ Intent_Status::REQUIRES_ACTION, false, Order_Status::PENDING, '', false ],
			'an order waiting on a voucher'        => [ Intent_Status::REQUIRES_ACTION, true, Order_Status::ON_HOLD, '', false ],
			'an order that is already paid'        => [ Intent_Status::REQUIRES_ACTION, true, Order_Status::PROCESSING, '', false ],
			'a cancelled order'                    => [ Intent_Status::REQUIRES_ACTION, true, Order_Status::CANCELLED, '', false ],
			'the WooPay payment the request posts' => [ Intent_Status::REQUIRES_ACTION, true, Order_Status::PENDING, self::ATTACHED_INTENT_ID, false ],
			'a failed order'                       => [ Intent_Status::REQUIRES_ACTION, true, Order_Status::FAILED, '', true ],
			'a request posting another intent'     => [ Intent_Status::REQUIRES_ACTION, true, Order_Status::PENDING, self::WOOPAY_INTENT_ID, true ],
			'a request posting an array as intent' => [ Intent_Status::REQUIRES_ACTION, true, Order_Status::PENDING, [ self::ATTACHED_INTENT_ID ], true ],
		];
	}

	/**
	 * A store can make more statuses payable, as an order status extension does for a custom "awaiting payment"
	 * status. A shopper can retry an order there, so an attempt left waiting is cancelled there too. What the
	 * store declares only adds to pending and failed, and never makes an order on hold one the retry cancels in:
	 * that is where an order waits on a voucher.
	 *
	 * @dataProvider provider_statuses_a_store_declares_payable
	 *
	 * @param mixed  $declared     What the store's filter callback returns.
	 * @param string $order_status The order's status.
	 * @param bool   $cancels      Whether the attempt is cancelled.
	 */
	public function test_check_payment_intent_attached_to_order_succeeded_follows_the_statuses_the_store_declares_payable( $declared, string $order_status, bool $cancels ) {
		add_filter(
			'wc_order_statuses',
			function ( $statuses ) {
				$statuses['wc-awaiting-pay'] = 'Awaiting payment (custom)';
				return $statuses;
			}
		);
		$order = $this->create_order_with_attached_intent();
		$order->set_status( $order_status );
		// Declared last, since it stands for what the filter returns in the end.
		$order_the_store_was_asked_about = null;
		add_filter(
			'woocommerce_valid_order_statuses_for_payment',
			function ( $statuses, $order_asked_about = null ) use ( $declared, &$order_the_store_was_asked_about ) {
				$order_the_store_was_asked_about = $order_asked_about;
				return $declared;
			},
			PHP_INT_MAX,
			2
		);
		$this->mock_attached_intent_statuses( $order, [ Intent_Status::REQUIRES_ACTION ] );
		$this->mock_order_service
			->expects( $cancels ? $this->once() : $this->never() )
			->method( 'cancel_attempt_replaced_by_new_payment' )
			->willReturn( true );

		$this->service->check_payment_intent_attached_to_order_succeeded( $order );

		// Stores decide per order, as a payment gateway does for its own orders.
		$this->assertSame( $order, $order_the_store_was_asked_about );
	}

	/**
	 * What a store's payable-statuses filter returns, the order's status, and whether the retry cancels.
	 *
	 * @return array
	 */
	public function provider_statuses_a_store_declares_payable(): array {
		return [
			'a custom status added to the list'         => [ [ 'pending', 'failed', 'awaiting-pay' ], 'awaiting-pay', true ],
			'a custom status the store did not declare' => [ [ 'pending', 'failed' ], 'awaiting-pay', false ],
			'a list that drops failed'                  => [ [ 'pending', 'awaiting-pay' ], 'failed', true ],
			'a list that drops pending'                 => [ [ 'awaiting-pay' ], 'pending', true ],
			'on hold added to the list'                 => [ [ 'pending', 'failed', 'on-hold' ], 'on-hold', false ],
			'a paid status added to the list'           => [ [ 'pending', 'failed', 'processing' ], 'processing', true ],
			'not a list'                                => [ null, 'pending', true ],
			'a single status as a string'               => [ 'awaiting-pay', 'awaiting-pay', true ],
			'a list holding something else'             => [ [ [ 'awaiting-pay' ], 7, 'awaiting-pay' ], 'awaiting-pay', true ],
		];
	}

	/**
	 * A re-submitted order with an open authorization stores the charge ID that webhooks look orders up by.
	 */
	public function test_check_payment_intent_attached_to_order_succeeded_stores_charge_id_of_open_authorization() {
		$this->use_real_order_service();
		$order = $this->create_order_with_attached_intent();
		$this->mock_attached_intent_statuses( $order, [ Intent_Status::REQUIRES_CAPTURE ] );

		$this->service->check_payment_intent_attached_to_order_succeeded( $order );

		$order = wc_get_order( $order->get_id() );
		$this->assertSame( 'ch_attached_charge_id', $order->get_meta( '_charge_id' ) );
		$this->assertSame( 'on-hold', $order->get_status() );
	}

	/**
	 * Other authorized intents keep the order's stored status, which picks how the order is updated,
	 * such as recording the fees of a payment captured after it was authorized.
	 *
	 * @dataProvider provider_check_payment_intent_attached_to_order_succeeded_does_not_attach_other_statuses
	 *
	 * @param string $intent_status The intent status.
	 */
	public function test_check_payment_intent_attached_to_order_succeeded_does_not_attach_other_statuses( string $intent_status ) {
		$order = $this->create_order_with_attached_intent();
		$this->mock_attached_intent_statuses( $order, [ $intent_status ] );
		$this->mock_order_service
			->expects( $this->never() )
			->method( 'attach_intent_info_to_order' );
		$this->mock_order_service
			->expects( $this->once() )
			->method( 'update_order_status_from_intent' );

		$this->service->check_payment_intent_attached_to_order_succeeded( $order );
	}

	public function provider_check_payment_intent_attached_to_order_succeeded_does_not_attach_other_statuses(): array {
		return [
			'Succeeded'  => [ Intent_Status::SUCCEEDED ],
			'Processing' => [ Intent_Status::PROCESSING ],
		];
	}

	/**
	 * Has the service under test work with a real order service, so the order it updates is the store's.
	 */
	private function use_real_order_service(): void {
		$this->service->init( $this->mock_gateway, new WC_Payments_Order_Service( $this->createMock( WC_Payments_API_Client::class ) ) );
	}

	/**
	 * Expects the attached intent to be cancelled once, and the cancel to succeed or fail.
	 *
	 * @param Throwable|null $failure What the cancel request throws, or null when Stripe cancels the intent.
	 */
	private function expect_cancel_of_attached_intent( ?Throwable $failure = null ): void {
		$format_response = $this->mock_wcpay_request( Cancel_Intention::class, 1, self::ATTACHED_INTENT_ID )
			->expects( $this->once() )
			->method( 'format_response' );
		if ( null !== $failure ) {
			$format_response->willThrowException( $failure );
			return;
		}

		$format_response->willReturn( WC_Helper_Intention::create_intention( [ 'status' => Intent_Status::CANCELED ] ) );
	}

	/**
	 * An order service whose cancel of the earlier attempt fails as the real one does, with the order no
	 * longer naming the attempt, and that is real otherwise, so the notes it checks and the order it updates
	 * are the store's.
	 *
	 * @return WC_Payments_Order_Service
	 */
	private function order_service_whose_cancel_fails(): WC_Payments_Order_Service {
		$order_service = $this->getMockBuilder( WC_Payments_Order_Service::class )
			->setConstructorArgs( [ $this->createMock( WC_Payments_API_Client::class ) ] )
			->onlyMethods( [ 'cancel_attempt_replaced_by_new_payment' ] )
			->getMock();
		$order_service
			->method( 'cancel_attempt_replaced_by_new_payment' )
			->willReturnCallback(
				function ( WC_Order $order ) {
					$order->update_meta_data( '_intent_id', '' );
					$order->save_meta_data();

					return false;
				}
			);

		return $order_service;
	}

	/**
	 * Mocks the attached intent being fetched once per given status, in order. A Throwable in place of a
	 * status makes that fetch fail.
	 *
	 * @param WC_Order   $order    The order the intent pays.
	 * @param array      $statuses The status each fetch returns, or the Throwable it throws.
	 * @param array|null $metadata The intent's metadata, when it is not the order's ID.
	 */
	private function mock_attached_intent_statuses( WC_Order $order, array $statuses, ?array $metadata = null ): void {
		// Each mocked request stands in for one `Get_Intention::create()`, in the order they are set up.
		foreach ( $statuses as $status ) {
			$format_response = $this->mock_wcpay_request( Get_Intention::class, 1, self::ATTACHED_INTENT_ID )
				->expects( $this->once() )
				->method( 'format_response' );

			if ( $status instanceof Throwable ) {
				$format_response->willThrowException( $status );
				continue;
			}

			$format_response->willReturn(
				WC_Helper_Intention::create_intention(
					[
						'id'       => self::ATTACHED_INTENT_ID,
						'status'   => $status,
						'amount'   => WC_Payments_Utils::prepare_amount( $order->get_total(), $order->get_currency() ),
						'metadata' => $metadata ?? [ 'order_id' => $order->get_id() ],
						'charge'   => [ 'id' => 'ch_attached_charge_id' ],
					]
				)
			);
		}
	}

	/**
	 * The contents of an order's notes, joined.
	 *
	 * @param WC_Order $order The order.
	 *
	 * @return string
	 */
	private function get_order_notes_text( WC_Order $order ): string {
		return implode( "\n", wp_list_pluck( wc_get_order_notes( [ 'order_id' => $order->get_id() ] ), 'content' ) );
	}

	/**
	 * Creates an order with an attached intent.
	 *
	 * @return WC_Order
	 */
	private function create_order_with_attached_intent(): WC_Order {
		$order = WC_Helper_Order::create_order();
		$order->update_meta_data( '_intent_id', self::ATTACHED_INTENT_ID );
		$order->save();

		return $order;
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

		// Assert: the merchant is told why, under the current brand name. Searched across every
		// note rather than a fixed index, since WooCommerce has changed how many notes it adds,
		// and in what order, between versions.
		$notes = wc_get_order_notes( [ 'order_id' => $order->get_id() ] );
		$this->assertStringContainsString(
			'WooPayments: detected and prevented a second payment for this order',
			implode( "\n", wp_list_pluck( $notes, 'content' ) )
		);
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

	/**
	 * Not every legitimate re-run of payment against a paid entity is expressible as an order
	 * status — the subscription payment-method change above is one such flow. This lets an
	 * extension declare the ones we have not anticipated.
	 */
	public function test_check_order_already_paid_defers_to_an_extension_opting_out() {
		// Assert: an opted-out payment never short-circuits.
		$this->mock_gateway->expects( $this->never() )->method( 'get_return_url' );

		// Arrange a paid order, which would otherwise be blocked.
		$order = WC_Helper_Order::create_order();
		$order->set_status( Order_Status::PROCESSING );
		$order->save();

		// Arrange an extension that lets this payment through, recording what it was handed.
		$received = [];
		$opt_out  = function ( $should_prevent, $filtered_order, $status ) use ( &$received ) {
			$received = [ $should_prevent, $filtered_order, $status ];
			return false;
		};
		add_filter( 'wcpay_should_prevent_payment_for_paid_order', $opt_out, 10, 3 );

		// Act: attempt to pay for the order a second time.
		$result = $this->service->check_order_already_paid( $order );

		remove_filter( 'wcpay_should_prevent_payment_for_paid_order', $opt_out, 10 );

		// Assert: processing continues.
		$this->assertNull( $result );

		// Assert: the extension is handed what it needs to decide, and the guard is on by default.
		$this->assertSame( [ true, $order, 'processing' ], $received );
	}

	public function test_check_order_already_paid_clears_the_session_processing_order() {
		$this->mock_gateway->method( 'get_return_url' )->willReturn( 'https://example.com' );

		// Arrange a paid order that is also the session's processing order.
		$order = WC_Helper_Order::create_order();
		$order->set_status( Order_Status::PROCESSING );
		$order->save();
		$this->service->maybe_update_session_processing_order( $order->get_id() );

		// Act: attempt to pay for the order a second time.
		$this->service->check_order_already_paid( $order );

		// Assert: the stale session entry is cleared so it cannot mislead a later submission.
		$this->assertNull(
			WC()->session->get( Duplicate_Payment_Prevention_Service::SESSION_KEY_PROCESSING_ORDER )
		);
	}
}
