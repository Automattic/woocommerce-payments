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
use WCPay\Exceptions\API_Exception;

/**
 * Records the dispute closure ledger for disputes that closed before the store upgraded.
 *
 * Closures used to be de-duplicated on the order note text alone. That text now carries the dispute
 * ID, so a closure the store already applied under an earlier version stops being recognised when
 * the platform redelivers the event, and a lost dispute refunds twice. Writing the ledger meta for
 * those older closures shuts that window.
 *
 * The evidence is the note the earlier version left on the order. It proves the handler ran to the
 * end, not that every side effect landed: 10.9.0 threw away what `wc_create_refund()` returned and
 * wrote the note anyway. Enough to suppress a redelivery, and no stronger claim than that.
 *
 * Marking a closure the store never processed would be worse than the bug, since a genuine
 * redelivery would then be suppressed and the refund never applied. So every ambiguity here resolves
 * the same way: leave the closure unmarked, and log enough to tell a scan that found nothing from
 * one that matched nothing.
 */
class WC_Payments_Dispute_Ledger_Backfill_Service {

	const BACKFILL_ACTION = 'wcpay_dispute_ledger_backfill';
	const STATE_OPTION    = 'wcpay_dispute_ledger_backfill_state';

	/**
	 * Log source for the scan, kept apart from the gateway's own log.
	 *
	 * A scan can run for days, and its lines would otherwise be scattered through whatever checkout
	 * traffic the store had at the time.
	 *
	 * @const string
	 */
	const LOG_SOURCE = 'wcpay-dispute-ledger-backfill';

	const STATUS_PENDING = 'pending';
	const STATUS_DONE    = 'done';
	const STATUS_FAILED  = 'failed';

	const PAGE_SIZE = 100;

	// Nothing resumes a scan that gave up, so the budget has to outlast a platform incident, not just
	// a blip. One page gets a little over a day to come back before the backfill is abandoned.
	const MAX_ATTEMPTS = 8;
	const RETRY_DELAY  = 3 * HOUR_IN_SECONDS;

	/**
	 * Ceiling on the pages one scan will walk.
	 *
	 * The scan stops on an empty page, which relies on the endpoint eventually returning one. An
	 * endpoint that ignored the page parameter would otherwise queue itself forever.
	 *
	 * @const int
	 */
	const MAX_PAGES = 1000;

	/**
	 * Meta key prefix, suffixed with the dispute status, naming the dispute a backfilled closure note
	 * was attributed to.
	 *
	 * The earlier version collapsed a charge's same-status closures into one note, so a charge with
	 * several of them carries evidence for only one. Within a page they are spotted as ambiguous and
	 * none are marked. Across a page boundary there is nothing to compare against, so this records
	 * which dispute claimed the evidence, and a later member of the group retracts that claim.
	 *
	 * Kept outside the ledger's own `_wcpay_dispute_closed_` namespace, where it would read as an
	 * entry for a dispute named after the status.
	 *
	 * @const string
	 */
	const BACKFILL_CLAIM_META_KEY_PREFIX = '_wcpay_dispute_backfill_claim_';

	/**
	 * Claim value standing in for a dispute ID once a (charge, status) group is known to be
	 * ambiguous, so no member of it can be marked on a later page either.
	 *
	 * Dispute IDs are `dp_`-prefixed, so this cannot be mistaken for one.
	 *
	 * @const string
	 */
	const BACKFILL_CLAIM_AMBIGUOUS = 'ambiguous';

