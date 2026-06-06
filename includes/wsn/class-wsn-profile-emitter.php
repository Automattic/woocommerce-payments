<?php
/**
 * Class WSN_Profile_Emitter
 *
 * @package WooCommerce\Payments\WSN
 */

defined( 'ABSPATH' ) || exit;

/**
 * Listens for Profile-affecting changes and emits the composed Profile
 * payload to the WooPay server through Action Scheduler.
 *
 * Triggers, with the scheduling strategy each uses:
 *
 *   1. `wcpay_wsn_profile_changed` — fires on WSN settings PUT when any
 *      of the 4 PROFILE_FIELDS changed. Routes to `force_immediate_push`
 *      (no debounce). A Save click is a single, deliberate, user-initiated
 *      event; there's no burst to collapse, and merchants expect the WSN
 *      storefront to reflect the change immediately.
 *   2. `wcpay_woopay_appearance_changed` — fires inside
 *      WC_Payments_Styles_Cache::set_woopay_appearance() after the
 *      appearance + font rules are persisted. Routes to
 *      `schedule_debounced_push` (60s window). These events can fire
 *      repeatedly within a single request via theme writes
 *      (after_switch_theme, save_post_wp_global_styles,
 *      customize_save_after) and plugin updates — debouncing collapses
 *      bursts into one push.
 *   3. `wcpay_wsn_profile_backstop` — 6-hour recurring Action Scheduler
 *      job that catches missed hook fires (plugin deactivation during
 *      emit, fatal during compose, race during deploy). Also routes
 *      through `schedule_debounced_push` to coalesce with any
 *      appearance-debounce already pending.
 *
 * `force_immediate_push` schedules an AS action at `time()`; the next AS
 * tick fires it. `schedule_debounced_push` schedules at
 * `time() + DEBOUNCE_SECONDS` (60s) and benefits from AS's same-hook+args
 * collapse so rapid re-schedules don't multiply rows.
 *
 * Skip-emit guard: each push hashes the canonical payload to a
 * `payload_version`. When that version matches what's in
 * `wsn_profile_last_synced_version`, the push is a no-op. This is what
 * makes the backstop cheap — at most one push per content change per
 * 6-hour window even if every backstop tick fires.
 *
 * Failure handling: send errors are caught into a 7-day
 * `wsn_profile_last_error` transient. The state is purely advisory — the
 * next trigger (settings change, appearance change, or backstop) will
 * try again. There is no internal retry queue; AS + the backstop are
 * the retry mechanism.
 *
 * Owned by RSM-3945.
 */
class WSN_Profile_Emitter {

	/**
	 * Action Scheduler hook for the debounced single-push action.
	 *
	 * @var string
	 */
	const ACTION_PUSH = 'wcpay_wsn_profile_push';

	/**
	 * Action Scheduler hook for the recurring 6h backstop.
	 *
	 * @var string
	 */
	const ACTION_BACKSTOP = 'wcpay_wsn_profile_backstop';

	/**
	 * Debounce window — how long after the last trigger we wait before
	 * the single AS action fires. Bursts of changes within this window
	 * collapse to one push.
	 *
	 * @var int
	 */
	const DEBOUNCE_SECONDS = MINUTE_IN_SECONDS;

	/**
	 * Recurring interval for the backstop AS job. Catches missed hook
	 * fires that the change-triggered debounce path didn't capture.
	 *
	 * @var int
	 */
	const BACKSTOP_INTERVAL_SECONDS = 6 * HOUR_IN_SECONDS;

	/**
	 * Option key storing the unix timestamp of the most recent successful
	 * push. Read by the Hub UI "Last synced X ago" badge.
	 *
	 * @var string
	 */
	const OPTION_LAST_SYNCED = 'wsn_profile_last_synced';

	/**
	 * Option key storing the payload_version (sha256) of the most recent
	 * successful push. Used by the skip-emit guard: if the next compose
	 * produces the same version, the push is a no-op.
	 *
	 * @var string
	 */
	const OPTION_LAST_SYNCED_VERSION = 'wsn_profile_last_synced_version';

	/**
	 * Transient storing the most recent send error. Read by the Hub UI to
	 * surface a "Last sync failed — Retry" badge. 7-day TTL so the badge
	 * eventually clears itself if the merchant abandons the page.
	 *
	 * @var string
	 */
	const TRANSIENT_LAST_ERROR = 'wsn_profile_last_error';

	/**
	 * TTL for the last-error transient.
	 *
	 * @var int
	 */
	const TRANSIENT_LAST_ERROR_TTL = WEEK_IN_SECONDS;

