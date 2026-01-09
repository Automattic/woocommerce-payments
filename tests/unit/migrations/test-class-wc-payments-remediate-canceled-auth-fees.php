<?php
/**
 * Class WC_Payments_Remediate_Canceled_Auth_Fees_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\Constants\Intent_Status;

/**
 * WC_Payments_Remediate_Canceled_Auth_Fees unit tests.
 */
class WC_Payments_Remediate_Canceled_Auth_Fees_Test extends WCPAY_UnitTestCase {

	/**
	 * System under test.
	 *
	 * @var WC_Payments_Remediate_Canceled_Auth_Fees
	 */
	private $remediation;

	/**
	 * Set up test.
	 */
	public function set_up() {
		parent::set_up();
		$this->remediation = new WC_Payments_Remediate_Canceled_Auth_Fees();

		// Clean up options before each test.
		delete_option( WC_Payments_Remediate_Canceled_Auth_Fees::STATUS_OPTION_KEY );
		delete_option( WC_Payments_Remediate_Canceled_Auth_Fees::LAST_ORDER_ID_OPTION_KEY );
		delete_option( WC_Payments_Remediate_Canceled_Auth_Fees::BATCH_SIZE_OPTION_KEY );
		delete_option( WC_Payments_Remediate_Canceled_Auth_Fees::STATS_OPTION_KEY );
		delete_option( WC_Payments_Remediate_Canceled_Auth_Fees::DRY_RUN_OPTION_KEY );
	}

	/**
	 * Tear down test.
	 */
	public function tear_down() {
		// Clean up options after each test.
		delete_option( WC_Payments_Remediate_Canceled_Auth_Fees::STATUS_OPTION_KEY );
		delete_option( WC_Payments_Remediate_Canceled_Auth_Fees::LAST_ORDER_ID_OPTION_KEY );
		delete_option( WC_Payments_Remediate_Canceled_Auth_Fees::BATCH_SIZE_OPTION_KEY );
		delete_option( WC_Payments_Remediate_Canceled_Auth_Fees::STATS_OPTION_KEY );
		delete_option( WC_Payments_Remediate_Canceled_Auth_Fees::DRY_RUN_OPTION_KEY );

		// Clean up any scheduled actions.
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( WC_Payments_Remediate_Canceled_Auth_Fees::ACTION_HOOK );
			as_unschedule_all_actions( WC_Payments_Remediate_Canceled_Auth_Fees::DRY_RUN_ACTION_HOOK );
		}

