<?php
/**
 * Class WC_Payments_Dispute_Ledger_Backfill_Service_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\Constants\Order_Status;
use WCPay\Core\Mode;
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

	/**
	 * The mode WC_Payments held before a test swapped it out.
	 *
	 * @var Mode|null
	 */
	private $mode_before;

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

		if ( $this->mode_before ) {
			$this->write_mode( $this->mode_before );
			$this->mode_before = null;
		}

		foreach ( $this->orders as $order ) {
			WC_Helper_Order::delete_order( $order->get_id() );
		}
		$this->orders = [];

		parent::tear_down();
	}

	public function test_records_the_ledger_for_a_closure_with_a_legacy_note() {
		$order = $this->create_disputed_order( 'ch_backfill_1' );
		$this->add_note_as_written_by_8_7_0( $order, 'ch_backfill_1', 'won' );

		$this->mock_disputes_page(
			[
				$this->dispute_row( 'dp_backfill_1', 'ch_backfill_1', 'won' ),
			]
		);

		$this->service->run_backfill_batch();

		$this->assertTrue( $this->reload( $order )->meta_exists( '_wcpay_dispute_closed_dp_backfill_1' ) );
	}

	/**
	 * A closure the store applied is one whose creation it applied first, so the same note settles
	 * both. Without the creation entry a legacy creation redelivered from the platform's queue puts a
	 * closed dispute's order back on hold.
	 */
	public function test_records_the_creation_ledger_on_the_same_evidence() {
		$order = $this->create_disputed_order( 'ch_backfill_10' );
		$this->add_note_as_written_by_8_7_0( $order, 'ch_backfill_10', 'won' );

		$this->mock_disputes_page(
			[
				$this->dispute_row( 'dp_backfill_10', 'ch_backfill_10', 'won' ),
			]
		);

		$this->service->run_backfill_batch();

		$this->assertTrue( $this->reload( $order )->meta_exists( '_wcpay_dispute_created_dp_backfill_10' ) );
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
	 * 8.7.0 changed the opening of the sentence, so a note an earlier version wrote no longer
	 * reconstructs. The scan has no way to tell that note from one the store never wrote at all, and
	 * both resolve the same way.
	 */
	public function test_skips_a_closure_noted_before_the_wording_changed() {
		$order = $this->create_disputed_order( 'ch_backfill_11' );
		$this->add_note_as_written_by_8_6_0( $order, 'ch_backfill_11', 'won' );

		$this->mock_disputes_page(
			[
				$this->dispute_row( 'dp_backfill_11', 'ch_backfill_11', 'won' ),
			]
		);

		$this->service->run_backfill_batch();

		$this->assertFalse( $this->reload( $order )->meta_exists( '_wcpay_dispute_closed_dp_backfill_11' ) );
	}

	/**
	 * 6.6.0 moved the link from the disputes page to the transaction page and swapped the ID in it
	 * from the dispute's to the charge's, so notes from before it cannot be reconstructed either.
	 */
	public function test_skips_a_closure_noted_before_the_link_changed() {
		$order = $this->create_disputed_order( 'ch_backfill_12' );
		$this->add_note_as_written_by_6_5_0( $order, 'dp_backfill_12', 'won' );

		$this->mock_disputes_page(
			[
				$this->dispute_row( 'dp_backfill_12', 'ch_backfill_12', 'won' ),
			]
		);

		$this->service->run_backfill_batch();

		$this->assertFalse( $this->reload( $order )->meta_exists( '_wcpay_dispute_closed_dp_backfill_12' ) );
	}

	/**
	 * A charge's same-status closures were collapsed into one note, and the note belongs to whichever
	 * of them closed first — which the list cannot say. Attributing it to the wrong one would suppress
	 * a refund that is still owed, so none of them are marked.
	 */
	public function test_marks_nothing_when_a_charge_has_several_same_status_closures() {
		$order = $this->create_disputed_order( 'ch_backfill_3' );
		$this->add_note_as_written_by_8_7_0( $order, 'ch_backfill_3', 'won' );

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

		// And nothing may claim the note later either, however the rest of the group is paginated.
		$this->assertSame( 'ambiguous', $order->get_meta( '_wcpay_dispute_backfill_claim_won' ) );
	}

	/**
	 * The scan is paginated, so a charge's second same-status closure can turn up in a later batch,
	 * long after the first claimed the single note the earlier version wrote. Leaving the first marked
	 * would rest the attribution on nothing better than the order the scan happened to walk in, so the
	 * claim is given back and neither ends up in the ledger.
	 */
	public function test_retracts_a_claim_when_a_same_status_sibling_turns_up_on_a_later_page() {
		$order = $this->create_disputed_order( 'ch_backfill_6' );
		$this->add_note_as_written_by_8_7_0( $order, 'ch_backfill_6', 'won' );

		$this->mock_disputes_page(
			[
				$this->dispute_row( 'dp_backfill_6_first', 'ch_backfill_6', 'won' ),
			]
		);
		$this->service->run_backfill_batch();

		$this->set_state( [ 'page' => 2 ] );
		$this->mock_disputes_page(
			[
				$this->dispute_row( 'dp_backfill_6_second', 'ch_backfill_6', 'won' ),
			]
		);
		$this->service->run_backfill_batch();

		$order = $this->reload( $order );

		$this->assertFalse( $order->meta_exists( '_wcpay_dispute_closed_dp_backfill_6_first' ) );
		$this->assertFalse( $order->meta_exists( '_wcpay_dispute_created_dp_backfill_6_first' ) );
		$this->assertFalse( $order->meta_exists( '_wcpay_dispute_closed_dp_backfill_6_second' ) );
		$this->assertSame( 'ambiguous', $order->get_meta( '_wcpay_dispute_backfill_claim_won' ) );
	}

	/**
	 * Once a group is known to be ambiguous, a third member arriving later must not be able to claim
	 * the note the first two gave up.
	 */
	public function test_leaves_a_retracted_group_unmarkable() {
		$order = $this->create_disputed_order( 'ch_backfill_13' );
		$this->add_note_as_written_by_8_7_0( $order, 'ch_backfill_13', 'won' );
		$order->update_meta_data( '_wcpay_dispute_backfill_claim_won', 'ambiguous' );
		$order->save();

		$this->mock_disputes_page(
			[
				$this->dispute_row( 'dp_backfill_13', 'ch_backfill_13', 'won' ),
			]
		);

		$this->service->run_backfill_batch();

		$order = $this->reload( $order );

		$this->assertFalse( $order->meta_exists( '_wcpay_dispute_closed_dp_backfill_13' ) );
		$this->assertSame( 'ambiguous', $order->get_meta( '_wcpay_dispute_backfill_claim_won' ) );
	}

	/**
	 * The two closures wrote separate notes under the earlier version, so both can be attributed.
	 */
	public function test_marks_closures_of_different_statuses_on_one_charge() {
		$order = $this->create_disputed_order( 'ch_backfill_4' );
		$this->add_note_as_written_by_8_7_0( $order, 'ch_backfill_4', 'won' );
		$this->add_note_as_written_by_8_7_0( $order, 'ch_backfill_4', 'warning_closed' );

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
		$this->add_note_as_written_by_8_7_0( $order, 'ch_backfill_5', 'won' );

		$this->mock_disputes_page(
			[
				$this->dispute_row( 'dp_backfill_5', 'ch_backfill_5', 'needs_response' ),
			]
		);

		$this->service->run_backfill_batch();

		$this->assertFalse( $this->reload( $order )->meta_exists( '_wcpay_dispute_closed_dp_backfill_5' ) );
	}

	/**
	 * Test mode selects a different account and a different table on the server, so a scan that read
	 * the wrong one records itself as done having examined nothing. A live store keeps its history in
	 * the live account even while the gateway's own test toggle is on.
	 */
	public function test_reads_the_live_account_for_a_live_onboarded_store() {
		$this->set_onboarding_mode( false );

		$request = $this->mock_disputes_page( [] );
		$request
			->expects( $this->once() )
			->method( 'set_filters' )
			->with( [ 'test_mode' => false ] );

		$this->service->run_backfill_batch();
	}

	/**
	 * A sandbox store has no live account at all, so asking for one gets `wcpay_account_not_found`
	 * rather than the dispute history sitting in the account it did onboard.
	 */
	public function test_reads_the_sandbox_account_for_a_test_onboarded_store() {
		$this->set_onboarding_mode( true );

		$request = $this->mock_disputes_page( [] );
		$request
			->expects( $this->once() )
			->method( 'set_filters' )
			->with( [ 'test_mode' => true ] );

		$this->service->run_backfill_batch();
	}

	/**
	 * The notes were written by a webhook, in the site locale. ActionScheduler's async runner reaches
	 * PHP through admin-ajax.php, which defines WP_ADMIN and forwards the dispatching admin's cookies,
	 * so `determine_locale()` can hand back that admin's personal locale — and a reconstruction in a
	 * language the store never wrote a note in matches nothing at all.
	 */
	public function test_reconstructs_the_note_in_the_site_locale() {
		$order = $this->create_disputed_order( 'ch_backfill_14' );
		$this->add_note_as_written_by_8_7_0( $order, 'ch_backfill_14', 'won' );

		// Priority 9 so the callbacks that hold the site locale, registered at 10, still win while a
		// switch is in force.
		$admin_locale = function () {
			return 'de_DE';
		};
		add_filter( 'determine_locale', $admin_locale, 9 );

		// Stands in for the catalogue that locale would load.
		$translate = function ( $translation, $text ) {
			if ( 0 !== strpos( $text, 'Dispute has been closed with status' ) || 'de_DE' !== determine_locale() ) {
				return $translation;
			}

			return 'Streitfall wurde mit dem Status %1$s geschlossen. Siehe <a>Streitfall-Übersicht</a>.';
		};
		add_filter( 'gettext', $translate, 10, 2 );

		$this->mock_disputes_page(
			[
				$this->dispute_row( 'dp_backfill_14', 'ch_backfill_14', 'won' ),
			]
		);

		$this->service->run_backfill_batch();

		remove_filter( 'gettext', $translate, 10 );
		remove_filter( 'determine_locale', $admin_locale, 9 );

		$this->assertTrue( $this->reload( $order )->meta_exists( '_wcpay_dispute_closed_dp_backfill_14' ) );
	}

	/**
	 * Only closures noted by 8.7.0 and later can be reconstructed, so the scan walks the newest
	 * disputes first: oldest-first would spend its page budget on a population that can never match.
	 */
	public function test_walks_the_newest_disputes_first() {
		$request = $this->mock_disputes_page( [] );
		$request->expects( $this->once() )->method( 'set_sort_by' )->with( 'created' );
		$request->expects( $this->once() )->method( 'set_sort_direction' )->with( 'desc' );

		$this->service->run_backfill_batch();
	}

	public function test_marks_the_backfill_done_when_a_page_comes_back_empty() {
		$this->mock_disputes_page( [] );

		$this->service->run_backfill_batch();

		$state = get_option( WC_Payments_Dispute_Ledger_Backfill_Service::STATE_OPTION );

		$this->assertSame( 'done', $state['status'] );
	}

	/**
	 * The backfill is one-shot and nothing can re-run it to find out what it did, and WooCommerce
	 * prunes the log it wrote to, so the finished state is the record of the outcome that lasts.
	 */
	public function test_keeps_the_counters_when_the_backfill_finishes() {
		$this->set_state(
			[
				'page'  => 4,
				'stats' => array_merge(
					WC_Payments_Dispute_Ledger_Backfill_Service::STATS_TEMPLATE,
					[ 'entries_recorded' => 7 ]
				),
			]
		);
		$this->mock_disputes_page( [] );

		$this->service->run_backfill_batch();

		$state = get_option( WC_Payments_Dispute_Ledger_Backfill_Service::STATE_OPTION );

		$this->assertSame( 7, $state['stats']['entries_recorded'] );
		$this->assertSame( 4, $state['page'] );
		$this->assertNotEmpty( $state['finished_at'] );
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
	 * ever seen would be a candidate. That is a broken state rather than a finished scan, so it is
	 * recorded as one.
	 */
	public function test_fails_when_the_upgrade_timestamp_is_missing() {
		$this->set_state( [ 'created_before' => '' ] );

		$this->mock_action_scheduler_service
			->expects( $this->never() )
			->method( 'schedule_job' );

		$this->service->run_backfill_batch();

		$state = get_option( WC_Payments_Dispute_Ledger_Backfill_Service::STATE_OPTION );

		$this->assertSame( 'failed', $state['status'] );
	}

	/**
	 * The scan stops on an empty page, so an endpoint that never serves one must not queue the job
	 * forever.
	 */
	public function test_stops_when_the_page_ceiling_is_reached() {
		$this->set_state( [ 'page' => WC_Payments_Dispute_Ledger_Backfill_Service::MAX_PAGES ] );

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
	 * A page that blows up while it is being processed leaves ActionScheduler holding a `failed`
	 * action, which the scheduling check cannot see — so the attempt has to be on record before the
	 * work starts, or the same page is queued again with the retry budget untouched.
	 */
	public function test_banks_the_attempt_before_processing_a_page() {
		$mock_wcpay_db = $this->createMock( WC_Payments_DB::class );
		$mock_wcpay_db
			->method( 'orders_with_charge_id_from_charge_ids' )
			->willThrowException( new TypeError( 'Argument #1 ($order) must be of type WC_Order, bool given' ) );

		$service = new WC_Payments_Dispute_Ledger_Backfill_Service(
			$this->order_service,
			$this->mock_action_scheduler_service,
			$mock_wcpay_db
		);

		$this->set_state( [ 'page' => 3 ] );
		$this->mock_disputes_page(
			[
				$this->dispute_row( 'dp_backfill_9', 'ch_backfill_9', 'won' ),
			]
		);

		$service->run_backfill_batch();

		$state = get_option( WC_Payments_Dispute_Ledger_Backfill_Service::STATE_OPTION );

		$this->assertSame( 1, $state['attempts'] );
		$this->assertSame( 3, $state['page'] );
		$this->assertSame( 'pending', $state['status'] );
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

	/**
	 * A store with no connected account will not grow one inside the retry window, so spending the
	 * budget on it only delays the same answer by a day.
	 */
	public function test_stops_at_once_when_the_site_has_no_connected_account() {
		$this->set_state( [ 'page' => 3 ] );

		$request = $this->mock_wcpay_request( List_Disputes::class );
		$request
			->method( 'format_response' )
			->willThrowException( new API_Exception( 'Error: No account found for this site.', 'wcpay_account_not_found', 401 ) );

		$this->mock_action_scheduler_service
			->expects( $this->never() )
			->method( 'schedule_job' );

		$this->service->run_backfill_batch();

		$state = get_option( WC_Payments_Dispute_Ledger_Backfill_Service::STATE_OPTION );

		$this->assertSame( 'failed', $state['status'] );
		$this->assertSame( 3, $state['page'] );
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
		$done_state = [
			'status'         => 'done',
			'page'           => 4,
			'created_before' => '2026-01-01 00:00:00',
			'attempts'       => 0,
		];
		update_option( WC_Payments_Dispute_Ledger_Backfill_Service::STATE_OPTION, $done_state );

		$this->mock_action_scheduler_service
			->expects( $this->never() )
			->method( 'schedule_job' );

		$this->service->run_backfill_batch();

		$state = get_option( WC_Payments_Dispute_Ledger_Backfill_Service::STATE_OPTION );

		$this->assertSame( $done_state, $state );
	}

	/**
	 * A scan that gave up is resumed by putting the status back to pending, not by the job that was
	 * already queued when it failed.
	 */
	public function test_does_nothing_once_the_backfill_has_failed() {
		$failed_state = [
			'status'         => 'failed',
			'page'           => 4,
			'created_before' => '2026-01-01 00:00:00',
			'attempts'       => 8,
		];
		update_option( WC_Payments_Dispute_Ledger_Backfill_Service::STATE_OPTION, $failed_state );

		$this->mock_action_scheduler_service
			->expects( $this->never() )
			->method( 'schedule_job' );

		$this->service->run_backfill_batch();

		$state = get_option( WC_Payments_Dispute_Ledger_Backfill_Service::STATE_OPTION );

		$this->assertSame( $failed_state, $state );
	}

	/**
	 * A store whose admin is never opened — headless, or driven over REST and WP-CLI — still has to
	 * start the scan, so the scheduling check cannot sit behind an admin-only hook.
	 */
	public function test_schedules_from_a_hook_that_runs_in_every_context() {
		$this->service->init_hooks();

		$this->assertFalse( has_action( 'admin_init', [ $this->service, 'maybe_schedule_backfill' ] ) );
		$this->assertNotFalse( has_action( 'init', [ $this->service, 'maybe_schedule_backfill' ] ) );
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
					'page'           => 1,
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
	 * Adds the closure note verbatim as 8.7.0 wrote it — the earliest wording the scan can still
	 * reconstruct, so these are the closures it is expected to recognise.
	 *
	 * The historical notes below are spelled out rather than produced by the note generator: asking
	 * the current generator for the "legacy" note would compare current code against itself, and the
	 * comparison would keep passing however far the wording drifts away from what stores actually
	 * have on their orders.
	 *
	 * @param WC_Order $order     Order to note.
	 * @param string   $charge_id The disputed charge.
	 * @param string   $status    The status the dispute closed with.
	 *
	 * @return void
	 */
	private function add_note_as_written_by_8_7_0( WC_Order $order, string $charge_id, string $status ) {
		if ( 0 === strpos( $status, 'warning_' ) ) {
			$order->add_order_note(
				'Payment inquiry has been closed with status ' . $status . '. See <a href="'
				. $this->transaction_details_url( $charge_id )
				. '" target="_blank" rel="noopener noreferrer">payment status</a> for more details.'
			);

			return;
		}

		$order->add_order_note(
			'Dispute has been closed with status ' . $status . '. See <a href="'
			. $this->transaction_details_url( $charge_id )
			. '" target="_blank" rel="noopener noreferrer">dispute overview</a> for more details.'
		);
	}

	/**
	 * Adds the closure note verbatim as 8.6.0 wrote it, before the sentence was reworded.
	 *
	 * @param WC_Order $order     Order to note.
	 * @param string   $charge_id The disputed charge.
	 * @param string   $status    The status the dispute closed with.
	 *
	 * @return void
	 */
	private function add_note_as_written_by_8_6_0( WC_Order $order, string $charge_id, string $status ) {
		$order->add_order_note(
			'Payment dispute has been closed with status ' . $status . '. See <a href="'
			. $this->transaction_details_url( $charge_id )
			. '" target="_blank" rel="noopener noreferrer">dispute overview</a> for more details.'
		);
	}

	/**
	 * Adds the closure note verbatim as 6.5.0 wrote it, before the link moved to the transaction page
	 * and started carrying the charge ID instead of the dispute's.
	 *
	 * @param WC_Order $order      Order to note.
	 * @param string   $dispute_id The dispute, which is what the link carried back then.
	 * @param string   $status     The status the dispute closed with.
	 *
	 * @return void
	 */
	private function add_note_as_written_by_6_5_0( WC_Order $order, string $dispute_id, string $status ) {
		$order->add_order_note(
			'Payment dispute has been closed with status ' . $status . '. See <a href="'
			. admin_url( 'admin.php' ) . '?page=wc-admin&path=/payments/disputes/details&id=' . $dispute_id
			. '" target="_blank" rel="noopener noreferrer">dispute overview</a> for more details.'
		);
	}

	/**
	 * The transaction details link the closure notes have carried since 8.6.0.
	 *
	 * @param string $charge_id The disputed charge.
	 *
	 * @return string
	 */
	private function transaction_details_url( string $charge_id ): string {
		return admin_url( 'admin.php' ) . '?page=wc-admin&path=%2Fpayments%2Ftransactions%2Fdetails&id=' . $charge_id;
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

	/**
	 * Puts a mode reporting the given onboarding mode in front of WC_Payments, until tear_down.
	 *
	 * The real Mode caches its flags on first read, so the `wcpay_test_mode_onboarding` filter has
	 * usually stopped being consulted by the time a test runs.
	 *
	 * @param bool $test_mode_onboarding Whether the store onboarded in test mode.
	 *
	 * @return void
	 */
	private function set_onboarding_mode( bool $test_mode_onboarding ) {
		$mock_mode = $this->createMock( Mode::class );
		$mock_mode
			->method( 'is_test_mode_onboarding' )
			->willReturn( $test_mode_onboarding );

		$this->mode_before = WC_Payments::mode();
		$this->write_mode( $mock_mode );
	}

	/**
	 * Sets the mode WC_Payments hands out.
	 *
	 * @param Mode $mode The mode to install.
	 *
	 * @return void
	 */
	private function write_mode( Mode $mode ) {
		$property = new ReflectionProperty( WC_Payments::class, 'mode' );
		$property->setAccessible( true );
		$property->setValue( null, $mode );
	}
}