	/**
	 * Transient key — cached "backstop is scheduled" flag.
	 *
	 * `as_has_scheduled_action()` is a DB query against the
	 * actionscheduler_actions table. Once we've verified the backstop
	 * is scheduled, caching that fact for one backstop interval saves
	 * an AS DB query on every WP request when the sub-flag is ON.
	 * At 100K merchants × ~100 requests/day = millions of unnecessary
	 * SELECTs/day without this cache.
	 *
	 * @var string
	 */
	const TRANSIENT_BACKSTOP_SCHEDULED = 'wsn_profile_backstop_scheduled';

	/**
	 * Max characters of an exception message we'll store in the
	 * last-error transient. Caps the cardinality of well-known
	 * transient keys against verbose network-layer messages that
	 * can leak endpoint URLs or stack frame text.
	 *
	 * @var int
	 */
	const MAX_LAST_ERROR_MESSAGE_LEN = 200;

	/**
	 * Direct Jetpack-signed POST/DELETE transport to the WooPay host.
	 *
	 * Replaces the prior `WC_Payments_API_Client` dependency. The api-client
	 * routes to the WCPay backend (`public-api.wordpress.com/wpcom/v2/sites/{blog_id}/wcpay/...`),
	 * which is the wrong destination for the WSN Profile push — no handler
	 * exists at that path. The transport targets WooPay directly via
	 * `Client::remote_request()`, mirroring the production `/init` pattern.
	 *
	 * @var WSN_Profile_Transport
	 */
	private $transport;

	/**
	 * Centralized Action Scheduler service — used for schedule_job's
	 * built-in deduplication (prevents N rapid changes scheduling N
	 * AS rows; instead they collapse to one rescheduled row).
	 *
	 * @var WC_Payments_Action_Scheduler_Service
	 */
	private $scheduler;

	/**
	 * Constructor.
	 *
	 * @param WSN_Profile_Transport                $transport Direct-to-WooPay Jetpack-signed transport.
	 * @param WC_Payments_Action_Scheduler_Service $scheduler Action Scheduler facade.
	 */
	public function __construct(
		WSN_Profile_Transport $transport,
		WC_Payments_Action_Scheduler_Service $scheduler
	) {
		$this->transport = $transport;
		$this->scheduler = $scheduler;
	}

	/**
	 * Register all WP hooks + ensure the recurring backstop is scheduled.
	 *
	 * Safe to call multiple times — add_action is idempotent for the same
	 * (hook, callback, priority) triple, and ensure_backstop_scheduled
	 * short-circuits when a backstop is already pending.
	 */
	public function init_hooks(): void {
		// Profile-tab Save: route through force_immediate_push so the
		// merchant sees the WSN storefront reflect the change as soon as
		// the AS tick fires (≤ a few seconds), not 60s later. This is a
		// deliberate, one-shot, user-initiated event — there's no burst
		// to collapse, and the rest-controller already protects against
		// rapid resubmits via its endpoint-level checks. Same execution
		// path the Retry button uses (force_immediate_push → ACTION_PUSH
		// at time() → execute_push → skip-emit guard + error handling).
		add_action( 'wcpay_wsn_profile_changed', [ $this, 'force_immediate_push' ], 10, 0 );

		// Appearance-change path stays debounced. `wcpay_woopay_appearance_changed`
		// can fire repeatedly within a single request via theme writes
		// (after_switch_theme, save_post_wp_global_styles, customize_save_after)
		// and plugin updates — the debounce collapses those bursts into a
		// single push.
		add_action( 'wcpay_woopay_appearance_changed', [ $this, 'schedule_debounced_push' ], 10, 0 );

		add_action( self::ACTION_PUSH, [ $this, 'execute_push' ] );
		add_action( self::ACTION_BACKSTOP, [ $this, 'schedule_debounced_push' ] );

		// Manual "Retry sync" trigger from the Hub UI Profile-tab badge.
		// REST throttle at the controller layer (60s site-wide transient)
		// keeps the AS schedule/unschedule churn off the DB under
		// button-mashing.
		add_action( 'wcpay_wsn_profile_force_resync', [ $this, 'force_immediate_push' ], 10, 0 );

		$this->ensure_backstop_scheduled();
	}

	/**
	 * Schedule (or re-schedule) the single debounced push action.
	 *
	 * `WC_Payments_Action_Scheduler_Service::schedule_job` internally
	 * unschedules any pending action with the same hook + args + group
	 * before scheduling the new one, so rapid bursts collapse into a
	 * single AS row.
	 *
	 * @return void
	 */
	public function schedule_debounced_push(): void {
		$this->scheduler->schedule_job( time() + self::DEBOUNCE_SECONDS, self::ACTION_PUSH );
	}