	/**
	 * Dispute statuses that reach WC_Payments_Order_Service::mark_payment_dispute_closed(), inquiry
	 * closures included, since they run through the same note generator.
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
	 * The counters a scan accumulates, so its outcome reads off a single log line.
	 *
	 * Marking nothing is a legitimate result: notes are reconstructed and compared verbatim, and a
	 * store that changed domain or site language matches none of them. Examining nothing is not.
	 * These tell the two apart.
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
		'groups_retracted'      => 0,
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
		// ActionScheduler is not up when the plugin update runs, so the migration only records that the
		// backfill is due and the first job is scheduled here. `init` because it has to fire in every
		// context: a headless store, or one driven over REST and WP-CLI, never opens the admin.
		// Priority 20 puts this after ActionScheduler's own `init` callbacks, which run at 1.
		add_action( 'init', [ $this, 'maybe_schedule_backfill' ], 20 );
		add_action( self::BACKFILL_ACTION, [ $this, 'run_backfill_batch' ] );
	}

	/**
	 * Schedule the next backfill job, unless one is already queued.
	 *
	 * @return void
	 */
	public function maybe_schedule_backfill() {
		// The ActionScheduler lookup below queries its tables uncached, so it sits behind the option
		// read. On every store but the few mid-scan, this costs one autoloaded option and stops.
		$state = get_option( self::STATE_OPTION );

		if ( ! is_array( $state ) || self::STATUS_PENDING !== ( $state['status'] ?? '' ) ) {
			return;
		}

		if ( ! function_exists( 'as_has_scheduled_action' ) || $this->action_scheduler_service->pending_action_exists( self::BACKFILL_ACTION ) ) {
			return;
		}

		$this->action_scheduler_service->schedule_job( time(), self::BACKFILL_ACTION );

		// A page normally queues its own successor, so reaching here twice means a job died without
		// rescheduling itself. The page number is what says which one, and that it is being retried.
		$this->log( 'Queued the dispute ledger backfill from page ' . max( 1, (int) ( $state['page'] ?? 1 ) ) . '.' );
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

		$page           = max( 1, (int) ( $state['page'] ?? 1 ) );
		$created_before = (string) ( $state['created_before'] ?? '' );

		if ( '' === $created_before ) {
			$this->fail( $state, 'the upgrade timestamp is missing from the backfill state' );
			return;
		}

		if ( $page >= self::MAX_PAGES ) {
			$this->fail( $state, 'the disputes list never returned an empty page within ' . self::MAX_PAGES . ' pages' );
			return;
		}

		// The attempt is banked before the work, not after. ActionScheduler marks an action that threw
		// as `failed`, and `as_has_scheduled_action()` only sees RUNNING and PENDING, so the next
		// scheduling pass re-queues a page that blew up as if it had never run. Counting up front is
		// what makes the cap and the retry delay bite on a page that fails outside the request.
		$state['attempts'] = (int) ( $state['attempts'] ?? 0 ) + 1;
		update_option( self::STATE_OPTION, $state );

		try {
			$request = List_Disputes::create();
			$request->set_page( $page );
			$request->set_page_size( self::PAGE_SIZE );
			// An explicit order keeps the pages of one scan disjoint. Without it the server picks its
			// own, and rows repeat or get skipped as the scan walks.
			// Newest first, because only 8.7.0 and later wrote a note this scan can reconstruct. Oldest
			// first would reach those last, where any truncation cuts them off.
			$request->set_sort_by( 'created' );
			$request->set_sort_direction( 'desc' );
			// The list carries no closed-at value, and a dispute cannot close before it was created, so
			// creation is the only bound available for keeping the scan on pre-upgrade closures.
			$request->set_created_before( $created_before );
			// This picks which side of the account the list is read from, not whether the data is real.
			// Reading the wrong side returns an empty first page, which the check below cannot tell
			// from having reached the end of the list, so it cannot be left to the mode the dispatching
			// request happened to be in. Onboarding mode is what names the side holding the store's
			// history: a sandbox store has no live side at all, and a live store's own history is live.
			// A live store that has also sold through the gateway's test toggle has closures on the
			// test side too, and those go unscanned — the cheaper miss, since a redelivery there
			// re-refunds a test-mode order rather than a live one.
			$request->set_filters( [ 'test_mode' => WC_Payments::mode()->is_test_mode_onboarding() ] );

			$response = $request->send();

			$disputes = isset( $response['data'] ) && is_array( $response['data'] ) ? $response['data'] : [];

			// An empty page rather than a short one: neither the page the server counts from nor the
			// size it will serve is guaranteed to match what is asked for here, and a short first page
			// would end the scan having read nothing. Repeating a page is harmless, every write below
			// is idempotent.
			if ( empty( $disputes ) ) {
				$this->finish( $state, 'reached the end of the disputes list' );
				return;
			}

			$page_stats = $this->backfill_page_in_site_locale( $disputes );
		} catch ( Throwable $e ) {
			// Throwable rather than Exception: `List_Disputes::format_response()` feeds a
			// `WC_Order|WC_Order_Refund|false` into a parameter typed `WC_Order`, so a TypeError can
			// escape `send()` as readily as an API failure does.
			if ( $e instanceof API_Exception && 'wcpay_account_not_found' === $e->get_error_code() ) {
				// A store with no account has no dispute history to read, and waiting will not give it
				// one, so the retry budget would only spend a day arriving at the same answer. Failed
				// rather than done, because a store that connects an account later has closures this
				// scan never saw, and only the state says so.
				$this->fail( $state, 'the site has no connected account to read disputes from' );
				return;
			}

			$this->handle_failure( $state, $e->getMessage() );
			return;
		}

		$state['stats']    = $this->merge_stats( $state['stats'] ?? [], $page_stats );
		$state['page']     = $page + 1;
		$state['attempts'] = 0;
		update_option( self::STATE_OPTION, $state );

		$this->action_scheduler_service->schedule_job( time(), self::BACKFILL_ACTION );
	}

