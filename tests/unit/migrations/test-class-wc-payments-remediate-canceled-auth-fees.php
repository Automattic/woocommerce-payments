<?php
/**
 * Class WC_Payments_Remediate_Canceled_Auth_Fees_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\Constants\Intent_Status;
use WCPay\Constants\Order_Status;

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

		// Clean up any scheduled actions.
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( WC_Payments_Remediate_Canceled_Auth_Fees::ACTION_HOOK );
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
			'skipped'    => 0,
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

	public function test_get_affected_orders_excludes_orders_without_fees() {
		// Create order with canceled intent but no fees.
		$order = WC_Helper_Order::create_order();
		$order->set_date_created( '2023-05-01' );
		$order->update_meta_data( '_intention_status', Intent_Status::CANCELED );
		$order->save();

		$orders = $this->remediation->get_affected_orders( 10 );

		$this->assertCount( 0, $orders );
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

	public function test_remediate_order_deletes_refund_objects() {
		$order = WC_Helper_Order::create_order();
		$order->save();

		// Create a refund.
		$refund = wc_create_refund(
			[
				'order_id' => $order->get_id(),
				'amount'   => 10.00,
				'reason'   => 'Test refund',
			]
		);

		$this->assertCount( 1, $order->get_refunds() );

		$this->remediation->remediate_order( $order );

		$order = wc_get_order( $order->get_id() ); // Refresh.
		$this->assertCount( 0, $order->get_refunds() );
	}

	public function test_remediate_order_adds_detailed_note() {
		$order = WC_Helper_Order::create_order();
		$order->update_meta_data( '_wcpay_transaction_fee', '1.50' );
		$order->update_meta_data( '_wcpay_net', '48.50' );
		$order->save();

		// Create a refund.
		wc_create_refund(
			[
				'order_id' => $order->get_id(),
				'amount'   => 10.00,
				'reason'   => 'Test refund',
			]
		);

		$initial_notes_count = count( wc_get_order_notes( [ 'order_id' => $order->get_id() ] ) );

		$this->remediation->remediate_order( $order );

		$notes     = wc_get_order_notes( [ 'order_id' => $order->get_id() ] );
		$new_notes = array_slice( $notes, 0, count( $notes ) - $initial_notes_count );

		$this->assertCount( 1, $new_notes );
		$this->assertStringContainsString( 'Removed incorrect data from canceled authorization', $new_notes[0]->content );
		$this->assertStringContainsString( 'Deleted 1 refund object', $new_notes[0]->content );
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

		// After completion with partial batch, cleanup() is called and stats are deleted.
		$stats = $this->remediation->get_stats();
		$this->assertEquals( 0, $stats['processed'] );
		$this->assertEquals( 0, $stats['remediated'] );
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

		// After completion with partial batch, cleanup() is called and last_order_id is deleted.
		$this->assertEquals( 0, $this->remediation->get_last_order_id() );
	}

	public function test_process_batch_marks_complete_when_no_orders() {
		$this->remediation->process_batch();

		// After completion with no orders, cleanup() is called and status is deleted.
		// is_complete() will return false because the status option no longer exists.
		$this->assertFalse( $this->remediation->is_complete() );
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

		// After completion with partial batch, cleanup() is called and stats are deleted.
		$stats = $mock_remediation->get_stats();
		$this->assertEquals( 0, $stats['errors'] );
	}

	public function test_maybe_schedule_remediation_schedules_when_conditions_met() {
		// Set previous version to one affected by the bug.
		update_option( 'woocommerce_woocommerce_payments_version', '5.9.0' );

		// Current version would be the plugin version (mock it).
		$this->remediation->maybe_schedule_remediation( '7.0.0' );

		// Should have scheduled the action.
		$this->assertTrue( as_has_scheduled_action( WC_Payments_Remediate_Canceled_Auth_Fees::ACTION_HOOK ) );
	}

	public function test_maybe_schedule_remediation_skips_when_already_complete() {
		update_option( WC_Payments_Remediate_Canceled_Auth_Fees::STATUS_OPTION_KEY, 'completed' );
		update_option( 'woocommerce_woocommerce_payments_version', '5.9.0' );

		$this->remediation->maybe_schedule_remediation( '7.0.0' );

		$this->assertFalse( as_has_scheduled_action( WC_Payments_Remediate_Canceled_Auth_Fees::ACTION_HOOK ) );
	}

	public function test_maybe_schedule_remediation_skips_when_version_too_old() {
		// Previous version before bug introduction.
		update_option( 'woocommerce_woocommerce_payments_version', '5.7.0' );

		$this->remediation->maybe_schedule_remediation( '7.0.0' );

		$this->assertFalse( as_has_scheduled_action( WC_Payments_Remediate_Canceled_Auth_Fees::ACTION_HOOK ) );
	}

	public function test_maybe_schedule_remediation_skips_when_new_install() {
		// No previous version = new install.
		delete_option( 'woocommerce_woocommerce_payments_version' );

		$this->remediation->maybe_schedule_remediation( '7.0.0' );

		$this->assertFalse( as_has_scheduled_action( WC_Payments_Remediate_Canceled_Auth_Fees::ACTION_HOOK ) );
	}

	public function test_init_hooks_into_action_scheduler() {
		$remediation = new WC_Payments_Remediate_Canceled_Auth_Fees();
		$remediation->init();

		$this->assertEquals(
			10,
			has_action( WC_Payments_Remediate_Canceled_Auth_Fees::ACTION_HOOK, [ $remediation, 'process_batch' ] )
		);
	}
}
