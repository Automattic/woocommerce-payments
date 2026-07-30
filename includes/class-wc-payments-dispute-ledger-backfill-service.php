<?php
/**
 * WC_Payments_Dispute_Ledger_Backfill_Service class
 *
 * @package WooCommerce\Payments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use WCPay\Core\Server\Request\List_Disputes;
use WCPay\Logger;

/**
 * Records the dispute closure ledger for disputes that closed before the store upgraded.
 *
 * Dispute closures used to be de-duplicated on the order note text alone. That text now carries the
 * dispute ID, so a closure the store already applied under an earlier version is no longer
 * recognised when the platform redelivers its event, and the side effects — for a lost dispute, a
 * refund — run again. Writing the ledger meta for those older closures closes that window.
 *
 * The ledger is only written where the closure demonstrably ran: the order still carries the note
 * the earlier version wrote. Marking a closure that never ran would be worse than the bug, because
 * a genuine redelivery from the platform's failed-event queue would then be suppressed and the
 * refund never applied. Every ambiguity in this class resolves the same way — leave the closure
 * unmarked, and log enough to tell a scan that found nothing from one that matched nothing.
 */
class WC_Payments_Dispute_Ledger_Backfill_Service {

	const BACKFILL_ACTION = 'wcpay_dispute_ledger_backfill';
	const STATE_OPTION    = 'wcpay_dispute_ledger_backfill_state';

	const STATUS_PENDING = 'pending';
	const STATUS_DONE    = 'done';
	const STATUS_FAILED  = 'failed';

	const PAGE_SIZE    = 100;
	const MAX_ATTEMPTS = 5;
	const RETRY_DELAY  = HOUR_IN_SECONDS;

	/**
	 * Ceiling on the pages one scan will walk.
	 *
	 * The scan stops on an empty page, which relies on the endpoint eventually returning one. This
	 * bounds the damage if it never does — an endpoint that ignores the page parameter would
	 * otherwise queue itself forever.
	 *
	 * @const int
	 */
	const MAX_PAGES = 1000;

	/**
	 * Meta key prefix, suffixed with the dispute status, naming the dispute a backfilled closure note
	 * was attributed to.
	 *
	 * The earlier version collapsed same-status closures on a charge into a single note, so a charge
	 * with several of them carries evidence for only one. Within a page they are recognised as
	 * ambiguous and none are marked; across a page boundary there is nothing to compare against, so
	 * this records which dispute claimed the evidence and the rest stay unmarked when they turn up
	 * later. That leaves the first of a split group marked on the strength of the scan's ordering,
	 * which is the one place the attribution can still be wrong.
	 *
	 * Deliberately outside the ledger's own `_wcpay_dispute_closed_` namespace: a key there would
	 * read as a ledger entry for a dispute called after the status.
	 *
	 * @const string
	 */
	const BACKFILL_CLAIM_META_KEY_PREFIX = '_wcpay_dispute_backfill_claim_';

	/**
	 * Dispute statuses that reach WC_Payments_Order_Service::mark_payment_dispute_closed(), inquiry
	 * closures included — they run through the same note generator.
	 *
	 * @const string[]
	 */
	const CLOSED_STATUSES = [
		'won',
		'lost',
		'warning_closed',
		'charge_refunded',
	];

	/**
	 * The counters a scan accumulates, so its outcome can be read off a single log line.
	 *
	 * Nothing being marked is a legitimate result — every note is reconstructed and compared
	 * verbatim, and a store that changed domain or site language no longer matches any of them —
	 * but so is nothing being examined. These tell the two apart.
	 *
	 * @const array<string, int>
	 */
	const STATS_TEMPLATE = [
		'disputes_examined'     => 0,
		'closures_found'        => 0,
		'charges_seen'          => 0,
		'orders_resolved'       => 0,
		'charges_without_order' => 0,
		'groups_skipped'        => 0,
		'entries_recorded'      => 0,
		'already_recorded'      => 0,
		'notes_not_matched'     => 0,
	];

	/**
	 * WC_Payments_Order_Service instance.
	 *
	 * @var WC_Payments_Order_Service
	 */
	private $order_service;

	/**
	 * WC_Payments_Action_Scheduler_Service.
	 *
	 * @var WC_Payments_Action_Scheduler_Service
	 */
	private $action_scheduler_service;

	/**
	 * Wrapper for database access.
	 *
	 * @var WC_Payments_DB
	 */
	private $wcpay_db;

