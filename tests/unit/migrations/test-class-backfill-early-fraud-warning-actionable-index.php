<?php
/**
 * Class Backfill_Early_Fraud_Warning_Actionable_Index_Test
 *
 * @package WooCommerce\Payments\Tests
 */

namespace WCPay\Migrations;

use PHPUnit\Framework\MockObject\MockObject;
use WC_Helper_Order;
use WC_Order;
use WC_Payments;
use WC_Payments_Action_Scheduler_Service;
use WC_Payments_API_Client;
use WC_Payments_Order_Service;
use WCPAY_UnitTestCase;

/**
 * WCPay\Migrations\Backfill_Early_Fraud_Warning_Actionable_Index unit tests.
 */
class Backfill_Early_Fraud_Warning_Actionable_Index_Test extends WCPAY_UnitTestCase {

	const WARNING_CREATED = 1719800000;

	/**
	 * Order service, real so the tests read what the Overview query reads.
	 *
	 * @var WC_Payments_Order_Service
	 */
	private $order_service;

	/**
	 * Action Scheduler service.
	 *
	 * @var WC_Payments_Action_Scheduler_Service|MockObject
	 */
	private $action_scheduler_service;

	/**
	 * Migration under test.
	 *
	 * @var Backfill_Early_Fraud_Warning_Actionable_Index
	 */
	private $migration;

	public function set_up() {
		parent::set_up();

		$this->order_service            = new WC_Payments_Order_Service( $this->createMock( WC_Payments_API_Client::class ) );
		$this->action_scheduler_service = $this->createMock( WC_Payments_Action_Scheduler_Service::class );
		$this->migration                = new Backfill_Early_Fraud_Warning_Actionable_Index(
			$this->order_service,
			$this->action_scheduler_service,
			WC_Payments::get_database_cache()
		);
	}

	public function tear_down() {
		delete_option( 'woocommerce_woocommerce_payments_version' );
		delete_option( 'wcpay_early_fraud_warning_orders_cache' );
		delete_option( 'wcpay_test_early_fraud_warning_orders_cache' );
		parent::tear_down();
	}

	public function test_upgrade_from_release_without_index_schedules_first_batch() {
		update_option( 'woocommerce_woocommerce_payments_version', '11.2.0' );

		$this->action_scheduler_service
			->expects( $this->once() )
			->method( 'schedule_job' )
			->with( $this->anything(), 'wcpay_backfill_early_fraud_warning_actionable_index', [ 1 ] );

		$this->migration->maybe_migrate();
	}

	public function test_upgrade_from_release_with_index_does_not_schedule() {
		update_option( 'woocommerce_woocommerce_payments_version', '11.3.0' );

		$this->action_scheduler_service->expects( $this->never() )->method( 'schedule_job' );

		$this->migration->maybe_migrate();
	}

	public function test_batch_indexes_legacy_actionable_warning_for_the_overview() {
		$legacy_order = $this->create_order_with_legacy_warning( true );

		$this->migration->process_batch( 1 );

		$this->assertSame(
			'1719800000',
			(string) wc_get_order( $legacy_order->get_id() )->get_meta( '_wcpay_early_fraud_warning_actionable', true )
		);
		$this->assertSame(
			[ $legacy_order->get_id() ],
			array_column( $this->get_live_actionable_orders(), 'order_id' )
		);
	}

	public function test_batch_skips_resolved_and_unflagged_orders() {
		$resolved_order  = $this->create_order_with_legacy_warning( false );
		$unflagged_order = WC_Helper_Order::create_order();

		$this->migration->process_batch( 1 );

		$this->assertFalse( wc_get_order( $resolved_order->get_id() )->meta_exists( '_wcpay_early_fraud_warning_actionable' ) );
		$this->assertFalse( wc_get_order( $unflagged_order->get_id() )->meta_exists( '_wcpay_early_fraud_warning_actionable' ) );
	}

