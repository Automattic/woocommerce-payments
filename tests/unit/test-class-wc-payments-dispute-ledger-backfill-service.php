<?php
/**
 * Class WC_Payments_Dispute_Ledger_Backfill_Service_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\Constants\Order_Status;
use WCPay\Core\Server\Request\List_Disputes;
use WCPay\Exceptions\API_Exception;

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

		$this->set_state();
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
	 * A charge's same-status closures were collapsed into one note, and the note belongs to whichever
	 * of them closed first — which the list cannot say. Attributing it to the wrong one would suppress
	 * a refund that is still owed, so none of them are marked.
	 */
	public function test_marks_nothing_when_a_charge_has_several_same_status_closures() {
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

		$this->assertFalse( $order->meta_exists( '_wcpay_dispute_closed_dp_backfill_3_first' ) );
		$this->assertFalse( $order->meta_exists( '_wcpay_dispute_closed_dp_backfill_3_second' ) );
		$this->assertFalse( $order->meta_exists( '_wcpay_dispute_backfill_claim_won' ) );
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

		$this->set_state( [ 'page' => 1 ] );
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

	public function test_marks_the_backfill_done_when_a_page_comes_back_empty() {
		$this->mock_disputes_page( [] );

		$this->service->run_backfill_batch();

		$state = get_option( WC_Payments_Dispute_Ledger_Backfill_Service::STATE_OPTION );

		$this->assertSame( 'done', $state['status'] );
	}

	/**
	 * Neither the page the server counts from nor the page size it is willing to serve is guaranteed
	 * to be the one asked for, so a page shorter than the requested size says nothing about whether
	 * the list has been exhausted.
	 */
	public function test_continues_past_a_page_shorter_than_the_page_size() {
		$this->set_state( [ 'page' => 1 ] );

		$request = $this->mock_disputes_page(
			[
				$this->dispute_row( 'dp_backfill_7', 'ch_backfill_7', 'won' ),
			]
		);
		$request->expects( $this->once() )->method( 'set_page' )->with( 1 );
		$request->expects( $this->once() )->method( 'set_page_size' )->with( 100 );

		$this->mock_action_scheduler_service
			->expects( $this->once() )
			->method( 'schedule_job' )
			->with( $this->anything(), 'wcpay_dispute_ledger_backfill' );

		$this->service->run_backfill_batch();

		$state = get_option( WC_Payments_Dispute_Ledger_Backfill_Service::STATE_OPTION );

		$this->assertSame( 'pending', $state['status'] );
		$this->assertSame( 2, $state['page'] );
	}

	/**
	 * Without the timestamp there is nothing to bound the scan to, and every closure the store has
	 * ever seen would be a candidate.
	 */
	public function test_finishes_when_the_upgrade_timestamp_is_missing() {
		$this->set_state( [ 'created_before' => '' ] );
		$this->mock_wcpay_request( List_Disputes::class, 0 );

		$this->service->run_backfill_batch();

		$state = get_option( WC_Payments_Dispute_Ledger_Backfill_Service::STATE_OPTION );

		$this->assertSame( 'done', $state['status'] );
	}

	/**
	 * The scan stops on an empty page, so an endpoint that never serves one must not queue the job
	 * forever.
	 */
	public function test_stops_when_the_page_ceiling_is_reached() {
		$this->set_state( [ 'page' => WC_Payments_Dispute_Ledger_Backfill_Service::MAX_PAGES ] );
		$this->mock_wcpay_request( List_Disputes::class, 0 );

		$this->mock_action_scheduler_service
			->expects( $this->never() )
			->method( 'schedule_job' );

		$this->service->run_backfill_batch();

		$state = get_option( WC_Payments_Dispute_Ledger_Backfill_Service::STATE_OPTION );

		$this->assertSame( 'failed', $state['status'] );
	}

	public function test_retries_a_page_the_server_could_not_serve() {
		$this->set_state( [ 'page' => 3 ] );
		$this->mock_failing_disputes_page();

		$this->mock_action_scheduler_service
			->expects( $this->once() )
			->method( 'schedule_job' )
			->with( $this->anything(), 'wcpay_dispute_ledger_backfill' );

		$this->service->run_backfill_batch();

		$state = get_option( WC_Payments_Dispute_Ledger_Backfill_Service::STATE_OPTION );

		$this->assertSame( 'pending', $state['status'] );
		$this->assertSame( 1, $state['attempts'] );
		$this->assertSame( 3, $state['page'] );
	}

	/**
	 * A store that cannot reach the server has to stop trying, but it is half-applied at that point,
	 * so the state has to say where the scan stopped rather than claim it finished.
	 */
	public function test_gives_up_after_the_attempt_cap_and_keeps_the_state_resumable() {
		$this->set_state(
			[
				'page'           => 3,
				'attempts'       => WC_Payments_Dispute_Ledger_Backfill_Service::MAX_ATTEMPTS - 1,
				'created_before' => '2026-01-01 00:00:00',
			]
		);
		$this->mock_failing_disputes_page();

		$this->mock_action_scheduler_service
			->expects( $this->never() )
			->method( 'schedule_job' );

		$this->service->run_backfill_batch();

		$state = get_option( WC_Payments_Dispute_Ledger_Backfill_Service::STATE_OPTION );

		$this->assertSame( 'failed', $state['status'] );
		$this->assertSame( 3, $state['page'] );
		$this->assertSame( '2026-01-01 00:00:00', $state['created_before'] );
	}

	public function test_resets_the_attempt_count_after_a_page_succeeds() {
		$this->set_state( [ 'attempts' => 2 ] );
		$this->mock_disputes_page(
			[
				$this->dispute_row( 'dp_backfill_8', 'ch_backfill_8', 'won' ),
			]
		);

		$this->service->run_backfill_batch();

		$state = get_option( WC_Payments_Dispute_Ledger_Backfill_Service::STATE_OPTION );

		$this->assertSame( 0, $state['attempts'] );
	}

	public function test_does_nothing_once_the_backfill_is_done() {
		update_option( WC_Payments_Dispute_Ledger_Backfill_Service::STATE_OPTION, [ 'status' => 'done' ] );
		$this->mock_wcpay_request( List_Disputes::class, 0 );

		$this->service->run_backfill_batch();
	}

	/**
	 * A scan that gave up is resumed by putting the status back to pending, not by the job that was
	 * already queued when it failed.
	 */
	public function test_does_nothing_once_the_backfill_has_failed() {
		$this->set_state( [ 'status' => WC_Payments_Dispute_Ledger_Backfill_Service::STATUS_FAILED ] );
		$this->mock_wcpay_request( List_Disputes::class, 0 );

		$this->service->run_backfill_batch();

		$state = get_option( WC_Payments_Dispute_Ledger_Backfill_Service::STATE_OPTION );

		$this->assertSame( 'failed', $state['status'] );
	}

	/**
	 * The check behind this queries the ActionScheduler tables uncached, so it must not sit on a hook
	 * that every front-end request runs.
	 */
	public function test_checks_for_a_queued_job_off_the_front_end_path() {
		$this->service->init_hooks();

		$this->assertFalse( has_action( 'init', [ $this->service, 'maybe_schedule_backfill' ] ) );
		$this->assertNotFalse( has_action( 'admin_init', [ $this->service, 'maybe_schedule_backfill' ] ) );
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
	 * Writes the backfill state, with the given overrides applied to a scan about to start.
	 *
	 * @param array $overrides State keys to replace.
	 *
	 * @return void
	 */
	private function set_state( array $overrides = [] ) {
		update_option(
			WC_Payments_Dispute_Ledger_Backfill_Service::STATE_OPTION,
			array_merge(
				[
					'status'         => WC_Payments_Dispute_Ledger_Backfill_Service::STATUS_PENDING,
					'page'           => 0,
					'created_before' => gmdate( 'Y-m-d H:i:s' ),
					'attempts'       => 0,
				],
				$overrides
			)
		);
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
	 * @return List_Disputes|PHPUnit\Framework\MockObject\MockObject
	 */
	private function mock_disputes_page( array $disputes ) {
		return $this->mock_wcpay_request(
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
	 * Mocks a disputes list request that fails the way an unreachable account does.
	 *
	 * @return List_Disputes|PHPUnit\Framework\MockObject\MockObject
	 */
	private function mock_failing_disputes_page() {
		$request = $this->mock_wcpay_request( List_Disputes::class );
		$request
			->method( 'format_response' )
			->willThrowException( new API_Exception( 'the account could not be reached', 'wcpay_backfill_test', 500 ) );

		return $request;
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
