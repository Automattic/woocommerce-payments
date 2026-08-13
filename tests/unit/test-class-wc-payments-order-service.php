<?php
/**
 * Class WC_Payments_Order_Service_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\Constants\Fraud_Meta_Box_Type;
use WCPay\Constants\Order_Status;
use WCPay\Constants\Intent_Status;
use WCPay\Constants\Order_Mode;
use WCPay\Constants\Payment_Method;
use WCPay\Fraud_Prevention\Models\Rule;
use WCPay\Constants\Refund_Status;
use WCPay\Constants\Refund_Failure_Reason;

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
	 * Pre-test setup
	 */
	public function set_up() {
		parent::set_up();

		$this->order_service = new WC_Payments_Order_Service( $this->createMock( WC_Payments_API_Client::class ) );
		$this->order         = WC_Helper_Order::create_order();

		$gateways = WC()->payment_gateways->payment_gateways();
		$this->order->set_payment_method( $gateways[ WC_Payment_Gateway_WCPay::GATEWAY_ID ] );
		$this->order->save();
	}

	/**
	 * Private method of `order_prepared_for_processing` stops processing if order passed isn't an order.
	 */
	public function test_order_status_not_updated_if_order_is_invalid() {
		// Arrange: Create intent, get expected notes.
		$intent         = WC_Helper_Intention::create_intention();
		$expected_notes = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );

		// Act: Attempt to mark the payment/order complete. Get updated notes.
		$this->order_service->update_order_status_from_intent( 'fake_order', $intent );

		// Assert: Check to make sure the intent/transaction id and intent_status meta were not set.
		$this->assertEquals( '', $this->order->get_transaction_id() );
		$this->assertEquals( '', $this->order_service->get_intention_status_for_order( $this->order ) );

		// Assert: Check that the notes were not updated.
		$updated_notes = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );
		$this->assertEquals( $expected_notes, $updated_notes );
	}

	/**
	 * Private method of `order_prepared_for_processing` stops processing if order already paid.
	 */
	public function test_order_status_not_updated_if_order_paid() {
		// Arrange: Create intent. Set the order status to processing, default is pending. Get expected notes.
		$intent = WC_Helper_Intention::create_intention();
		$this->order->set_status( Order_Status::PROCESSING );
		$expected_notes = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );

		// Act: Attempt to mark the payment/order complete.
		$this->order_service->update_order_status_from_intent( $this->order, $intent );

		// Assert: Check to make sure the intent/transaction id and intent_status meta were not set.
		$this->assertEquals( '', $this->order->get_transaction_id() );
		$this->assertEquals( '', $this->order_service->get_intention_status_for_order( $this->order ) );

		// Assert: Check that the notes were not updated.
		$updated_notes = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );
		$this->assertEquals( $expected_notes, $updated_notes );
	}

	/**
	 * Private method of `order_prepared_for_processing` stops processing if order is locked.
	 */
	public function test_order_status_not_updated_if_order_locked() {
		// Arrange: Create intent. Lock the order. Get expected notes.
		$intent         = WC_Helper_Intention::create_intention();
		$transient_name = 'wcpay_processing_intent_' . $this->order->get_id();
		set_transient( $transient_name, $intent->get_id(), 5 * MINUTE_IN_SECONDS );
		$expected_notes = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );

		// Act: Attempt to mark the payment/order complete. Get updated notes.
		$this->order_service->update_order_status_from_intent( $this->order, $intent );

		// Assert: Check to make sure the intent/transaction id and intent_status meta were not set.
		$this->assertEquals( '', $this->order->get_transaction_id() );
		$this->assertEquals( '', $this->order_service->get_intention_status_for_order( $this->order ) );

		// Assert: Check that the notes were not updated.
		$updated_notes = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );
		$this->assertEquals( $expected_notes, $updated_notes );
	}

	/**
	 * Tests if the order is marked completed correctly.
	 * Public method update_order_status_from_intent calls private method mark_payment_completed.
	 *
	 * @dataProvider mark_payment_completed_provider
	 */
	public function test_mark_payment_completed( $order_status, $intent_args, $expected_note_old, $expected_note_new, $expected_fraud_outcome, $expected_fraud_meta_box ) {
		// Arrange: Create intention with proper outcome status, update order status if needed.
		$intent = WC_Helper_Intention::create_intention( $intent_args );
		if ( $order_status ) {
			$this->order->set_status( $order_status );
			$this->order->save();
		}

		// Act: Attempt to mark the payment/order complete.
		$this->order_service->update_order_status_from_intent( $this->order, $intent );

		// Assert: Check to make sure the intent/transaction id was set, and that intent_status meta was set.
		$this->assertEquals( $intent->get_id(), $this->order->get_transaction_id() );
		$this->assertEquals( $intent->get_status(), $this->order_service->get_intention_status_for_order( $this->order ) );

		// Assert: Confirm that the fraud outcome status and meta box type were set correctly.
		$this->assertEquals( $expected_fraud_outcome, $this->order_service->get_fraud_outcome_status_for_order( $this->order ) );
		$this->assertEquals( $expected_fraud_meta_box, $this->order_service->get_fraud_meta_box_type_for_order( $this->order ) );

		// Assert: Check that the order status was updated to a paid status.
		$this->assertTrue( $this->order->has_status( wc_get_is_paid_statuses() ) );

		// Assert: Check that the notes were updated.
		$notes         = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );
		$expected_note = version_compare( constant( 'WC_VERSION' ), '10.4', '>=' ) ? $expected_note_new : $expected_note_old;
		$this->assertStringContainsString( $expected_note, $notes[1]->content );
		$this->assertStringContainsString( 'successfully charged</strong> using WooPayments', $notes[0]->content );
		$this->assertStringContainsString( '%2Fpayments%2Ftransactions%2Fdetails&id=pi_mock" target="_blank" rel="noopener noreferrer">pi_mock', $notes[0]->content );

		// Assert: Check that the order was unlocked.
		$this->assertFalse( get_transient( 'wcpay_processing_intent_' . $this->order->get_id() ) );

		// Assert: Applying the same data multiple times does not cause duplicate actions.
		$this->order_service->update_order_status_from_intent( $this->order, $intent );
		$notes_2 = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );
		$this->assertEquals( count( $notes ), count( $notes_2 ) );
	}

	public function mark_payment_completed_provider() {
		return [
			'mark_complete_no_fraud_outcome_no_pmtype'   => [
				'order_status'            => false,
				'intent_args'             => [],
				'expected_note_old'       => 'Pending payment to Processing',
				'expected_note_new'       => 'Payment via Card (pi_mock)',
				'expected_fraud_outcome'  => false,
				'expected_fraud_meta_box' => Fraud_Meta_Box_Type::NOT_CARD,
			],
			'mark_complete_no_fraud_outcome_pmtype_card' => [
				'order_status'            => false,
				'intent_args'             => [
					'payment_method_options' => [ 'card' => [ 'request_three_d_secure' => 'automatic' ] ],
				],
				'expected_note_old'       => 'Pending payment to Processing',
				'expected_note_new'       => 'Payment via Card (pi_mock)',
				'expected_fraud_outcome'  => false,
				'expected_fraud_meta_box' => false,
			],
			'mark_complete_fraud_outcome_allow'          => [
				'order_status'            => false,
				'intent_args'             => [
					'metadata'               => [
						'fraud_outcome' => Rule::FRAUD_OUTCOME_ALLOW,
					],
					'payment_method_options' => [ 'card' => [ 'request_three_d_secure' => 'automatic' ] ],
				],
				'expected_note_old'       => 'Pending payment to Processing',
				'expected_note_new'       => 'Payment via Card (pi_mock)',
				'expected_fraud_outcome'  => Rule::FRAUD_OUTCOME_ALLOW,
				'expected_fraud_meta_box' => Fraud_Meta_Box_Type::ALLOW,
			],
			'mark_complete_fraud_outcome_review'         => [
				'order_status'            => Order_Status::ON_HOLD,
				'intent_args'             => [
					'metadata'               => [
						'fraud_outcome' => Rule::FRAUD_OUTCOME_ALLOW,
					],
					'payment_method_options' => [ 'card' => [ 'request_three_d_secure' => 'automatic' ] ],
				],
				'expected_note_old'       => 'On hold to Processing',
				'expected_note_new'       => 'Payment via Card (pi_mock)',
				'expected_fraud_outcome'  => Rule::FRAUD_OUTCOME_ALLOW,
				'expected_fraud_meta_box' => Fraud_Meta_Box_Type::REVIEW_ALLOWED,
			],
		];
	}

	/**
	 * A successful payment processed while the order was in test mode should produce a
	 * persistent order note that flags the test mode and clarifies no funds were collected.
	 */
	public function test_mark_payment_completed_adds_test_mode_note_when_order_in_test_mode() {
		// Arrange: Mark the order as having been paid in test mode (as the gateway does at process time).
		$this->order->update_meta_data( WC_Payments_Order_Service::WCPAY_MODE_META_KEY, Order_Mode::TEST );
		$this->order->save();
		$intent = WC_Helper_Intention::create_intention();

		// Act: Mark the payment/order complete.
		$this->order_service->update_order_status_from_intent( $this->order, $intent );

		// Assert: The most recent note flags test mode and that no real funds were collected.
		$notes = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );
		$this->assertStringContainsString( 'test mode', $notes[0]->content );
		$this->assertStringContainsString( 'No real funds were collected', $notes[0]->content );
		// Assert: The transaction link is preserved.
		$this->assertStringContainsString( 'pi_mock', $notes[0]->content );
		// Assert: The transaction URL is well-formed — path must contain the real slash-separated
		// path, not the corrupted "0.000000" that sprintf produces when %2F is misread as a
		// printf float specifier (%2F → 0.000000).
		$this->assertStringContainsString( 'path=%2Fpayments%2Ftransactions%2Fdetails', $notes[0]->content );
		$this->assertStringNotContainsString( '0.000000', $notes[0]->content );
		// Assert: It does NOT use the standard "successfully charged" wording.
		$this->assertStringNotContainsString( 'successfully charged', $notes[0]->content );
	}

	/**
	 * A successful payment processed while the order was in production (live) mode should keep
	 * the standard success note unchanged. Guards against "TEST" leaking onto real orders.
	 */
	public function test_mark_payment_completed_keeps_standard_note_in_production_mode() {
		// Arrange: Mark the order as paid in production mode.
		$this->order->update_meta_data( WC_Payments_Order_Service::WCPAY_MODE_META_KEY, Order_Mode::PRODUCTION );
		$this->order->save();
		$intent = WC_Helper_Intention::create_intention();

		// Act.
		$this->order_service->update_order_status_from_intent( $this->order, $intent );

		// Assert: Standard wording, no test-mode flag.
		$notes = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );
		$this->assertStringContainsString( 'successfully charged</strong> using WooPayments', $notes[0]->content );
		$this->assertStringNotContainsString( 'test mode', $notes[0]->content );
		$this->assertStringNotContainsString( 'No real funds were collected', $notes[0]->content );
	}

	/**
	 * The email subject filter callback prepends a test-mode marker for orders paid in test mode.
	 */
	public function test_email_subject_gets_test_marker_for_test_mode_order() {
		$this->order->update_meta_data( WC_Payments_Order_Service::WCPAY_MODE_META_KEY, Order_Mode::TEST );
		$this->order->save();

		$subject = $this->order_service->maybe_add_test_mode_to_email_subject( '[Apparel Clothing]: New order #1811', $this->order );

		$this->assertStringContainsString( '[Test]', $subject );
		$this->assertStringContainsString( 'New order #1811', $subject );
	}

	/**
	 * The email subject filter callback leaves production-mode order subjects untouched.
	 */
	public function test_email_subject_unchanged_for_production_order() {
		$this->order->update_meta_data( WC_Payments_Order_Service::WCPAY_MODE_META_KEY, Order_Mode::PRODUCTION );
		$this->order->save();
		$original = '[Apparel Clothing]: New order #1811';

		$this->assertSame( $original, $this->order_service->maybe_add_test_mode_to_email_subject( $original, $this->order ) );
	}

	/**
	 * The email heading filter callback prepends a test-mode marker for orders paid in test mode.
	 */
	public function test_email_heading_gets_test_marker_for_test_mode_order() {
		$this->order->update_meta_data( WC_Payments_Order_Service::WCPAY_MODE_META_KEY, Order_Mode::TEST );
		$this->order->save();

		$heading = $this->order_service->maybe_add_test_mode_to_email_heading( 'New order: #1811', $this->order );

		$this->assertStringContainsString( '[Test]', $heading );
		$this->assertStringContainsString( 'New order: #1811', $heading );
	}

	/**
	 * The email heading filter callback leaves production-mode order headings untouched.
	 */
	public function test_email_heading_unchanged_for_production_order() {
		$this->order->update_meta_data( WC_Payments_Order_Service::WCPAY_MODE_META_KEY, Order_Mode::PRODUCTION );
		$this->order->save();
		$original = 'New order: #1811';

		$this->assertSame( $original, $this->order_service->maybe_add_test_mode_to_email_heading( $original, $this->order ) );
	}

	/**
	 * Orders with no stored mode (legacy or non-WooPayments orders) must not be flagged as test.
	 */
	public function test_email_subject_unchanged_when_mode_meta_absent() {
		$original = '[Apparel Clothing]: New order #1811';

		$this->assertSame( $original, $this->order_service->maybe_add_test_mode_to_email_subject( $original, $this->order ) );
	}

	/**
	 * Orders with no stored mode (legacy or non-WooPayments orders) must not have their heading flagged.
	 */
	public function test_email_heading_unchanged_when_mode_meta_absent() {
		$original = 'New order: #1811';

		$this->assertSame( $original, $this->order_service->maybe_add_test_mode_to_email_heading( $original, $this->order ) );
	}

	/**
	 * The callbacks must be a no-op (no fatal) when the second argument is not a WC_Order.
	 */
	public function test_email_marker_callbacks_ignore_non_order_argument() {
		$this->assertSame( 'subject', $this->order_service->maybe_add_test_mode_to_email_subject( 'subject', null ) );
		$this->assertSame( 'heading', $this->order_service->maybe_add_test_mode_to_email_heading( 'heading', 'not-an-order' ) );
	}

	/**
	 * init_hooks must register the subject and heading filters for every order email in
	 * TEST_MODE_INDICATOR_EMAIL_IDS, including the paid-invoice variant.
	 */
	public function test_init_hooks_registers_test_mode_email_filters() {
		$this->order_service->init_hooks();

		foreach ( WC_Payments_Order_Service::TEST_MODE_INDICATOR_EMAIL_IDS as $email_id ) {
			$this->assertNotFalse(
				has_filter( "woocommerce_email_subject_{$email_id}", [ $this->order_service, 'maybe_add_test_mode_to_email_subject' ] ),
				"Missing subject filter for {$email_id}"
			);
			$this->assertNotFalse(
				has_filter( "woocommerce_email_heading_{$email_id}", [ $this->order_service, 'maybe_add_test_mode_to_email_heading' ] ),
				"Missing heading filter for {$email_id}"
			);
		}

		// Paid invoices route through the *_paid filter variant (WC_Email_Customer_Invoice swaps
		// the suffix for processing/completed orders), so it must be covered explicitly.
		$this->assertContains( 'customer_invoice_paid', WC_Payments_Order_Service::TEST_MODE_INDICATOR_EMAIL_IDS );

		// The admin failure/cancellation notifications carry the marker too: a test-mode decline
		// reaches process_payment (which persists the mode) before failing, so the order keeps its
		// test-mode meta and the email would otherwise read as a real failed/cancelled payment.
		$this->assertContains( 'failed_order', WC_Payments_Order_Service::TEST_MODE_INDICATOR_EMAIL_IDS );
		$this->assertContains( 'cancelled_order', WC_Payments_Order_Service::TEST_MODE_INDICATOR_EMAIL_IDS );
	}

	/**
	 * The marker must apply end-to-end through WordPress' filter system (not only via a direct
	 * callback call), which guards against an accepted_args regression that would null $order and
	 * silently drop the marker on every real email.
	 */
	public function test_test_mode_marker_applied_through_registered_filters() {
		$this->order->update_meta_data( WC_Payments_Order_Service::WCPAY_MODE_META_KEY, Order_Mode::TEST );
		$this->order->save();
		$this->order_service->init_hooks();

		$this->assertStringContainsString(
			'[Test]',
			apply_filters( 'woocommerce_email_subject_new_order', '[Apparel Clothing]: New order #1811', $this->order ) // phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingHookComment
		);
		$this->assertStringContainsString(
			'[Test]',
			apply_filters( 'woocommerce_email_heading_new_order', 'New order: #1811', $this->order ) // phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingHookComment
		);
		// Paid-invoice variant: the dominant path for invoice resends on WooPayments orders.
		$this->assertStringContainsString(
			'[Test]',
			apply_filters( 'woocommerce_email_subject_customer_invoice_paid', 'Invoice for order #1811', $this->order ) // phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingHookComment
		);
	}

	/**
	 * Tests if the order is marked with the capture completed correctly.
	 * Public method update_order_status_from_intent calls private method mark_payment_capture_completed.
	 *
	 * @dataProvider mark_payment_capture_completed_provider
	 */
	public function test_mark_payment_capture_completed( $intent_args, $order_fraud_outcome_meta, $expected_fraud_outcome, $expected_fraud_meta_box ) {
		// Arrange: Create intention with proper outcome status, update order status to On Hold.
		$intent = WC_Helper_Intention::create_intention( $intent_args );
		$this->order_service->set_intention_status_for_order( $this->order, Intent_Status::REQUIRES_CAPTURE );
		$this->order->set_status( Order_Status::ON_HOLD );
		$this->order->save();
		if ( $order_fraud_outcome_meta ) {
			$this->order_service->set_fraud_outcome_status_for_order( $this->order, $order_fraud_outcome_meta );
		}

		// Act: Attempt to mark the payment/order complete.
		$this->order_service->update_order_status_from_intent( $this->order, $intent );

		// Assert: Check to make sure the intent/transaction id was set, and that intent_status meta was set.
		$this->assertEquals( $intent->get_id(), $this->order->get_transaction_id() );
		$this->assertEquals( $intent->get_status(), $this->order_service->get_intention_status_for_order( $this->order ) );

		// Assert: Confirm that the fraud outcome status and meta box type were set correctly.
		$this->assertEquals( $expected_fraud_outcome, $this->order_service->get_fraud_outcome_status_for_order( $this->order ) );
		$this->assertEquals( $expected_fraud_meta_box, $this->order_service->get_fraud_meta_box_type_for_order( $this->order ) );

		// Assert: Check that the order status was updated to a paid status.
		$this->assertTrue( $this->order->has_status( wc_get_is_paid_statuses() ) );

		// Assert: Check that the notes were updated.
		$notes         = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );
		$expected_note = version_compare( constant( 'WC_VERSION' ), '10.4', '>=' )
			? 'Payment via Card (pi_mock).'
			: 'On hold to Processing';
		$this->assertStringContainsString( $expected_note, $notes[1]->content );
		$this->assertStringContainsString( 'successfully captured</strong> using WooPayments', $notes[0]->content );
		$this->assertStringContainsString( '%2Fpayments%2Ftransactions%2Fdetails&id=pi_mock" target="_blank" rel="noopener noreferrer">pi_mock', $notes[0]->content );

		// Assert: Check that the order was unlocked.
		$this->assertFalse( get_transient( 'wcpay_processing_intent_' . $this->order->get_id() ) );

		// Assert: Applying the same data multiple times does not cause duplicate actions.
		$this->order_service->update_order_status_from_intent( $this->order, $intent );
		$notes_2 = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );
		$this->assertEquals( count( $notes ), count( $notes_2 ) );
	}

	public function mark_payment_capture_completed_provider() {
		return [
			'mark_capture_complete_no_fraud_outcome'     => [
				'intent_args'              => [],
				'order_fraud_outcome_meta' => false,
				'expected_fraud_outcome'   => false,
				'expected_fraud_meta_box'  => false,
			],
			'mark_capture_complete_fraud_outcome_allow'  => [
				'intent_args'              => [
					'metadata' => [
						'fraud_outcome' => Rule::FRAUD_OUTCOME_ALLOW,
					],
				],
				'order_fraud_outcome_meta' => false,
				'expected_fraud_outcome'   => Rule::FRAUD_OUTCOME_ALLOW,
				'expected_fraud_meta_box'  => Fraud_Meta_Box_Type::ALLOW,
			],
			'mark_capture_complete_fraud_outcome_review' => [
				'intent_args'              => [
					'metadata' => [
						'fraud_outcome' => Rule::FRAUD_OUTCOME_ALLOW,
					],
				],
				'order_fraud_outcome_meta' => Rule::FRAUD_OUTCOME_REVIEW,
				'expected_fraud_outcome'   => Rule::FRAUD_OUTCOME_ALLOW,
				'expected_fraud_meta_box'  => Fraud_Meta_Box_Type::REVIEW_ALLOWED,
			],
		];
	}

	/**
	 * Tests that the "charged" note is not added when a "captured" note already exists.
	 * This prevents duplicate notes due to race conditions between manual capture and webhooks.
	 *
	 * @see https://github.com/Automattic/woocommerce-payments/issues/XXXXX
	 */
	public function test_mark_payment_completed_skips_when_capture_note_exists() {
		// Arrange: Create a succeeded intent (simulating what webhook receives).
		$intent = WC_Helper_Intention::create_intention( [ 'status' => Intent_Status::SUCCEEDED ] );

		// Simulate the scenario where capture flow already completed:
		// 1. Order status is already "processing" (paid status)
		// 2. A "captured" note already exists
		// 3. _intention_status is already "succeeded".
		$this->order->set_status( Order_Status::PROCESSING );
		$this->order->save();
		$this->order_service->set_intention_status_for_order( $this->order, Intent_Status::SUCCEEDED );

		// Add the capture note that would have been added by process_captured_payment().
		$capture_note = sprintf(
			'A payment of %s was <strong>successfully captured</strong> using WooPayments (<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>).',
			wp_strip_all_tags( html_entity_decode( wc_price( $this->order->get_total(), [ 'currency' => $this->order->get_currency() ] ) ) ),
			WC_Payments_Utils::compose_transaction_url( $intent->get_id(), $intent->get_charge()->get_id() ),
			$intent->get_id()
		);
		$this->order->add_order_note( $capture_note );

		$notes_before = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );

		// Act: Simulate webhook calling update_order_status_from_intent with a succeeded intent.
		// Since _intention_status is already "succeeded" (not "requires_capture"),
		// this would normally call mark_payment_completed() and add a "charged" note.
		$this->order_service->update_order_status_from_intent( $this->order, $intent );

		// Assert: No new notes should be added because the capture note already exists.
		$notes_after = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );
		$this->assertEquals( count( $notes_before ), count( $notes_after ), 'No new notes should be added when capture note exists' );

		// Assert: Verify that no "charged" note was added.
		foreach ( $notes_after as $note ) {
			$this->assertStringNotContainsString( 'successfully charged', $note->content, 'No "charged" note should exist' );
		}
	}

	/**
	 * Tests if the order is marked with the payment authorized correctly.
	 * Public method update_order_status_from_intent calls private method mark_payment_authorized.
	 *
	 * @dataProvider mark_payment_authorized_provider
	 */
	public function test_mark_payment_authorized( $intent_args, $expected_fraud_outcome, $expected_fraud_meta_box ) {
		// Arrange: Create intention with provided args.
		$intent = WC_Helper_Intention::create_intention( $intent_args );

		// Act: Attempt to mark the payment authorized.
		$this->order_service->update_order_status_from_intent( $this->order, $intent );

		// Assert: Check to make sure the intent_status meta was set.
		$this->assertEquals( $intent->get_status(), $this->order_service->get_intention_status_for_order( $this->order ) );

		// Assert: Confirm that the fraud outcome status and meta box type were set correctly.
		$this->assertEquals( $expected_fraud_outcome, $this->order_service->get_fraud_outcome_status_for_order( $this->order ) );
		$this->assertEquals( $expected_fraud_meta_box, $this->order_service->get_fraud_meta_box_type_for_order( $this->order ) );

		// Assert: Check that the order status was updated to on hold.
		$this->assertTrue( $this->order->has_status( Order_Status::ON_HOLD ) );

		// Assert: Check that the notes were updated.
		$notes = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );
		$this->assertStringContainsString( 'Pending payment to On hold', $notes[1]->content );
		$this->assertStringContainsString( 'authorized</strong> using WooPayments', $notes[0]->content );
		$this->assertStringContainsString( '%2Fpayments%2Ftransactions%2Fdetails&id=pi_mock" target="_blank" rel="noopener noreferrer">pi_mock', $notes[0]->content );

		// Assert: Check that the order was unlocked.
		$this->assertFalse( get_transient( 'wcpay_processing_intent_' . $this->order->get_id() ) );

		// Assert: Applying the same data multiple times does not cause duplicate actions.
		$this->order_service->update_order_status_from_intent( $this->order, $intent );
		$notes_2 = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );
		$this->assertEquals( count( $notes ), count( $notes_2 ) );
	}

	public function mark_payment_authorized_provider() {
		return [
			'mark_authorized_no_fraud_outcome_intent_status_requires_capture' => [
				'intent_args'             => [
					'status' => Intent_Status::REQUIRES_CAPTURE,
				],
				'expected_fraud_outcome'  => false,
				'expected_fraud_meta_box' => false,
			],
			'mark_authorized_no_fraud_outcome_intent_status_processing' => [
				'intent_args'             => [
					'status' => Intent_Status::PROCESSING,
				],
				'expected_fraud_outcome'  => false,
				'expected_fraud_meta_box' => false,
			],
			'mark_authorized_fraud_outcome_allow' => [
				'intent_args'             => [
					'status'   => Intent_Status::REQUIRES_CAPTURE,
					'metadata' => [
						'fraud_outcome' => Rule::FRAUD_OUTCOME_ALLOW,
					],
				],
				'expected_fraud_outcome'  => Rule::FRAUD_OUTCOME_ALLOW,
				'expected_fraud_meta_box' => Fraud_Meta_Box_Type::ALLOW,
			],
		];
	}

	/**
	 * Method `mark_payment_authorized` should exit if the order status is already on-hold.
	 */
	public function test_mark_payment_authorized_exits_on_existing_order_status_on_hold() {
		// Arrange: Create intention, set order status, and get the expected notes.
		$intent = WC_Helper_Intention::create_intention( [ 'status' => Intent_Status::REQUIRES_CAPTURE ] );
		$this->order->set_status( Order_Status::ON_HOLD );
		$this->order->save();
		$expected_notes = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );

		// Act: Attempt to mark the payment/order on-hold.
		$this->order_service->update_order_status_from_intent( $this->order, $intent );

		// Assert: Check that the notes were not updated.
		$updated_notes = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );
		$this->assertEquals( $expected_notes, $updated_notes );

		// Assert: Check that the order is not locked.
		$this->assertFalse( get_transient( 'wcpay_processing_intent_' . $this->order->get_id() ) );
	}

	/**
	 * Tests if the order is marked with the payment authorized correctly.
	 * Public method update_order_status_from_intent calls private method mark_order_held_for_review_for_fraud.
	 *
	 * @dataProvider mark_order_held_for_review_for_fraud_provider
	 */
	public function test_mark_order_held_for_review_for_fraud( $intent_args ) {
		// Arrange: Create intention with provided args.
		$intent = WC_Helper_Intention::create_intention( $intent_args );

		// Act: Attempt to mark the payment authorized.
		$this->order_service->update_order_status_from_intent( $this->order, $intent );

		// Assert: Check to make sure the intent_status meta was set.
		$this->assertEquals( $intent->get_status(), $this->order_service->get_intention_status_for_order( $this->order ) );

		// Assert: Confirm that the fraud outcome status and meta box type were set correctly.
		$this->assertEquals( Rule::FRAUD_OUTCOME_REVIEW, $this->order_service->get_fraud_outcome_status_for_order( $this->order ) );
		$this->assertEquals( Fraud_Meta_Box_Type::REVIEW, $this->order_service->get_fraud_meta_box_type_for_order( $this->order ) );

		// Assert: Check that the order status was updated to on hold.
		$this->assertTrue( $this->order->has_status( Order_Status::ON_HOLD ) );

		// Assert: Check that the notes were updated.
		$notes = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );
		$this->assertStringContainsString( 'Pending payment to On hold', $notes[1]->content );
		$this->assertStringContainsString( 'held for review</strong> by one or more risk filters', $notes[0]->content );
		$this->assertStringContainsString( '%2Fpayments%2Ftransactions%2Fdetails&id=pi_mock&status_is=review&type_is=order_note" target="_blank" rel="noopener noreferrer">View more details', $notes[0]->content );

		// Assert: Check that the order was unlocked.
		$this->assertFalse( get_transient( 'wcpay_processing_intent_' . $this->order->get_id() ) );

		// Assert: Applying the same data multiple times does not cause duplicate actions.
		$this->order_service->update_order_status_from_intent( $this->order, $intent );
		$notes_2 = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );
		$this->assertEquals( count( $notes ), count( $notes_2 ) );
	}

	public function mark_order_held_for_review_for_fraud_provider() {
		return [
			'mark_held_for_review_no_fraud_outcome_intent_status_requires_capture' => [
				'intent_args' => [
					'status'   => Intent_Status::REQUIRES_CAPTURE,
					'metadata' => [
						'fraud_outcome' => Rule::FRAUD_OUTCOME_REVIEW,
					],
				],
			],
			'mark_held_for_review_no_fraud_outcome_intent_status_processing' => [
				'intent_args' => [
					'status'   => Intent_Status::PROCESSING,
					'metadata' => [
						'fraud_outcome' => Rule::FRAUD_OUTCOME_REVIEW,
					],
				],
			],
		];
	}

	/**
	 * Tests if the order is marked with the payment authorized correctly.
	 * Public method update_order_status_from_intent calls private method mark_payment_started.
	 *
	 * @dataProvider mark_payment_started_provider
	 */
	public function test_mark_payment_started( $intent_args, $expected_fraud_meta_box ) {
		// Arrange: Create intention with provided args.
		$intent = WC_Helper_Intention::create_intention( $intent_args );

		// Act: Attempt to mark the payment started.
		$this->order_service->update_order_status_from_intent( $this->order, $intent );

		// Assert: Check to make sure the intent_status meta was set.
		$this->assertEquals( $intent->get_status(), $this->order_service->get_intention_status_for_order( $this->order ) );

		// Assert: Confirm that the fraud outcome status and fraud meta box type meta were not set/set correctly.
		$this->assertEquals( false, $this->order_service->get_fraud_outcome_status_for_order( $this->order ) );
		$this->assertEquals( $expected_fraud_meta_box, $this->order_service->get_fraud_meta_box_type_for_order( $this->order ) );

		// Assert: Check that the order status was not updated.
		$this->assertTrue( $this->order->has_status( Order_Status::PENDING ) );

		// Assert: Check that the notes were updated.
		$notes = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );
		$this->assertStringContainsString( 'started</strong> using WooPayments', $notes[0]->content );
		$this->assertStringContainsString( 'Payments (<code>pi_mock</code>)', $notes[0]->content );

		// Assert: Check that the order was unlocked.
		$this->assertFalse( get_transient( 'wcpay_processing_intent_' . $this->order->get_id() ) );

		// Assert: Applying the same data multiple times does not cause duplicate actions.
		$this->order_service->update_order_status_from_intent( $this->order, $intent );
		$notes_2 = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );
		$this->assertEquals( count( $notes ), count( $notes_2 ) );
	}

	public function mark_payment_started_provider() {
		return [
			'mark_payment_started_intent_status_requires_action' => [
				'intent_args'             => [
					'status'                 => Intent_Status::REQUIRES_ACTION,
					'payment_method_options' => [ 'card' => [ 'request_three_d_secure' => 'automatic' ] ],
				],
				'expected_fraud_meta_box' => Fraud_Meta_Box_Type::PAYMENT_STARTED,
			],
			'mark_payment_started_intent_status_requires_payment_method' => [
				'intent_args'             => [
					'status'                 => Intent_Status::REQUIRES_PAYMENT_METHOD,
					'payment_method_options' => [ 'card' => [ 'request_three_d_secure' => 'automatic' ] ],
				],
				'expected_fraud_meta_box' => Fraud_Meta_Box_Type::PAYMENT_STARTED,
			],
			'mark_payment_started_intent_status_requires_action_not_card' => [
				'intent_args'             => [
					'status'               => Intent_Status::REQUIRES_ACTION,
					'payment_method_types' => [ 'bancontact' ],
				],
				'expected_fraud_meta_box' => Fraud_Meta_Box_Type::NOT_CARD,
			],
		];
	}

	/**
	 * Tests if the order is marked with the payment on hold for offline payments.
	 * Public method update_order_status_from_intent calls private method mark_payment_on_hold.
	 */
	public function test_mark_payment_on_hold() {
		// Arrange: Create intention with provided args.
		$intent = WC_Helper_Intention::create_intention(
			[
				'status'                 => Intent_Status::REQUIRES_ACTION,
				'payment_method_types'   => [ 'offline_test_payment_method' ],
				'payment_method_options' => [ Payment_Method::OFFLINE_PAYMENT_METHODS[0] => [] ],
			]
		);

		// Act: Attempt to mark the payment on hold.
		$this->order_service->update_order_status_from_intent( $this->order, $intent );

		// Assert: Check to make sure the intent_status meta was set.
		$this->assertEquals( $intent->get_status(), $this->order_service->get_intention_status_for_order( $this->order ) );

		// Assert: Confirm that the fraud outcome status and fraud meta box type meta were not set/set correctly.
		$this->assertEquals( false, $this->order_service->get_fraud_outcome_status_for_order( $this->order ) );
		$this->assertEquals( Fraud_Meta_Box_Type::NOT_CARD, $this->order_service->get_fraud_meta_box_type_for_order( $this->order ) );

		// Assert: Check that the order status was updated to on hold.
		$this->assertTrue( $this->order->has_status( Order_Status::ON_HOLD ) );

		// Assert: Check that the notes were updated.
		$notes = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );
		$this->assertStringContainsString( 'started</strong> using WooPayments', $notes[0]->content );
		$this->assertStringContainsString( 'Payments (<code>pi_mock</code>)', $notes[0]->content );

		// Assert: Check that the order was unlocked.
		$this->assertFalse( get_transient( 'wcpay_processing_intent_' . $this->order->get_id() ) );

		// Assert: Applying the same data multiple times does not cause duplicate actions.
		$this->order_service->update_order_status_from_intent( $this->order, $intent );
		$notes_2 = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );
		$this->assertEquals( count( $notes ), count( $notes_2 ) );
	}

	/**
	 * Tests if mark_payment_started exits if the order status is not Peding.
	 * Public method update_order_status_from_intent calls private method mark_payment_started.
	 */
	public function test_mark_payment_started_exits_on_existing_order_status_not_pending() {
		// Arrange: Create intention with provided args, update order status.
		$intent = WC_Helper_Intention::create_intention( [ 'status' => Intent_Status::REQUIRES_ACTION ] );
		$this->order->set_status( Order_Status::ON_HOLD );
		$this->order->save();
		$expected_notes = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );

		// Act: Attempt to mark the payment started.
		$this->order_service->update_order_status_from_intent( $this->order, $intent );

		// Assert: Check to make sure the intent_status meta was not set.
		$this->assertEquals( '', $this->order_service->get_intention_status_for_order( $this->order ) );

		// Assert: Confirm that the fraud outcome status and fraud meta box type meta were not set/set correctly.
		$this->assertEquals( false, $this->order_service->get_fraud_outcome_status_for_order( $this->order ) );
		$this->assertEquals( false, $this->order_service->get_fraud_meta_box_type_for_order( $this->order ) );

		// Assert: Check that the order status was not updated.
		$this->assertTrue( $this->order->has_status( Order_Status::ON_HOLD ) );

		// Assert: Check that the notes were not updated.
		$updated_notes = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );
		$this->assertEquals( $expected_notes, $updated_notes );

		// Assert: Check that the order was unlocked.
		$this->assertFalse( get_transient( 'wcpay_processing_intent_' . $this->order->get_id() ) );
	}

	/**
	 * Tests if mark_payment_started does not set the fraud meta box type for the order.
	 * Public method update_order_status_from_intent calls private method mark_payment_started.
	 */
	public function test_mark_payment_started_does_not_add_fraud_meta_box_type_if_fraud_settings_disabled() {
		// Arrange: Create intention with provided args, turn off fraud settings.
		$intent = WC_Helper_Intention::create_intention( [ 'status' => Intent_Status::REQUIRES_ACTION ] );

		// Act: Attempt to mark the payment started.
		$this->order_service->update_order_status_from_intent( $this->order, $intent );

		// Assert: Check to make sure the intent_status meta was not set.
		$this->assertEquals( Intent_Status::REQUIRES_ACTION, $this->order_service->get_intention_status_for_order( $this->order ) );

		// Assert: Confirm that the fraud outcome status was set and the fraud meta box type was not set.
		$this->assertEquals( false, $this->order_service->get_fraud_outcome_status_for_order( $this->order ) );
		$this->assertEquals( Fraud_Meta_Box_Type::NOT_CARD, $this->order_service->get_fraud_meta_box_type_for_order( $this->order ) );

		// Assert: Check that the order status was not updated.
		$this->assertTrue( $this->order->has_status( Order_Status::PENDING ) );

		// Assert: Check that the notes were updated.
		$notes = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );
		$this->assertStringContainsString( 'started</strong> using WooPayments', $notes[0]->content );
		$this->assertStringContainsString( 'Payments (<code>pi_mock</code>)', $notes[0]->content );

		// Assert: Check that the order was unlocked.
		$this->assertFalse( get_transient( 'wcpay_processing_intent_' . $this->order->get_id() ) );

		// Assert: Applying the same data multiple times does not cause duplicate actions.
		$this->order_service->update_order_status_from_intent( $this->order, $intent );
		$notes_2 = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );
		$this->assertEquals( count( $notes ), count( $notes_2 ) );

		// Assert: Check that the order was unlocked.
		$this->assertFalse( get_transient( 'wcpay_processing_intent_' . $this->order->get_id() ) );
	}

	/**
	 * Tests if the order was marked failed successfully.
	 */
	public function test_mark_payment_failed() {
		// Arrange: Create the intent, get the charge, and set additional failed message.
		$intent  = WC_Helper_Intention::create_intention( [ 'status' => Intent_Status::REQUIRES_ACTION ] );
		$charge  = $intent->get_charge();
		$message = 'This is the test failed message.';

		// Act: Attempt to mark the payment/order failed.
		$this->order_service->mark_payment_failed( $this->order, $intent->get_id(), $intent->get_status(), $charge->get_id(), $message );

		// Assert: Check to make sure the intent_status meta was set.
		$this->assertEquals( Intent_Status::REQUIRES_ACTION, $this->order_service->get_intention_status_for_order( $this->order ) );

		// Assert: Check that the order status was updated to failed status.
		$this->assertTrue( $this->order->has_status( Order_Status::FAILED ) );

		// Assert: Check that the notes were updated.
		$notes = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );
		$this->assertStringContainsString( 'Pending payment to Failed', $notes[1]->content );
		$this->assertStringContainsString( 'failed</strong> using WooPayments', $notes[0]->content );
		$this->assertStringContainsString( '%2Fpayments%2Ftransactions%2Fdetails&id=pi_mock" target="_blank" rel="noopener noreferrer">pi_mock', $notes[0]->content );
		$this->assertStringContainsString( 'This is the test failed message.', $notes[0]->content );

		// Assert: Check that the order was unlocked.
		$this->assertFalse( get_transient( 'wcpay_processing_intent_' . $this->order->get_id() ) );

		// Assert: Applying the same data multiple times does not cause duplicate actions.
		$this->order_service->mark_payment_failed( $this->order, $intent->get_id(), $intent->get_status(), $charge->get_id(), $message );
		$notes_2 = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );
		$this->assertCount( 2, $notes_2 );
	}

	/**
	 * Method `mark_payment_failed` should exit if the order status is already failed.
	 */
	public function test_mark_payment_failed_exits_on_existing_order_status_failed() {
		// Arrange: Create the intent, get the charge, set additional failed message, and get expected notes.
		$intent = WC_Helper_Intention::create_intention( [ 'status' => Intent_Status::REQUIRES_ACTION ] );
		$this->order->set_status( Order_Status::FAILED );
		$this->order->save();
		$expected_notes = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );

		// Act: Attempt to mark the payment/order failed.
		$this->order_service->mark_payment_failed( $this->order, $intent->get_id(), $intent->get_status(), '' );

		// Assert: Check that the notes were not updated.
		$updated_notes = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );
		$this->assertEquals( $expected_notes, $updated_notes );

		// Assert: Check that the order is not locked.
		$this->assertFalse( get_transient( 'wcpay_processing_intent_' . $this->order->get_id() ) );
	}

	/**
	 * Tests if the payment capture was noted failed.
	 *
	 * @dataProvider mark_payment_capture_failed_provider
	 */
	public function test_mark_payment_capture_failed_with_provider( $fraud_outcome, $expected_fraud_outcome, $expected_fraud_meta_box ) {
		// Arrange: Create the intent and get the charge. Set the fraud outcome status.
		$intent = WC_Helper_Intention::create_intention( [ 'status' => Intent_Status::REQUIRES_ACTION ] );
		$charge = $intent->get_charge();
		if ( $fraud_outcome ) {
			$this->order_service->set_fraud_outcome_status_for_order( $this->order, $fraud_outcome );
		}

		// Act: Attempt to mark the payment capture failed.
		$this->order_service->mark_payment_capture_failed( $this->order, $intent->get_id(), $intent->get_status(), $charge->get_id() );

		// Assert: Check to make sure the intent_status meta was set.
		$this->assertEquals( Intent_Status::REQUIRES_ACTION, $this->order_service->get_intention_status_for_order( $this->order ) );

		// Assert: Confirm that the fraud outcome status has not been changed, and that the fraud meta box type meta was set correctly.
		$this->assertEquals( $expected_fraud_outcome, $this->order_service->get_fraud_outcome_status_for_order( $this->order ) );
		$this->assertEquals( $expected_fraud_meta_box, $this->order_service->get_fraud_meta_box_type_for_order( $this->order ) );

		// Assert: Check that the order status was not updated.
		$this->assertTrue( $this->order->has_status( [ Order_Status::PENDING ] ) );

		// Assert: Check that the notes were updated.
		$notes = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );
		$this->assertStringContainsString( 'failed</strong> to complete using WooPayments', $notes[0]->content );
		$this->assertStringContainsString( '%2Fpayments%2Ftransactions%2Fdetails&id=pi_mock" target="_blank" rel="noopener noreferrer">pi_mock', $notes[0]->content );

		// Assert: Check that the order was unlocked.
		$this->assertFalse( get_transient( 'wcpay_processing_intent_' . $this->order->get_id() ) );
	}

	public function mark_payment_capture_failed_provider() {
		return [
			'fraud_outcome_not_set' => [
				'fraud_outcome'           => false,
				'expected_fraud_outcome'  => '',
				'expected_fraud_meta_box' => '',
			],
			'fraud_outcome_review'  => [
				'fraud_outcome'           => Rule::FRAUD_OUTCOME_REVIEW,
				'expected_fraud_outcome'  => Rule::FRAUD_OUTCOME_REVIEW,
				'expected_fraud_meta_box' => Fraud_Meta_Box_Type::REVIEW_FAILED,
			],
		];
	}

	/**
	 * Tests if the payment capture was noted failed with a null intent status.
	 */
	public function test_mark_payment_capture_failed_null_intent_status() {
		// Arrange: Create the intent and get the charge.
		$intent = WC_Helper_Intention::create_intention( [ 'status' => null ] );
		$charge = $intent->get_charge();

		// Act: Attempt to mark the payment capture failed.
		$this->order_service->mark_payment_capture_failed( $this->order, $intent->get_id(), $intent->get_status(), $charge->get_id() );

		// Assert: Check to make sure the intent_status meta was not set.
		$this->assertEquals( '', $this->order_service->get_intention_status_for_order( $this->order ) );

		// Assert: Check that the order status was not updated.
		$this->assertTrue( $this->order->has_status( [ Order_Status::PENDING ] ) );

		// Assert: Check that the notes were updated.
		$notes = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );
		$this->assertStringContainsString( 'failed</strong> to complete using WooPayments', $notes[0]->content );
		$this->assertStringContainsString( '%2Fpayments%2Ftransactions%2Fdetails&id=pi_mock" target="_blank" rel="noopener noreferrer">pi_mock', $notes[0]->content );

		// Assert: Check that the order was unlocked.
		$this->assertFalse( get_transient( 'wcpay_processing_intent_' . $this->order->get_id() ) );
	}

	/**
	 * Tests if the payment capture was marked expired succefully.
	 *
	 * @dataProvider mark_payment_capture_expired_provider
	 */
	public function test_mark_payment_capture_expired_with_provider( $fraud_outcome, $expected_fraud_outcome, $expected_fraud_meta_box ) {
		// Arrange: Create the intent, get the proper order status variations, and get the charge. Set the fraud outcome status.
		$intent            = WC_Helper_Intention::create_intention( [ 'status' => Intent_Status::CANCELED ] );
		$order_status      = Order_Status::FAILED;
		$wc_order_statuses = wc_get_order_statuses();
		$charge            = $intent->get_charge();
		if ( $fraud_outcome ) {
			$this->order_service->set_fraud_outcome_status_for_order( $this->order, $fraud_outcome );
		}

		// Act: Attempt to mark the payment/order expired/failed.
		$this->order_service->mark_payment_capture_expired( $this->order, $intent->get_id(), $intent->get_status(), $charge->get_id() );

		// Assert: Check to make sure the intent_status meta was set.
		$this->assertEquals( Intent_Status::CANCELED, $this->order_service->get_intention_status_for_order( $this->order ) );

		// Assert: Confirm that the fraud outcome status has not been changed, and that the fraud meta box type meta was set correctly.
		$this->assertEquals( $expected_fraud_outcome, $this->order_service->get_fraud_outcome_status_for_order( $this->order ) );
		$this->assertEquals( $expected_fraud_meta_box, $this->order_service->get_fraud_meta_box_type_for_order( $this->order ) );

		// Assert: Check that the order status was updated to cancelled status.
		$this->assertTrue( $this->order->has_status( [ $order_status ] ) );

		// Assert: Check that the notes were updated.
		$notes = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );
		$this->assertStringContainsString( 'Pending payment to ' . $wc_order_statuses['wc-failed'], $notes[1]->content );
		$this->assertStringContainsString( 'Payment authorization has <strong>expired</strong>', $notes[0]->content );
		$this->assertStringContainsString( '%2Fpayments%2Ftransactions%2Fdetails&id=pi_mock" target="_blank" rel="noopener noreferrer">pi_mock', $notes[0]->content );

		// Assert: Check that the order was unlocked.
		$this->assertFalse( get_transient( 'wcpay_processing_intent_' . $this->order->get_id() ) );

		// Assert: Applying the same data multiple times does not cause duplicate actions.
		$this->order_service->mark_payment_capture_expired( $this->order, $intent->get_id(), $intent->get_status(), $charge->get_id() );
		$notes_2 = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );
		$this->assertCount( 2, $notes_2 );
	}

	public function mark_payment_capture_expired_provider() {
		return [
			'fraud_outcome_not_set' => [
				'fraud_outcome'           => false,
				'expected_fraud_outcome'  => '',
				'expected_fraud_meta_box' => '',
			],
			'fraud_outcome_review'  => [
				'fraud_outcome'           => Rule::FRAUD_OUTCOME_REVIEW,
				'expected_fraud_outcome'  => Rule::FRAUD_OUTCOME_REVIEW,
				'expected_fraud_meta_box' => Fraud_Meta_Box_Type::REVIEW_EXPIRED,
			],
		];
	}

	/**
	 * Tests if the order is marked with the payment authorized correctly.
	 * Public method update_order_status_from_intent calls private method mark_payment_cancelled.
	 *
	 * @dataProvider mark_payment_cancelled_provider
	 */
	public function test_mark_payment_capture_cancelled( $intent_args, $order_fraud_outcome, $expected_fraud_outcome, $expected_fraud_meta_box ) {
		// Arrange: Create the intent, get the proper order status variations. Set the fraud outcome status.
		$intent            = WC_Helper_Intention::create_intention( $intent_args ); // Stripe uses single 'l'.
		$order_status      = Order_Status::CANCELLED; // WCPay uses double 'l'.
		$wc_order_statuses = wc_get_order_statuses(); // WooCommerce uses single 'l' for US English.
		if ( $order_fraud_outcome ) {
			$this->order_service->set_fraud_outcome_status_for_order( $this->order, Rule::FRAUD_OUTCOME_REVIEW );
		}

		// Act: Attempt to mark the payment/order cancelled.
		$this->order_service->update_order_status_from_intent( $this->order, $intent );

		// Assert: Check to make sure the intent_status meta was set.
		$this->assertEquals( Intent_Status::CANCELED, $this->order_service->get_intention_status_for_order( $this->order ) );

		// Assert: Confirm that the fraud outcome status has not been changed, and that the fraud meta box type meta was set correctly.
		$this->assertEquals( $expected_fraud_outcome, $this->order_service->get_fraud_outcome_status_for_order( $this->order ) );
		$this->assertEquals( $expected_fraud_meta_box, $this->order_service->get_fraud_meta_box_type_for_order( $this->order ) );

		// Assert: Check that the order status was updated to cancelled status.
		$this->assertTrue( $this->order->has_status( [ $order_status ] ) );

		// Assert: Check that the notes were updated.
		$notes = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );
		$this->assertStringContainsString( 'Pending payment to ' . $wc_order_statuses['wc-cancelled'], $notes[0]->content );
		$this->assertStringContainsString( 'Payment authorization was successfully <strong>cancelled</strong>', $notes[1]->content );

		// Assert: Check that the order was unlocked.
		$this->assertFalse( get_transient( 'wcpay_processing_intent_' . $this->order->get_id() ) );

		// Assert: Applying the same data multiple times does not cause duplicate actions.
		$this->order_service->update_order_status_from_intent( $this->order, $intent );
		$notes_2 = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );
		$this->assertCount( 2, $notes_2 );
	}

	public function mark_payment_cancelled_provider() {
		return [
			'mark_payment_cancelled_no_fraud_outcome' => [
				'intent_args'             => [
					'status' => Intent_Status::CANCELED,
				],
				'order_fraud_outcome'     => false,
				'expected_fraud_outcome'  => '',
				'expected_fraud_meta_box' => '',
			],
			'mark_payment_cancelled_outcome_review_meta_box_blocked' => [
				'intent_args'             => [
					'status'   => Intent_Status::CANCELED,
					'metadata' => [
						'fraud_outcome' => Rule::FRAUD_OUTCOME_REVIEW,
					],
				],
				'order_fraud_outcome'     => Rule::FRAUD_OUTCOME_REVIEW,
				'expected_fraud_outcome'  => Rule::FRAUD_OUTCOME_REVIEW,
				'expected_fraud_meta_box' => Fraud_Meta_Box_Type::REVIEW_BLOCKED,
			],
		];
	}

	/**
	 * Tests if the payment was blocked through the fraud rules.
	 */
	public function test_mark_order_blocked_for_fraud() {
		// Act: Attempt to mark the payment/order expired/cancelled.
		$this->order_service->mark_order_blocked_for_fraud( $this->order, 'pi_mock', Intent_Status::CANCELED );

		// Assert: Check to make sure the intent_status meta was set.
		$this->assertEquals( Intent_Status::CANCELED, $this->order_service->get_intention_status_for_order( $this->order ) );

		// Assert: Confirm that the fraud outcome status and fraud meta box type meta were not set.
		$this->assertEquals( Rule::FRAUD_OUTCOME_BLOCK, $this->order_service->get_fraud_outcome_status_for_order( $this->order ) );
		$this->assertEquals( Fraud_Meta_Box_Type::BLOCK, $this->order_service->get_fraud_meta_box_type_for_order( $this->order ) );

		// Assert: Check that the order status was has not been updated.
		$this->assertTrue( $this->order->has_status( Order_Status::PENDING ) );

		// Assert: Check that the notes were updated.
		$notes = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );
		$this->assertStringContainsString( 'blocked</strong> by one or more risk filters', $notes[0]->content );
		$this->assertStringContainsString( '%2Fpayments%2Ftransactions%2Fdetails&id=pi_mock&status_is=block&type_is=order_note" target="_blank" rel="noopener noreferrer">View more details', $notes[0]->content );

		// Assert: Check that the order was unlocked.
		$this->assertFalse( get_transient( 'wcpay_processing_intent_' . $this->order->get_id() ) );

		// Assert: Applying the same data multiple times does not cause duplicate actions.
		$this->order_service->mark_order_blocked_for_fraud( $this->order, 'pi_mock', Intent_Status::CANCELED );
		$notes_2 = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );
		$this->assertCount( 1, $notes_2 );
	}

	/**
	 * Tests that a rule engine block, which fires before an intent exists, links the note to the order.
	 */
	public function test_mark_order_blocked_for_fraud_without_intent_links_to_order() {
		// Act: Block the order with no intent id, as happens for rule engine blocks.
		$this->order_service->mark_order_blocked_for_fraud( $this->order, '', Intent_Status::CANCELED );

		// Assert: With no intent to link to, the note falls back to the order id.
		$notes = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );
		$this->assertStringContainsString( '%2Fpayments%2Ftransactions%2Fdetails&id=' . $this->order->get_id() . '&status_is=block&type_is=order_note" target="_blank" rel="noopener noreferrer">View more details', $notes[0]->content );
	}

	/**
	 * Tests that the blocked note names the risk filters that fired when ruleset results are available.
	 */
	public function test_mark_order_blocked_for_fraud_with_ruleset_results() {
		// Act: Attempt to mark the payment/order blocked, with the rule engine results.
		$this->order_service->mark_order_blocked_for_fraud(
			$this->order,
			'pi_mock',
			Intent_Status::CANCELED,
			[
				'international_ip_address' => 'block',
				'some_unknown_rule'        => 'block',
			]
		);

		// Assert: Check that the note lists the fired risk filters, with a humanized fallback for the unknown rule key.
		$notes = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );
		$this->assertStringContainsString( 'blocked</strong> by the following risk filters', $notes[0]->content );
		$this->assertStringContainsString( 'Block if the country resolved from customer IP is not listed in your selling countries', $notes[0]->content );
		$this->assertStringContainsString( 'Some unknown rule', $notes[0]->content );
		$this->assertStringContainsString( '%2Fpayments%2Ftransactions%2Fdetails&id=pi_mock&status_is=block&type_is=order_note" target="_blank" rel="noopener noreferrer">View more details', $notes[0]->content );

		// Assert: Check that the ruleset results were persisted on the order.
		$this->assertSame(
			[
				'international_ip_address' => 'block',
				'some_unknown_rule'        => 'block',
			],
			$this->order_service->get_fraud_ruleset_results_for_order( $this->order )
		);

		// Assert: Applying the same data multiple times does not cause duplicate actions.
		$this->order_service->mark_order_blocked_for_fraud(
			$this->order,
			'pi_mock',
			Intent_Status::CANCELED,
			[
				'international_ip_address' => 'block',
				'some_unknown_rule'        => 'block',
			]
		);
		$notes_2 = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );
		$this->assertCount( 1, $notes_2 );
	}

	/**
	 * Tests that the held for review note names the risk filters that fired when the intent
	 * metadata carries the ruleset results.
	 */
	public function test_mark_order_held_for_review_for_fraud_with_ruleset_results() {
		// Arrange: Create intention with the fraud outcome and ruleset results in the metadata.
		$intent = WC_Helper_Intention::create_intention(
			[
				'status'   => Intent_Status::REQUIRES_CAPTURE,
				'metadata' => [
					'fraud_outcome'         => Rule::FRAUD_OUTCOME_REVIEW,
					'fraud_ruleset_results' => wp_json_encode( [ 'order_items_threshold' => 'review' ] ),
				],
			]
		);

		// Act: Attempt to mark the payment held for review.
		$this->order_service->update_order_status_from_intent( $this->order, $intent );

		// Assert: Check that the note lists the fired risk filter.
		$notes = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );
		$this->assertStringContainsString( 'held for review</strong> by the following risk filters', $notes[0]->content );
		$this->assertStringContainsString( 'Place in review if the items count is not in your defined range', $notes[0]->content );

		// Assert: Check that the ruleset results were persisted on the order.
		$this->assertSame( [ 'order_items_threshold' => 'review' ], $this->order_service->get_fraud_ruleset_results_for_order( $this->order ) );

		// Assert: Confirm that the fraud outcome status and meta box type were set correctly.
		$this->assertEquals( 'review', $this->order_service->get_fraud_outcome_status_for_order( $this->order ) );
		$this->assertEquals( 'review', $this->order_service->get_fraud_meta_box_type_for_order( $this->order ) );

		// Assert: Applying the same data multiple times does not cause duplicate actions.
		$this->order_service->update_order_status_from_intent( $this->order, $intent );
		$notes_2 = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );
		$this->assertEquals( count( $notes ), count( $notes_2 ) );
	}

	/**
	 * Tests if the payment was updated to show dispute created.
	 */
	public function test_mark_payment_dispute_created() {
		// Arrange: Set the charge_id and reason, and the order status.
		$charge_id    = 'ch_123';
		$amount       = '$123.45';
		$reason       = 'product_not_received';
		$deadline     = 'June 7, 2023';
		$order_status = Order_Status::ON_HOLD;

		// Act: Attempt to mark payment dispute created.
		$this->order_service->mark_payment_dispute_created( $this->order, $charge_id, $amount, $reason, $deadline );

		// Assert: Check that the order status was updated to on-hold status.
		$this->assertTrue( $this->order->has_status( [ $order_status ] ) );

		$notes = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );

		// Assert: Check that dispute order note was added with relevant info and link to dispute detail.
		$this->assertStringContainsString( 'Payment has been disputed', $notes[0]->content );
		$this->assertStringContainsString( $amount, $notes[0]->content );
		$this->assertStringContainsString( 'Product not received', $notes[0]->content );
		$this->assertStringContainsString( $deadline, $notes[0]->content );
		$this->assertStringContainsString( '%2Fpayments%2Ftransactions%2Fdetails&id=ch_123" target="_blank" rel="noopener noreferrer">Response due by', $notes[0]->content );

		// Assert: Check that order status change note was added.
		$this->assertStringContainsString( 'Pending payment to On hold', $notes[1]->content );

		// Assert: Applying the same data multiple times does not cause duplicate actions.
		$this->order_service->mark_payment_dispute_created( $this->order, $charge_id, $amount, $reason, $deadline );
		$notes_2 = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );
		$this->assertCount( 2, $notes_2 );
	}


	/**
	 * A charge can carry several disputes with identical amount, reason and
	 * deadline. The dispute ID keeps their notes distinct so they are not
	 * collapsed into one, while a re-delivered webhook for the same dispute
	 * still de-duplicates.
	 */
	public function test_mark_payment_dispute_created_distinguishes_disputes_by_id() {
		$charge_id = 'ch_123';
		$amount    = '$123.45';
		$reason    = 'product_not_received';
		$deadline  = 'June 7, 2023';

		$dispute_notes = function () {
			$notes = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );
			return array_values(
				array_filter(
					$notes,
					function ( $note ) {
						return false !== strpos( $note->content, 'Payment has been disputed' );
					}
				)
			);
		};

		// Act: Two disputes sharing everything but their ID.
		$this->order_service->mark_payment_dispute_created( $this->order, $charge_id, $amount, $reason, $deadline, '', 'dp_first' );
		$this->order_service->mark_payment_dispute_created( $this->order, $charge_id, $amount, $reason, $deadline, '', 'dp_second' );

		// Assert: Both disputes produced a note, each carrying its own ID.
		$this->assertCount( 2, $dispute_notes() );
		$contents = implode( "\n", wp_list_pluck( $dispute_notes(), 'content' ) );
		$this->assertStringContainsString( '(Dispute ID: dp_first)', $contents );
		$this->assertStringContainsString( '(Dispute ID: dp_second)', $contents );

		// Assert: Re-delivering the same dispute does not add another note.
		$this->order_service->mark_payment_dispute_created( $this->order, $charge_id, $amount, $reason, $deadline, '', 'dp_first' );
		$this->assertCount( 2, $dispute_notes() );
	}

	/**
	 * Tests if the payment was updated to show inquiry created.
	 */
	public function test_mark_payment_dispute_created_for_inquiry() {
		// Arrange: Set the charge_id and reason, and the order status.
		$charge_id      = 'ch_123';
		$amount         = '$123.45';
		$reason         = 'product_not_received';
		$deadline       = 'June 7, 2023';
		$order_status   = Order_Status::ON_HOLD;
		$dispute_status = 'warning_needs_response';

		// Act: Attempt to mark payment dispute created.
		$this->order_service->mark_payment_dispute_created( $this->order, $charge_id, $amount, $reason, $deadline, $dispute_status );

		// Assert: Check that the order status was updated to on-hold status.
		$this->assertTrue( $this->order->has_status( [ $order_status ] ) );

		$notes = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );

		// Assert: Check that dispute order note was added with relevant info and link to dispute detail.
		$this->assertStringNotContainsString( 'Payment has been disputed', $notes[0]->content );
		$this->assertStringContainsString( 'inquiry', $notes[0]->content );
		$this->assertStringContainsString( $amount, $notes[0]->content );
		$this->assertStringContainsString( 'Product not received', $notes[0]->content );
		$this->assertStringContainsString( $deadline, $notes[0]->content );
		$this->assertStringContainsString( '%2Fpayments%2Ftransactions%2Fdetails&id=ch_123" target="_blank" rel="noopener noreferrer">Response due by', $notes[0]->content );

		// Assert: Check that order status change note was added.
		$this->assertStringContainsString( 'Pending payment to On hold', $notes[1]->content );

		// Assert: Applying the same data multiple times does not cause duplicate actions.
		$this->order_service->mark_payment_dispute_created( $this->order, $charge_id, $amount, $reason, $deadline, $dispute_status );
		$notes_2 = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );
		$this->assertCount( 2, $notes_2 );
	}

	/**
	 * Tests to make sure mark_payment_dispute_created exits if the order is invalid.
	 */
	public function test_mark_payment_dispute_created_exits_if_order_invalid() {
		// Arrange: Set the charge_id and reason, and the order status.
		$charge_id = 'ch_123';
		$amount    = '$123.45';
		$reason    = 'product_not_received';
		$deadline  = 'June 7, 2023';

		$order_status   = $this->order->get_status();
		$expected_notes = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );

		// Act: Attempt to mark payment dispute created.
		$this->order_service->mark_payment_dispute_created( 'fake_order', $charge_id, $amount, $reason, $deadline );

		// Assert: Check that the order status was not updated.
		$this->assertTrue( $this->order->has_status( [ $order_status ] ) );

		// Assert: Confirm the notes were not updated.
		$updated_notes = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );
		$this->assertEquals( $expected_notes, $updated_notes );
	}

	/**
	 * Tests if the payment was updated to show dispute closed with a win.
	 */
	public function test_mark_payment_dispute_closed_with_status_won() {
		// Arrange: Set the charge_id and status, and the order status.
		$charge_id    = 'ch_123';
		$status       = 'won';
		$order_status = Order_Status::COMPLETED;

		// Act: Attempt to mark payment dispute created.
		$this->order_service->mark_payment_dispute_closed( $this->order, $charge_id, $status );

		// Assert: Check that the order status was updated to completed status.
		$this->assertTrue( $this->order->has_status( [ $order_status ] ) );

		// Assert: Check that the notes were updated.
		$notes = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );
		$this->assertStringContainsString( 'Pending payment to Completed', $notes[1]->content );
		$this->assertStringContainsString( 'Dispute has been closed with status won', $notes[0]->content );
		$this->assertStringContainsString( '%2Fpayments%2Ftransactions%2Fdetails&id=ch_123" target="_blank" rel="noopener noreferrer">dispute overview', $notes[0]->content );

		// Assert: Applying the same data multiple times does not cause duplicate actions.
		$this->order_service->mark_payment_dispute_closed( $this->order, $charge_id, $status );
		$notes_2 = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );
		$this->assertCount( 2, $notes_2 );
	}

	/**
	 * Tests if the payment was updated to show dispute closed with a loss and a refund.
	 */
	public function test_mark_payment_dispute_closed_with_status_lost() {
		// Arrange: Set the charge_id, dispute status, the order status, and update the order status.
		$charge_id    = 'ch_123';
		$status       = 'lost';
		$order_status = Order_Status::ON_HOLD;
		$this->order->update_status( $order_status ); // When a dispute is created, the order status is changed to On Hold.

		// Act: Attempt to mark payment dispute created.
		$this->order_service->mark_payment_dispute_closed( $this->order, $charge_id, $status );

		// Assert: Check that the order status was left in on-hold status.
		$this->assertTrue( $this->order->has_status( [ $order_status ] ) );

		// Assert: Check that the notes were updated.
		$notes = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );
		$this->assertStringContainsString( 'On hold to Refunded', $notes[1]->content );
		$this->assertStringContainsString( 'Dispute has been closed with status lost', $notes[0]->content );
		$this->assertStringContainsString( '%2Fpayments%2Ftransactions%2Fdetails&id=ch_123" target="_blank" rel="noopener noreferrer">dispute overview', $notes[0]->content );

		// Assert: Check for created refund, and the amount is correct.
		$refunds = $this->order->get_refunds();
		$this->assertCount( 1, $refunds );
		$this->assertEquals( '-' . $this->order->get_total(), $refunds[0]->get_total() );

		// Assert: Applying the same data multiple times does not cause duplicate actions.
		$this->order_service->mark_payment_dispute_closed( $this->order, $charge_id, $status );
		$notes_2 = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );
		$this->assertCount( 3, $notes_2 );
	}


	/**
	 * Tests if the order note was added to show inquiry closed.
	 */
	public function test_mark_payment_dispute_closed_with_status_warning_closed() {
		// Arrange: Set the charge_id, dispute status, the order status, and update the order status.
		$charge_id    = 'ch_123';
		$status       = 'warning_closed';
		$order_status = Order_Status::COMPLETED;
		$this->order->update_status( Order_Status::ON_HOLD ); // When a dispute is created, the order status is changed to On Hold.

		// Act: Attempt to mark payment dispute created.
		$this->order_service->mark_payment_dispute_closed( $this->order, $charge_id, $status );

		// Assert: Check that the order status was left in on-hold status.
		$this->assertTrue( $this->order->has_status( [ $order_status ] ) );

		// Assert: Check that the notes were updated.
		$notes = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );
		$this->assertStringNotContainsString( 'Dispute has been closed with status won', $notes[0]->content );
		$this->assertStringContainsString( 'inquiry', $notes[0]->content );
		$this->assertStringContainsString( '%2Fpayments%2Ftransactions%2Fdetails&id=ch_123" target="_blank" rel="noopener noreferrer">payment status', $notes[0]->content );

		// Assert: Applying the same data multiple times does not cause duplicate actions.
		$this->order_service->mark_payment_dispute_closed( $this->order, $charge_id, $status );
		$notes_2 = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );
		$this->assertCount( 3, $notes_2 );
	}

	/**
	 * Tests that a non-lost close leaves an already fully refunded order refunded instead of
	 * promoting it back to completed. The motivating case is a charge whose sibling dispute was
	 * lost and refunded in full first.
	 */
	public function test_mark_payment_dispute_closed_with_status_won_leaves_fully_refunded_order_unchanged() {
		// Arrange: Put the order on hold as a dispute would, then refund it in full, which WooCommerce core moves to refunded.
		$charge_id = 'ch_123';
		$status    = 'won';
		$this->order->update_status( Order_Status::ON_HOLD );
		wc_create_refund(
			[
				'amount'   => $this->order->get_total(),
				'order_id' => $this->order->get_id(),
			]
		);
		$order = wc_get_order( $this->order->get_id() );

		// Act: Attempt to mark payment dispute closed.
		$this->order_service->mark_payment_dispute_closed( $order, $charge_id, $status );

		// Assert: Check that the order was left in refunded status.
		$this->assertTrue( $order->has_status( [ 'refunded' ] ) );

		// Assert: Check that both the skipped completion note and the dispute closed note were added.
		$notes    = wc_get_order_notes( [ 'order_id' => $order->get_id() ] );
		$contents = $this->order_note_contents();
		$this->assertStringContainsString( 'Dispute has been closed with status won', $contents );
		$this->assertStringContainsString( 'The order was not marked as completed because it has already been fully refunded.', $contents );
		$this->assertStringContainsString( 'On hold to Refunded', $contents );
		$this->assertCount( 4, $notes );

		// Assert: Applying the same data multiple times does not cause duplicate actions.
		$this->order_service->mark_payment_dispute_closed( $order, $charge_id, $status );
		$notes_2 = wc_get_order_notes( [ 'order_id' => $order->get_id() ] );
		$this->assertCount( 4, $notes_2 );
	}

	/**
	 * Tests that an inquiry closing on an already fully refunded order leaves the order refunded.
	 */
	public function test_mark_payment_dispute_closed_with_status_warning_closed_leaves_fully_refunded_order_unchanged() {
		// Arrange: Put the order on hold as an inquiry would, then refund it in full.
		$charge_id = 'ch_123';
		$status    = 'warning_closed';
		$this->order->update_status( Order_Status::ON_HOLD );
		wc_create_refund(
			[
				'amount'   => $this->order->get_total(),
				'order_id' => $this->order->get_id(),
			]
		);
		$order = wc_get_order( $this->order->get_id() );

		// Act: Attempt to mark payment dispute closed.
		$this->order_service->mark_payment_dispute_closed( $order, $charge_id, $status );

		// Assert: Check that the order was left in refunded status.
		$this->assertTrue( $order->has_status( [ 'refunded' ] ) );

		// Assert: Check that both the skipped completion note and the inquiry closed note were added.
		$notes    = wc_get_order_notes( [ 'order_id' => $order->get_id() ] );
		$contents = $this->order_note_contents();
		$this->assertStringContainsString( 'inquiry', $contents );
		$this->assertStringContainsString( 'The order was not marked as completed because it has already been fully refunded.', $contents );
		$this->assertCount( 4, $notes );
	}

	/**
	 * Tests the webhook ordering where the refund lands before the dispute: the order is fully
	 * refunded, a dispute then puts it back on hold, and the close has to resolve that hold
	 * rather than leaving the order stranded on hold with no further webhook to move it.
	 */
	public function test_mark_payment_dispute_closed_with_status_won_refunds_fully_refunded_order_on_hold() {
		// Arrange: Refund the order in full, then put it back on hold as a later dispute would.
		$charge_id = 'ch_123';
		$status    = 'won';
		wc_create_refund(
			[
				'amount'   => $this->order->get_total(),
				'order_id' => $this->order->get_id(),
			]
		);
		$order = wc_get_order( $this->order->get_id() );
		$order->update_status( Order_Status::ON_HOLD );

		// Act: Attempt to mark payment dispute closed.
		$this->order_service->mark_payment_dispute_closed( $order, $charge_id, $status );

		// Assert: Check that the order was moved back to refunded rather than left on hold.
		$this->assertTrue( $order->has_status( [ 'refunded' ] ) );

		// Assert: Check that both the skipped completion note and the dispute closed note were added.
		$contents = $this->order_note_contents();
		$this->assertStringContainsString( 'Dispute has been closed with status won', $contents );
		$this->assertStringContainsString( 'The order was not marked as completed because it has already been fully refunded.', $contents );
		$this->assertStringContainsString( 'On hold to Refunded', $contents );
	}

	/**
	 * Tests that an inquiry closing on a fully refunded order that is on hold also resolves the hold.
	 */
	public function test_mark_payment_dispute_closed_with_status_warning_closed_refunds_fully_refunded_order_on_hold() {
		// Arrange: Refund the order in full, then put it back on hold as a later inquiry would.
		$charge_id = 'ch_123';
		$status    = 'warning_closed';
		wc_create_refund(
			[
				'amount'   => $this->order->get_total(),
				'order_id' => $this->order->get_id(),
			]
		);
		$order = wc_get_order( $this->order->get_id() );
		$order->update_status( Order_Status::ON_HOLD );

		// Act: Attempt to mark payment dispute closed.
		$this->order_service->mark_payment_dispute_closed( $order, $charge_id, $status );

		// Assert: Check that the order was moved back to refunded rather than left on hold.
		$this->assertTrue( $order->has_status( [ 'refunded' ] ) );

		// Assert: Check that both the skipped completion note and the inquiry closed note were added.
		$contents = $this->order_note_contents();
		$this->assertStringContainsString( 'inquiry', $contents );
		$this->assertStringContainsString( 'The order was not marked as completed because it has already been fully refunded.', $contents );
	}

	/**
	 * Tests that the guard is deliberately limited to full refunds: a partially refunded order
	 * still moves to completed, exactly as it did before.
	 */
	public function test_mark_payment_dispute_closed_with_status_won_completes_partially_refunded_order() {
		// Arrange: Put the order on hold as a dispute would, then refund half of it.
		$charge_id = 'ch_123';
		$status    = 'won';
		$this->order->update_status( Order_Status::ON_HOLD );
		wc_create_refund(
			[
				'amount'   => (float) $this->order->get_total() / 2,
				'order_id' => $this->order->get_id(),
			]
		);
		$order = wc_get_order( $this->order->get_id() );

		// Act: Attempt to mark payment dispute closed.
		$this->order_service->mark_payment_dispute_closed( $order, $charge_id, $status );

		// Assert: Check that the order status was updated to completed status.
		$this->assertTrue( $order->has_status( [ 'completed' ] ) );

		// Assert: Check that the notes were updated, and no skip note was added.
		$notes    = wc_get_order_notes( [ 'order_id' => $order->get_id() ] );
		$contents = $this->order_note_contents();
		$this->assertStringContainsString( 'Dispute has been closed with status won', $contents );
		$this->assertStringContainsString( 'On hold to Completed', $contents );
		$this->assertCount( 3, $notes );
	}

	/**
	 * Tests that a zero total order, whose remaining refund amount is trivially zero, is not
	 * mistaken for a fully refunded one.
	 */
	public function test_mark_payment_dispute_closed_with_status_won_completes_zero_total_order() {
		// Arrange: Create a zero total order and put it on hold as a dispute would.
		$charge_id = 'ch_123';
		$status    = 'won';
		$order     = WC_Helper_Order::create_order( 1, 0 );
		$order->update_status( Order_Status::ON_HOLD );

		// Act: Attempt to mark payment dispute closed.
		$this->order_service->mark_payment_dispute_closed( $order, $charge_id, $status );

		// Assert: Check that the order status was updated to completed status.
		$this->assertTrue( $order->has_status( [ 'completed' ] ) );

		// Assert: Check that the notes were updated, and no skip note was added.
		$notes    = wc_get_order_notes( [ 'order_id' => $order->get_id() ] );
		$contents = $this->order_note_contents( $order );
		$this->assertStringContainsString( 'Dispute has been closed with status won', $contents );
		$this->assertStringContainsString( 'On hold to Completed', $contents );
		$this->assertCount( 3, $notes );
	}

	/**
	 * Tests that the zero total clamp does not override an explicitly refunded status: a zero total
	 * order already marked refunded must not be promoted to completed.
	 */
	public function test_mark_payment_dispute_closed_with_status_won_leaves_refunded_zero_total_order_unchanged() {
		// Arrange: Create a zero total order that has already been marked refunded.
		$charge_id = 'ch_123';
		$status    = 'won';
		$order     = WC_Helper_Order::create_order( 1, 0 );
		$order->update_status( Order_Status::REFUNDED );

		// Act: Attempt to mark payment dispute closed.
		$this->order_service->mark_payment_dispute_closed( $order, $charge_id, $status );

		// Assert: Check that the order was left in refunded status.
		$this->assertTrue( $order->has_status( [ 'refunded' ] ) );

		// Assert: Check that both the skipped completion note and the dispute closed note were added.
		$contents = $this->order_note_contents( $order );
		$this->assertStringContainsString( 'Dispute has been closed with status won', $contents );
		$this->assertStringContainsString( 'The order was not marked as completed because it has already been fully refunded.', $contents );
	}

	/**
	 * Tests that closing one of a charge's disputes does not lift the hold that a sibling
	 * dispute still needs. The motivating case is an AmEx or Klarna charge disputed once per
	 * separately shipped item: winning the first must not present the order as settled while
	 * the second is still counting down its evidence deadline.
	 */
	public function test_mark_payment_dispute_closed_leaves_order_on_hold_while_sibling_dispute_open() {
		// Arrange: Two disputes on the same charge, both open, so the order sits on hold.
		$charge_id = 'ch_123';
		$this->create_dispute( $charge_id, 'dp_first' );
		$this->create_dispute( $charge_id, 'dp_second' );

		// Act: Close only the first dispute.
		$this->order_service->mark_payment_dispute_closed( $this->order, $charge_id, 'won', [], 'dp_first' );

		// Assert: Check that the order was left on hold for the dispute that is still open.
		$this->assertTrue( $this->order->has_status( [ 'on-hold' ] ) );

		// Assert: Check that the close was recorded and the reason for staying on hold explained.
		$contents = $this->order_note_contents();
		$this->assertStringContainsString( 'Dispute has been closed with status won', $contents );
		$this->assertStringContainsString( '(Dispute ID: dp_first)', $contents );
		$this->assertStringContainsString( 'The order was not marked as completed because 1 other dispute on this payment is still open.', $contents );
		$this->assertStringNotContainsString( 'On hold to Completed', $contents );
	}

	/**
	 * Tests that the hold is lifted once the charge's last open dispute closes, so the guard
	 * against a sibling dispute does not strand the order on hold.
	 */
	public function test_mark_payment_dispute_closed_completes_order_once_last_dispute_closes() {
		// Arrange: Two disputes on the same charge, the first of which has already closed.
		$charge_id = 'ch_123';
		$this->create_dispute( $charge_id, 'dp_first' );
		$this->create_dispute( $charge_id, 'dp_second' );
		$this->order_service->mark_payment_dispute_closed( $this->order, $charge_id, 'won', [], 'dp_first' );

		// Act: Close the remaining dispute.
		$this->order_service->mark_payment_dispute_closed( $this->order, $charge_id, 'won', [], 'dp_second' );

		// Assert: Check that the order status was updated to completed status.
		$this->assertTrue( $this->order->has_status( [ 'completed' ] ) );

		// Assert: Check that each dispute produced its own close note rather than being de-duplicated.
		$contents = $this->order_note_contents();
		$this->assertStringContainsString( '(Dispute ID: dp_first)', $contents );
		$this->assertStringContainsString( '(Dispute ID: dp_second)', $contents );
		$this->assertStringContainsString( 'On hold to Completed', $contents );
	}

	/**
	 * Tests the combined failure: the first dispute is lost and refunds the order in full, then
	 * the sibling is won. The order must stay refunded rather than being promoted to completed,
	 * which would have WooCommerce Analytics count the payment as revenue a second time.
	 */
	public function test_mark_payment_dispute_closed_leaves_order_refunded_after_sibling_dispute_lost() {
		// Arrange: Two disputes on the same charge, the first of which was lost and refunded in full.
		$charge_id = 'ch_123';
		$this->create_dispute( $charge_id, 'dp_first' );
		$this->create_dispute( $charge_id, 'dp_second' );
		$this->order_service->mark_payment_dispute_closed( $this->order, $charge_id, 'lost', [], 'dp_first' );
		$order = wc_get_order( $this->order->get_id() );

		// Act: Close the surviving dispute in the merchant's favour.
		$this->order_service->mark_payment_dispute_closed( $order, $charge_id, 'won', [], 'dp_second' );

		// Assert: Check that the order was left in refunded status.
		$this->assertTrue( $order->has_status( [ 'refunded' ] ) );

		// Assert: Check that the refund from the lost dispute was not joined by a second one.
		$this->assertCount( 1, $order->get_refunds() );

		// Assert: Check that the reason for skipping completion was recorded.
		$contents = $this->order_note_contents();
		$this->assertStringContainsString( 'The order was not marked as completed because it has already been fully refunded.', $contents );
		$this->assertStringNotContainsString( 'On hold to Completed', $contents );
	}

	/**
	 * Tests that a charge carrying more than two disputes reports every one that is still open.
	 */
	public function test_mark_payment_dispute_closed_counts_every_open_sibling_dispute() {
		// Arrange: Three disputes on the same charge, all open.
		$charge_id = 'ch_123';
		$this->create_dispute( $charge_id, 'dp_first' );
		$this->create_dispute( $charge_id, 'dp_second' );
		$this->create_dispute( $charge_id, 'dp_third' );

		// Act: Close one of them.
		$this->order_service->mark_payment_dispute_closed( $this->order, $charge_id, 'won', [], 'dp_first' );

		// Assert: Check that the note counted both remaining disputes.
		$this->assertStringContainsString(
			'The order was not marked as completed because 2 other disputes on this payment are still open.',
			$this->order_note_contents()
		);
	}

	/**
	 * Tests that an inquiry closing while a dispute is still open also leaves the hold in place.
	 */
	public function test_mark_payment_dispute_closed_with_status_warning_closed_leaves_order_on_hold_while_sibling_open() {
		// Arrange: An inquiry and a dispute on the same charge, both open.
		$charge_id = 'ch_123';
		$this->create_dispute( $charge_id, 'dp_inquiry', 'warning_needs_response' );
		$this->create_dispute( $charge_id, 'dp_dispute' );

		// Act: Close the inquiry.
		$this->order_service->mark_payment_dispute_closed( $this->order, $charge_id, 'warning_closed', [], 'dp_inquiry' );

		// Assert: Check that the order was left on hold for the dispute that is still open.
		$this->assertTrue( $this->order->has_status( [ 'on-hold' ] ) );

		// Assert: Check that the inquiry close was recorded and the hold explained.
		$contents = $this->order_note_contents();
		$this->assertStringContainsString( 'inquiry', $contents );
		$this->assertStringContainsString( '(Dispute ID: dp_inquiry)', $contents );
		$this->assertStringContainsString( 'The order was not marked as completed because 1 other dispute on this payment is still open.', $contents );
	}

	/**
	 * Tests that a re-delivered close webhook is still de-duplicated now that the note carries a
	 * dispute ID, and that the replay does not resurrect the dispute as open.
	 */
	public function test_mark_payment_dispute_closed_is_idempotent_per_dispute_id() {
		// Arrange: Two disputes on the same charge, both open.
		$charge_id = 'ch_123';
		$this->create_dispute( $charge_id, 'dp_first' );
		$this->create_dispute( $charge_id, 'dp_second' );
		$this->order_service->mark_payment_dispute_closed( $this->order, $charge_id, 'won', [], 'dp_first' );

		// Assert: The status change, the two dispute notes, the sibling note and the close note.
		$this->assertCount( 5, wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] ) );

		// Act: Replay the same close.
		$this->order_service->mark_payment_dispute_closed( $this->order, $charge_id, 'won', [], 'dp_first' );

		// Assert: Check that the replay added no notes and left the order on hold.
		$this->assertCount( 5, wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] ) );
		$this->assertTrue( $this->order->has_status( [ 'on-hold' ] ) );

		// Assert: Check that closing the sibling still completes the order.
		$this->order_service->mark_payment_dispute_closed( $this->order, $charge_id, 'won', [], 'dp_second' );
		$this->assertTrue( $this->order->has_status( [ 'completed' ] ) );
	}

	/**
	 * Tests that an order whose dispute was recorded before this bookkeeping existed keeps the
	 * behaviour it had: with no open disputes on record, a win completes the order.
	 */
	public function test_mark_payment_dispute_closed_completes_order_without_recorded_disputes() {
		// Arrange: Put the order on hold as a dispute would, without recording the dispute ID.
		$charge_id = 'ch_123';
		$this->order->update_status( Order_Status::ON_HOLD );

		// Act: Close a dispute the order has no record of.
		$this->order_service->mark_payment_dispute_closed( $this->order, $charge_id, 'won', [], 'dp_unknown' );

		// Assert: Check that the order status was updated to completed status.
		$this->assertTrue( $this->order->has_status( [ 'completed' ] ) );

		// Assert: Check that no open sibling was claimed.
		$this->assertStringNotContainsString( 'still open', $this->order_note_contents() );
	}

	/**
	 * Tests to make sure mark_payment_dispute_closed exits if the order is invalid.
	 */
	public function test_mark_payment_dispute_closed_exits_if_order_invalid() {
		// Arrange: Set the charge_id and reason, and the order status.
		$charge_id      = 'ch_123';
		$status         = 'won';
		$order_status   = $this->order->get_status();
		$expected_notes = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );

		// Act: Attempt to mark payment dispute created.
		$this->order_service->mark_payment_dispute_closed( 'fake_order', $charge_id, $status );

		// Assert: Check that the order status was not updated.
		$this->assertTrue( $this->order->has_status( [ $order_status ] ) );

		// Assert: Confirm the notes were not updated.
		$updated_notes = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );
		$this->assertEquals( $expected_notes, $updated_notes );
	}

	/**
	 * Tests that an actionable early fraud warning stores meta and adds a note once.
	 */
	public function test_mark_payment_early_fraud_warning_actionable() {
		// Act: Mark the early fraud warning on the order.
		$this->order_service->mark_payment_early_fraud_warning( $this->order, 'ch_123', 'issfr_123', true, 'made_with_stolen_card', 1719800000 );

		// Assert: Check that the early fraud warning meta was persisted (read back from the database).
		$this->assertSame(
			[
				'efw_id'     => 'issfr_123',
				'actionable' => true,
				'fraud_type' => 'made_with_stolen_card',
				'created'    => 1719800000,
			],
			wc_get_order( $this->order->get_id() )->get_meta( '_wcpay_early_fraud_warning', true )
		);

		// Assert: Check that the note was added with the reason and a link to the payment details.
		$notes = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );
		$this->assertCount( 1, $notes );
		$this->assertStringContainsString( 'Payment has received an early fraud warning with reason', $notes[0]->content );
		$this->assertStringContainsString( 'Made with stolen card', $notes[0]->content );
		$this->assertStringContainsString( '%2Fpayments%2Ftransactions%2Fdetails&id=ch_123" class="wcpay-efw-refund-link" target="_blank" rel="noopener noreferrer">Refunding the payment now</a> can prevent a dispute', $notes[0]->content );
		$this->assertStringContainsString( '%2Fpayments%2Ftransactions%2Fdetails&id=ch_123" target="_blank" rel="noopener noreferrer">payment details', $notes[0]->content );

		// Assert: Applying the same data multiple times does not cause duplicate notes.
		$this->order_service->mark_payment_early_fraud_warning( $this->order, 'ch_123', 'issfr_123', true, 'made_with_stolen_card', 1719800000 );
		$notes_2 = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );
		$this->assertCount( 1, $notes_2 );
	}

	/**
	 * Tests that a resolved early fraud warning overwrites the meta and adds a resolved note.
	 */
	public function test_mark_payment_early_fraud_warning_resolved() {
		// Arrange: Store an actionable early fraud warning first.
		$this->order_service->mark_payment_early_fraud_warning( $this->order, 'ch_123', 'issfr_123', true, 'made_with_stolen_card', 1719800000 );

		// Act: Mark the same early fraud warning as no longer actionable.
		$this->order_service->mark_payment_early_fraud_warning( $this->order, 'ch_123', 'issfr_123', false, 'made_with_stolen_card', 1719800000 );

		// Assert: Check that the persisted early fraud warning meta was overwritten with the latest state.
		$this->assertSame(
			[
				'efw_id'     => 'issfr_123',
				'actionable' => false,
				'fraud_type' => 'made_with_stolen_card',
				'created'    => 1719800000,
			],
			wc_get_order( $this->order->get_id() )->get_meta( '_wcpay_early_fraud_warning', true )
		);

		// Assert: Check that a resolved note was added on top of the actionable one.
		$notes = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );
		$this->assertCount( 2, $notes );
		$this->assertStringContainsString( 'The early fraud warning received for this payment is no longer actionable', $notes[0]->content );
		$this->assertStringContainsString( 'Payment has received an early fraud warning', $notes[1]->content );
	}

	/**
	 * Tests that an unknown fraud type produces a note without a reason.
	 */
	public function test_mark_payment_early_fraud_warning_with_unknown_fraud_type() {
		// Act: Mark an early fraud warning with a fraud type we have no label for.
		$this->order_service->mark_payment_early_fraud_warning( $this->order, 'ch_123', 'issfr_123', true, 'some_future_fraud_type', 1719800000 );

		// Assert: Check that the note was added without the reason clause.
		$notes = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );
		$this->assertStringContainsString( 'Payment has received an early fraud warning. <a', $notes[0]->content );
		$this->assertStringContainsString( '>Refunding the payment now</a> can prevent a dispute', $notes[0]->content );
		$this->assertStringNotContainsString( 'with reason', $notes[0]->content );
	}

	/**
	 * Tests to make sure mark_payment_early_fraud_warning exits if the order is invalid.
	 */
	public function test_mark_payment_early_fraud_warning_exits_if_order_invalid() {
		// Arrange: Get current notes.
		$expected_notes = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );

		// Act: Attempt to mark the early fraud warning on an invalid order.
		$this->order_service->mark_payment_early_fraud_warning( 'fake_order', 'ch_123', 'issfr_123', true, 'made_with_stolen_card', 1719800000 );

		// Assert: Confirm no meta was stored and the notes were not updated.
		$this->assertSame( '', $this->order->get_meta( '_wcpay_early_fraud_warning', true ) );
		$updated_notes = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );
		$this->assertEquals( $expected_notes, $updated_notes );
	}

	/**
	 * Tests if the order was completed successfully.
	 */
	public function test_mark_terminal_payment_completed() {
		// Arrange: Create the intent and set the order status.
		$intent       = WC_Helper_Intention::create_intention( [ 'status' => Intent_Status::SUCCEEDED ] );
		$order_status = Order_Status::COMPLETED;

		// Act: Attempt to mark the payment/order complete.
		$this->order_service->mark_terminal_payment_completed( $this->order, $intent->get_id(), $intent->get_status() );

		// Assert: Check to make sure the intent_status meta was set.
		$this->assertEquals( $intent->get_status(), $this->order_service->get_intention_status_for_order( $this->order ) );

		// Assert: Check the proper fraud meta box was set.
		$this->assertEquals( Fraud_Meta_Box_Type::TERMINAL_PAYMENT, $this->order_service->get_fraud_meta_box_type_for_order( $this->order ) );

		// Assert: Check that the order status was updated to completed status.
		$this->assertTrue( $this->order->has_status( [ $order_status ] ) );

		// Assert: Check that the notes were updated.
		$notes = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );
		$this->assertStringContainsString( 'Pending payment to Completed', $notes[0]->content );

		// Assert: Check that the order was unlocked.
		$this->assertFalse( get_transient( 'wcpay_processing_intent_' . $this->order->get_id() ) );
	}

	/**
	 * Tests if the order status is set to processing by a filter
	 */
	public function test_mark_terminal_payment_order_completed_status() {
		// Create the intent.
		$intent = WC_Helper_Intention::create_intention( [ 'status' => Intent_Status::SUCCEEDED ] );

		// Filter the order status to processing.
		add_filter(
			'wcpay_terminal_payment_completed_order_status',
			function () {
				return Order_Status::PROCESSING;
			}
		);

		// Attempt to mark the payment/order processing.
		$this->order_service->mark_terminal_payment_completed( $this->order, $intent->get_id(), $intent->get_status() );

		// Assert: Check that the order status was updated to processing status.
		$this->assertTrue( $this->order->has_status( [ Order_Status::PROCESSING ] ) );

		remove_all_filters( 'wcpay_terminal_payment_completed_order_status' );
	}

	public function test_mark_terminal_payment_failed_triggers_status_transition_on_first_failure() {
		// Arrange: Create the intent and ensure order is in pending status.
		$intent = WC_Helper_Intention::create_intention( [ 'status' => Intent_Status::REQUIRES_PAYMENT_METHOD ] );
		$this->order->set_status( Order_Status::PENDING );
		$this->order->save();

		$action_count_before = did_action( 'woocommerce_order_status_pending_to_failed' );

		// Act: Mark the terminal payment as failed.
		$this->order_service->mark_terminal_payment_failed( $this->order, $intent->get_id(), $intent->get_status(), 'ch_test123', 'Card declined' );

		// Assert: WC core fires the status transition hook (pending → failed), which
		// triggers notifications via WC_Emails when the email system is initialized.
		$this->assertGreaterThan( $action_count_before, did_action( 'woocommerce_order_status_pending_to_failed' ), 'Status transition hook should fire on first failure.' );
	}

	public function test_mark_terminal_payment_failed_fires_notification_manually_on_repeated_failure() {
		// Arrange: Create the intent and set order to already failed.
		$intent = WC_Helper_Intention::create_intention( [ 'status' => Intent_Status::REQUIRES_PAYMENT_METHOD ] );
		$this->order->set_status( Order_Status::FAILED );
		$this->order->save();

		$action_count_before = did_action( 'woocommerce_order_status_failed_notification' );

		// Act: Mark the terminal payment as failed again.
		$this->order_service->mark_terminal_payment_failed( $this->order, $intent->get_id(), $intent->get_status(), 'ch_test456', 'Card declined' );

		// Assert: WC core won't fire hooks (status didn't change), so our code manually triggers the notification.
		$this->assertGreaterThan( $action_count_before, did_action( 'woocommerce_order_status_failed_notification' ), 'Notification should fire manually when order was already failed.' );
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

	public function test_set_intent_id_for_order() {
		$intent_id = 'pi_mock_123';
		$this->order_service->set_intent_id_for_order( $this->order, $intent_id );
		$this->assertEquals( $this->order->get_meta( '_intent_id', true ), $intent_id );
		$this->assertSame( 1, did_action( 'wcpay_order_intent_id_updated' ) );
		$this->assertSame( 0, did_action( 'wcpay_order_payment_method_id_updated' ) );
	}

	public function test_get_intent_id_for_order() {
		$intent_id = 'pi_mock';
		$this->order->update_meta_data( '_intent_id', $intent_id );
		$this->order->save_meta_data();
		$intent_id_from_service = $this->order_service->get_intent_id_for_order( $this->order->get_id() );
		$this->assertEquals( $intent_id_from_service, $intent_id );
	}

	public function test_get_payment_method_id() {
		$payment_method_id = 'pm_mock_123';
		$this->order->update_meta_data( '_payment_method_id', $payment_method_id );
		$this->order->save_meta_data();
		$payment_method_from_service = $this->order_service->get_payment_method_id_for_order( $this->order->get_id() );
		$this->assertEquals( $payment_method_from_service, $payment_method_id );
	}

	public function test_set_charge_id() {
		$charge_id = 'ch_mock';
		$this->order_service->set_charge_id_for_order( $this->order, $charge_id );
		$this->assertEquals( $this->order->get_meta( '_charge_id', true ), $charge_id );
	}

	public function test_get_charge_id() {
		$charge_id = 'ch_mock';
		$this->order->update_meta_data( '_charge_id', $charge_id );
		$this->order->save_meta_data();
		$charge_id_from_service = $this->order_service->get_charge_id_for_order( $this->order->get_id() );
		$this->assertEquals( $charge_id_from_service, $charge_id );
	}

	public function test_set_intention_status() {
		$intention_status = 'mock_status';
		$this->order_service->set_intention_status_for_order( $this->order, $intention_status );
		$this->assertEquals( $this->order->get_meta( '_intention_status', true ), $intention_status );
	}

	public function test_get_intention_status() {
		$intention_status = 'succeeded';
		$this->order->update_meta_data( '_intention_status', $intention_status );
		$this->order->save_meta_data();
		$intention_status_from_service = $this->order_service->get_intention_status_for_order( $this->order->get_id() );
		$this->assertEquals( $intention_status_from_service, $intention_status );
	}

	public function test_set_customer_id() {
		$customer_id = 'cus_123';
		$this->order_service->set_customer_id_for_order( $this->order, $customer_id );
		$this->assertEquals( $this->order->get_meta( '_stripe_customer_id', true ), $customer_id );
	}

	public function test_get_customer_id() {
		$customer_id = 'cus_mock';
		$this->order->update_meta_data( '_stripe_customer_id', $customer_id );
		$this->order->save_meta_data();
		$customer_id_from_service = $this->order_service->get_customer_id_for_order( $this->order->get_id() );
		$this->assertEquals( $customer_id_from_service, $customer_id );
	}

	public function test_set_wcpay_intent_currency() {
		$wcpay_intent_currency = 'mock_curr';
		$this->order_service->set_wcpay_intent_currency_for_order( $this->order, $wcpay_intent_currency );
		$this->assertEquals( $this->order->get_meta( '_wcpay_intent_currency', true ), $wcpay_intent_currency );
	}

	public function test_get_wcpay_intent_currency() {
		$wcpay_intent_currency = 'EUR';
		$this->order->update_meta_data( '_wcpay_intent_currency', $wcpay_intent_currency );
		$this->order->save_meta_data();
		$wcpay_intent_currency_from_service = $this->order_service->get_wcpay_intent_currency_for_order( $this->order->get_id() );
		$this->assertEquals( $wcpay_intent_currency_from_service, $wcpay_intent_currency );
	}

	public function test_set_wcpay_refund_id() {
		$wcpay_refund_id = 'ri_mock';
		$this->order_service->set_wcpay_refund_id_for_refund( $this->order, $wcpay_refund_id );
		$this->assertEquals( $this->order->get_meta( '_wcpay_refund_id', true ), $wcpay_refund_id );
	}

	public function set_wcpay_refund_transaction_id_for_order() {
		$wcpay_refund_transaction_id = 'txn_mock';
		$this->order_service->set_wcpay_refund_transaction_id_for_order( $this->order, $wcpay_refund_transaction_id );
		$this->assertSame( $this->order->get_meta( WC_Payments_Order_Service::WCPAY_REFUND_TRANSACTION_ID_META_KEY, true ), $wcpay_refund_transaction_id );
	}

	public function test_get_wcpay_refund_id() {
		$wcpay_refund_id = 'ri_1234';
		$this->order->update_meta_data( '_wcpay_refund_id', $wcpay_refund_id );
		$this->order->save_meta_data();
		$wcpay_refund_id_from_service = $this->order_service->get_wcpay_refund_id_for_order( $this->order->get_id() );
		$this->assertEquals( $wcpay_refund_id_from_service, $wcpay_refund_id );
	}

	public function test_set_wcpay_refund_status() {
		$wcpay_refund_status = 'failed';
		$this->order_service->set_wcpay_refund_status_for_order( $this->order, $wcpay_refund_status );
		$this->assertEquals( $this->order->get_meta( '_wcpay_refund_status', true ), $wcpay_refund_status );
	}

	public function test_get_wcpay_refund_status() {
		$wcpay_refund_status = 'mock_status';
		$this->order->update_meta_data( '_wcpay_refund_status', $wcpay_refund_status );
		$this->order->save_meta_data();
		$wcpay_refund_status_from_service = $this->order_service->get_wcpay_refund_status_for_order( $this->order->get_id() );
		$this->assertEquals( $wcpay_refund_status_from_service, $wcpay_refund_status );
	}

	public function test_set_fraud_outcome_status() {
		$fraud_outcome_status = Rule::FRAUD_OUTCOME_ALLOW;
		$this->order_service->set_fraud_outcome_status_for_order( $this->order, $fraud_outcome_status );
		$this->assertEquals( $this->order->get_meta( '_wcpay_fraud_outcome_status', true ), $fraud_outcome_status );
	}

	public function test_get_fraud_outcome_status() {
		$fraud_outcome_status = Rule::FRAUD_OUTCOME_ALLOW;
		$this->order->update_meta_data( '_wcpay_fraud_outcome_status', $fraud_outcome_status );
		$this->order->save_meta_data();
		$fraud_outcome_status_from_service = $this->order_service->get_fraud_outcome_status_for_order( $this->order->get_id() );
		$this->assertEquals( $fraud_outcome_status_from_service, $fraud_outcome_status );
	}

	public function test_set_fraud_meta_box_type_status() {
		$fraud_meta_box_type = Fraud_Meta_Box_Type::ALLOW;
		$this->order_service->set_fraud_meta_box_type_for_order( $this->order, $fraud_meta_box_type );
		$this->assertEquals( $this->order->get_meta( '_wcpay_fraud_meta_box_type', true ), $fraud_meta_box_type );
	}

	public function test_get_fraud_meta_box_type() {
		$fraud_meta_box_type = Fraud_Meta_Box_Type::ALLOW;
		$this->order->update_meta_data( '_wcpay_fraud_meta_box_type', $fraud_meta_box_type );
		$this->order->save_meta_data();
		$fraud_meta_box_type_from_service = $this->order_service->get_fraud_meta_box_type_for_order( $this->order->get_id() );
		$this->assertEquals( $fraud_meta_box_type_from_service, $fraud_meta_box_type );
	}

	public function test_set_early_fraud_warning_for_order() {
		$early_fraud_warning = [
			'efw_id'     => 'issfr_123',
			'actionable' => true,
			'fraud_type' => 'made_with_stolen_card',
			'created'    => 1719800000,
		];
		$this->order_service->set_early_fraud_warning_for_order( $this->order, $early_fraud_warning );
		$this->assertSame( $this->order->get_meta( '_wcpay_early_fraud_warning', true ), $early_fraud_warning );
	}

	public function test_get_early_fraud_warning_for_order() {
		$early_fraud_warning = [
			'efw_id'     => 'issfr_123',
			'actionable' => true,
			'fraud_type' => 'made_with_stolen_card',
			'created'    => 1719800000,
		];
		$this->order->update_meta_data( '_wcpay_early_fraud_warning', $early_fraud_warning );
		$this->order->save_meta_data();
		$early_fraud_warning_from_service = $this->order_service->get_early_fraud_warning_for_order( $this->order->get_id() );
		$this->assertSame( $early_fraud_warning_from_service, $early_fraud_warning );
	}

	public function test_get_early_fraud_warning_for_order_returns_null_when_not_set() {
		$this->assertNull( $this->order_service->get_early_fraud_warning_for_order( $this->order->get_id() ) );
	}

	public function test_set_payment_transaction_id_for_order() {
		$transaction_id = 'txn_mock';
		$this->order_service->set_payment_transaction_id_for_order( $this->order, $transaction_id );
		$this->assertSame( $this->order->get_meta( '_wcpay_payment_transaction_id', true ), $transaction_id );
	}

	public function test_attach_intent_info_to_order() {
		$intent_id = 'pi_mock';
		$intent    = WC_Helper_Intention::create_intention( [ 'id' => $intent_id ] );
		$this->order_service->attach_intent_info_to_order( $this->order, $intent );

		$this->assertEquals( $intent_id, $this->order->get_meta( '_intent_id', true ) );
	}

	public function test_attach_intent_order_with_allow_update_on_success() {
		$intent = WC_Helper_Intention::create_intention(
			[
				'id'     => 'pi_mock',
				'status' => Intent_Status::SUCCEEDED,
			]
		);
		$this->order_service->attach_intent_info_to_order( $this->order, $intent );

		$another_intent = WC_Helper_Intention::create_intention(
			[
				'id'     => 'pi_mock_2',
				'status' => Intent_Status::CANCELED,
			]
		);
		$this->order_service->attach_intent_info_to_order( $this->order, $another_intent, true );

		$this->assertEquals( Intent_Status::CANCELED, $this->order->get_meta( '_intention_status', true ) );
	}

	public function test_attach_intent_info_to_order_after_successful_payment() {
		$intent = WC_Helper_Intention::create_intention(
			[
				'id'     => 'pi_mock',
				'status' => Intent_Status::SUCCEEDED,
			]
		);
		$this->order_service->attach_intent_info_to_order( $this->order, $intent );

		$another_intent = WC_Helper_Intention::create_intention(
			[
				'id'     => 'pi_mock_2',
				'status' => Intent_Status::CANCELED,
			]
		);
		$this->order_service->attach_intent_info_to_order( $this->order, $another_intent );

		$this->assertEquals( Intent_Status::SUCCEEDED, $this->order->get_meta( '_intention_status', true ) );
	}

	/**
	 * Several methods use the private method get_order to get the order being worked on. If an order is not found
	 * then an exception is thrown. This test attempt to confirm that exception gets thrown.
	 */
	public function test_get_order_throws_exception() {
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'The requested order was not found.' );
		$this->order_service->set_intent_id_for_order( 'fake_order', '' );
	}

	public function test_attach_transaction_fee_to_order() {
		$order  = WC_Helper_Order::create_order();
		$charge = new WC_Payments_API_Charge( 'ch_mock', 1500, new DateTime(), null, null, null, null, 113, [], [], 'usd' );
		$charge->set_captured( true );
		$this->order_service->attach_transaction_fee_to_order( $order, $charge );
		$this->assertEquals( 1.13, $order->get_meta( '_wcpay_transaction_fee', true ) );
	}

	public function test_attach_transaction_fee_to_order_zero_fee() {
		$order  = WC_Helper_Order::create_order();
		$charge = new WC_Payments_API_Charge( 'ch_mock', 1500, new DateTime(), null, null, null, null, 0, [], [], 'eur' );
		$charge->set_captured( true );
		$this->order_service->attach_transaction_fee_to_order( $order, $charge );
		$this->assertEquals( 0, $order->get_meta( '_wcpay_transaction_fee', true ) );
	}

	public function test_attach_transaction_fee_to_order_zero_decimal_fee() {
		$order  = WC_Helper_Order::create_order();
		$charge = new WC_Payments_API_Charge( 'ch_mock', 1500, new DateTime(), null, null, null, null, 30000, [], [], 'jpy' );
		$charge->set_captured( true );
		$this->order_service->attach_transaction_fee_to_order( $order, $charge );
		$this->assertEquals( 30000, $order->get_meta( '_wcpay_transaction_fee', true ) );
	}

	public function test_attach_transaction_fee_to_order_null_fee() {
		$mock_order = $this->createMock( 'WC_Order' );
		$mock_order
			->expects( $this->never() )
			->method( 'update_meta_data' );
		$this->order_service->attach_transaction_fee_to_order( $mock_order, new WC_Payments_API_Charge( 'ch_mock', 1500, new DateTime(), null, null, null, null, null, [], [], 'eur' ) );
	}

	public function test_attach_transaction_fee_to_order_uncaptured_charge() {
		$mock_order = $this->createMock( 'WC_Order' );
		$mock_order
			->expects( $this->never() )
			->method( 'update_meta_data' );

		$charge = new WC_Payments_API_Charge( 'ch_mock', 1500, new DateTime(), null, null, null, null, 113, [], [], 'usd' );
		$charge->set_captured( false );

		// Fee should not be set for uncaptured charges.
		$this->order_service->attach_transaction_fee_to_order( $mock_order, $charge );
	}

	public function test_add_note_and_metadata_for_created_refund_successful_fully_refunded(): void {
		$order = WC_Helper_Order::create_order();
		$order->save();

		$refunded_amount               = 50;
		$refund_id                     = 're_1J2a3B4c5D6e7F8g9H0';
		$refund_reason                 = 'Test refund';
		$refund_balance_transaction_id = 'txn_1J2a3B4c5D6e7F8g9H0';

		$wc_refund = $this->order_service->create_refund_for_order( $order, $refunded_amount, $refund_reason, $order->get_items() );

		$this->order_service->add_note_and_metadata_for_created_refund( $order, $wc_refund, $refund_id, $refund_balance_transaction_id );

		$order_note = wc_get_order_notes( [ 'order_id' => $order->get_id() ] )[0]->content;
		$this->assertStringContainsString( $refunded_amount, $order_note, 'Order note does not contain expected refund amount' );
		$this->assertStringContainsString( $refund_id, $order_note, 'Order note does not contain expected refund id' );
		$this->assertStringContainsString( $refund_reason, $order_note, 'Order note does not contain expected refund reason' );
		$this->assertStringContainsString( 'was successfully processed', $order_note, 'Order note should indicate successful processing' );

		$this->assertSame( 'successful', $order->get_meta( WC_Payments_Order_Service::WCPAY_REFUND_STATUS_META_KEY, true ) );
		$this->assertSame( $refund_id, $wc_refund->get_meta( WC_Payments_Order_Service::WCPAY_REFUND_ID_META_KEY, true ) );
		$this->assertSame( $refund_balance_transaction_id, $order->get_refunds()[0]->get_meta( WC_Payments_Order_Service::WCPAY_REFUND_TRANSACTION_ID_META_KEY, true ) );

		WC_Helper_Order::delete_order( $order->get_id() );
	}

	public function test_add_note_and_metadata_for_created_refund_successful_partially_refunded(): void {
		$order = WC_Helper_Order::create_order();
		$order->save();

		$refunded_amount               = 10;
		$refund_id                     = 're_1J2a3B4c5D6e7F8g9H0';
		$refund_reason                 = 'Test refund';
		$refund_balance_transaction_id = 'txn_1J2a3B4c5D6e7F8g9H0';
		$wc_refund                     = $this->order_service->create_refund_for_order( $order, $refunded_amount, $refund_reason, $order->get_items() );

		$this->order_service->add_note_and_metadata_for_created_refund( $order, $wc_refund, $refund_id, $refund_balance_transaction_id );

		$this->assertSame( Order_Status::PENDING, $order->get_status() );

		$order_note = wc_get_order_notes( [ 'order_id' => $order->get_id() ] )[0]->content;
		$this->assertStringContainsString( $refunded_amount, $order_note, 'Order note does not contain expected refund amount' );
		$this->assertStringContainsString( $refund_id, $order_note, 'Order note does not contain expected refund id' );
		$this->assertStringContainsString( $refund_reason, $order_note, 'Order note does not contain expected refund reason' );
		$this->assertStringContainsString( 'was successfully processed', $order_note, 'Order note should indicate successful processing' );

		$this->assertSame( 'successful', $order->get_meta( WC_Payments_Order_Service::WCPAY_REFUND_STATUS_META_KEY, true ) );
		$this->assertSame( $refund_id, $wc_refund->get_meta( WC_Payments_Order_Service::WCPAY_REFUND_ID_META_KEY, true ) );
		$this->assertSame( $refund_balance_transaction_id, $order->get_refunds()[0]->get_meta( WC_Payments_Order_Service::WCPAY_REFUND_TRANSACTION_ID_META_KEY, true ) );

		WC_Helper_Order::delete_order( $order->get_id() );
	}

	public function test_add_note_and_metadata_for_created_refund_pending(): void {
		$order = WC_Helper_Order::create_order();
		$order->save();

		$refunded_amount               = 50;
		$refund_id                     = 're_1J2a3B4c5D6e7F8g9H0';
		$refund_reason                 = 'Test refund';
		$refund_balance_transaction_id = 'txn_1J2a3B4c5D6e7F8g9H0';

		$wc_refund = $this->order_service->create_refund_for_order( $order, $refunded_amount, $refund_reason, $order->get_items() );

		$this->order_service->add_note_and_metadata_for_created_refund( $order, $wc_refund, $refund_id, $refund_balance_transaction_id, true );

		$order_note = wc_get_order_notes( [ 'order_id' => $order->get_id() ] )[0]->content;
		$this->assertStringContainsString( $refunded_amount, $order_note, 'Order note does not contain expected refund amount' );
		$this->assertStringContainsString( $refund_id, $order_note, 'Order note does not contain expected refund id' );
		$this->assertStringContainsString( $refund_reason, $order_note, 'Order note does not contain expected refund reason' );
		$this->assertStringContainsString( 'is pending', $order_note, 'Order note should indicate pending status' );
		$this->assertStringContainsString( 'https://woocommerce.com/document/woopayments/managing-money/#pending-refunds', $order_note, 'Order note should contain link to pending refunds documentation' );

		$this->assertSame( Refund_Status::PENDING, $order->get_meta( WC_Payments_Order_Service::WCPAY_REFUND_STATUS_META_KEY, true ) );
		$this->assertSame( $refund_id, $wc_refund->get_meta( WC_Payments_Order_Service::WCPAY_REFUND_ID_META_KEY, true ) );
		$this->assertSame( $refund_balance_transaction_id, $order->get_refunds()[0]->get_meta( WC_Payments_Order_Service::WCPAY_REFUND_TRANSACTION_ID_META_KEY, true ) );

		WC_Helper_Order::delete_order( $order->get_id() );
	}

	public function test_add_note_and_metadata_for_created_refund_no_duplicate_notes(): void {
		$order = WC_Helper_Order::create_order();
		$order->save();

		$refunded_amount               = 50;
		$refund_id                     = 're_1J2a3B4c5D6e7F8g9H0';
		$refund_reason                 = 'Test refund';
		$refund_balance_transaction_id = 'txn_1J2a3B4c5D6e7F8g9H0';

		$wc_refund = $this->order_service->create_refund_for_order( $order, $refunded_amount, $refund_reason, $order->get_items() );

		// Add note first time.
		$this->order_service->add_note_and_metadata_for_created_refund( $order, $wc_refund, $refund_id, $refund_balance_transaction_id );
		$initial_notes_count = count( wc_get_order_notes( [ 'order_id' => $order->get_id() ] ) );

		// Add note second time.
		$this->order_service->add_note_and_metadata_for_created_refund( $order, $wc_refund, $refund_id, $refund_balance_transaction_id );
		$final_notes_count = count( wc_get_order_notes( [ 'order_id' => $order->get_id() ] ) );

		$this->assertSame( $initial_notes_count, $final_notes_count, 'Duplicate notes should not be added' );

		WC_Helper_Order::delete_order( $order->get_id() );
	}

	public function test_process_captured_payment() {
		$order = WC_Helper_Order::create_order();
		$order->save();

		$intent = WC_Helper_Intention::create_intention( [ 'status' => Intent_Status::SUCCEEDED ] );
		$this->order_service->set_intention_status_for_order( $this->order, Intent_Status::REQUIRES_CAPTURE );
		$this->order_service->set_intent_id_for_order( $order, $intent->get_id() );
		$order->set_status( Order_Status::PROCESSING ); // Let's simulate that order is set to processing, so order status should not interfere with the process.
		$order->save();

		$this->order_service->process_captured_payment( $order, $intent );

		$this->assertEquals( $intent->get_status(), $this->order_service->get_intention_status_for_order( $order ) );

		$this->assertTrue( $order->has_status( wc_get_is_paid_statuses() ) );

		$notes = wc_get_order_notes( [ 'order_id' => $order->get_id() ] );
		$this->assertStringContainsString( 'successfully captured</strong> using WooPayments', $notes[0]->content );
		$this->assertStringContainsString( '%2Fpayments%2Ftransactions%2Fdetails&id=pi_mock" target="_blank" rel="noopener noreferrer">pi_mock', $notes[0]->content );

		// Assert: Check that the order was unlocked.
		$this->assertFalse( get_transient( 'wcpay_processing_intent_' . $order->get_id() ) );

		// Assert: Applying the same data multiple times does not cause duplicate actions.
		$this->order_service->update_order_status_from_intent( $order, $intent );
		$notes_2 = wc_get_order_notes( [ 'order_id' => $order->get_id() ] );
		$this->assertEquals( count( $notes ), count( $notes_2 ) );
	}

	/**
	 * Tests handling of failed refunds.
	 *
	 * @dataProvider provider_handle_failed_refund
	 */
	public function test_handle_failed_refund( string $initial_order_status, bool $has_refund, bool $expect_status_change ): void {
		// Arrange: Create order and optionally add a refund.
		$order     = WC_Helper_Order::create_order();
		$wc_refund = null;
		if ( $has_refund ) {
			$wc_refund = $this->order_service->create_refund_for_order( $order, $order->get_total(), 'Test refund reason', $order->get_items() );
		}
		$order->set_status( $initial_order_status );
		$order->save();

		$refund_id = 're_123456789';
		$amount    = 1000; // $10.00
		$currency  = 'usd';

		// Act: Handle the failed refund.
		$this->order_service->handle_failed_refund( $order, $refund_id, $amount, $currency, $wc_refund );

		// Assert: Check order status was updated if needed.
		if ( $expect_status_change ) {
			$this->assertTrue( $order->has_status( Order_Status::FAILED ) );
		} else {
			$this->assertTrue( $order->has_status( $initial_order_status ) );
		}

		// Assert: Check refund status was set to failed.
		$this->assertSame( Refund_Status::FAILED, $this->order_service->get_wcpay_refund_status_for_order( $order ) );

		// Assert: Check order note was added.
		$notes = wc_get_order_notes( [ 'order_id' => $order->get_id() ] );

		// There should be at least two notes - one for status change and one for the failed refund.
		$this->assertGreaterThanOrEqual( 2, count( $notes ) );

		// Find our custom note about the unsuccessful refund.
		$found_unsuccessful_note = false;
		foreach ( $notes as $note ) {
			if ( strpos( $note->content, 'unsuccessful' ) !== false ) {
				$found_unsuccessful_note = true;
				$this->assertStringContainsString( $refund_id, $note->content );
				break;
			}
		}
		$this->assertTrue( $found_unsuccessful_note, 'Could not find note about unsuccessful refund' );

		// Assert: If refund existed, it was deleted.
		if ( $has_refund ) {
			$this->assertEmpty( $order->get_refunds() );
		}

		WC_Helper_Order::delete_order( $order->get_id() );
	}

	public function provider_handle_failed_refund(): array {
		return [
			'Order not refunded - no status change'       => [
				'initial_order_status' => Order_Status::PROCESSING,
				'has_refund'           => false,
				'expect_status_change' => false,
			],
			'Order fully refunded - status changes to failed' => [
				'initial_order_status' => Order_Status::REFUNDED,
				'has_refund'           => true,
				'expect_status_change' => true,
			],
			'Order partially refunded - no status change' => [
				'initial_order_status' => Order_Status::PROCESSING,
				'has_refund'           => true,
				'expect_status_change' => false,
			],
		];
	}

	/**
	 * Tests that handle_failed_refund doesn't add duplicate notes.
	 */
	public function test_handle_failed_refund_no_duplicate_notes(): void {
		// Arrange: Create order and handle failed refund twice.
		$order = WC_Helper_Order::create_order();
		$order->save();

		$refund_id = 're_123456789';
		$amount    = 1000;
		$currency  = 'usd';

		$this->order_service->handle_failed_refund( $order, $refund_id, $amount, $currency );
		$initial_notes_count = count( wc_get_order_notes( [ 'order_id' => $order->get_id() ] ) );

		$this->order_service->handle_failed_refund( $order, $refund_id, $amount, $currency );
		$final_notes_count = count( wc_get_order_notes( [ 'order_id' => $order->get_id() ] ) );

		// Assert: No duplicate notes were added.
		$this->assertSame( $initial_notes_count, $final_notes_count );

		WC_Helper_Order::delete_order( $order->get_id() );
	}

	/**
	 * Tests that handle_failed_refund adds the correct note for cancelled refunds.
	 */
	public function test_handle_failed_refund_cancelled(): void {
		// Arrange: Create order and handle cancelled refund.
		$order = WC_Helper_Order::create_order();
		$order->save();

		$refund_id = 're_123456789';
		$amount    = 1000;
		$currency  = 'usd';

		// Act: Handle the cancelled refund.
		$this->order_service->handle_failed_refund( $order, $refund_id, $amount, $currency, null, true );

		// Assert: Check order note was added with cancelled status.
		$notes = wc_get_order_notes( [ 'order_id' => $order->get_id() ] );
		$this->assertStringContainsString( 'cancelled', $notes[0]->content );
		$this->assertStringContainsString( $refund_id, $notes[0]->content );

		// Assert: Check refund status was set to failed.
		$this->assertSame( Refund_Status::FAILED, $this->order_service->get_wcpay_refund_status_for_order( $order ) );

		WC_Helper_Order::delete_order( $order->get_id() );
	}

	/**
	 * Tests that handle_failed_refund doesn't add duplicate notes for cancelled refunds.
	 */
	public function test_handle_failed_refund_cancelled_no_duplicate_notes(): void {
		// Arrange: Create order and handle cancelled refund twice.
		$order = WC_Helper_Order::create_order();
		$order->save();

		$refund_id = 're_123456789';
		$amount    = 1000;
		$currency  = 'usd';

		$this->order_service->handle_failed_refund( $order, $refund_id, $amount, $currency, null, true );
		$initial_notes_count = count( wc_get_order_notes( [ 'order_id' => $order->get_id() ] ) );

		$this->order_service->handle_failed_refund( $order, $refund_id, $amount, $currency, null, true );
		$final_notes_count = count( wc_get_order_notes( [ 'order_id' => $order->get_id() ] ) );

		// Assert: No duplicate notes were added.
		$this->assertSame( $initial_notes_count, $final_notes_count );

		WC_Helper_Order::delete_order( $order->get_id() );
	}

	/**
	 * Tests that handle_failed_refund updates order status to failed when fully refunded.
	 */
	public function test_handle_failed_refund_cancelled_updates_order_status(): void {
		// Arrange: Create order and set it to refunded status.
		$order = WC_Helper_Order::create_order();
		$order->set_status( Order_Status::REFUNDED );
		$order->save();

		$refund_id = 're_123456789';
		$amount    = 1000;
		$currency  = 'usd';

		// Act: Handle the cancelled refund.
		$this->order_service->handle_failed_refund( $order, $refund_id, $amount, $currency, null, true );

		// Assert: Order status was updated to failed.
		$this->assertTrue( $order->has_status( Order_Status::FAILED ) );

		WC_Helper_Order::delete_order( $order->get_id() );
	}

	public function test_handle_insufficient_balance_for_refund() {
		// Create a test order and refund.
		$order = WC_Helper_Order::create_order();
		$order->save();

		$refund_amount = 10;
		wc_create_refund(
			[
				'amount'   => $refund_amount,
				'reason'   => 'Testing refund',
				'order_id' => $order->get_id(),
			]
		);

		// Test handling insufficient balance.
		$this->order_service->handle_insufficient_balance_for_refund( $order, $refund_amount );

		// Check that only one note was added for insufficient funds.
		$notes = array_filter(
			wc_get_order_notes( [ 'order_id' => $order->get_id() ] ),
			function ( $note ) {
				return strpos( $note->content, 'insufficient funds' ) !== false;
			}
		);
		$this->assertCount( 1, $notes );

		// Clean up.
		WC_Helper_Order::delete_order( $order->get_id() );
	}

	/**
	 * Tests that handle_failed_refund correctly handles the insufficient funds case.
	 */
	public function test_handle_failed_refund_with_insufficient_funds() {
		// Create a test order.
		$order = WC_Helper_Order::create_order();
		$order->save();

		$refund_id = 're_123456789';
		$amount    = 1000; // $10.00
		$currency  = 'usd';

		// Test handling failed refund with insufficient funds.
		$this->order_service->handle_failed_refund( $order, $refund_id, $amount, $currency, null, false, Refund_Failure_Reason::INSUFFICIENT_FUNDS );

		// Check that only one note was added for insufficient funds.
		$notes = array_filter(
			wc_get_order_notes( [ 'order_id' => $order->get_id() ] ),
			function ( $note ) {
				return strpos( $note->content, 'insufficient funds' ) !== false;
			}
		);
		$this->assertCount( 1, $notes );

		// Check that the refund status was set to failed.
		$this->assertSame( Refund_Status::FAILED, $this->order_service->get_wcpay_refund_status_for_order( $order ) );

		// Clean up.
		WC_Helper_Order::delete_order( $order->get_id() );
	}

	/**
	 * Tests that mark_payment_dispute_closed handles dispute summary data correctly.
	 */
	public function test_mark_payment_dispute_closed_with_dispute_summary(): void {
		// Create a test order and set it to on-hold status (as dispute would).
		$order = WC_Helper_Order::create_order();
		$order->set_status( Order_Status::ON_HOLD );
		$order->save();

		$charge_id = 'ch_123';
		$status    = 'lost';

		// Test dispute summary data.
		$dispute_summary = [
			'disputed_amount' => 5000, // $50.00 in cents
			'currency'        => 'usd',
			'fee'             => 1500, // $15.00 in cents
			'network_cost'    => 500,  // $5.00 in cents
			'exchange_rate'   => 1,
		];

		// Act: Mark payment dispute closed with dispute summary.
		$this->order_service->mark_payment_dispute_closed( $order, $charge_id, $status, $dispute_summary );

		// Assert: Check that the order status was left in on-hold status.
		$this->assertTrue( $order->has_status( [ Order_Status::ON_HOLD ] ) );

		// Assert: Check that a refund was created with the correct amount.
		$refunds = $order->get_refunds();
		$this->assertCount( 1, $refunds );
		$this->assertEquals( -50.00, $refunds[0]->get_total() );

		// Assert: Full dispute (disputed amount == order total) should include line items.
		$this->assertNotEmpty( $refunds[0]->get_items(), 'Full dispute refund should include line items.' );

		// Assert: Check that the notes were updated.
		$notes = wc_get_order_notes( [ 'order_id' => $order->get_id() ] );
		$this->assertStringContainsString( 'Dispute has been closed with status lost', $notes[0]->content );

		// Clean up.
		WC_Helper_Order::delete_order( $order->get_id() );
	}

	/**
	 * Tests that mark_payment_dispute_closed handles partial refunds correctly.
	 */
	public function test_mark_payment_dispute_closed_with_partial_refund(): void {
		// Create a test order with a total of $100.
		$order = WC_Helper_Order::create_order();
		$order->set_total( 100.00 );
		$order->set_status( Order_Status::ON_HOLD );
		$order->save();

		$charge_id = 'ch_123';
		$status    = 'lost';

		// Test dispute summary data with disputed amount less than order total.
		$dispute_summary = [
			'disputed_amount' => 3000, // $30.00 in cents (partial amount)
			'currency'        => 'usd',
			'fee'             => 1500, // $15.00 in cents
			'network_cost'    => 500,  // $5.00 in cents
			'exchange_rate'   => 1,
		];

		// Act: Mark payment dispute closed with dispute summary.
		$this->order_service->mark_payment_dispute_closed( $order, $charge_id, $status, $dispute_summary );

		// Assert: Check that a refund was created with the partial amount.
		$refunds = $order->get_refunds();
		$this->assertCount( 1, $refunds );
		$this->assertEquals( -30.00, $refunds[0]->get_total(), 'Refund is created for the partial amount from the dispute summary.' );

		// Assert: Partial dispute should have empty line items to avoid inconsistency.
		$this->assertEmpty( $refunds[0]->get_items(), 'Partial dispute refund should have empty line items.' );

		// Clean up.
		WC_Helper_Order::delete_order( $order->get_id() );
	}

	/**
	 * Two lost disputes on one charge should each record a refund. The dedup keyed
	 * on charge + status, so the second was skipped — refund and all.
	 */
	public function test_mark_payment_dispute_closed_records_a_refund_per_lost_dispute(): void {
		$order = WC_Helper_Order::create_order();
		$order->set_total( 100.00 );
		$order->set_status( Order_Status::ON_HOLD );
		$order->save();

		$charge_id = 'ch_123';
		$status    = 'lost';

		// Act: two lost disputes on the same charge, each disputing part of the order.
		$this->order_service->mark_payment_dispute_closed(
			$order,
			$charge_id,
			$status,
			[
				'disputed_amount' => 3000,
				'currency'        => 'usd',
			],
			'dp_first'
		);
		$this->order_service->mark_payment_dispute_closed(
			$order,
			$charge_id,
			$status,
			[
				'disputed_amount' => 7000,
				'currency'        => 'usd',
			],
			'dp_second'
		);

		// Assert: each lost dispute recorded its own refund.
		$refunds = $order->get_refunds();
		$this->assertCount( 2, $refunds );
		$refund_totals = array_map(
			function ( $refund ) {
				return (float) $refund->get_total();
			},
			$refunds
		);
		$this->assertEqualsCanonicalizing( [ -30.00, -70.00 ], $refund_totals );

		// Assert: each dispute produced its own closed note, distinguished by ID.
		$contents = implode( "\n", wp_list_pluck( wc_get_order_notes( [ 'order_id' => $order->get_id() ] ), 'content' ) );
		$this->assertStringContainsString( '(Dispute ID: dp_first)', $contents );
		$this->assertStringContainsString( '(Dispute ID: dp_second)', $contents );

		// Assert: re-delivering the same dispute's close does not double-refund.
		$this->order_service->mark_payment_dispute_closed(
			$order,
			$charge_id,
			$status,
			[
				'disputed_amount' => 3000,
				'currency'        => 'usd',
			],
			'dp_first'
		);
		$this->assertCount( 2, $order->get_refunds() );

		WC_Helper_Order::delete_order( $order->get_id() );
	}

	/**
	 * Tests that mark_payment_dispute_closed handles missing amount in refund.
	 */
	public function test_mark_payment_dispute_closed_with_missing_amount_in_summary(): void {
		// Create a test order with a total of $100.
		$order = WC_Helper_Order::create_order();
		$order->set_total( 100.00 );
		$order->set_status( Order_Status::ON_HOLD );
		$order->save();

		$charge_id = 'ch_123';
		$status    = 'lost';

		// Test dispute summary data with disputed amount less than order total.
		$dispute_summary = [
			'currency'      => 'usd',
			'fee'           => 1500, // $15.00 in cents
			'network_cost'  => 500,  // $5.00 in cents
			'exchange_rate' => 1,
		];

		// Act: Mark payment dispute closed with dispute summary.
		$this->order_service->mark_payment_dispute_closed( $order, $charge_id, $status, $dispute_summary );

		// Assert: Check that a refund was created with the total order amount.
		$refunds = $order->get_refunds();
		$this->assertCount( 1, $refunds );
		$this->assertEquals( -100.00, $refunds[0]->get_total(), 'Refund is created with order total if dispute summary amount is missing.' );

		// Clean up.
		WC_Helper_Order::delete_order( $order->get_id() );
	}


	/**
	 * Tests that mark_payment_dispute_closed handles disputed amount exceeding order total.
	 */
	public function test_mark_payment_dispute_closed_with_excessive_disputed_amount(): void {
		// Create a test order with a total of $50.
		$order = WC_Helper_Order::create_order();
		$order->set_total( 50.00 );
		$order->set_status( Order_Status::ON_HOLD );
		$order->save();

		$charge_id = 'ch_123';
		$status    = 'lost';

		// Test dispute summary data with disputed amount greater than order total.
		$dispute_summary = [
			'disputed_amount' => 6000, // $60.00 in cents (more than order total)
			'currency'        => 'usd',
			'fee'             => 1500, // $15.00 in cents
			'network_cost'    => 500,  // $5.00 in cents
			'exchange_rate'   => 1,
		];

		// Act: Mark payment dispute closed with dispute summary.
		$this->order_service->mark_payment_dispute_closed( $order, $charge_id, $status, $dispute_summary );

		// Assert: Check that a refund was created with the order total amount (not exceeding).
		$refunds = $order->get_refunds();
		$this->assertCount( 1, $refunds );
		$this->assertEquals( -50.00, $refunds[0]->get_total() );

		// Assert: Disputed amount >= order total means full dispute, so line items should be present.
		$this->assertNotEmpty( $refunds[0]->get_items(), 'Full dispute refund should include line items.' );

		// Clean up.
		WC_Helper_Order::delete_order( $order->get_id() );
	}

	/**
	 * Tests that mark_payment_dispute_closed works without dispute summary (backward compatibility).
	 */
	public function test_mark_payment_dispute_closed_without_dispute_summary(): void {
		// Create a test order and set it to on-hold status.
		$order = WC_Helper_Order::create_order();
		$order->set_status( Order_Status::ON_HOLD );
		$order->save();

		$charge_id = 'ch_123';
		$status    = 'lost';

		// Act: Mark payment dispute closed without dispute summary (old behavior).
		$this->order_service->mark_payment_dispute_closed( $order, $charge_id, $status );

		// Assert: Check that the order status was left in on-hold status.
		$this->assertTrue( $order->has_status( [ Order_Status::ON_HOLD ] ) );

		// Assert: Check that a refund was created with the full order amount.
		$refunds = $order->get_refunds();
		$this->assertCount( 1, $refunds );
		$this->assertEquals( -$order->get_total(), $refunds[0]->get_total() );

		// Clean up.
		WC_Helper_Order::delete_order( $order->get_id() );
	}

	/**
	 * Test that add_fee_breakdown_to_order_notes returns early when timeline data is missing.
	 */
	public function test_add_fee_breakdown_returns_early_when_timeline_data_missing() {
		$mock_api_client = $this->createMock( WC_Payments_API_Client::class );
		$mock_api_client->expects( $this->once() )
			->method( 'get_timeline' )
			->willReturn( [] ); // No 'data' key.

		$order_service = new WC_Payments_Order_Service( $mock_api_client );

		$notes_before = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );

		$order_service->add_fee_breakdown_to_order_notes( $this->order->get_id(), 'pi_test_123' );

		$notes_after = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );
		$this->assertCount( count( $notes_before ), $notes_after );
	}

	/**
	 * Test that add_fee_breakdown_to_order_notes returns early when timeline data is not an array.
	 */
	public function test_add_fee_breakdown_returns_early_when_timeline_data_not_array() {
		$mock_api_client = $this->createMock( WC_Payments_API_Client::class );
		$mock_api_client->expects( $this->once() )
			->method( 'get_timeline' )
			->willReturn( [ 'data' => null ] );

		$order_service = new WC_Payments_Order_Service( $mock_api_client );

		$notes_before = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );

		$order_service->add_fee_breakdown_to_order_notes( $this->order->get_id(), 'pi_test_123' );

		$notes_after = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );
		$this->assertCount( count( $notes_before ), $notes_after );
	}

	/**
	 * Test that add_fee_breakdown_to_order_notes returns early when no captured event is found.
	 */
	public function test_add_fee_breakdown_returns_early_when_no_captured_event() {
		$mock_api_client = $this->createMock( WC_Payments_API_Client::class );
		$mock_api_client->expects( $this->once() )
			->method( 'get_timeline' )
			->willReturn(
				[
					'data' => [
						[ 'type' => 'authorized' ],
					],
				]
			);

		$order_service = new WC_Payments_Order_Service( $mock_api_client );

		$notes_before = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );

		$order_service->add_fee_breakdown_to_order_notes( $this->order->get_id(), 'pi_test_123' );

		$notes_after = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );
		$this->assertCount( count( $notes_before ), $notes_after );
	}

	public function test_maybe_record_first_live_sale_short_circuits_when_option_already_set(): void {
		update_option( WC_Payments_Order_Service::HAS_LIVE_SALE_OPTION, '1', true );
		set_transient( WC_Payments_Post_Kyc_Activation_Notice::TRANSIENT_ELIGIBLE, '1', HOUR_IN_SECONDS );

		$this->order->update_meta_data( WC_Payments_Order_Service::WCPAY_MODE_META_KEY, Order_Mode::PRODUCTION );
		$this->order->save();

		$this->order_service->maybe_record_first_live_sale( $this->order->get_id() );

		$this->assertSame( '1', get_transient( WC_Payments_Post_Kyc_Activation_Notice::TRANSIENT_ELIGIBLE ) );

		delete_option( WC_Payments_Order_Service::HAS_LIVE_SALE_OPTION );
		delete_transient( WC_Payments_Post_Kyc_Activation_Notice::TRANSIENT_ELIGIBLE );
	}

	public function test_maybe_record_first_live_sale_short_circuits_when_order_id_invalid(): void {
		delete_option( WC_Payments_Order_Service::HAS_LIVE_SALE_OPTION );
		set_transient( WC_Payments_Post_Kyc_Activation_Notice::TRANSIENT_ELIGIBLE, '1', HOUR_IN_SECONDS );

		$this->order_service->maybe_record_first_live_sale( 99999999 );

		$this->assertFalse( get_option( WC_Payments_Order_Service::HAS_LIVE_SALE_OPTION ) );
		$this->assertSame( '1', get_transient( WC_Payments_Post_Kyc_Activation_Notice::TRANSIENT_ELIGIBLE ) );

		delete_transient( WC_Payments_Post_Kyc_Activation_Notice::TRANSIENT_ELIGIBLE );
	}

	public function test_maybe_record_first_live_sale_skips_test_mode_order(): void {
		delete_option( WC_Payments_Order_Service::HAS_LIVE_SALE_OPTION );
		set_transient( WC_Payments_Post_Kyc_Activation_Notice::TRANSIENT_ELIGIBLE, '1', HOUR_IN_SECONDS );

		$this->order->update_meta_data( WC_Payments_Order_Service::WCPAY_MODE_META_KEY, Order_Mode::TEST );
		$this->order->save();

		$this->order_service->maybe_record_first_live_sale( $this->order->get_id() );

		$this->assertFalse( get_option( WC_Payments_Order_Service::HAS_LIVE_SALE_OPTION ) );
		$this->assertSame( '1', get_transient( WC_Payments_Post_Kyc_Activation_Notice::TRANSIENT_ELIGIBLE ) );

		delete_transient( WC_Payments_Post_Kyc_Activation_Notice::TRANSIENT_ELIGIBLE );
	}

	public function test_maybe_record_first_live_sale_records_for_production_order(): void {
		delete_option( WC_Payments_Order_Service::HAS_LIVE_SALE_OPTION );

		$this->order->update_meta_data( WC_Payments_Order_Service::WCPAY_MODE_META_KEY, Order_Mode::PRODUCTION );
		$this->order->save();

		$this->order_service->maybe_record_first_live_sale( $this->order->get_id() );

		$this->assertSame( '1', get_option( WC_Payments_Order_Service::HAS_LIVE_SALE_OPTION ) );

		delete_option( WC_Payments_Order_Service::HAS_LIVE_SALE_OPTION );
	}

	// -------------------------------------------------------------------------
	// has_live_sale()
	// -------------------------------------------------------------------------

	public function test_has_live_sale_returns_true_when_option_is_set(): void {
		update_option( WC_Payments_Order_Service::HAS_LIVE_SALE_OPTION, '1', true );

		$this->assertTrue( $this->order_service->has_live_sale() );

		delete_option( WC_Payments_Order_Service::HAS_LIVE_SALE_OPTION );
	}

	public function test_has_live_sale_returns_false_when_no_orders_exist(): void {
		delete_option( WC_Payments_Order_Service::HAS_LIVE_SALE_OPTION );

		$this->assertFalse( $this->order_service->has_live_sale() );
	}

	public function test_has_live_sale_falls_back_to_query_and_writes_option_for_production_order(): void {
		delete_option( WC_Payments_Order_Service::HAS_LIVE_SALE_OPTION );

		$order = WC_Helper_Order::create_order();
		$order->set_payment_method( 'woocommerce_payments' );
		$order->set_status( 'completed' );
		$order->update_meta_data( WC_Payments_Order_Service::WCPAY_MODE_META_KEY, Order_Mode::PRODUCTION );
		$order->save();

		$this->assertTrue( $this->order_service->has_live_sale() );
		$this->assertSame( '1', get_option( WC_Payments_Order_Service::HAS_LIVE_SALE_OPTION ) );

		$order->delete( true );
		delete_option( WC_Payments_Order_Service::HAS_LIVE_SALE_OPTION );
	}

	public function test_has_live_sale_ignores_test_mode_wcpay_orders(): void {
		delete_option( WC_Payments_Order_Service::HAS_LIVE_SALE_OPTION );

		$order = WC_Helper_Order::create_order();
		$order->set_payment_method( 'woocommerce_payments' );
		$order->set_status( 'completed' );
		$order->update_meta_data( WC_Payments_Order_Service::WCPAY_MODE_META_KEY, Order_Mode::TEST );
		$order->save();

		$this->assertFalse( $this->order_service->has_live_sale() );
		$this->assertFalse( get_option( WC_Payments_Order_Service::HAS_LIVE_SALE_OPTION ) );

		$order->delete( true );
	}

	public function test_has_live_sale_ignores_non_wcpay_orders(): void {
		delete_option( WC_Payments_Order_Service::HAS_LIVE_SALE_OPTION );

		$order = WC_Helper_Order::create_order();
		$order->set_payment_method( 'cheque' );
		$order->set_status( 'completed' );
		$order->save();

		$this->assertFalse( $this->order_service->has_live_sale() );

		$order->delete( true );
	}

	/**
	 * Raises a dispute on the test order the way the charge.dispute.created webhook would.
	 *
	 * @param string $charge_id  The ID of the disputed charge.
	 * @param string $dispute_id The ID of the dispute being raised.
	 * @param string $status     The status the dispute was raised with.
	 *
	 * @return void
	 */
	private function create_dispute( string $charge_id, string $dispute_id, string $status = 'needs_response' ) {
		$this->order_service->mark_payment_dispute_created(
			$this->order,
			$charge_id,
			'$123.45',
			'product_not_received',
			'June 7, 2023',
			$status,
			$dispute_id
		);
	}

	/**
	 * Collects every note on an order into one string, so assertions can look for a note
	 * without depending on how many notes the surrounding status changes happened to add.
	 *
	 * @param WC_Order|null $order The order to read. Defaults to the shared test order.
	 *
	 * @return string
	 */
	private function order_note_contents( ?WC_Order $order = null ): string {
		$order = $order ?? $this->order;
		$notes = wc_get_order_notes( [ 'order_id' => $order->get_id() ] );

		return implode( "\n", wp_list_pluck( $notes, 'content' ) );
	}
}