		parent::tear_down();
	}

	public function test_class_exists() {
		$this->assertInstanceOf( WC_Payments_Remediate_Canceled_Auth_Fees::class, $this->remediation );
	}

	public function test_is_complete_returns_false_when_not_started() {
		$this->assertFalse( $this->remediation->is_complete() );
	}

	public function test_is_complete_returns_true_when_marked_complete() {
		update_option( WC_Payments_Remediate_Canceled_Auth_Fees::STATUS_OPTION_KEY, 'completed' );
		$this->assertTrue( $this->remediation->is_complete() );
	}

	public function test_get_batch_size_returns_initial_size_when_not_set() {
		$this->assertEquals( 20, $this->remediation->get_batch_size() );
	}

	public function test_update_batch_size_stores_value() {
		$this->remediation->update_batch_size( 50 );
		$this->assertEquals( 50, $this->remediation->get_batch_size() );
	}

	public function test_get_last_order_id_returns_zero_when_not_set() {
		$this->assertEquals( 0, $this->remediation->get_last_order_id() );
	}

	public function test_update_last_order_id_stores_value() {
		$this->remediation->update_last_order_id( 123 );
		$this->assertEquals( 123, $this->remediation->get_last_order_id() );
	}

	public function test_get_stats_returns_empty_array_when_not_set() {
		$expected = [
			'processed'  => 0,
			'remediated' => 0,
			'errors'     => 0,
		];
		$this->assertEquals( $expected, $this->remediation->get_stats() );
	}

	public function test_increment_stat_updates_counter() {
		$this->remediation->increment_stat( 'processed' );
		$this->remediation->increment_stat( 'processed' );
		$stats = $this->remediation->get_stats();
		$this->assertEquals( 2, $stats['processed'] );
	}

	public function test_get_affected_orders_returns_canceled_orders_with_fees() {
		// Create order with canceled intent and fees.
		$order = WC_Helper_Order::create_order();
		$order->set_date_created( '2023-05-01' );
		$order->update_meta_data( '_intention_status', Intent_Status::CANCELED );
		$order->update_meta_data( '_wcpay_transaction_fee', '1.50' );
		$order->save();

		$orders = $this->remediation->get_affected_orders( 10 );

		$this->assertCount( 1, $orders );
		$this->assertEquals( $order->get_id(), $orders[0]->get_id() );
	}

	public function test_get_affected_orders_excludes_orders_before_bug_date() {
		// Create order before bug introduction.
		$order = WC_Helper_Order::create_order();
		$order->set_date_created( '2023-03-01' );
		$order->update_meta_data( '_intention_status', Intent_Status::CANCELED );
		$order->update_meta_data( '_wcpay_transaction_fee', '1.50' );
		$order->save();

		$orders = $this->remediation->get_affected_orders( 10 );

		$this->assertCount( 0, $orders );
	}

	public function test_get_affected_orders_excludes_orders_without_canceled_status() {
		// Create order with succeeded intent.
		$order = WC_Helper_Order::create_order();
		$order->set_date_created( '2023-05-01' );
		$order->update_meta_data( '_intention_status', Intent_Status::SUCCEEDED );
		$order->update_meta_data( '_wcpay_transaction_fee', '1.50' );
		$order->save();

		$orders = $this->remediation->get_affected_orders( 10 );

		$this->assertCount( 0, $orders );
	}

	public function test_get_affected_orders_excludes_orders_without_fees_or_refunds() {
		// Create order with canceled intent but no fees and no refunds.
		$order = WC_Helper_Order::create_order();
		$order->set_date_created( '2023-05-01' );
		$order->update_meta_data( '_intention_status', Intent_Status::CANCELED );
		$order->save();

		$orders = $this->remediation->get_affected_orders( 10 );

		$this->assertCount( 0, $orders );
	}

	public function test_get_affected_orders_finds_orders_with_refunds_but_no_fees() {
		// Create order with canceled intent and refund, but no fee metadata.
		$order = WC_Helper_Order::create_order();
		$order->set_date_created( '2023-05-01' );
		$order->update_meta_data( '_intention_status', Intent_Status::CANCELED );
		$order->save();

		// Create a refund for this order.
		wc_create_refund(
			[
				'order_id' => $order->get_id(),
				'amount'   => 10.00,
				'reason'   => 'Test refund',
			]
		);

		$orders = $this->remediation->get_affected_orders( 10 );

		$this->assertCount( 1, $orders );
		$this->assertEquals( $order->get_id(), $orders[0]->get_id() );
	}

	public function test_get_affected_orders_respects_batch_size() {
		// Create 5 affected orders.
		for ( $i = 0; $i < 5; $i++ ) {
			$order = WC_Helper_Order::create_order();
			$order->set_date_created( '2023-05-01' );
			$order->update_meta_data( '_intention_status', Intent_Status::CANCELED );
			$order->update_meta_data( '_wcpay_transaction_fee', '1.50' );
			$order->save();
		}

		$orders = $this->remediation->get_affected_orders( 3 );

		$this->assertCount( 3, $orders );
	}

	public function test_get_affected_orders_uses_offset_from_last_order_id() {
		// Create 3 affected orders.
		$order1 = WC_Helper_Order::create_order();
		$order1->set_date_created( '2023-05-01' );
		$order1->update_meta_data( '_intention_status', Intent_Status::CANCELED );
		$order1->update_meta_data( '_wcpay_transaction_fee', '1.50' );
		$order1->save();

		$order2 = WC_Helper_Order::create_order();
		$order2->set_date_created( '2023-05-02' );
		$order2->update_meta_data( '_intention_status', Intent_Status::CANCELED );
		$order2->update_meta_data( '_wcpay_transaction_fee', '1.50' );
		$order2->save();

		$order3 = WC_Helper_Order::create_order();
		$order3->set_date_created( '2023-05-03' );
		$order3->update_meta_data( '_intention_status', Intent_Status::CANCELED );
		$order3->update_meta_data( '_wcpay_transaction_fee', '1.50' );
		$order3->save();

		// Set last order ID to skip first order.
		$this->remediation->update_last_order_id( $order1->get_id() );

		$orders = $this->remediation->get_affected_orders( 10 );

		$this->assertCount( 2, $orders );
		$this->assertEquals( $order2->get_id(), $orders[0]->get_id() );
	}

	public function test_remediate_order_removes_fee_metadata() {
		$order = WC_Helper_Order::create_order();
		$order->update_meta_data( '_wcpay_transaction_fee', '1.50' );
		$order->update_meta_data( '_wcpay_net', '48.50' );
		$order->save();

		$this->remediation->remediate_order( $order );

		$order = wc_get_order( $order->get_id() ); // Refresh.
		$this->assertEquals( '', $order->get_meta( '_wcpay_transaction_fee', true ) );
		$this->assertEquals( '', $order->get_meta( '_wcpay_net', true ) );
	}

	public function test_remediate_order_deletes_wcpay_refund_objects() {
		$order = WC_Helper_Order::create_order();
		$order->save();

		// Create a WCPay refund (has _wcpay_refund_id metadata).
		$refund = wc_create_refund(
			[
				'order_id' => $order->get_id(),
				'amount'   => 10.00,
				'reason'   => 'Test refund',
			]
		);
		$refund->update_meta_data( '_wcpay_refund_id', 're_test123' );
		$refund->save();

		$this->assertCount( 1, $order->get_refunds() );

		$this->remediation->remediate_order( $order );

		$order = wc_get_order( $order->get_id() ); // Refresh.
		$this->assertCount( 0, $order->get_refunds() );
	}

	public function test_remediate_order_preserves_non_wcpay_refund_objects() {
		$order = WC_Helper_Order::create_order();
		$order->save();

		// Create a non-WCPay refund (no _wcpay_refund_id metadata).
		wc_create_refund(
			[
				'order_id' => $order->get_id(),
				'amount'   => 10.00,
				'reason'   => 'Manual refund',
			]
		);

		$this->assertCount( 1, $order->get_refunds() );

		$this->remediation->remediate_order( $order );

		$order = wc_get_order( $order->get_id() ); // Refresh.
		// Non-WCPay refunds should be preserved.
		$this->assertCount( 1, $order->get_refunds() );
	}

	public function test_remediate_order_deletes_only_wcpay_refunds_among_mixed() {
		$order = WC_Helper_Order::create_order();
		$order->save();

		// Create a WCPay refund.
		$wcpay_refund = wc_create_refund(
			[
				'order_id' => $order->get_id(),
				'amount'   => 10.00,
				'reason'   => 'WCPay refund',
			]
		);
		$wcpay_refund->update_meta_data( '_wcpay_refund_id', 're_test123' );
		$wcpay_refund->save();

		// Create a manual refund.
		wc_create_refund(
			[
				'order_id' => $order->get_id(),
				'amount'   => 5.00,
				'reason'   => 'Manual refund',
			]
		);

		$this->assertCount( 2, $order->get_refunds() );

		$this->remediation->remediate_order( $order );

		$order = wc_get_order( $order->get_id() ); // Refresh.
		// Only the manual refund should remain.
		$refunds = $order->get_refunds();
		$this->assertCount( 1, $refunds );
		$this->assertEquals( 'Manual refund', $refunds[0]->get_reason() );
	}

	public function test_remediate_order_adds_detailed_note() {
		$order = WC_Helper_Order::create_order();
		$order->update_meta_data( '_wcpay_transaction_fee', '1.50' );
		$order->update_meta_data( '_wcpay_net', '48.50' );
		$order->save();

		// Create a WCPay refund (has _wcpay_refund_id metadata).
		$refund = wc_create_refund(
			[
				'order_id' => $order->get_id(),
				'amount'   => 10.00,
				'reason'   => 'Test refund',
			]
		);
		$refund->update_meta_data( '_wcpay_refund_id', 're_test123' );
		$refund->save();

		$initial_notes_count = count( wc_get_order_notes( [ 'order_id' => $order->get_id() ] ) );

		$this->remediation->remediate_order( $order );

		$notes     = wc_get_order_notes( [ 'order_id' => $order->get_id() ] );
		$new_notes = array_slice( $notes, 0, count( $notes ) - $initial_notes_count );

		$this->assertCount( 1, $new_notes );
		$this->assertStringContainsString( 'Removed incorrect data from canceled authorization', $new_notes[0]->content );
		$this->assertStringContainsString( 'WooPayments refund object', $new_notes[0]->content );
		$this->assertStringContainsString( 'transaction fee', $new_notes[0]->content );
	}

	public function test_remediate_order_returns_true_on_success() {
		$order = WC_Helper_Order::create_order();
		$order->update_meta_data( '_wcpay_transaction_fee', '1.50' );
		$order->save();

		$result = $this->remediation->remediate_order( $order );

		$this->assertTrue( $result );
	}

	public function test_remediate_order_handles_missing_fee_gracefully() {
		$order = WC_Helper_Order::create_order();
		$order->save();

		$result = $this->remediation->remediate_order( $order );

		$this->assertTrue( $result );
	}

	public function test_remediate_order_changes_refunded_status_to_cancelled() {
		$order = WC_Helper_Order::create_order();
		$order->set_status( 'refunded' );
		$order->save();

		$this->assertEquals( 'refunded', $order->get_status() );

		$this->remediation->remediate_order( $order );

		$order = wc_get_order( $order->get_id() ); // Refresh.
		$this->assertEquals( 'cancelled', $order->get_status() );
	}

	public function test_remediate_order_does_not_change_non_refunded_status() {
		$order = WC_Helper_Order::create_order();
		$order->set_status( 'on-hold' );
		$order->save();

		$this->assertEquals( 'on-hold', $order->get_status() );

		$this->remediation->remediate_order( $order );

		$order = wc_get_order( $order->get_id() ); // Refresh.
		$this->assertEquals( 'on-hold', $order->get_status() );
	}

	public function test_remediate_order_adds_status_change_to_note() {
		$order = WC_Helper_Order::create_order();
		$order->set_status( 'refunded' );
		$order->save();

		$initial_notes_count = count( wc_get_order_notes( [ 'order_id' => $order->get_id() ] ) );

		$this->remediation->remediate_order( $order );

		$notes     = wc_get_order_notes( [ 'order_id' => $order->get_id() ] );
		$new_notes = array_slice( $notes, 0, count( $notes ) - $initial_notes_count );

		// Check that our remediation note contains the status change info.
		// Note: WooCommerce may add additional notes when status changes.
		$found_remediation_note = false;
		foreach ( $new_notes as $note ) {
			if ( strpos( $note->content, 'Changed order status from "Refunded" to "Cancelled"' ) !== false ) {
				$found_remediation_note = true;
				break;
			}
		}
		$this->assertTrue( $found_remediation_note, 'Remediation note with status change should be present' );
	}

	public function test_get_affected_orders_finds_orders_with_refunded_status() {
		// Create order with canceled intent and refunded status (no fees, no refunds).
		$order = WC_Helper_Order::create_order();
		$order->set_date_created( '2023-05-01' );
		$order->set_status( 'refunded' );
		$order->update_meta_data( '_intention_status', Intent_Status::CANCELED );
		$order->save();

		$orders = $this->remediation->get_affected_orders( 10 );

		$this->assertCount( 1, $orders );
		$this->assertEquals( $order->get_id(), $orders[0]->get_id() );
	}

	public function test_adjust_batch_size_doubles_on_fast_execution() {
		$this->remediation->update_batch_size( 20 );
		$this->remediation->adjust_batch_size( 3 ); // 3 seconds < 5 seconds.

		$this->assertEquals( 40, $this->remediation->get_batch_size() );
	}

	public function test_adjust_batch_size_halves_on_slow_execution() {
		$this->remediation->update_batch_size( 40 );
		$this->remediation->adjust_batch_size( 25 ); // 25 seconds > 20 seconds.

		$this->assertEquals( 20, $this->remediation->get_batch_size() );
	}

	public function test_adjust_batch_size_unchanged_on_good_execution() {
		$this->remediation->update_batch_size( 30 );
		$this->remediation->adjust_batch_size( 10 ); // 10 seconds is between 5 and 20.

		$this->assertEquals( 30, $this->remediation->get_batch_size() );
	}

	public function test_adjust_batch_size_respects_minimum() {
		$this->remediation->update_batch_size( 10 );
		$this->remediation->adjust_batch_size( 25 ); // Try to halve to 5.

		$this->assertEquals( 10, $this->remediation->get_batch_size() ); // Should stay at minimum.
	}

	public function test_adjust_batch_size_respects_maximum() {
		$this->remediation->update_batch_size( 100 );
		$this->remediation->adjust_batch_size( 3 ); // Try to double to 200.

		$this->assertEquals( 100, $this->remediation->get_batch_size() ); // Should stay at maximum.
	}

	public function test_process_batch_remediates_affected_orders() {
		// Create 3 affected orders.
		for ( $i = 0; $i < 3; $i++ ) {
			$order = WC_Helper_Order::create_order();
			$order->set_date_created( '2023-05-01' );
			$order->update_meta_data( '_intention_status', Intent_Status::CANCELED );
			$order->update_meta_data( '_wcpay_transaction_fee', '1.50' );
			$order->save();
		}

		$this->remediation->process_batch();

		// Stats should be preserved after completion for display in Tools page.
		$stats = $this->remediation->get_stats();
		$this->assertEquals( 3, $stats['processed'] );
		$this->assertEquals( 3, $stats['remediated'] );
	}

	public function test_process_batch_updates_last_order_id() {
		$order1 = WC_Helper_Order::create_order();
		$order1->set_date_created( '2023-05-01' );
		$order1->update_meta_data( '_intention_status', Intent_Status::CANCELED );
		$order1->update_meta_data( '_wcpay_transaction_fee', '1.50' );
		$order1->save();

		$order2 = WC_Helper_Order::create_order();
		$order2->set_date_created( '2023-05-02' );
		$order2->update_meta_data( '_intention_status', Intent_Status::CANCELED );
		$order2->update_meta_data( '_wcpay_transaction_fee', '1.50' );
		$order2->save();

		$this->remediation->process_batch();

		// After processing, last_order_id should be set to the highest processed order ID.
		// This is used for pagination in subsequent batches.
		$this->assertEquals( $order2->get_id(), $this->remediation->get_last_order_id() );
	}

	public function test_process_batch_marks_complete_when_no_orders() {
		$this->remediation->process_batch();

		// Status should be preserved as 'completed' for display in Tools page.
		$this->assertTrue( $this->remediation->is_complete() );
	}

	public function test_process_batch_increments_error_count_on_failure() {
		$order = WC_Helper_Order::create_order();
		$order->set_date_created( '2023-05-01' );
		$order->update_meta_data( '_intention_status', Intent_Status::CANCELED );
		$order->update_meta_data( '_wcpay_transaction_fee', '1.50' );
		$order->save();

		// Create a mock to force remediate_order to fail.
		$mock_remediation = $this->getMockBuilder( WC_Payments_Remediate_Canceled_Auth_Fees::class )
			->onlyMethods( [ 'remediate_order' ] )
			->getMock();

		$mock_remediation->method( 'remediate_order' )->willReturn( false );

		$mock_remediation->process_batch();

		// Stats should be preserved after completion for display in Tools page.
		$stats = $mock_remediation->get_stats();
		$this->assertEquals( 1, $stats['errors'] );
	}

	public function test_schedule_remediation_schedules_action() {
		$this->remediation->schedule_remediation();

		// Should have scheduled the action.
		$this->assertTrue( as_has_scheduled_action( WC_Payments_Remediate_Canceled_Auth_Fees::ACTION_HOOK ) );

		// Should have marked as running.
		$this->assertEquals( 'running', get_option( WC_Payments_Remediate_Canceled_Auth_Fees::STATUS_OPTION_KEY ) );
	}

	public function test_has_affected_orders_returns_true_when_orders_exist() {
		// Create an affected order.
		$order = WC_Helper_Order::create_order();
		$order->set_date_created( '2023-05-01' );
		$order->update_meta_data( '_intention_status', Intent_Status::CANCELED );
		$order->update_meta_data( '_wcpay_transaction_fee', '1.50' );
		$order->save();

		$this->assertTrue( $this->remediation->has_affected_orders() );
	}

	public function test_has_affected_orders_returns_false_when_no_orders() {
		$this->assertFalse( $this->remediation->has_affected_orders() );
	}

	public function test_init_hooks_into_action_scheduler() {
		$remediation = new WC_Payments_Remediate_Canceled_Auth_Fees();
		$remediation->init();

		$this->assertEquals(
			10,
			has_action( WC_Payments_Remediate_Canceled_Auth_Fees::ACTION_HOOK, [ $remediation, 'process_batch' ] )
		);
	}

	public function test_get_affected_orders_uses_hpos_when_enabled() {
		// Create a mock that forces HPOS mode.
		$mock_remediation = $this->getMockBuilder( WC_Payments_Remediate_Canceled_Auth_Fees::class )
			->onlyMethods( [ 'is_hpos_enabled' ] )
			->getMock();

		$mock_remediation->method( 'is_hpos_enabled' )->willReturn( true );

		// HPOS tables don't exist in the test environment, so this should return empty.
		// This test verifies the HPOS code path is taken without errors.
		$orders = $mock_remediation->get_affected_orders( 10 );

		$this->assertIsArray( $orders );
	}

	public function test_get_affected_orders_uses_cpt_when_hpos_disabled() {
		// Create a mock that forces CPT mode.
		$mock_remediation = $this->getMockBuilder( WC_Payments_Remediate_Canceled_Auth_Fees::class )
			->onlyMethods( [ 'is_hpos_enabled' ] )
			->getMock();

		$mock_remediation->method( 'is_hpos_enabled' )->willReturn( false );

		// Create order with canceled intent and fees.
		$order = WC_Helper_Order::create_order();
		$order->set_date_created( '2023-05-01' );
		$order->update_meta_data( '_intention_status', Intent_Status::CANCELED );
		$order->update_meta_data( '_wcpay_transaction_fee', '1.50' );
		$order->save();

		$orders = $mock_remediation->get_affected_orders( 10 );

		$this->assertCount( 1, $orders );
		$this->assertEquals( $order->get_id(), $orders[0]->get_id() );
	}

	public function test_is_hpos_enabled_returns_boolean() {
		// Use reflection to test protected method.
		$reflection = new ReflectionMethod( WC_Payments_Remediate_Canceled_Auth_Fees::class, 'is_hpos_enabled' );
		$reflection->setAccessible( true );

		$result = $reflection->invoke( $this->remediation );

		$this->assertIsBool( $result );
	}

	public function test_sync_order_stats_does_not_throw_when_class_unavailable() {
		// Use reflection to test protected method.
		$reflection = new ReflectionMethod( WC_Payments_Remediate_Canceled_Auth_Fees::class, 'sync_order_stats' );
		$reflection->setAccessible( true );

		// This should not throw, even if OrdersStatsDataStore is unavailable.
		$reflection->invoke( $this->remediation, 123 );

		// If we get here without exception, the test passes.
		$this->assertTrue( true );
	}

	public function test_remediate_order_calls_delete_order_stats_for_each_wcpay_refund() {
		$order = WC_Helper_Order::create_order();
		$order->save();

		// Create two WCPay refunds.
		$refund1 = wc_create_refund(
			[
				'order_id' => $order->get_id(),
				'amount'   => 10.00,
				'reason'   => 'Test refund 1',
			]
		);
		$refund1->update_meta_data( '_wcpay_refund_id', 're_test123' );
		$refund1->save();

		$refund2 = wc_create_refund(
			[
				'order_id' => $order->get_id(),
				'amount'   => 5.00,
				'reason'   => 'Test refund 2',
			]
		);
		$refund2->update_meta_data( '_wcpay_refund_id', 're_test456' );
		$refund2->save();

		$refund1_id = $refund1->get_id();
		$refund2_id = $refund2->get_id();

		// Refresh the order to ensure it has the updated refunds with metadata.
		$order = wc_get_order( $order->get_id() );

		// Track which order IDs delete_order_stats is called with.
		$deleted_order_ids = [];

		// Create a mock that tracks delete_order_stats calls.
		$mock_remediation = $this->getMockBuilder( WC_Payments_Remediate_Canceled_Auth_Fees::class )
			->onlyMethods( [ 'delete_order_stats', 'sync_order_stats' ] )
			->getMock();

		// Capture each call to delete_order_stats.
		$mock_remediation->expects( $this->exactly( 2 ) )
			->method( 'delete_order_stats' )
			->willReturnCallback(
				function ( $order_id ) use ( &$deleted_order_ids ) {
					$deleted_order_ids[] = $order_id;
				}
			);

		$mock_remediation->remediate_order( $order );

		// Verify delete_order_stats was called for both refunds.
		$this->assertContains( $refund1_id, $deleted_order_ids, 'delete_order_stats should be called for refund 1' );
		$this->assertContains( $refund2_id, $deleted_order_ids, 'delete_order_stats should be called for refund 2' );
	}

	public function test_remediate_order_calls_sync_order_stats() {
		// Create a mock that tracks if sync_order_stats is called.
		$mock_remediation = $this->getMockBuilder( WC_Payments_Remediate_Canceled_Auth_Fees::class )
			->onlyMethods( [ 'sync_order_stats' ] )
			->getMock();

		$order = WC_Helper_Order::create_order();
		$order->update_meta_data( '_wcpay_transaction_fee', '1.50' );
		$order->save();

		$mock_remediation->expects( $this->once() )
			->method( 'sync_order_stats' )
			->with( $order->get_id() );

		$mock_remediation->remediate_order( $order );
	}

	public function test_remediate_order_fires_woocommerce_refund_deleted_hook() {
		$order = WC_Helper_Order::create_order();
		$order->save();

		// Create a WCPay refund.
		$refund = wc_create_refund(
			[
				'order_id' => $order->get_id(),
				'amount'   => 10.00,
				'reason'   => 'Test refund',
			]
		);
		$refund->update_meta_data( '_wcpay_refund_id', 're_test123' );
		$refund->save();

		$refund_id = $refund->get_id();
		$order_id  = $order->get_id();

		// Track if the hook is fired with correct arguments.
		$hook_fired     = false;
		$hook_refund_id = null;
		$hook_order_id  = null;

		add_action(
			'woocommerce_refund_deleted',
			function ( $fired_refund_id, $fired_order_id ) use ( &$hook_fired, &$hook_refund_id, &$hook_order_id ) {
				$hook_fired     = true;
				$hook_refund_id = $fired_refund_id;
				$hook_order_id  = $fired_order_id;
			},
			10,
			2
		);

		// Create a mock that prevents sync_order_stats and delete_order_stats from running.
		$mock_remediation = $this->getMockBuilder( WC_Payments_Remediate_Canceled_Auth_Fees::class )
			->onlyMethods( [ 'sync_order_stats', 'delete_order_stats' ] )
			->getMock();

		$mock_remediation->remediate_order( $order );

		$this->assertTrue( $hook_fired, 'woocommerce_refund_deleted hook should be fired' );
		$this->assertEquals( $refund_id, $hook_refund_id, 'Hook should receive correct refund ID' );
		$this->assertEquals( $order_id, $hook_order_id, 'Hook should receive correct order ID' );
	}

	// ==================== Dry Run Tests ====================

	public function test_is_dry_run_returns_false_by_default() {
		$this->assertFalse( $this->remediation->is_dry_run() );
	}

	public function test_is_dry_run_returns_true_when_enabled() {
		update_option( WC_Payments_Remediate_Canceled_Auth_Fees::DRY_RUN_OPTION_KEY, true );
		$this->assertTrue( $this->remediation->is_dry_run() );
	}

	public function test_remediate_order_dry_run_does_not_delete_refunds() {
		$order = WC_Helper_Order::create_order();
		$order->save();

		// Create a WCPay refund.
		$refund = wc_create_refund(
			[
				'order_id' => $order->get_id(),
				'amount'   => 10.00,
				'reason'   => 'Test refund',
			]
		);
		$refund->update_meta_data( '_wcpay_refund_id', 're_test123' );
		$refund->save();

		$refund_id = $refund->get_id();

		// Run in dry run mode.
		$this->remediation->remediate_order( $order, true );

		// Refund should still exist.
		$refund_after = wc_get_order( $refund_id );
		$this->assertNotFalse( $refund_after, 'Refund should not be deleted in dry run mode' );
	}

	public function test_remediate_order_dry_run_does_not_remove_metadata() {
		$order = WC_Helper_Order::create_order();
		$order->update_meta_data( '_wcpay_transaction_fee', '1.50' );
		$order->update_meta_data( '_wcpay_net', '48.50' );
		$order->save();

		// Run in dry run mode.
		$this->remediation->remediate_order( $order, true );

		// Reload order.
		$order = wc_get_order( $order->get_id() );

		// Metadata should still exist.
		$this->assertEquals( '1.50', $order->get_meta( '_wcpay_transaction_fee', true ), 'Fee metadata should not be removed in dry run mode' );
		$this->assertEquals( '48.50', $order->get_meta( '_wcpay_net', true ), 'Net metadata should not be removed in dry run mode' );
	}

	public function test_remediate_order_dry_run_does_not_change_status() {
		$order = WC_Helper_Order::create_order();
		$order->set_status( 'refunded' );
		$order->save();

		// Run in dry run mode.
		$this->remediation->remediate_order( $order, true );

		// Reload order.
		$order = wc_get_order( $order->get_id() );

		// Status should still be refunded.
		$this->assertEquals( 'refunded', $order->get_status(), 'Order status should not change in dry run mode' );
	}

	public function test_remediate_order_dry_run_does_not_add_order_note() {
		$order = WC_Helper_Order::create_order();
		$order->update_meta_data( '_wcpay_transaction_fee', '1.50' );
		$order->save();

		$notes_before = wc_get_order_notes( [ 'order_id' => $order->get_id() ] );
		$count_before = count( $notes_before );

		// Run in dry run mode.
		$this->remediation->remediate_order( $order, true );

		$notes_after = wc_get_order_notes( [ 'order_id' => $order->get_id() ] );
		$count_after = count( $notes_after );

		$this->assertEquals( $count_before, $count_after, 'No order note should be added in dry run mode' );
	}

	public function test_remediate_order_dry_run_returns_true() {
		$order = WC_Helper_Order::create_order();
		$order->update_meta_data( '_wcpay_transaction_fee', '1.50' );
		$order->save();

		$result = $this->remediation->remediate_order( $order, true );

		$this->assertTrue( $result, 'Dry run should return true on success' );
	}

	public function test_schedule_dry_run_enables_dry_run_mode() {
		// Use reflection to call the protected method indirectly via schedule_dry_run.
		$this->remediation->schedule_dry_run();

		$this->assertTrue( $this->remediation->is_dry_run(), 'Dry run mode should be enabled after scheduling' );
	}

	public function test_schedule_dry_run_marks_as_running() {
		$this->remediation->schedule_dry_run();

		$status = get_option( WC_Payments_Remediate_Canceled_Auth_Fees::STATUS_OPTION_KEY );
		$this->assertEquals( 'running', $status, 'Status should be running after scheduling dry run' );
	}

	public function test_schedule_remediation_disables_dry_run_mode() {
		// First enable dry run.
		update_option( WC_Payments_Remediate_Canceled_Auth_Fees::DRY_RUN_OPTION_KEY, true );
		$this->assertTrue( $this->remediation->is_dry_run() );

		// Then schedule actual remediation.
		$this->remediation->schedule_remediation();

		$this->assertFalse( $this->remediation->is_dry_run(), 'Dry run mode should be disabled when scheduling actual remediation' );
	}
}
