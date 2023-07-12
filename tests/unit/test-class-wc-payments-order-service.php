<?php
/**
 * Class WC_Payments_Order_Service_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\Constants\Fraud_Meta_Box_Type;
use WCPay\Constants\Order_Status;
use WCPay\Constants\Payment_Intent_Status;
use WCPay\Exceptions\Order_Not_Found_Exception;
use WCPay\Fraud_Prevention\Models\Rule;

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

		// Turn on fraud settings.
		update_option( 'wcpay_fraud_protection_settings_active', '1' );
	}

	public function tear_down() {
		// Disable fraud settings.
		update_option( 'wcpay_fraud_protection_settings_active', 0 );

		parent::tear_down();
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
	public function test_mark_payment_completed( $order_status, $intent_args, $expected_note_1, $expected_fraud_outcome, $expected_fraud_meta_box ) {
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
		$notes = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );
		$this->assertStringContainsString( $expected_note_1, $notes[1]->content );
		$this->assertStringContainsString( 'successfully charged</strong> using WooPayments', $notes[0]->content );
		$this->assertStringContainsString( '/payments/transactions/details&id=pi_mock" target="_blank" rel="noopener noreferrer">pi_mock', $notes[0]->content );

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
				'expected_note_1'         => 'Pending payment to Processing',
				'expected_fraud_outcome'  => false,
				'expected_fraud_meta_box' => Fraud_Meta_Box_Type::NOT_CARD,
			],
			'mark_complete_no_fraud_outcome_pmtype_card' => [
				'order_status'            => false,
				'intent_args'             => [
					'payment_method_types' => [ 'card' ],
				],
				'expected_note_1'         => 'Pending payment to Processing',
				'expected_fraud_outcome'  => false,
				'expected_fraud_meta_box' => false,
			],
			'mark_complete_fraud_outcome_allow'          => [
				'order_status'            => false,
				'intent_args'             => [
					'metadata'             => [
						'fraud_outcome' => Rule::FRAUD_OUTCOME_ALLOW,
					],
					'payment_method_types' => [ 'card' ],
				],
				'expected_note_1'         => 'Pending payment to Processing',
				'expected_fraud_outcome'  => Rule::FRAUD_OUTCOME_ALLOW,
				'expected_fraud_meta_box' => Fraud_Meta_Box_Type::ALLOW,
			],
			'mark_complete_fraud_outcome_review'         => [
				'order_status'            => Order_Status::ON_HOLD,
				'intent_args'             => [
					'metadata'             => [
						'fraud_outcome' => Rule::FRAUD_OUTCOME_ALLOW,
					],
					'payment_method_types' => [ 'card' ],
				],
				'expected_note_1'         => 'On hold to Processing',
				'expected_fraud_outcome'  => Rule::FRAUD_OUTCOME_ALLOW,
				'expected_fraud_meta_box' => Fraud_Meta_Box_Type::REVIEW_ALLOWED,
			],
		];
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
		$this->order_service->set_intention_status_for_order( $this->order, Payment_Intent_Status::REQUIRES_CAPTURE );
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
		$notes = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );
		$this->assertStringContainsString( 'On hold to Processing', $notes[1]->content );
		$this->assertStringContainsString( 'successfully captured</strong> using WooPayments', $notes[0]->content );
		$this->assertStringContainsString( '/payments/transactions/details&id=pi_mock" target="_blank" rel="noopener noreferrer">pi_mock', $notes[0]->content );

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
		$this->assertStringContainsString( '/payments/transactions/details&id=pi_mock" target="_blank" rel="noopener noreferrer">pi_mock', $notes[0]->content );

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
					'status' => Payment_Intent_Status::REQUIRES_CAPTURE,
				],
				'expected_fraud_outcome'  => false,
				'expected_fraud_meta_box' => false,
			],
			'mark_authorized_no_fraud_outcome_intent_status_processing' => [
				'intent_args'             => [
					'status' => Payment_Intent_Status::PROCESSING,
				],
				'expected_fraud_outcome'  => false,
				'expected_fraud_meta_box' => false,
			],
			'mark_authorized_fraud_outcome_allow' => [
				'intent_args'             => [
					'status'   => Payment_Intent_Status::REQUIRES_CAPTURE,
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
		$intent = WC_Helper_Intention::create_intention( [ 'status' => Payment_Intent_Status::REQUIRES_CAPTURE ] );
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
		$this->assertStringContainsString( '/payments/transactions/details&id=pi_mock&status_is=review&type_is=order_note" target="_blank" rel="noopener noreferrer">View more details', $notes[0]->content );

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
					'status'   => Payment_Intent_Status::REQUIRES_CAPTURE,
					'metadata' => [
						'fraud_outcome' => Rule::FRAUD_OUTCOME_REVIEW,
					],
				],
			],
			'mark_held_for_review_no_fraud_outcome_intent_status_processing' => [
				'intent_args' => [
					'status'   => Payment_Intent_Status::PROCESSING,
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
					'status'               => Payment_Intent_Status::REQUIRES_ACTION,
					'payment_method_types' => [ 'card' ],
				],
				'expected_fraud_meta_box' => Fraud_Meta_Box_Type::PAYMENT_STARTED,
			],
			'mark_payment_started_intent_status_requires_payment_method' => [
				'intent_args'             => [
					'status'               => Payment_Intent_Status::REQUIRES_PAYMENT_METHOD,
					'payment_method_types' => [ 'card' ],
				],
				'expected_fraud_meta_box' => Fraud_Meta_Box_Type::PAYMENT_STARTED,
			],
			'mark_payment_started_intent_status_requires_action_not_card' => [
				'intent_args'             => [
					'status'               => Payment_Intent_Status::REQUIRES_ACTION,
					'payment_method_types' => [ 'bancontact' ],
				],
				'expected_fraud_meta_box' => Fraud_Meta_Box_Type::NOT_CARD,
			],
		];
	}

	/**
	 * Tests if mark_payment_started exits if the order status is not Peding.
	 * Public method update_order_status_from_intent calls private method mark_payment_started.
	 */
	public function test_mark_payment_started_exits_on_existing_order_status_not_pending() {
		// Arrange: Create intention with provided args, update order status.
		$intent = WC_Helper_Intention::create_intention( [ 'status' => Payment_Intent_Status::REQUIRES_ACTION ] );
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
		$intent = WC_Helper_Intention::create_intention( [ 'status' => Payment_Intent_Status::REQUIRES_ACTION ] );
		update_option( 'wcpay_fraud_protection_settings_active', 0 );

		// Act: Attempt to mark the payment started.
		$this->order_service->update_order_status_from_intent( $this->order, $intent );

		// Assert: Check to make sure the intent_status meta was not set.
		$this->assertEquals( Payment_Intent_Status::REQUIRES_ACTION, $this->order_service->get_intention_status_for_order( $this->order ) );

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
		$intent  = WC_Helper_Intention::create_intention( [ 'status' => Payment_Intent_Status::REQUIRES_ACTION ] );
		$charge  = $intent->get_charge();
		$message = 'This is the test failed message.';

		// Act: Attempt to mark the payment/order failed.
		$this->order_service->mark_payment_failed( $this->order, $intent->get_id(), $intent->get_status(), $charge->get_id(), $message );

		// Assert: Check to make sure the intent_status meta was set.
		$this->assertEquals( Payment_Intent_Status::REQUIRES_ACTION, $this->order_service->get_intention_status_for_order( $this->order ) );

		// Assert: Check that the order status was updated to failed status.
		$this->assertTrue( $this->order->has_status( Order_Status::FAILED ) );

		// Assert: Check that the notes were updated.
		$notes = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );
		$this->assertStringContainsString( 'Pending payment to Failed', $notes[1]->content );
		$this->assertStringContainsString( 'failed</strong> using WooPayments', $notes[0]->content );
		$this->assertStringContainsString( '/payments/transactions/details&id=pi_mock" target="_blank" rel="noopener noreferrer">pi_mock', $notes[0]->content );
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
		$intent = WC_Helper_Intention::create_intention( [ 'status' => Payment_Intent_Status::REQUIRES_ACTION ] );
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
		$intent = WC_Helper_Intention::create_intention( [ 'status' => Payment_Intent_Status::REQUIRES_ACTION ] );
		$charge = $intent->get_charge();
		if ( $fraud_outcome ) {
			$this->order_service->set_fraud_outcome_status_for_order( $this->order, $fraud_outcome );
		}

		// Act: Attempt to mark the payment capture failed.
		$this->order_service->mark_payment_capture_failed( $this->order, $intent->get_id(), $intent->get_status(), $charge->get_id() );

		// Assert: Check to make sure the intent_status meta was set.
		$this->assertEquals( Payment_Intent_Status::REQUIRES_ACTION, $this->order_service->get_intention_status_for_order( $this->order ) );

		// Assert: Confirm that the fraud outcome status has not been changed, and that the fraud meta box type meta was set correctly.
		$this->assertEquals( $expected_fraud_outcome, $this->order_service->get_fraud_outcome_status_for_order( $this->order ) );
		$this->assertEquals( $expected_fraud_meta_box, $this->order_service->get_fraud_meta_box_type_for_order( $this->order ) );

		// Assert: Check that the order status was not updated.
		$this->assertTrue( $this->order->has_status( [ Order_Status::PENDING ] ) );

		// Assert: Check that the notes were updated.
		$notes = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );
		$this->assertStringContainsString( 'failed</strong> to complete using WooPayments', $notes[0]->content );
		$this->assertStringContainsString( '/payments/transactions/details&id=pi_mock" target="_blank" rel="noopener noreferrer">pi_mock', $notes[0]->content );

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
		$this->assertStringContainsString( '/payments/transactions/details&id=pi_mock" target="_blank" rel="noopener noreferrer">pi_mock', $notes[0]->content );

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
		$intent            = WC_Helper_Intention::create_intention( [ 'status' => Payment_Intent_Status::CANCELED ] );
		$order_status      = Order_Status::FAILED;
		$wc_order_statuses = wc_get_order_statuses();
		$charge            = $intent->get_charge();
		if ( $fraud_outcome ) {
			$this->order_service->set_fraud_outcome_status_for_order( $this->order, $fraud_outcome );
		}

		// Act: Attempt to mark the payment/order expired/failed.
		$this->order_service->mark_payment_capture_expired( $this->order, $intent->get_id(), $intent->get_status(), $charge->get_id() );

		// Assert: Check to make sure the intent_status meta was set.
		$this->assertEquals( Payment_Intent_Status::CANCELED, $this->order_service->get_intention_status_for_order( $this->order ) );

		// Assert: Confirm that the fraud outcome status has not been changed, and that the fraud meta box type meta was set correctly.
		$this->assertEquals( $expected_fraud_outcome, $this->order_service->get_fraud_outcome_status_for_order( $this->order ) );
		$this->assertEquals( $expected_fraud_meta_box, $this->order_service->get_fraud_meta_box_type_for_order( $this->order ) );

		// Assert: Check that the order status was updated to cancelled status.
		$this->assertTrue( $this->order->has_status( [ $order_status ] ) );

		// Assert: Check that the notes were updated.
		$notes = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );
		$this->assertStringContainsString( 'Pending payment to ' . $wc_order_statuses['wc-failed'], $notes[1]->content );
		$this->assertStringContainsString( 'Payment authorization has <strong>expired</strong>', $notes[0]->content );
		$this->assertStringContainsString( '/payments/transactions/details&id=pi_mock" target="_blank" rel="noopener noreferrer">pi_mock', $notes[0]->content );

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
		$this->assertEquals( Payment_Intent_Status::CANCELED, $this->order_service->get_intention_status_for_order( $this->order ) );

		// Assert: Confirm that the fraud outcome status has not been changed, and that the fraud meta box type meta was set correctly.
		$this->assertEquals( $expected_fraud_outcome, $this->order_service->get_fraud_outcome_status_for_order( $this->order ) );
		$this->assertEquals( $expected_fraud_meta_box, $this->order_service->get_fraud_meta_box_type_for_order( $this->order ) );

		// Assert: Check that the order status was updated to cancelled status.
		$this->assertTrue( $this->order->has_status( [ $order_status ] ) );

		// Assert: Check that the notes were updated.
		$notes = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );
		$this->assertStringContainsString( 'Pending payment to ' . $wc_order_statuses['wc-cancelled'], $notes[1]->content );
		$this->assertStringContainsString( 'Payment authorization was successfully <strong>cancelled</strong>', $notes[0]->content );

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
					'status' => Payment_Intent_Status::CANCELED,
				],
				'order_fraud_outcome'     => false,
				'expected_fraud_outcome'  => '',
				'expected_fraud_meta_box' => '',
			],
			'mark_payment_cancelled_outcome_review_meta_box_blocked' => [
				'intent_args'             => [
					'status'   => Payment_Intent_Status::CANCELED,
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
		$this->order_service->mark_order_blocked_for_fraud( $this->order, 'pi_mock', Payment_Intent_Status::CANCELED );

		// Assert: Check to make sure the intent_status meta was set.
		$this->assertEquals( Payment_Intent_Status::CANCELED, $this->order_service->get_intention_status_for_order( $this->order ) );

		// Assert: Confirm that the fraud outcome status and fraud meta box type meta were not set.
		$this->assertEquals( Rule::FRAUD_OUTCOME_BLOCK, $this->order_service->get_fraud_outcome_status_for_order( $this->order ) );
		$this->assertEquals( Fraud_Meta_Box_Type::BLOCK, $this->order_service->get_fraud_meta_box_type_for_order( $this->order ) );

		// Assert: Check that the order status was has not been updated.
		$this->assertTrue( $this->order->has_status( Order_Status::PENDING ) );

		// Assert: Check that the notes were updated.
		$notes = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );
		$this->assertStringContainsString( 'blocked</strong> by one or more risk filters', $notes[0]->content );
		$this->assertStringContainsString( '/payments/transactions/details&id=' . $this->order->get_id() . '&status_is=block&type_is=order_note" target="_blank" rel="noopener noreferrer">View more details', $notes[0]->content );

		// Assert: Check that the order was unlocked.
		$this->assertFalse( get_transient( 'wcpay_processing_intent_' . $this->order->get_id() ) );

		// Assert: Applying the same data multiple times does not cause duplicate actions.
		$this->order_service->mark_order_blocked_for_fraud( $this->order, 'pi_mock', Payment_Intent_Status::CANCELED );
		$notes_2 = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );
		$this->assertCount( 1, $notes_2 );
	}

	/**
	 * Tests if the payment was updated to show dispute created.
	 */
	public function test_mark_payment_dispute_created() {
		// Arrange: Set the dispute_id and reason, and the order status.
		$dispute_id   = 'dp_123';
		$amount       = '$123.45';
		$reason       = 'product_not_received';
		$deadline     = 'June 7, 2023';
		$order_status = Order_Status::ON_HOLD;

		// Act: Attempt to mark payment dispute created.
		$this->order_service->mark_payment_dispute_created( $this->order, $dispute_id, $amount, $reason, $deadline );

		// Assert: Check that the order status was updated to on-hold status.
		$this->assertTrue( $this->order->has_status( [ $order_status ] ) );

		$notes = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );

		// Assert: Check that dispute order note was added with relevant info and link to dispute detail.
		$this->assertStringContainsString( 'Payment has been disputed', $notes[0]->content );
		$this->assertStringContainsString( $amount, $notes[0]->content );
		$this->assertStringContainsString( 'Product not received', $notes[0]->content );
		$this->assertStringContainsString( $deadline, $notes[0]->content );
		$this->assertStringContainsString( '/payments/disputes/details&id=dp_123" target="_blank" rel="noopener noreferrer">Response due by', $notes[0]->content );

		// Assert: Check that order status change note was added.
		$this->assertStringContainsString( 'Pending payment to On hold', $notes[1]->content );

		// Assert: Applying the same data multiple times does not cause duplicate actions.
		$this->order_service->mark_payment_dispute_created( $this->order, $dispute_id, $amount, $reason, $deadline );
		$notes_2 = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );
		$this->assertCount( 2, $notes_2 );
	}

	/**
	 * Tests to make sure mark_payment_dispute_created exits if the order is invalid.
	 */
	public function test_mark_payment_dispute_created_exits_if_order_invalid() {
		// Arrange: Set the dispute_id and reason, and the order status.
		$dispute_id = 'dp_123';
		$amount     = '$123.45';
		$reason     = 'product_not_received';
		$deadline   = 'June 7, 2023';

		$order_status   = $this->order->get_status();
		$expected_notes = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );

		// Act: Attempt to mark payment dispute created.
		$this->order_service->mark_payment_dispute_created( 'fake_order', $dispute_id, $amount, $reason, $deadline );

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
	 * Tests to make sure mark_payment_dispute_closed exits if the order is invalid.
	 */
	public function test_mark_payment_dispute_closed_exits_if_order_invalid() {
		// Arrange: Set the dispute_id and reason, and the order status.
		$dispute_id     = 'dp_123';
		$status         = 'won';
		$order_status   = $this->order->get_status();
		$expected_notes = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );

		// Act: Attempt to mark payment dispute created.
		$this->order_service->mark_payment_dispute_closed( 'fake_order', $dispute_id, $status );

		// Assert: Check that the order status was not updated.
		$this->assertTrue( $this->order->has_status( [ $order_status ] ) );

		// Assert: Confirm the notes were not updated.
		$updated_notes = wc_get_order_notes( [ 'order_id' => $this->order->get_id() ] );
		$this->assertEquals( $expected_notes, $updated_notes );
	}

	/**
	 * Tests if the order was completed successfully.
	 */
	public function test_mark_terminal_payment_completed() {
		// Arrange: Create the intent and set the order status.
		$intent       = WC_Helper_Intention::create_intention( [ 'status' => Payment_Intent_Status::SUCCEEDED ] );
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

	public function test_set_payment_method_id() {
		$payment_method = 'pm_mock';
		$this->order_service->set_payment_method_id_for_order( $this->order, $payment_method );
		$this->assertEquals( $this->order->get_meta( '_payment_method_id', true ), $payment_method );
		$this->assertSame( 0, did_action( 'wcpay_order_intent_id_updated' ) );
		$this->assertSame( 1, did_action( 'wcpay_order_payment_method_id_updated' ) );
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
		$this->order_service->set_wcpay_refund_id_for_order( $this->order, $wcpay_refund_id );
		$this->assertEquals( $this->order->get_meta( '_wcpay_refund_id', true ), $wcpay_refund_id );
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

	public function test_attach_intent_info_to_order() {
		$intent_id      = 'pi_mock';
		$intent_status  = 'succeeded';
		$payment_method = 'woocommerce_payments';
		$customer_id    = 'cus_12345';
		$charge_id      = 'ch_mock';
		$currency       = 'USD';
		$this->order_service->attach_intent_info_to_order( $this->order, $intent_id, $intent_status, $payment_method, $customer_id, $charge_id, $currency );

		$this->assertEquals( $intent_id, $this->order->get_meta( '_intent_id', true ) );
	}

	/**
	 * Several methods use the private method get_order to get the order being worked on. If an order is not found
	 * then an exception is thrown. This test attempt to confirm that exception gets thrown.
	 */
	public function test_get_order_throws_exception() {
		$this->expectException( Order_Not_Found_Exception::class );
		$this->expectExceptionMessage( 'The requested order was not found.' );
		$this->order_service->set_intent_id_for_order( 'fake_order', '' );
	}

	public function test_attach_transaction_fee_to_order() {
		$order = WC_Helper_Order::create_order();
		$this->order_service->attach_transaction_fee_to_order( $order, new WC_Payments_API_Charge( 'ch_mock', 1500, new DateTime(), null, null, null, null, 113, [], [], 'usd' ) );
		$this->assertEquals( 1.13, $order->get_meta( '_wcpay_transaction_fee', true ) );
	}

	public function test_attach_transaction_fee_to_order_zero_fee() {
		$order = WC_Helper_Order::create_order();
		$this->order_service->attach_transaction_fee_to_order( $order, new WC_Payments_API_Charge( 'ch_mock', 1500, new DateTime(), null, null, null, null, 0, [], [], 'eur' ) );
		$this->assertEquals( 0, $order->get_meta( '_wcpay_transaction_fee', true ) );
	}

	public function test_attach_transaction_fee_to_order_zero_decimal_fee() {
		$order = WC_Helper_Order::create_order();
		$this->order_service->attach_transaction_fee_to_order( $order, new WC_Payments_API_Charge( 'ch_mock', 1500, new DateTime(), null, null, null, null, 30000, [], [], 'jpy' ) );
		$this->assertEquals( 30000, $order->get_meta( '_wcpay_transaction_fee', true ) );
	}

	public function test_attach_transaction_fee_to_order_null_fee() {
		$mock_order = $this->createMock( 'WC_Order' );
		$mock_order
			->expects( $this->never() )
			->method( 'update_meta_data' );
		$this->order_service->attach_transaction_fee_to_order( $mock_order, new WC_Payments_API_Charge( 'ch_mock', 1500, new DateTime(), null, null, null, null, null, [], [], 'eur' ) );
	}
}