	/**
	 * WC_Payments_Dispute_Ledger_Backfill_Service constructor.
	 *
	 * @param WC_Payments_Order_Service            $order_service            Order service.
	 * @param WC_Payments_Action_Scheduler_Service $action_scheduler_service Wrapper for ActionScheduler service.
	 * @param WC_Payments_DB                       $wcpay_db                 Wrapper for database access.
	 */
	public function __construct(
		WC_Payments_Order_Service $order_service,
		WC_Payments_Action_Scheduler_Service $action_scheduler_service,
		WC_Payments_DB $wcpay_db
	) {
		$this->order_service            = $order_service;
		$this->action_scheduler_service = $action_scheduler_service;
		$this->wcpay_db                 = $wcpay_db;
	}

	/**
	 * Register the hooks for this service.
	 *
	 * @return void
	 */
	public function init_hooks() {
		// ActionScheduler is not up yet when the plugin update runs, so the migration only records
		// that the backfill is due and the first job is scheduled from a later hook. `admin_init`
		// rather than `init`, because the check behind it queries the ActionScheduler tables
		// uncached and would do so on every front-end request until the scan finishes.
		add_action( 'admin_init', [ $this, 'maybe_schedule_backfill' ] );
		add_action( self::BACKFILL_ACTION, [ $this, 'run_backfill_batch' ] );
	}

	/**
	 * Schedule the next backfill job, unless one is already queued.
	 *
	 * @return void
	 */
	public function maybe_schedule_backfill() {
		$state = get_option( self::STATE_OPTION );

		if ( ! is_array( $state ) || self::STATUS_PENDING !== ( $state['status'] ?? '' ) ) {
			return;
		}

		if ( ! function_exists( 'as_has_scheduled_action' ) || $this->action_scheduler_service->pending_action_exists( self::BACKFILL_ACTION ) ) {
			return;
		}

		$this->action_scheduler_service->schedule_job( time(), self::BACKFILL_ACTION );
	}

	/**
	 * Process one page of disputes, then queue the next.
	 *
	 * @return void
	 */
	public function run_backfill_batch() {
		$state = get_option( self::STATE_OPTION );

		if ( ! is_array( $state ) || self::STATUS_PENDING !== ( $state['status'] ?? '' ) ) {
			return;
		}

		$page           = (int) ( $state['page'] ?? 0 );
		$created_before = (string) ( $state['created_before'] ?? '' );

		if ( '' === $created_before ) {
			$this->finish( $state, 'the upgrade timestamp is missing from the backfill state' );
			return;
		}

		if ( $page >= self::MAX_PAGES ) {
			$this->fail( $state, 'the disputes list never returned an empty page within ' . self::MAX_PAGES . ' pages' );
			return;
		}

		try {
			$request = List_Disputes::create();
			$request->set_page( $page );
			$request->set_page_size( self::PAGE_SIZE );
			// An explicit order is what keeps the pages of one scan disjoint. Without it the server
			// picks its own, and rows can repeat or be skipped as the scan walks.
			$request->set_sort_by( 'created' );
			$request->set_sort_direction( 'asc' );
			// The list carries no closed-at value, and a dispute cannot close before it was created,
			// so bounding on creation is the available way to keep the scan on the closures that
			// predate the upgrade. Anything newer is skipped further down for want of a legacy note.
			$request->set_created_before( $created_before );

			$response = $request->send();
		} catch ( Exception $e ) {
			$this->handle_failure( $state, $e->getMessage() );
			return;
		}

		$disputes = isset( $response['data'] ) && is_array( $response['data'] ) ? $response['data'] : [];

		// Deliberately an empty page rather than a short one: neither the page the server counts from
		// nor the page size it is willing to serve is guaranteed to match what is asked for here, and
		// a short first page would end the scan having read nothing. A page that repeats work already
		// done is harmless, since every write below is idempotent.
		if ( empty( $disputes ) ) {
			$this->finish( $state, 'reached the end of the disputes list' );
			return;
		}

		$state['stats']    = $this->merge_stats( $state['stats'] ?? [], $this->backfill_page( $disputes ) );
		$state['page']     = $page + 1;
		$state['attempts'] = 0;
		update_option( self::STATE_OPTION, $state );

		$this->action_scheduler_service->schedule_job( time(), self::BACKFILL_ACTION );
	}

