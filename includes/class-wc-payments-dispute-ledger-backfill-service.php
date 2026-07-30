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
 * refund never applied.
 */
class WC_Payments_Dispute_Ledger_Backfill_Service {

	const BACKFILL_ACTION = 'wcpay_dispute_ledger_backfill';
	const STATE_OPTION    = 'wcpay_dispute_ledger_backfill_state';

	const STATUS_PENDING = 'pending';
	const STATUS_DONE    = 'done';

	const PAGE_SIZE    = 100;
	const MAX_ATTEMPTS = 5;
	const RETRY_DELAY  = HOUR_IN_SECONDS;

	/**
	 * Meta key prefix, suffixed with the dispute status, naming the dispute a backfilled closure note
	 * was attributed to.
	 *
	 * The earlier version collapsed same-status closures on a charge into a single note, so a charge
	 * with several of them carries evidence for only one. This records which dispute claimed that
	 * evidence, so the rest stay unmarked even when they turn up in a later page of the scan.
	 *
	 * @const string
	 */
	const BACKFILL_CLAIM_META_KEY_PREFIX = '_wcpay_dispute_closed_backfill_';

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
		// that the backfill is due and the first job is scheduled from `init` instead.
		add_action( 'init', [ $this, 'maybe_schedule_backfill' ], 20 );
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
			$this->finish( 'the upgrade timestamp is missing from the backfill state' );
			return;
		}

		try {
			$request = List_Disputes::create();
			$request->set_page( $page );
			$request->set_page_size( self::PAGE_SIZE );
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

		$this->backfill_page( $disputes );

		if ( count( $disputes ) < self::PAGE_SIZE ) {
			$this->finish( 'reached the end of the disputes list' );
			return;
		}

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
	 * @return void
	 */
	private function backfill_page( array $disputes ) {
		$candidates = [];

		foreach ( $disputes as $dispute ) {
			$dispute_id = (string) ( $dispute['dispute_id'] ?? '' );
			$charge_id  = (string) ( $dispute['charge_id'] ?? '' );
			$status     = (string) ( $dispute['status'] ?? '' );

			if ( '' === $dispute_id || '' === $charge_id || ! in_array( $status, self::CLOSED_STATUSES, true ) ) {
				continue;
			}

			$candidates[ $charge_id ][ $status ][] = $dispute_id;
		}

		if ( empty( $candidates ) ) {
			return;
		}

		foreach ( $this->wcpay_db->orders_with_charge_id_from_charge_ids( array_keys( $candidates ) ) as $result ) {
			$order     = $result['order'];
			$charge_id = $result['charge_id'];

			foreach ( $candidates[ $charge_id ] ?? [] as $status => $dispute_ids ) {
				// The page is sorted by creation, and the list exposes no closed-at value, so the
				// first of a charge's same-status disputes is the closest available stand-in for the
				// one whose closure the earlier version actually applied.
				$this->backfill_dispute( $order, $charge_id, $status, $dispute_ids[0] );
			}
		}
	}

	/**
	 * Write the ledger for a single dispute, if the order shows its closure was applied.
	 *
	 * @param WC_Order $order      Order the disputed charge belongs to.
	 * @param string   $charge_id  The ID of the disputed charge.
	 * @param string   $status     The status the dispute closed with.
	 * @param string   $dispute_id The ID of the dispute.
	 *
	 * @return void
	 */
	private function backfill_dispute( WC_Order $order, string $charge_id, string $status, string $dispute_id ) {
		$claim_meta_key  = self::BACKFILL_CLAIM_META_KEY_PREFIX . $status;
		$ledger_meta_key = WC_Payments_Order_Service::WCPAY_DISPUTE_CLOSED_META_KEY_PREFIX . $dispute_id;

		if ( $order->meta_exists( $claim_meta_key ) || $order->meta_exists( $ledger_meta_key ) ) {
			return;
		}

		// An empty dispute ID reproduces the note the earlier version wrote. Its absence means the
		// closure never reached this store, so the ledger stays unwritten and a redelivery is free to
		// apply it.
		if ( ! $this->order_service->order_note_exists( $order, $this->order_service->get_dispute_closed_note( $charge_id, $status ) ) ) {
			return;
		}

		$order->update_meta_data( $ledger_meta_key, gmdate( 'Y-m-d H:i:s' ) );
		$order->update_meta_data( $claim_meta_key, $dispute_id );
		$order->save_meta_data();

		Logger::info( 'Recorded the closure of dispute ' . $dispute_id . ' on order ' . $order->get_id() . ' from an order note predating the dispute ledger.' );
	}

	/**
	 * Mark the backfill as complete.
	 *
	 * @param string $reason Why the backfill stopped, for the log.
	 *
	 * @return void
	 */
	private function finish( string $reason ) {
		update_option( self::STATE_OPTION, [ 'status' => self::STATUS_DONE ] );
		Logger::info( 'Finished the dispute ledger backfill: ' . $reason . '.' );
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
		$attempts = (int) ( $state['attempts'] ?? 0 ) + 1;

		if ( $attempts >= self::MAX_ATTEMPTS ) {
			$this->finish( 'giving up after ' . $attempts . ' failed attempts, the last with "' . $message . '"' );
			return;
		}

		$state['attempts'] = $attempts;
		update_option( self::STATE_OPTION, $state );

		Logger::error( 'Could not fetch disputes for the ledger backfill (attempt ' . $attempts . '). Error: ' . $message );

		$this->action_scheduler_service->schedule_job( time() + self::RETRY_DELAY, self::BACKFILL_ACTION );
	}
}
