<?php
/**
 * Class WC_Payments_Dispute_Ledger_Backfill_Service_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\Constants\Order_Status;
use WCPay\Core\Server\Request\List_Disputes;

/**
 * WC_Payments_Dispute_Ledger_Backfill_Service unit tests.
 */
class WC_Payments_Dispute_Ledger_Backfill_Service_Test extends WCPAY_UnitTestCase {

	/**
	 * System under test.
	 *
	 * @var WC_Payments_Dispute_Ledger_Backfill_Service
	 */
	private $service;

	/**
	 * @var WC_Payments_Order_Service
	 */
	private $order_service;

	/**
	 * @var WC_Payments_Action_Scheduler_Service|PHPUnit\Framework\MockObject\MockObject
	 */
	private $mock_action_scheduler_service;

	/**
	 * Orders created by the test, to be cleaned up.
	 *
	 * @var WC_Order[]
	 */
	private $orders = [];

	public function set_up() {
		parent::set_up();

		$this->order_service                 = new WC_Payments_Order_Service( $this->createMock( WC_Payments_API_Client::class ) );
		$this->mock_action_scheduler_service = $this->createMock( WC_Payments_Action_Scheduler_Service::class );

		$this->service = new WC_Payments_Dispute_Ledger_Backfill_Service(
			$this->order_service,
			$this->mock_action_scheduler_service,
			new WC_Payments_DB()
		);

		update_option(
			WC_Payments_Dispute_Ledger_Backfill_Service::STATE_OPTION,
			[
				'status'         => WC_Payments_Dispute_Ledger_Backfill_Service::STATUS_PENDING,
				'page'           => 0,
				'created_before' => gmdate( 'Y-m-d H:i:s' ),
				'attempts'       => 0,
			]
		);
	}

	public function tear_down() {
		delete_option( WC_Payments_Dispute_Ledger_Backfill_Service::STATE_OPTION );

		foreach ( $this->orders as $order ) {
			WC_Helper_Order::delete_order( $order->get_id() );
		}
		$this->orders = [];

		parent::tear_down();
	}

	public function test_records_the_ledger_for_a_closure_with_a_legacy_note() {
		$order = $this->create_disputed_order( 'ch_backfill_1' );
		$this->apply_legacy_closure( $order, 'ch_backfill_1', 'won' );

		$this->mock_disputes_page(
			[
				$this->dispute_row( 'dp_backfill_1', 'ch_backfill_1', 'won' ),
			]
		);

		$this->service->run_backfill_batch();

		$this->assertTrue( $this->reload( $order )->meta_exists( '_wcpay_dispute_closed_dp_backfill_1' ) );
	}

	/**
	 * A closure the store never processed carries no note. Marking it would suppress the platform's
	 * redelivery, and for a lost dispute the refund would never be applied.
	 */
	public function test_skips_a_closure_with_no_legacy_note() {
		$order = $this->create_disputed_order( 'ch_backfill_2' );

		$this->mock_disputes_page(
			[
				$this->dispute_row( 'dp_backfill_2', 'ch_backfill_2', 'lost' ),
			]
		);

		$this->service->run_backfill_batch();

		$this->assertFalse( $this->reload( $order )->meta_exists( '_wcpay_dispute_closed_dp_backfill_2' ) );
	}

	/**
	 * A charge's same-status closures were collapsed into one note, so only the first of them can be
	 * shown to have run.
	 */
	public function test_marks_only_the_earliest_of_several_closures_on_a_charge() {
		$order = $this->create_disputed_order( 'ch_backfill_3' );
		$this->apply_legacy_closure( $order, 'ch_backfill_3', 'won' );

		$this->mock_disputes_page(
			[
				$this->dispute_row( 'dp_backfill_3_first', 'ch_backfill_3', 'won' ),
				$this->dispute_row( 'dp_backfill_3_second', 'ch_backfill_3', 'won' ),
			]
		);

		$this->service->run_backfill_batch();

		$order = $this->reload( $order );
		$this->assertTrue( $order->meta_exists( '_wcpay_dispute_closed_dp_backfill_3_first' ) );
		$this->assertFalse( $order->meta_exists( '_wcpay_dispute_closed_dp_backfill_3_second' ) );
	}