	/**
	 * Write the ledger for the closed disputes in one page of the disputes list.
	 *
	 * @param array $disputes Rows from the disputes list response.
	 *
	 * @return array<string, int> The counters for this page.
	 */
	private function backfill_page( array $disputes ): array {
		$stats                      = self::STATS_TEMPLATE;
		$stats['disputes_examined'] = count( $disputes );

		$candidates = [];

		foreach ( $disputes as $dispute ) {
			$dispute_id = (string) ( $dispute['dispute_id'] ?? '' );
			$charge_id  = (string) ( $dispute['charge_id'] ?? '' );
			$status     = (string) ( $dispute['status'] ?? '' );

			if ( '' === $dispute_id || '' === $charge_id || ! in_array( $status, self::CLOSED_STATUSES, true ) ) {
				continue;
			}

			++$stats['closures_found'];
			$candidates[ $charge_id ][ $status ][] = $dispute_id;
		}

		$stats['charges_seen'] = count( $candidates );

		if ( empty( $candidates ) ) {
			return $stats;
		}

		$unresolved_charge_ids = array_fill_keys( array_keys( $candidates ), true );

		foreach ( $this->wcpay_db->orders_with_charge_id_from_charge_ids( array_keys( $candidates ) ) as $result ) {
			$order     = $result['order'];
			$charge_id = $result['charge_id'];

			unset( $unresolved_charge_ids[ $charge_id ] );
			++$stats['orders_resolved'];

			foreach ( $candidates[ $charge_id ] ?? [] as $status => $dispute_ids ) {
				if ( count( $dispute_ids ) > 1 ) {
					// The earlier version wrote one note for a charge's same-status closures and
					// returned, so the note belongs to whichever of them closed first — and the list
					// carries no closed-at value to work that out. Attributing it to the wrong dispute
					// would suppress a refund that is still owed, so none of them are marked and the
					// charge keeps the double-refund exposure this backfill exists to narrow.
					++$stats['groups_skipped'];
					Logger::info(
						'Dispute ledger backfill: leaving charge ' . $charge_id . ' unmarked for status ' . $status
						. ', its closure note cannot be attributed to one of ' . implode( ', ', $dispute_ids ) . '.'
					);
					continue;
				}

				$outcome = $this->backfill_dispute( $order, $charge_id, $status, $dispute_ids[0] );
				++$stats[ $outcome ];
			}
		}

		if ( ! empty( $unresolved_charge_ids ) ) {
			$stats['charges_without_order'] = count( $unresolved_charge_ids );

			// Either the charge never reached an order on this store, or the shared lookup dropped it:
			// that query caps its result at the number of charge IDs it was given, so a charge held by
			// several orders pushes another charge's order out of the set. Both leave closures
			// unmarked, so they are logged rather than passed over.
			Logger::info(
				'Dispute ledger backfill: no order found for charges ' . implode( ', ', array_keys( $unresolved_charge_ids ) ) . '.'
			);
		}

		return $stats;
	}

	/**
	 * Write the ledger for a single dispute, if the order shows its closure was applied.
	 *
	 * @param WC_Order $order      Order the disputed charge belongs to.
	 * @param string   $charge_id  The ID of the disputed charge.
	 * @param string   $status     The status the dispute closed with.
	 * @param string   $dispute_id The ID of the dispute.
	 *
	 * @return string The counter this outcome belongs to.
	 */
	private function backfill_dispute( WC_Order $order, string $charge_id, string $status, string $dispute_id ): string {
		$claim_meta_key  = self::BACKFILL_CLAIM_META_KEY_PREFIX . $status;
		$ledger_meta_key = WC_Payments_Order_Service::WCPAY_DISPUTE_CLOSED_META_KEY_PREFIX . $dispute_id;

		if ( $order->meta_exists( $claim_meta_key ) || $order->meta_exists( $ledger_meta_key ) ) {
			return 'already_recorded';
		}

		// An empty dispute ID reproduces the note the earlier version wrote. Its absence means the
		// closure never reached this store, so the ledger stays unwritten and a redelivery is free to
		// apply it. The comparison is exact, so a note whose wording has since been re-translated, or
		// which links to a domain the store has since left, no longer matches either — the same safe
		// direction, and the reason the scan reports how many notes it failed to match.
		if ( ! $this->order_service->order_note_exists( $order, $this->order_service->get_dispute_closed_note( $charge_id, $status ) ) ) {
			return 'notes_not_matched';
		}

		$order->update_meta_data( $ledger_meta_key, gmdate( 'Y-m-d H:i:s' ) );
		$order->update_meta_data( $claim_meta_key, $dispute_id );

		// On HPOS a meta write counts as an order change: the data store bumps `date_modified`, saves,
		// and fires `woocommerce_update_order`, which queues a fraud-tracking job. This scan only
		// annotates history and must not make historical orders look freshly modified to reports and
		// accounting sync, so it opts out the way core does when it writes meta alone.
		add_filter( 'woocommerce_orders_table_datastore_should_save_after_meta_change', '__return_false' );
		$order->save_meta_data();
		remove_filter( 'woocommerce_orders_table_datastore_should_save_after_meta_change', '__return_false' );

		Logger::info( 'Recorded the closure of dispute ' . $dispute_id . ' on order ' . $order->get_id() . ' from an order note predating the dispute ledger.' );

		return 'entries_recorded';
	}