	public function test_batch_keeps_index_written_by_a_newer_webhook() {
		$newer_created = self::WARNING_CREATED + 3600;
		$order         = $this->create_order_with_legacy_warning( true );
		$order->update_meta_data( '_wcpay_early_fraud_warning_actionable', $newer_created );
		$order->save_meta_data();

		$this->migration->process_batch( 1 );

		$this->assertSame(
			'1719803600',
			(string) wc_get_order( $order->get_id() )->get_meta( '_wcpay_early_fraud_warning_actionable', true )
		);
	}

	public function test_short_batch_clears_both_caches_and_stops() {
		$this->create_order_with_legacy_warning( true );
		update_option( 'wcpay_early_fraud_warning_orders_cache', [ 'data' => [] ] );
		update_option( 'wcpay_test_early_fraud_warning_orders_cache', [ 'data' => [] ] );

		$this->action_scheduler_service->expects( $this->never() )->method( 'schedule_job' );

		$this->migration->process_batch( 1 );

		$this->assertFalse( get_option( 'wcpay_early_fraud_warning_orders_cache' ) );
		$this->assertFalse( get_option( 'wcpay_test_early_fraud_warning_orders_cache' ) );
	}

	public function test_full_batch_schedules_next_page_and_keeps_caches() {
		for ( $i = 0; $i < Backfill_Early_Fraud_Warning_Actionable_Index::BATCH_SIZE; $i++ ) {
			$this->create_order_with_legacy_warning( false, wc_create_order() );
		}
		update_option( 'wcpay_early_fraud_warning_orders_cache', [ 'data' => [] ] );

		$this->action_scheduler_service
			->expects( $this->once() )
			->method( 'schedule_job' )
			->with( $this->anything(), 'wcpay_backfill_early_fraud_warning_actionable_index', [ 2 ] );

		$this->migration->process_batch( 1 );

		$this->assertSame( [ 'data' => [] ], get_option( 'wcpay_early_fraud_warning_orders_cache' ) );
	}

	public function test_second_page_indexes_orders_past_the_first() {
		for ( $i = 0; $i < Backfill_Early_Fraud_Warning_Actionable_Index::BATCH_SIZE; $i++ ) {
			$this->create_order_with_legacy_warning( false, wc_create_order() );
		}
		$second_page_order = $this->create_order_with_legacy_warning( true, wc_create_order() );

		$this->migration->process_batch( 2 );

		$this->assertTrue( wc_get_order( $second_page_order->get_id() )->meta_exists( '_wcpay_early_fraud_warning_actionable' ) );
	}

	/**
	 * Stores a warning the way 11.1.0 and 11.2.0 did: the warning array without the actionable index.
	 *
	 * @param bool          $actionable Whether the warning is still actionable.
	 * @param WC_Order|null $order      The order to flag; a new order when omitted.
	 *
	 * @return WC_Order
	 */
	private function create_order_with_legacy_warning( bool $actionable, ?WC_Order $order = null ): WC_Order {
		$order = $order ?? WC_Helper_Order::create_order();
		$order->update_meta_data(
			'_wcpay_early_fraud_warning',
			[
				'efw_id'         => 'issfr_legacy',
				'efw_actionable' => $actionable,
				'efw_type'       => 'made_with_stolen_card',
				'created'        => self::WARNING_CREATED,
			]
		);
		$order->save();

		return $order;
	}

	/**
	 * Reads the Overview list in live mode, which orders without mode meta belong to.
	 *
	 * @return array[]
	 */
	private function get_live_actionable_orders(): array {
		$was_test_mode = WC_Payments::mode()->is_test();
		WC_Payments::mode()->live();

		try {
			return $this->order_service->get_actionable_early_fraud_warning_orders();
		} finally {
			$was_test_mode ? WC_Payments::mode()->test() : WC_Payments::mode()->live();
		}
	}
}
