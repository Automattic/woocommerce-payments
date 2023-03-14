<?php
/**
 * Class WC_Payments_Order_Service_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\Constants\Fraud_Outcome_Status;
use WCPay\Constants\Order_Status;
use WCPay\Constants\Payment_Intent_Status;

/**
 * WC_Payments_Order_Service unit tests.
 */
class WC_Payments_Order_Service_Test extends WCPAY_UnitTestCase {

	/**
	 * System under test.
	 *
	 * @var WC_Payments_Order_Service
	 */
	private $order_service;

	/**
	 * WC_Order.
	 *
	 * @var WC_Order
	 */
	private $order;

	/**
	 * Mock intent ID.
	 *
	 * @var string
	 */
	private $intent_id = 'pi_123';

	/**
	 * Mock charge id.
	 *
	 * @var string
	 */
	private $charge_id = 'py_123';

	/**
	 * Pre-test setup
	 */
	public function set_up() {
		parent::set_up();

		$this->order_service = new WC_Payments_Order_Service( $this->createMock( WC_Payments_API_Client::class ) );
		$this->order         = WC_Helper_Order::create_order();
	}

	/**
	 * Private method of `order_prepared_for_processing` stops processing if order passed isn't an order.
	 */
	public function test_order_status_not_updated_if_order_is_invalid() {
		// Arrange: Get expected notes.
		$expected_notes = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );

		// Act: Attempt to mark the payment/order complete. Get updated notes.
		$this->order_service->mark_payment_completed( 'fake_order', $this->intent_id, Payment_Intent_Status::SUCCEEDED, $this->charge_id );

		// Assert: Check to make sure the intent/transaction id and intent_status meta were not set.
		$this->assertEquals( '', $this->order->get_transaction_id() );
		$this->assertEquals( '', $this->order->get_meta( '_intention_status' ) );