	/**
	 * Add a page's counters to the ones the scan has accumulated so far.
	 *
	 * @param array $totals The counters from the preceding pages.
	 * @param array $page   The counters from the page just processed.
	 *
	 * @return array<string, int>
	 */
	private function merge_stats( array $totals, array $page ): array {
		$merged = [];

		foreach ( self::STATS_TEMPLATE as $key => $zero ) {
			$merged[ $key ] = (int) ( $totals[ $key ] ?? $zero ) + (int) ( $page[ $key ] ?? $zero );
		}

		return $merged;
	}

	/**
	 * Renders a scan's counters for the log.
	 *
	 * @param array $stats The accumulated counters.
	 *
	 * @return string
	 */
	private function describe_stats( array $stats ): string {
		$stats = array_merge( self::STATS_TEMPLATE, array_intersect_key( $stats, self::STATS_TEMPLATE ) );

		return sprintf(
			'Examined %d disputes, %d of them closed, across %d charges; %d charges resolved to an order and %d to none; skipped %d ambiguous same-status groups; recorded %d ledger entries, found %d already recorded and %d with no matching legacy note.',
			$stats['disputes_examined'],
			$stats['closures_found'],
			$stats['charges_seen'],
			$stats['orders_resolved'],
			$stats['charges_without_order'],
			$stats['groups_skipped'],
			$stats['entries_recorded'],
			$stats['already_recorded'],
			$stats['notes_not_matched']
		);
	}

	/**
	 * Mark the backfill as complete.
	 *
	 * @param array  $state  The current backfill state.
	 * @param string $reason Why the backfill stopped, for the log.
	 *
	 * @return void
	 */
	private function finish( array $state, string $reason ) {
		update_option( self::STATE_OPTION, [ 'status' => self::STATUS_DONE ] );
		Logger::info( 'Finished the dispute ledger backfill: ' . $reason . '. ' . $this->describe_stats( $state['stats'] ?? [] ) );
	}

	/**
	 * Stop the backfill part-way, keeping enough state for someone to resume it.
	 *
	 * @param array  $state  The current backfill state.
	 * @param string $reason Why the backfill stopped, for the log.
	 *
	 * @return void
	 */
	private function fail( array $state, string $reason ) {
		// Distinct from `done`, and keeps `created_before` and `page`: a store that lost its
		// connection mid-scan is half-applied, and putting the status back to `pending` is all it
		// takes to pick the scan up where it stopped.
		$state['status'] = self::STATUS_FAILED;
		update_option( self::STATE_OPTION, $state );

		Logger::error( 'Stopped the dispute ledger backfill: ' . $reason . '. ' . $this->describe_stats( $state['stats'] ?? [] ) );
	}

	/**
	 * Retry a failed page, up to a cap so an account that cannot reach the server stops trying.
	 *
	 * @param array  $state   The current backfill state.
	 * @param string $message The error the disputes request failed with.
	 *
	 * @return void
	 */
	private function handle_failure( array $state, string $message ) {
		$attempts          = (int) ( $state['attempts'] ?? 0 ) + 1;
		$state['attempts'] = $attempts;

		if ( $attempts >= self::MAX_ATTEMPTS ) {
			$this->fail( $state, 'giving up after ' . $attempts . ' failed attempts, the last with "' . $message . '"' );
			return;
		}

		update_option( self::STATE_OPTION, $state );

		Logger::error( 'Could not fetch disputes for the ledger backfill (attempt ' . $attempts . '). Error: ' . $message );

		$this->action_scheduler_service->schedule_job( time() + self::RETRY_DELAY, self::BACKFILL_ACTION );
	}
}