	/**
	 * Process a page with the site locale in force.
	 *
	 * The notes were written by a webhook, so they were translated in the site locale. In admin
	 * context `determine_locale()` hands back the current user's own locale instead, and
	 * ActionScheduler's async runner reaches PHP through `admin-ajax.php`, which defines `WP_ADMIN`
	 * and forwards the dispatching admin's cookies. Without this the reconstruction can come out in a
	 * language the store never wrote a note in, and match nothing.
	 *
	 * @param array $disputes Rows from the disputes list response.
	 *
	 * @return array<string, int> The counters for this page.
	 */
	private function backfill_page_in_site_locale( array $disputes ): array {
		$site_locale = get_locale();

		// A text domain is loaded from `determine_locale()`, which the locale switcher only started
		// filtering in WordPress 6.2. On 6.0 and 6.1 the switch alone reloads the catalogue in the
		// admin's language — the language this exists to keep out. Drop this once the minimum supported
		// version reaches 6.2; leaving it on there would also override a locale another plugin switched
		// to from a hook inside the loop, which 6.2 respects.
		$needs_locale_filter = version_compare( get_bloginfo( 'version' ), '6.2', '<' );

		// Only while a switch is in force, which is what 6.2 does and has to be done: filtering
		// unconditionally leaves `switch_to_locale()` with nothing to switch to, and it skips the
		// reload the filter exists to steer.
		$force_site_locale = function ( $locale ) use ( $site_locale ) {
			return is_locale_switched() ? $site_locale : $locale;
		};

		if ( $needs_locale_filter ) {
			add_filter( 'determine_locale', $force_site_locale );
		}

		$switched = switch_to_locale( $site_locale );

		try {
			return $this->backfill_page( $disputes );
		} finally {
			if ( $switched ) {
				restore_previous_locale();
			}

			if ( $needs_locale_filter ) {
				remove_filter( 'determine_locale', $force_site_locale );
			}
		}
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
					// returned, so the note belongs to whichever of them closed first, and the list
					// carries no closed-at value to work that out. Guessing wrong would suppress a
					// refund that is still owed, so none of them are marked.
					++$stats['groups_skipped'];
					$this->retract_group( $order, $charge_id, $status, $dispute_ids );
					continue;
				}