	/**
	 * AS handler. Composes the payload, applies the skip-emit guard,
	 * and fires the Jetpack-signed POST. Updates state on success or
	 * records the error on failure.
	 *
	 * Never throws — exceptions are caught and recorded as a transient
	 * so AS doesn't retry the action (the backstop is the retry
	 * mechanism; AS's own retry would compound).
	 *
	 * @return void
	 */
	public function execute_push(): void {
		try {
			$payload = WSN_Profile_Payload_Composer::compose();

			// Skip-emit guard: same content → no push.
			$last_version = (string) get_option( self::OPTION_LAST_SYNCED_VERSION, '' );
			if ( '' !== $last_version && ( $payload['payload_version'] ?? '' ) === $last_version ) {
				return;
			}

			$this->transport->send( $payload );

			update_option( self::OPTION_LAST_SYNCED, time(), false );
			update_option( self::OPTION_LAST_SYNCED_VERSION, (string) ( $payload['payload_version'] ?? '' ), false );
			delete_transient( self::TRANSIENT_LAST_ERROR );
		} catch ( \Throwable $e ) {
			// Cap message length — network-layer exceptions can include
			// endpoint URLs, stack frame fragments, or PHP internals
			// that aren't useful to the merchant and add cardinality to
			// any error-monitoring that hashes by message. The Hub UI
			// only needs enough text to distinguish failure modes
			// ("network", "401", "422 + field name").
			set_transient(
				self::TRANSIENT_LAST_ERROR,
				[
					'message'   => substr( (string) $e->getMessage(), 0, self::MAX_LAST_ERROR_MESSAGE_LEN ),
					'timestamp' => time(),
				],
				self::TRANSIENT_LAST_ERROR_TTL
			);
		}
	}

	/**
	 * Ensure the recurring backstop is scheduled. Idempotent — short-
	 * circuits when one is already pending.
	 *
	 * Jittered 10-60s so a fleet-wide WCPay update doesn't synchronize
	 * every merchant's backstop tick to the same wall-clock second.
	 * Mirrors `WC_Payments_Action_Scheduler_Service::__construct`'s
	 * pattern at the STORE_SETUP_SYNC_ACTION registration.
	 *
	 * @return bool True if a new backstop was scheduled by this call;
	 *              false if one was already pending or AS is unavailable.
	 */
	public function ensure_backstop_scheduled(): bool {
		// Short-circuit on the cached "already scheduled" flag — avoids
		// an AS DB query on every WP request once the backstop is in
		// place. Transient TTL matches the backstop interval so the
		// re-check cadence matches the natural firing cadence.
		if ( get_transient( self::TRANSIENT_BACKSTOP_SCHEDULED ) ) {
			return false;
		}

		if ( ! function_exists( 'as_has_scheduled_action' )
			|| ! function_exists( 'as_schedule_recurring_action' ) ) {
			return false;
		}

		if ( as_has_scheduled_action( self::ACTION_BACKSTOP ) ) {
			set_transient( self::TRANSIENT_BACKSTOP_SCHEDULED, 1, self::BACKSTOP_INTERVAL_SECONDS );
			return false;
		}

		as_schedule_recurring_action(
			time() + wp_rand( 10, 60 ),
			self::BACKSTOP_INTERVAL_SECONDS,
			self::ACTION_BACKSTOP
		);
		set_transient( self::TRANSIENT_BACKSTOP_SCHEDULED, 1, self::BACKSTOP_INTERVAL_SECONDS );
		return true;
	}

	/**
	 * Force an immediate push, bypassing the debounce. Called by the
	 * Hub UI "Retry sync" button.
	 *
	 * Re-schedules the AS action at time() — the action fires on the next
	 * AS tick. Still goes through `execute_push` so the skip-emit guard
	 * and error handling apply identically.
	 *
	 * @return void
	 */
	public function force_immediate_push(): void {
		$this->scheduler->schedule_job( time(), self::ACTION_PUSH );
	}

	// Static state accessors — surfaced to the Hub UI.

	/**
	 * Returns the unix timestamp of the most recent successful push.
	 *
	 * @return int|null Timestamp or null when no successful push has occurred.
	 */
	public static function get_last_synced_time(): ?int {
		$value = get_option( self::OPTION_LAST_SYNCED, null );
		return is_numeric( $value ) ? (int) $value : null;
	}

	/**
	 * Returns the payload_version of the most recent successful push.
	 *
	 * @return string sha256 hash, or empty string when no successful push has occurred.
	 */
	public static function get_last_synced_version(): string {
		return (string) get_option( self::OPTION_LAST_SYNCED_VERSION, '' );
	}

	/**
	 * Returns the most recent send error (within the 7-day TTL).
	 *
	 * @return array|null { message: string, timestamp: int } or null when no recent error.
	 */
	public static function get_last_error(): ?array {
		$value = get_transient( self::TRANSIENT_LAST_ERROR );
		return is_array( $value ) ? $value : null;
	}
}