		// Assert: Check that the notes were not updated.
		$updated_notes = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );
		$this->assertEquals( $expected_notes, $updated_notes );
	}

	/**
	 * Private method of `order_prepared_for_processing` stops processing if order already paid.
	 */
	public function test_order_status_not_updated_if_order_paid() {
		// Arrange: Set the order status to processing, default is pending. Get expected notes.
		$this->order->set_status( Order_Status::PROCESSING );
		$expected_notes = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );

		// Act: Attempt to mark the payment/order complete.
		$this->order_service->mark_payment_completed( $this->order, $this->intent_id, Payment_Intent_Status::SUCCEEDED, $this->charge_id );

		// Assert: Check to make sure the intent/transaction id and intent_status meta were not set.
		$this->assertEquals( '', $this->order->get_transaction_id() );
		$this->assertEquals( '', $this->order->get_meta( '_intention_status' ) );

		// Assert: Check that the notes were not updated.
		$updated_notes = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );
		$this->assertEquals( $expected_notes, $updated_notes );
	}

	/**
	 * Private method of `order_prepared_for_processing` stops processing if order is locked.
	 */
	public function test_order_status_not_updated_if_order_locked() {
		// Arrange: Lock the order. Get expected notes.
		$transient_name = 'wcpay_processing_intent_' . $this->order->get_id();
		set_transient( $transient_name, $this->intent_id, 5 * MINUTE_IN_SECONDS );
		$expected_notes = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );

		// Act: Attempt to mark the payment/order complete. Get updated notes.
		$this->order_service->mark_payment_completed( $this->order, $this->intent_id, Payment_Intent_Status::SUCCEEDED, $this->charge_id );
		$updated_notes = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );

		// Assert: Check to make sure the intent/transaction id and intent_status meta were not set.
		$this->assertEquals( '', $this->order->get_transaction_id() );
		$this->assertEquals( '', $this->order->get_meta( '_intention_status' ) );

		// Assert: Check that the notes were not updated.
		$updated_notes = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );
		$this->assertEquals( $expected_notes, $updated_notes );
	}

	/**
	 * Tests if the order was completed successfully.
	 */
	public function test_mark_payment_completed() {
		// Arrange: Set the intent status.
		$intent_status = Payment_Intent_Status::SUCCEEDED;

		// Act: Attempt to mark the payment/order complete.
		$this->order_service->mark_payment_completed( $this->order, $this->intent_id, $intent_status, $this->charge_id );

		// Assert: Check to make sure the intent/transaction id was set, and that intent_status meta was set.
		$this->assertEquals( $this->intent_id, $this->order->get_transaction_id() );
		$this->assertEquals( $intent_status, $this->order->get_meta( '_intention_status' ) );

		// Assert: Check that the order status was updated to a paid status.
		$this->assertTrue( $this->order->has_status( wc_get_is_paid_statuses() ) );

		// Assert: Check that the notes were updated.
		$notes = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );
		$this->assertStringContainsString( 'Pending payment to Processing', $notes[1]->content );
		$this->assertStringContainsString( 'successfully charged</strong> using WooCommerce Payments', $notes[0]->content );
		$this->assertStringContainsString( '/payments/transactions/details&id=pi_123" target="_blank" rel="noopener noreferrer">pi_123', $notes[0]->content );

		// Assert: Check that the order was unlocked.
		$this->assertFalse( get_transient( 'wcpay_processing_intent_' . $this->order->get_id() ) );

		// Assert: Applying the same data multiple times does not cause duplicate actions.
		$this->order_service->mark_payment_completed( $this->order, $this->intent_id, $intent_status, $this->charge_id );
		$notes_2 = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );
		$this->assertCount( 2, $notes_2 );
	}

	/**
	 * Tests if the order was marked failed successfully.
	 */
	public function test_mark_payment_failed() {
		// Arrange: Set the intent status and additional failed message.
		$intent_status = 'failed';
		$message       = 'This is the test failed message.';

		// Act: Attempt to mark the payment/order failed.
		$this->order_service->mark_payment_failed( $this->order, $this->intent_id, $intent_status, $this->charge_id, $message );

		// Assert: Check to make sure the intent_status meta was set.
		$this->assertEquals( $intent_status, $this->order->get_meta( '_intention_status' ) );

		// Assert: Check that the order status was updated to failed status.
		$this->assertTrue( $this->order->has_status( [ $intent_status ] ) );

		// Assert: Check that the notes were updated.
		$notes = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );
		$this->assertStringContainsString( 'Pending payment to Failed', $notes[1]->content );
		$this->assertStringContainsString( 'failed</strong> using WooCommerce Payments', $notes[0]->content );
		$this->assertStringContainsString( '/payments/transactions/details&id=pi_123" target="_blank" rel="noopener noreferrer">pi_123', $notes[0]->content );
		$this->assertStringContainsString( 'This is the test failed message.', $notes[0]->content );

		// Assert: Check that the order was unlocked.
		$this->assertFalse( get_transient( 'wcpay_processing_intent_' . $this->order->get_id() ) );

		// Assert: Applying the same data multiple times does not cause duplicate actions.
		$this->order_service->mark_payment_failed( $this->order, $this->intent_id, $intent_status, $this->charge_id, $message );
		$notes_2 = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );
		$this->assertCount( 2, $notes_2 );
	}

	/**
	 * Method `mark_payment_failed` should exit if the order status is already failed.
	 */
	public function test_mark_payment_failed_exits_on_existing_order_status_failed() {
		// Arrange: Set the intent status, order status, and get the expected notes.
		$intent_status = 'failed';
		$this->order->set_status( Order_Status::FAILED );
		$expected_notes = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );

		// Act: Attempt to mark the payment/order failed.
		$this->order_service->mark_payment_failed( $this->order, $this->intent_id, $intent_status, $this->charge_id );

		// Assert: Check that the notes were not updated.
		$updated_notes = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );
		$this->assertEquals( $expected_notes, $updated_notes );

		// Assert: Check that the order is not locked.
		$this->assertFalse( get_transient( 'wcpay_processing_intent_' . $this->order->get_id() ) );
	}

	/**
	 * Method `mark_payment_failed` should exit if the intent status is already failed.
	 */
	public function test_mark_payment_failed_exits_on_existing_intent_status_failed() {
		// Arrange: Set the intent status, apply intent status, and get the expected notes.
		$intent_status = 'failed';
		$this->order->update_meta_data( '_intention_status', $intent_status );
		$expected_notes = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );

		// Act: Attempt to mark the payment/order failed.
		$this->order_service->mark_payment_failed( $this->order, $this->intent_id, $intent_status, $this->charge_id );

		// Assert: Check that the notes were not updated.
		$updated_notes = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );
		$this->assertEquals( $expected_notes, $updated_notes );

		// Assert: Check that the order is not locked.
		$this->assertFalse( get_transient( 'wcpay_processing_intent_' . $this->order->get_id() ) );
	}

	/**
	 * Tests if the order was marked on-hold successfully.
	 */
	public function test_mark_payment_authorized() {
		// Arrange: Set the intent and order statuses.
		$intent_status = Payment_Intent_Status::REQUIRES_CAPTURE;
		$order_status  = Order_Status::ON_HOLD;

		// Act: Attempt to mark the payment/order on-hold.
		$this->order_service->mark_payment_authorized( $this->order, $this->intent_id, $intent_status, $this->charge_id );

		// Assert: Check to make sure the intent_status meta was set.
		$this->assertEquals( $intent_status, $this->order->get_meta( '_intention_status' ) );

		// Assert: Check that the order status was updated to on-hold status.
		$this->assertTrue( $this->order->has_status( [ $order_status ] ) );

		// Assert: Check that the notes were updated.
		$notes = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );
		$this->assertStringContainsString( 'Pending payment to On hold', $notes[1]->content );
		$this->assertStringContainsString( 'authorized</strong> using WooCommerce Payments', $notes[0]->content );
		$this->assertStringContainsString( '/payments/transactions/details&id=pi_123" target="_blank" rel="noopener noreferrer">pi_123', $notes[0]->content );

		// Assert: Check that the order was unlocked.
		$this->assertFalse( get_transient( 'wcpay_processing_intent_' . $this->order->get_id() ) );
	}

	/**
	 * Method `mark_payment_authorized` should exit if the order status is already on-hold.
	 */
	public function test_mark_payment_authorized_exits_on_existing_order_status_on_hold() {
		// Arrange: Set the intent status, order status, and get the expected notes.
		$intent_status = Payment_Intent_Status::REQUIRES_CAPTURE;
		$this->order->set_status( Order_Status::ON_HOLD );
		$expected_notes = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );

		// Act: Attempt to mark the payment/order on-hold.
		$this->order_service->mark_payment_authorized( $this->order, $this->intent_id, $intent_status, $this->charge_id );

		// Assert: Check that the notes were not updated.
		$updated_notes = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );
		$this->assertEquals( $expected_notes, $updated_notes );

		// Assert: Check that the order is not locked.
		$this->assertFalse( get_transient( 'wcpay_processing_intent_' . $this->order->get_id() ) );
	}

	/**
	 * Tests marking/leaving the payment in pending status with a started note.
	 */
	public function test_mark_payment_started() {
		// Arrange: Set the intent and order statuses.
		$intent_status = Payment_Intent_Status::REQUIRES_ACTION;
		$order_status  = Order_Status::PENDING;

		// Act: Attempt to mark the payment/order pending.
		$this->order_service->mark_payment_started( $this->order, $this->intent_id, $intent_status, $this->charge_id );

		// Assert: Check to make sure the intent_status meta was set.
		$this->assertEquals( $intent_status, $this->order->get_meta( '_intention_status' ) );

		// Assert: Check that the order status was left on pending status.
		$this->assertTrue( $this->order->has_status( [ $order_status ] ) );

		// Assert: Check that the notes were updated.
		$notes = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );
		$this->assertStringContainsString( 'started</strong> using WooCommerce Payments', $notes[0]->content );
		$this->assertStringContainsString( 'Payments (<code>pi_123</code>)', $notes[0]->content );

		// Assert: Check that the order was unlocked.
		$this->assertFalse( get_transient( 'wcpay_processing_intent_' . $this->order->get_id() ) );
	}

	/**
	 * Method `mark_payment_started` should exit if the order status is not already pending.
	 */
	public function test_mark_payment_started_exits_on_existing_order_status_not_pending() {
		// Arrange: Set the intent status, order status, and get the expected notes.
		$intent_status = Payment_Intent_Status::REQUIRES_ACTION;
		$this->order->set_status( Order_Status::ON_HOLD );
		$expected_notes = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );

		// Act: Attempt to mark the payment/order pending.
		$this->order_service->mark_payment_started( $this->order, $this->intent_id, $intent_status, $this->charge_id );

		// Assert: Check that the notes were not updated.
		$updated_notes = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );
		$this->assertEquals( $expected_notes, $updated_notes );

		// Assert: Check that the order is not locked.
		$this->assertFalse( get_transient( 'wcpay_processing_intent_' . $this->order->get_id() ) );
	}

	/**
	 * Tests if the payment was captured successfully.
	 */
	public function test_mark_payment_capture_completed() {
		// Arrange: Set the intent status.
		$intent_status = Payment_Intent_Status::SUCCEEDED;

		// Act: Attempt to mark the payment/order complete.
		$this->order_service->mark_payment_capture_completed( $this->order, $this->intent_id, $intent_status, $this->charge_id );

		// Assert: Check to make sure the intent/transaction id was set, and that intent_status meta was set.
		$this->assertEquals( $this->intent_id, $this->order->get_transaction_id() );
		$this->assertEquals( $intent_status, $this->order->get_meta( '_intention_status' ) );

		// Assert: Check that the order status was updated to a paid status.
		$this->assertTrue( $this->order->has_status( wc_get_is_paid_statuses() ) );

		// Assert: Check that the notes were updated.
		$notes = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );
		$this->assertStringContainsString( 'Pending payment to Processing', $notes[1]->content );
		$this->assertStringContainsString( 'successfully captured</strong> using WooCommerce Payments', $notes[0]->content );
		$this->assertStringContainsString( '/payments/transactions/details&id=pi_123" target="_blank" rel="noopener noreferrer">pi_123', $notes[0]->content );

		// Assert: Check that the order was unlocked.
		$this->assertFalse( get_transient( 'wcpay_processing_intent_' . $this->order->get_id() ) );
	}

	/**
	 * Tests if the payment capture was noted failed.
	 */
	public function test_mark_payment_capture_failed() {
		// Arrange: Set the intent status.
		$intent_status = 'failed';

		// Act: Attempt to mark the payment capture failed.
		$this->order_service->mark_payment_capture_failed( $this->order, $this->intent_id, $intent_status, $this->charge_id );

		// Assert: Check to make sure the intent_status meta was set.
		$this->assertEquals( $intent_status, $this->order->get_meta( '_intention_status' ) );

		// Assert: Check that the order status was not updated.
		$this->assertTrue( $this->order->has_status( [ Order_Status::PENDING ] ) );

		// Assert: Check that the notes were updated.
		$notes = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );
		$this->assertStringContainsString( 'failed</strong> to complete using WooCommerce Payments', $notes[0]->content );
		$this->assertStringContainsString( '/payments/transactions/details&id=pi_123" target="_blank" rel="noopener noreferrer">pi_123', $notes[0]->content );

		// Assert: Check that the order was unlocked.
		$this->assertFalse( get_transient( 'wcpay_processing_intent_' . $this->order->get_id() ) );
	}

	/**
	 * Tests if the payment capture was noted failed with a null intent status.
	 */
	public function test_mark_payment_capture_failed_null_intent_status() {
		// Arrange: Set the intent status.
		$intent_status = null;

		// Act: Attempt to mark the payment capture failed.
		$this->order_service->mark_payment_capture_failed( $this->order, $this->intent_id, $intent_status, $this->charge_id );

		// Assert: Check to make sure the intent_status meta was not set.
		$this->assertEquals( '', $this->order->get_meta( '_intention_status' ) );

		// Assert: Check that the order status was not updated.
		$this->assertTrue( $this->order->has_status( [ Order_Status::PENDING ] ) );

		// Assert: Check that the notes were updated.
		$notes = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );
		$this->assertStringContainsString( 'failed</strong> to complete using WooCommerce Payments', $notes[0]->content );
		$this->assertStringContainsString( '/payments/transactions/details&id=pi_123" target="_blank" rel="noopener noreferrer">pi_123', $notes[0]->content );

		// Assert: Check that the order was unlocked.
		$this->assertFalse( get_transient( 'wcpay_processing_intent_' . $this->order->get_id() ) );
	}

	/**
	 * Tests if the payment capture was marked expired succefully.
	 */
	public function test_mark_payment_capture_expired() {
		// Arrange: Set the intent and order statuses.
		$intent_status     = Payment_Intent_Status::CANCELED;  // Stripe uses single 'l'.
		$order_status      = Order_Status::CANCELLED; // WooCommerce uses double 'l'.
		$wc_order_statuses = wc_get_order_statuses(); // WooCommerce uses single 'l' for US English.

		// Act: Attempt to mark the payment/order expired/cancelled.
		$this->order_service->mark_payment_capture_expired( $this->order, $this->intent_id, $intent_status, $this->charge_id );

		// Assert: Check to make sure the intent_status meta was set.
		$this->assertEquals( $intent_status, $this->order->get_meta( '_intention_status' ) );

		// Assert: Check that the order status was updated to cancelled status.
		$this->assertTrue( $this->order->has_status( [ $order_status ] ) );

		// Assert: Check that the notes were updated.
		$notes = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );
		$this->assertStringContainsString( 'Pending payment to ' . $wc_order_statuses['wc-cancelled'], $notes[1]->content );
		$this->assertStringContainsString( 'Payment authorization has <strong>expired</strong>', $notes[0]->content );
		$this->assertStringContainsString( '/payments/transactions/details&id=pi_123" target="_blank" rel="noopener noreferrer">pi_123', $notes[0]->content );

		// Assert: Check that the order was unlocked.
		$this->assertFalse( get_transient( 'wcpay_processing_intent_' . $this->order->get_id() ) );

		// Assert: Applying the same data multiple times does not cause duplicate actions.
		$this->order_service->mark_payment_capture_expired( $this->order, $this->intent_id, $intent_status, $this->charge_id );
		$notes_2 = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );
		$this->assertCount( 2, $notes_2 );
	}

	/**
	 * Tests if the payment capture was marked cancelled succefully.
	 */
	public function test_mark_payment_capture_cancelled() {
		// Arrange: Set the intent and order statuses.
		$intent_status     = Payment_Intent_Status::CANCELED;  // Stripe uses single 'l'.
		$order_status      = Order_Status::CANCELLED; // WooCommerce uses double 'l' in the background.
		$wc_order_statuses = wc_get_order_statuses(); // WooCommerce uses single 'l' for US English.

		// Act: Attempt to mark the payment/order expired/cancelled.
		$this->order_service->mark_payment_capture_cancelled( $this->order, $this->intent_id, $intent_status );

		// Assert: Check to make sure the intent_status meta was set.
		$this->assertEquals( $intent_status, $this->order->get_meta( '_intention_status' ) );

		// Assert: Check that the order status was updated to cancelled status.
		$this->assertTrue( $this->order->has_status( [ $order_status ] ) );

		// Assert: Check that the notes were updated.
		$notes = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );
		$this->assertStringContainsString( 'Pending payment to ' . $wc_order_statuses['wc-cancelled'], $notes[1]->content );
		$this->assertStringContainsString( 'Payment authorization was successfully <strong>cancelled</strong>', $notes[0]->content );

		// Assert: Check that the order was unlocked.
		$this->assertFalse( get_transient( 'wcpay_processing_intent_' . $this->order->get_id() ) );
	}

	/**
	 * Tests if the payment was updated to show dispute created.
	 */
	public function test_mark_payment_dispute_created() {
		// Arrange: Set the dispute_id and reason, and the order status.
		$dispute_id   = 'dp_123';
		$reason       = 'product_not_received';
		$order_status = Order_Status::ON_HOLD;

		// Act: Attempt to mark payment dispute created.
		$this->order_service->mark_payment_dispute_created( $this->order, $dispute_id, $reason );

		// Assert: Check that the order status was updated to on-hold status.
		$this->assertTrue( $this->order->has_status( [ $order_status ] ) );

		// Assert: Check that the notes were updated.
		$notes = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );
		$this->assertStringContainsString( 'Pending payment to On hold', $notes[1]->content );
		$this->assertStringContainsString( 'Payment has been disputed as product_not_received', $notes[0]->content );
		$this->assertStringContainsString( '/payments/disputes/details&id=dp_123" target="_blank" rel="noopener noreferrer">dispute overview', $notes[0]->content );

		// Assert: Applying the same data multiple times does not cause duplicate actions.
		$this->order_service->mark_payment_dispute_created( $this->order, $dispute_id, $reason );
		$notes_2 = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );
		$this->assertCount( 2, $notes_2 );
	}

	/**
	 * Tests if the payment was updated to show dispute closed with a win.
	 */
	public function test_mark_payment_dispute_closed_with_status_won() {
		// Arrange: Set the dispute_id and status, and the order status.
		$dispute_id   = 'dp_123';
		$status       = 'won';
		$order_status = Order_Status::COMPLETED;

		// Act: Attempt to mark payment dispute created.
		$this->order_service->mark_payment_dispute_closed( $this->order, $dispute_id, $status );

		// Assert: Check that the order status was updated to completed status.
		$this->assertTrue( $this->order->has_status( [ $order_status ] ) );

		// Assert: Check that the notes were updated.
		$notes = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );
		$this->assertStringContainsString( 'Pending payment to Completed', $notes[1]->content );
		$this->assertStringContainsString( 'Payment dispute has been closed with status won', $notes[0]->content );
		$this->assertStringContainsString( '/payments/disputes/details&id=dp_123" target="_blank" rel="noopener noreferrer">dispute overview', $notes[0]->content );

		// Assert: Applying the same data multiple times does not cause duplicate actions.
		$this->order_service->mark_payment_dispute_closed( $this->order, $dispute_id, $status );
		$notes_2 = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );
		$this->assertCount( 2, $notes_2 );
	}

	/**
	 * Tests if the payment was updated to show dispute closed with a loss and a refund.
	 */
	public function test_mark_payment_dispute_closed_with_status_lost() {
		// Arrange: Set the dispute_id, dispute status, the order status, and update the order status.
		$dispute_id   = 'dp_123';
		$status       = 'lost';
		$order_status = Order_Status::ON_HOLD;
		$this->order->update_status( $order_status ); // When a dispute is created, the order status is changed to On Hold.

		// Act: Attempt to mark payment dispute created.
		$this->order_service->mark_payment_dispute_closed( $this->order, $dispute_id, $status );

		// Assert: Check that the order status was left in on-hold status.
		$this->assertTrue( $this->order->has_status( [ $order_status ] ) );

		// Assert: Check that the notes were updated.
		$notes = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );
		$this->assertStringContainsString( 'On hold to Refunded', $notes[1]->content );
		$this->assertStringContainsString( 'Payment dispute has been closed with status lost', $notes[0]->content );
		$this->assertStringContainsString( '/payments/disputes/details&id=dp_123" target="_blank" rel="noopener noreferrer">dispute overview', $notes[0]->content );

		// Assert: Check for created refund, and the amount is correct.
		$refunds = $this->order->get_refunds();
		$this->assertCount( 1, $refunds );
		$this->assertEquals( '-' . $this->order->get_total(), $refunds[0]->get_total() );

		// Assert: Applying the same data multiple times does not cause duplicate actions.
		$this->order_service->mark_payment_dispute_closed( $this->order, $dispute_id, $status );
		$notes_2 = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );
		$this->assertCount( 3, $notes_2 );
	}

	/**
	 * Tests if the order was completed successfully.
	 */
	public function test_mark_terminal_payment_completed() {
		// Arrange: Set the intent status.
		$intent_status = Payment_Intent_Status::SUCCEEDED;
		$order_status  = Order_Status::COMPLETED;

		// Act: Attempt to mark the payment/order complete.
		$this->order_service->mark_terminal_payment_completed( $this->order, $this->intent_id, $intent_status );

		// Assert: Check to make sure the intent_status meta was set.
		$this->assertEquals( $intent_status, $this->order->get_meta( '_intention_status' ) );

		// Assert: Check that the order status was updated to completed status.
		$this->assertTrue( $this->order->has_status( [ $order_status ] ) );

		// Assert: Check that the notes were updated.
		$notes = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );
		$this->assertStringContainsString( 'Pending payment to Completed', $notes[0]->content );

		// Assert: Check that the order was unlocked.
		$this->assertFalse( get_transient( 'wcpay_processing_intent_' . $this->order->get_id() ) );
	}

	/**
	 * Tests if the payment was captured successfully after a fraud check.
	 */
	public function test_mark_order_payment_capture_completed_after_fraud_check() {
		// Arrange: Set the intent status.
		$intent_status = Payment_Intent_Status::SUCCEEDED;

		// Act: Attempt to mark the payment/order complete.
		$this->order_service->mark_order_payment_capture_completed_after_fraud_check( $this->order, $this->intent_id, $intent_status, $this->charge_id );

		// Assert: Check to make sure the intent/transaction id was set, and that intent_status meta was set.
		$this->assertEquals( $this->intent_id, $this->order->get_transaction_id() );
		$this->assertEquals( $intent_status, $this->order->get_meta( '_intention_status' ) );

		// Assert: Check to make sure the _wcpay_fraud_outcome_status meta was set.
		$this->assertEquals( Fraud_Outcome_Status::APPROVE, $this->order->get_meta( '_wcpay_fraud_outcome_status' ) );

		// Assert: Check that the order status was updated to a paid status.
		$this->assertTrue( $this->order->has_status( wc_get_is_paid_statuses() ) );

		// Assert: Check that the notes were updated.
		$notes = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );
		$this->assertStringContainsString( 'Pending payment to Processing', $notes[1]->content );
		$this->assertStringContainsString( 'successfully captured</strong> using WooCommerce Payments', $notes[0]->content );
		$this->assertStringContainsString( '/payments/transactions/details&id=pi_123" target="_blank" rel="noopener noreferrer">pi_123', $notes[0]->content );

		// Assert: Check that the order was unlocked.
		$this->assertFalse( get_transient( 'wcpay_processing_intent_' . $this->order->get_id() ) );
	}

	/**
	 * Tests to see if the order correctly get set on hold for review.
	 */
	public function test_mark_order_held_for_review_for_fraud() {
		// Arrange: Set the intent and order statuses.
		$intent_status = Payment_Intent_Status::REQUIRES_CAPTURE;
		$order_status  = Order_Status::ON_HOLD;

		// Act: Attempt to mark the payment/order on-hold.
		$this->order_service->mark_order_held_for_review_for_fraud( $this->order, $this->intent_id, $intent_status, $this->charge_id );

		// Assert: Check to make sure the intent_status meta was set.
		$this->assertEquals( $intent_status, $this->order->get_meta( '_intention_status' ) );

		// Assert: Check to make sure the _wcpay_fraud_outcome_status meta was set.
		$this->assertEquals( Fraud_Outcome_Status::REVIEW, $this->order->get_meta( '_wcpay_fraud_outcome_status' ) );

		// Assert: Check that the order status was updated to on-hold status.
		$this->assertTrue( $this->order->has_status( [ $order_status ] ) );

		// Assert: Check that the notes were updated.
		$notes = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );
		$this->assertStringContainsString( 'Pending payment to On hold', $notes[1]->content );
		$this->assertStringContainsString( 'held for review</strong> by one or more risk filters', $notes[0]->content );
		$this->assertStringContainsString( '/payments/transactions/details&id=pi_123" target="_blank" rel="noopener noreferrer">View more details', $notes[0]->content );

		// Assert: Check that the order was unlocked.
		$this->assertFalse( get_transient( 'wcpay_processing_intent_' . $this->order->get_id() ) );
	}

	/**
	 * Tests to see if the order is correctly set to blocked for fraud reasons.
	 */
	public function test_mark_order_blocked_for_fraud() {
		// Arrange: Set the intent and order statuses.
		$intent_status     = Payment_Intent_Status::CANCELED;
		$order_status      = Order_Status::CANCELLED; // WooCommerce uses double 'l'.
		$wc_order_statuses = wc_get_order_statuses(); // WooCommerce uses single 'l' for US English.

		// Act: Attempt to mark the payment/order as blocked/cancelled.
		$this->order_service->mark_order_blocked_for_fraud( $this->order, $this->intent_id, $intent_status, $this->charge_id );

		// Assert: Check to make sure the intent_status meta was set.
		$this->assertEquals( $intent_status, $this->order->get_meta( '_intention_status' ) );

		// Assert: Check to make sure the _wcpay_fraud_outcome_status meta was set.
		$this->assertEquals( Fraud_Outcome_Status::BLOCK, $this->order->get_meta( '_wcpay_fraud_outcome_status' ) );

		// Assert: Check that the order status was updated to cancelled status.
		$this->assertTrue( $this->order->has_status( [ $order_status ] ) );

		// Assert: Check that the notes were updated.
		$notes = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );
		$this->assertStringContainsString( 'Pending payment to ' . $wc_order_statuses['wc-cancelled'], $notes[1]->content );
		$this->assertStringContainsString( 'blocked</strong> by one or more risk filters', $notes[0]->content );
		$this->assertStringContainsString( '/payments/transactions/details&id=pi_123" target="_blank" rel="noopener noreferrer">View more details', $notes[0]->content );

		// Assert: Check that the order was unlocked.
		$this->assertFalse( get_transient( 'wcpay_processing_intent_' . $this->order->get_id() ) );
	}

	/**
	 * @dataProvider provider_order_note_exists
	 */
	public function test_order_note_exists( array $notes, string $note_to_check, bool $expected ) {

		foreach ( $notes as $note ) {
			$this->order->add_order_note( $note );
		}

		$this->assertSame( $expected, $this->order_service->order_note_exists( $this->order, $note_to_check ) );
	}

	public function provider_order_note_exists(): array {
		return [
			'Note does not exist'                        => [ [ 'note 1', 'note 2' ], 'check_string', false ],
			'Note does not exist when order has no note' => [ [], 'check_string', false ],
			'Note exists at the beginning'               => [ [ 'check_string', 'note 1', 'note 2' ], 'check_string', true ],
			'Note exists at the end'                     => [ [ 'note 1', 'note 2', 'check_string' ], 'check_string', true ],
		];
	}
}