				$outcome = $this->backfill_dispute( $order, $charge_id, $status, $dispute_ids[0] );
				++$stats[ $outcome ];
			}
		}

		if ( ! empty( $unresolved_charge_ids ) ) {
			$stats['charges_without_order'] = count( $unresolved_charge_ids );

			// Either the charge never reached an order on this store, or the shared lookup dropped it:
			// that query caps its result at the number of charge IDs given, so a charge held by several
			// orders pushes another charge's order out of the set. Both leave closures unmarked.
			$this->log(
				'No order found for charges ' . implode( ', ', array_keys( $unresolved_charge_ids ) ) . '.'
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
		$claim_meta_key = self::BACKFILL_CLAIM_META_KEY_PREFIX . $status;

		if ( $order->meta_exists( WC_Payments_Order_Service::WCPAY_DISPUTE_CLOSED_META_KEY_PREFIX . $dispute_id ) ) {
			return 'already_recorded';
		}

		// A claim held by anything other than this dispute means a same-status group split across page
		// boundaries: the member seen first claimed the one note the earlier version wrote, and this
		// one is proof the note cannot be attributed to either of them.
		if ( $order->meta_exists( $claim_meta_key ) ) {
			$this->retract_group( $order, $charge_id, $status, [ $dispute_id ] );
			return 'groups_retracted';
		}

		// An empty dispute ID reproduces the note the earlier version wrote. No note means the closure
		// never reached this store, so the ledger stays unwritten and a redelivery is free to apply it.
		// The comparison is exact, so a re-translated note, or one linking to a domain the store has
		// since left, misses too. Same safe direction, and the reason the scan counts what it missed.
		if ( ! $this->order_service->order_note_exists( $order, $this->order_service->get_dispute_closed_note( $charge_id, $status ) ) ) {
			return 'notes_not_matched';
		}

		$order->update_meta_data( WC_Payments_Order_Service::WCPAY_DISPUTE_CLOSED_META_KEY_PREFIX . $dispute_id, gmdate( 'Y-m-d H:i:s' ) );
		// The same evidence settles the creation: a store that applied the closure applied the creation
		// first. Without the entry, a redelivered legacy creation puts a closed dispute's order back on
		// hold and suspends the subscription it is the parent of.
		$order->update_meta_data( WC_Payments_Order_Service::WCPAY_DISPUTE_CREATED_META_KEY_PREFIX . $dispute_id, gmdate( 'Y-m-d H:i:s' ) );
		$order->update_meta_data( $claim_meta_key, $dispute_id );
		$this->save_order_meta_without_touching_the_order( $order );

		$this->log( 'Recorded the closure of dispute ' . $dispute_id . ' on order ' . $order->get_id() . ' from an order note predating the dispute ledger.' );

		return 'entries_recorded';
	}

	/**
	 * Take a (charge, status) group out of the backfill's reach, undoing anything already written for
	 * it.
	 *
	 * A group turns ambiguous the moment it has a second member, and members can arrive pages apart,
	 * so the retraction works backwards as well as forwards: whichever dispute already claimed the
	 * note gives its entries back, and the claim key keeps a value no dispute ID can equal, so a third
	 * member cannot claim it later.
	 *
	 * Removing both entries is safe because this scan wrote them together. It cannot have written over
	 * the live path's: that path records a creation only alongside or after the matching closure, and
	 * a dispute whose closure is already in the ledger never reaches here.
	 *
	 * @param WC_Order $order       Order the disputed charge belongs to.
	 * @param string   $charge_id   The ID of the disputed charge.
	 * @param string   $status      The status the disputes closed with.
	 * @param string[] $dispute_ids The disputes that made the group ambiguous, for the log.
	 *
	 * @return void
	 */
	private function retract_group( WC_Order $order, string $charge_id, string $status, array $dispute_ids ) {
		$claim_meta_key = self::BACKFILL_CLAIM_META_KEY_PREFIX . $status;
		$claimed_by     = (string) $order->get_meta( $claim_meta_key );

		if ( self::BACKFILL_CLAIM_AMBIGUOUS === $claimed_by ) {
			return;
		}

		if ( '' !== $claimed_by ) {
			$order->delete_meta_data( WC_Payments_Order_Service::WCPAY_DISPUTE_CLOSED_META_KEY_PREFIX . $claimed_by );
			$order->delete_meta_data( WC_Payments_Order_Service::WCPAY_DISPUTE_CREATED_META_KEY_PREFIX . $claimed_by );
			$dispute_ids[] = $claimed_by;
		}

		$order->update_meta_data( $claim_meta_key, self::BACKFILL_CLAIM_AMBIGUOUS );
		$this->save_order_meta_without_touching_the_order( $order );

		$this->log(
			'Leaving charge ' . $charge_id . ' unmarked for status ' . $status
			. ', its closure note cannot be attributed to one of ' . implode( ', ', array_unique( $dispute_ids ) ) . '.'
		);
	}

	/**
	 * Persist the order's meta without the order itself counting as modified.
	 *
	 * On HPOS a meta write counts as an order change: the data store bumps `date_modified`, saves, and
	 * fires `woocommerce_update_order`, which queues a fraud-tracking job. This scan only annotates
	 * history and must not make historical orders look freshly modified to reports and accounting
	 * sync, so it opts out the way core does when it writes meta alone.
	 *
	 * @param WC_Order $order Order whose meta has been changed.
	 *
	 * @return void
	 */
	private function save_order_meta_without_touching_the_order( WC_Order $order ) {
		add_filter( 'woocommerce_orders_table_datastore_should_save_after_meta_change', '__return_false' );
		$order->save_meta_data();
		remove_filter( 'woocommerce_orders_table_datastore_should_save_after_meta_change', '__return_false' );
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
			'Examined %d disputes, %d of them closed, across %d charges; %d charges resolved to an order and %d to none; skipped %d ambiguous same-status groups and retracted %d that only turned out ambiguous on a later page; recorded %d ledger entries, found %d already recorded and %d with no matching legacy note.',
			$stats['disputes_examined'],
			$stats['closures_found'],
			$stats['charges_seen'],
			$stats['orders_resolved'],
			$stats['charges_without_order'],
			$stats['groups_skipped'],
			$stats['groups_retracted'],
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
		// The counters stay in the option, not only in the log line below. Nobody can re-run a one-shot
		// migration to find out what it did, and WooCommerce prunes its logs after 30 days, so on a
		// store that upgraded months ago the option is the only surviving record of whether the scan
		// marked nothing or ten thousand entries.
		$state['status']      = self::STATUS_DONE;
		$state['attempts']    = 0;
		$state['stats']       = $this->merge_stats( $state['stats'] ?? [], [] );
		$state['finished_at'] = gmdate( 'Y-m-d H:i:s' );
		update_option( self::STATE_OPTION, $state );

		$this->log( 'Finished the dispute ledger backfill: ' . $reason . '. ' . $this->describe_stats( $state['stats'] ?? [] ) );
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
		// Distinct from `done`, and keeps `created_before`, `page` and the counters: a store that lost
		// its connection mid-scan is half-applied, and the state says how far it got.
		// Nothing picks it back up on its own, so resuming means setting the option's status back to
		// `pending` by hand.
		$state['status']    = self::STATUS_FAILED;
		$state['stats']     = $this->merge_stats( $state['stats'] ?? [], [] );
		$state['failed_at'] = gmdate( 'Y-m-d H:i:s' );
		update_option( self::STATE_OPTION, $state );

		$this->log( 'Stopped the dispute ledger backfill: ' . $reason . '. ' . $this->describe_stats( $state['stats'] ?? [] ), 'error' );
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
		// Already banked, and already persisted, by the caller before it started work.
		$attempts = (int) ( $state['attempts'] ?? 0 );

		if ( $attempts >= self::MAX_ATTEMPTS ) {
			$this->fail( $state, 'giving up after ' . $attempts . ' failed attempts, the last with "' . $message . '"' );
			return;
		}

		$this->log( 'Could not process a page of disputes for the ledger backfill (attempt ' . $attempts . '). Error: ' . $message, 'error' );

		$this->action_scheduler_service->schedule_job( time() + self::RETRY_DELAY, self::BACKFILL_ACTION );
	}

	/**
	 * Write a line to the scan's log.
	 *
	 * Straight to WC_Logger rather than through WCPay\Logger, which stays quiet unless the merchant
	 * turned WooPayments logging on. Nobody turns it on for a job they were never told about, and this
	 * one runs once and cannot be repeated to find out what it did, so the record has to be written
	 * the first time or not at all.
	 *
	 * @param string $message The line to write.
	 * @param string $level   A WC_Log_Levels level.
	 *
	 * @return void
	 */
	private function log( string $message, string $level = 'info' ) {
		wc_get_logger()->log( $level, $message, [ 'source' => self::LOG_SOURCE ] );
	}
}