	/**
	 * The scan is paginated, so a charge's second same-status closure can turn up in a later batch,
	 * long after the note it would otherwise match has been claimed.
	 */
	public function test_leaves_a_same_status_closure_from_a_later_page_unmarked() {
		$order = $this->create_disputed_order( 'ch_backfill_6' );
		$this->apply_legacy_closure( $order, 'ch_backfill_6', 'won' );

		$this->mock_disputes_page(
			[
				$this->dispute_row( 'dp_backfill_6_first', 'ch_backfill_6', 'won' ),
			]
		);
		$this->service->run_backfill_batch();

		$this->reset_state_to_pending();
		$this->mock_disputes_page(
			[
				$this->dispute_row( 'dp_backfill_6_second', 'ch_backfill_6', 'won' ),
			]
		);
		$this->service->run_backfill_batch();

		$order = $this->reload( $order );
		$this->assertTrue( $order->meta_exists( '_wcpay_dispute_closed_dp_backfill_6_first' ) );
		$this->assertFalse( $order->meta_exists( '_wcpay_dispute_closed_dp_backfill_6_second' ) );
	}

	/**
	 * The two closures wrote separate notes under the earlier version, so both can be attributed.
	 */
	public function test_marks_closures_of_different_statuses_on_one_charge() {
		$order = $this->create_disputed_order( 'ch_backfill_4' );
		$this->apply_legacy_closure( $order, 'ch_backfill_4', 'won' );
		$this->apply_legacy_closure( $order, 'ch_backfill_4', 'warning_closed' );

		$this->mock_disputes_page(
			[
				$this->dispute_row( 'dp_backfill_4_won', 'ch_backfill_4', 'won' ),
				$this->dispute_row( 'dp_backfill_4_inquiry', 'ch_backfill_4', 'warning_closed' ),
			]
		);

		$this->service->run_backfill_batch();

		$order = $this->reload( $order );
		$this->assertTrue( $order->meta_exists( '_wcpay_dispute_closed_dp_backfill_4_won' ) );
		$this->assertTrue( $order->meta_exists( '_wcpay_dispute_closed_dp_backfill_4_inquiry' ) );
	}

	public function test_ignores_disputes_that_are_still_open() {
		$order = $this->create_disputed_order( 'ch_backfill_5' );
		$this->apply_legacy_closure( $order, 'ch_backfill_5', 'won' );

		$this->mock_disputes_page(
			[
				$this->dispute_row( 'dp_backfill_5', 'ch_backfill_5', 'needs_response' ),
			]
		);

		$this->service->run_backfill_batch();

		$this->assertFalse( $this->reload( $order )->meta_exists( '_wcpay_dispute_closed_dp_backfill_5' ) );
	}

	public function test_marks_the_backfill_done_when_the_last_page_is_reached() {
		$this->mock_disputes_page( [] );

		$this->service->run_backfill_batch();

		$state = get_option( WC_Payments_Dispute_Ledger_Backfill_Service::STATE_OPTION );
		$this->assertSame( 'done', $state['status'] );
	}

	public function test_does_nothing_once_the_backfill_is_done() {
		update_option( WC_Payments_Dispute_Ledger_Backfill_Service::STATE_OPTION, [ 'status' => 'done' ] );
		$this->mock_wcpay_request( List_Disputes::class, 0 );

		$this->service->run_backfill_batch();
	}

	public function test_schedules_the_first_job_when_the_backfill_is_pending() {
		$this->mock_action_scheduler_service
			->method( 'pending_action_exists' )
			->willReturn( false );
		$this->mock_action_scheduler_service
			->expects( $this->once() )
			->method( 'schedule_job' )
			->with( $this->anything(), 'wcpay_dispute_ledger_backfill' );

		$this->service->maybe_schedule_backfill();
	}

	public function test_does_not_schedule_a_second_job_while_one_is_queued() {
		$this->mock_action_scheduler_service
			->method( 'pending_action_exists' )
			->willReturn( true );
		$this->mock_action_scheduler_service
			->expects( $this->never() )
			->method( 'schedule_job' );

		$this->service->maybe_schedule_backfill();
	}

	public function test_does_not_schedule_a_job_once_the_backfill_is_done() {
		update_option( WC_Payments_Dispute_Ledger_Backfill_Service::STATE_OPTION, [ 'status' => 'done' ] );
		$this->mock_action_scheduler_service
			->expects( $this->never() )
			->method( 'schedule_job' );

		$this->service->maybe_schedule_backfill();
	}

	/**
	 * Creates an order carrying the charge the disputes resolve against.
	 *
	 * @param string $charge_id The charge ID to attach.
	 *
	 * @return WC_Order
	 */
	private function create_disputed_order( string $charge_id ): WC_Order {
		$order = WC_Helper_Order::create_order();
		$order->update_meta_data( WC_Payments_DB::META_KEY_CHARGE_ID, $charge_id );
		$order->set_status( Order_Status::ON_HOLD );
		$order->save();

		$this->orders[] = $order;

		return $order;
	}

	/**
	 * Applies a dispute closure the way a version predating the ledger did: a note without a dispute
	 * ID, and no ledger meta.
	 *
	 * @param WC_Order $order     Order to apply the closure to.
	 * @param string   $charge_id The disputed charge.
	 * @param string   $status    The status the dispute closed with.
	 *
	 * @return void
	 */
	private function apply_legacy_closure( WC_Order $order, string $charge_id, string $status ) {
		$this->order_service->mark_payment_dispute_closed( $order, $charge_id, $status );
	}

	/**
	 * Builds a row of the disputes list response.
	 *
	 * @param string $dispute_id The dispute ID.
	 * @param string $charge_id  The disputed charge.
	 * @param string $status     The dispute status.
	 *
	 * @return array
	 */
	private function dispute_row( string $dispute_id, string $charge_id, string $status ): array {
		return [
			'dispute_id' => $dispute_id,
			'charge_id'  => $charge_id,
			'status'     => $status,
			'amount'     => 1000,
			'currency'   => 'usd',
			'created'    => gmdate( 'Y-m-d H:i:s', strtotime( '-30 days' ) ),
		];
	}

	/**
	 * Mocks a single page of the disputes list.
	 *
	 * @param array $disputes Rows to return.
	 *
	 * @return void
	 */
	private function mock_disputes_page( array $disputes ) {
		$this->mock_wcpay_request(
			List_Disputes::class,
			1,
			null,
			[
				'data'        => $disputes,
				'total_count' => count( $disputes ),
			]
		);
	}

	/**
	 * Queues another batch, standing in for the next page of a scan that spans several jobs.
	 *
	 * @return void
	 */
	private function reset_state_to_pending() {
		update_option(
			WC_Payments_Dispute_Ledger_Backfill_Service::STATE_OPTION,
			[
				'status'         => WC_Payments_Dispute_Ledger_Backfill_Service::STATUS_PENDING,
				'page'           => 1,
				'created_before' => gmdate( 'Y-m-d H:i:s' ),
				'attempts'       => 0,
			]
		);
	}

	/**
	 * Reads the order back from the database, so meta written elsewhere is visible.
	 *
	 * @param WC_Order $order Order to reload.
	 *
	 * @return WC_Order
	 */
	private function reload( WC_Order $order ): WC_Order {
		return wc_get_order( $order->get_id() );
	}
}
