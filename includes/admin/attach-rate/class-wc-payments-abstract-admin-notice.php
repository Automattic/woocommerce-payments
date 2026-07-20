<?php
/**
 * Abstract base class for WooPayments admin notice nudges.
 *
 * @package WooCommerce\Payments\Admin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Captures the lifecycle scaffolding shared across every WooPayments admin
 * notice: derived naming from a single get_slug(), per-request memoization of
 * the should-show decision, transient-cached eligibility, and the
 * hide/snooze/render/enqueue handlers. Subclasses supply the slug, the
 * eligibility predicate, and the CTA destination.
 */
abstract class WC_Payments_Abstract_Admin_Notice {

	/**
	 * WCPay gateway service.
	 *
	 * @var WC_Payment_Gateway_WCPay
	 */
	protected $wcpay_gateway;

	/**
	 * Account service.
	 *
	 * @var WC_Payments_Account
	 */
	protected $account;

	/**
	 * Per-request memo of should_show(). Same instance is reused across
	 * admin_enqueue_scripts and the woocommerce_sections_{$tab} callback.
	 *
	 * @var bool|null
	 */
	private $should_show_memo = null;

	/**
	 * Constructor.
	 *
	 * @param WC_Payment_Gateway_WCPay $wcpay_gateway WCPay gateway service.
	 * @param WC_Payments_Account      $account       Account service.
	 */
	public function __construct( WC_Payment_Gateway_WCPay $wcpay_gateway, WC_Payments_Account $account ) {
		$this->wcpay_gateway = $wcpay_gateway;
		$this->account       = $account;
	}

	/**
	 * Hooks that fire regardless of request context. Default is a no-op;
	 * subclasses override to register handlers that must run outside admin
	 * context — e.g. order-completion hooks that fire from storefront checkout
	 * and REST webhook handlers (both is_admin() === false).
	 *
	 * The coordinator calls this on every request; init_hooks() stays
	 * admin-gated for the render/CTA/dismiss/snooze surface.
	 *
	 * @return void
	 */
	public function init_global_hooks(): void {}

	/**
	 * Registers admin-init handlers, enqueue hooks, and the WC settings page
	 * section-render hook. Subclasses may override to add notice-specific
	 * admin-context triggers.
	 *
	 * @return void
	 */
	public function init_hooks(): void {
		add_action( 'admin_init', [ $this, 'hide_notice' ] );
		if ( $this->supports_snooze() ) {
			add_action( 'admin_init', [ $this, 'snooze_notice' ] );
		}
		add_action( 'admin_init', [ $this, 'handle_cta' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'register_script' ], 9 );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_script' ] );

		// Inject the mount-point inside the page content area on WC settings
		// pages — bypasses the WC Admin notice interception.
		if ( isset( $_GET['page'] ) && 'wc-settings' === sanitize_key( wp_unslash( $_GET['page'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			add_action( "woocommerce_sections_{$tab}", [ $this, 'maybe_show' ] );
		}
	}

	/**
	 * Whether the notice should render for the current user this request.
	 *
	 * @return bool
	 */
	public function should_show(): bool {
		if ( null !== $this->should_show_memo ) {
			return $this->should_show_memo;
		}
		$this->should_show_memo = $this->compute_should_show();
		return $this->should_show_memo;
	}

	/**
	 * Outputs the React mount-point if the notice is eligible and records the
	 * impression once per user.
	 *
	 * @return void
	 */
	public function maybe_show(): void {
		if ( ! $this->should_show() ) {
			return;
		}
		$this->record_impression_if_first();
		echo '<div id="' . esc_attr( $this->mount_div_id() ) . '"></div>';
	}

	/**
	 * Registers the React bundle and stylesheet.
	 *
	 * @return void
	 */
	public function register_script(): void {
		WC_Payments::register_script_with_dependencies( $this->script_handle(), 'dist/' . $this->dist_name() );
		wp_set_script_translations( $this->script_handle(), 'woocommerce-payments' );
		WC_Payments_Utils::register_style(
			$this->script_handle(),
			plugins_url( 'dist/' . $this->dist_name() . '.css', WCPAY_PLUGIN_FILE ),
			[],
			WC_Payments::get_file_version( 'dist/' . $this->dist_name() . '.css' ),
			'all'
		);
	}

	/**
	 * Enqueues the bundle when the notice is eligible and the current screen is
	 * a WooCommerce / WC-Admin screen. Localizes the action URLs the React
	 * component needs.
	 *
	 * @return void
	 */
	public function enqueue_script(): void {
		// Screen gate before the eligibility check, which runs an order query.
		if ( ! $this->is_notice_screen() ) {
			return;
		}

		if ( ! $this->should_show() ) {
			return;
		}

		// Record the impression here in addition to maybe_show() — maybe_show()
		// only fires on WC settings pages (via woocommerce_sections_{$tab}),
		// while enqueue_script() fires on every eligible WC-admin screen where
		// the React mount can actually render. record_impression_if_first() is
		// idempotent so the duplication is safe.
		$this->record_impression_if_first();

		$settings = array_merge(
			[
				'ctaUrl'     => $this->build_action_url( $this->cta_query_arg(), $this->cta_nonce_action(), $this->cta_nonce_arg() ),
				'dismissUrl' => $this->build_action_url( $this->hide_query_arg(), $this->hide_nonce_action(), $this->hide_nonce_arg() ),
			],
			$this->supports_snooze() ? [
				'snoozeUrl' => $this->build_action_url( $this->snooze_query_arg(), $this->snooze_nonce_action(), $this->snooze_nonce_arg() ),
			] : [],
			$this->get_extra_localize_data()
		);

		wp_localize_script( $this->script_handle(), $this->localize_var_name(), $settings );
		wp_enqueue_script( $this->script_handle() );
		wp_enqueue_style( $this->script_handle() );
	}

	/**
	 * Persists the dismissal in user meta when the dismiss link is followed.
	 *
	 * @return void
	 */
	public function hide_notice(): void {
		if ( ! $this->verify_action_request( $this->hide_query_arg(), $this->hide_nonce_arg(), $this->hide_nonce_action() ) ) {
			return;
		}
		$this->record_tracks_event( $this->dismissed_event_name() );
		update_user_meta( get_current_user_id(), $this->dismissed_meta_key(), time() );
		wp_safe_redirect(
			remove_query_arg(
				array_merge(
					[ $this->hide_query_arg(), $this->hide_nonce_arg() ],
					array_keys( $this->get_extra_action_query_args() )
				)
			)
		);
		exit;
	}

	/**
	 * Records the snooze timestamp when the snooze link is followed.
	 *
	 * @return void
	 */
	public function snooze_notice(): void {
		if ( ! $this->supports_snooze() ) {
			return;
		}
		if ( ! $this->verify_action_request( $this->snooze_query_arg(), $this->snooze_nonce_arg(), $this->snooze_nonce_action() ) ) {
			return;
		}
		$this->record_tracks_event( $this->snoozed_event_name() );
		update_user_meta( get_current_user_id(), $this->snoozed_meta_key(), time() );
		wp_safe_redirect(
			remove_query_arg(
				array_merge(
					[ $this->snooze_query_arg(), $this->snooze_nonce_arg() ],
					array_keys( $this->get_extra_action_query_args() )
				)
			)
		);
		exit;
	}

	/**
	 * Handles the CTA click. Subclass owns the destination; the protected
	 * `record_dismissal_and_redirect()` helper covers the common
	 * "CTA terminally dismisses the notice" case.
	 *
	 * @return void
	 */
	abstract public function handle_cta(): void;

	/**
	 * Snake_case identifier for this notice. All derived keys (user meta,
	 * transients, script handles, query args, Tracks events) compute from it.
	 *
	 * @return string
	 */
	abstract protected function get_slug(): string;

	/**
	 * Notice's eligibility predicate. Called inside is_eligible() behind the
	 * transient cache.
	 *
	 * @return bool
	 */
	abstract protected function compute_eligibility(): bool;

	/**
	 * Whether the store has a connected, valid, non-test-drive account with
	 * payments enabled. Every notice gates on this before applying its own
	 * mode and cohort checks, so compute_eligibility() implementations should
	 * open with it.
	 *
	 * @return bool
	 */
	protected function has_active_payments_account(): bool {
		if ( ! $this->wcpay_gateway->is_connected() || ! $this->account->is_stripe_account_valid() ) {
			return false;
		}

		$account_status = $this->account->get_account_status_data();

		if ( ! empty( $account_status['testDrive'] ) ) {
			return false;
		}

		return ! empty( $account_status['paymentsEnabled'] );
	}

	/**
	 * Whether this notice exposes a snooze flow. Override to return false for
	 * notices that only support dismiss (e.g. the post-KYC notice).
	 *
	 * @return bool
	 */
	protected function supports_snooze(): bool {
		return true;
	}

	/**
	 * Extra query args appended to every action URL (cta/dismiss/snooze) and
	 * stripped from the redirect URL after the handler fires. Used by
	 * subclasses that need per-request context (e.g. the Post-KYC notice's
	 * `wcpay_stage`).
	 *
	 * @return array<string, scalar>
	 */
	protected function get_extra_action_query_args(): array {
		return [];
	}

	/**
	 * Extra entries merged into the script's localized settings object — for
	 * subclasses that need to pass data beyond the standard URL trio to their
	 * React entrypoint.
	 *
	 * @return array<string, mixed>
	 */
	protected function get_extra_localize_data(): array {
		return [];
	}

	/**
	 * Properties attached to the impression Tracks event. Subclasses override
	 * to thread per-request context (e.g. the current stage).
	 *
	 * @return array<string, scalar>
	 */
	protected function get_impression_tracks_props(): array {
		return [];
	}

	/**
	 * User meta key marking that the current admin dismissed this notice.
	 *
	 * @return string
	 */
	protected function dismissed_meta_key(): string {
		return "wcpay_{$this->get_slug()}_notice_dismissed";
	}

	/**
	 * User meta key recording when the current admin snoozed this notice.
	 *
	 * @return string
	 */
	protected function snoozed_meta_key(): string {
		return "wcpay_{$this->get_slug()}_notice_snoozed";
	}

	/**
	 * User meta key recording that the impression Tracks event has fired for this user.
	 *
	 * @return string
	 */
	protected function shown_meta_key(): string {
		return "wcpay_{$this->get_slug()}_notice_shown";
	}

	/**
	 * Transient key caching the result of the global eligibility check.
	 *
	 * @return string
	 */
	protected function eligibility_transient_key(): string {
		return "wcpay_{$this->get_slug()}_eligible";
	}

	/**
	 * WP script handle for the React bundle.
	 *
	 * @return string
	 */
	protected function script_handle(): string {
		return 'WCPAY_' . strtoupper( $this->get_slug() ) . '_NOTICE';
	}

	/**
	 * Build artifact base name in the dist/ directory.
	 *
	 * @return string
	 */
	protected function dist_name(): string {
		return 'wc-payments-' . str_replace( '_', '-', $this->get_slug() ) . '-notice';
	}

	/**
	 * DOM id the React component mounts into.
	 *
	 * @return string
	 */
	protected function mount_div_id(): string {
		return 'wcpay-' . str_replace( '_', '-', $this->get_slug() ) . '-notice';
	}

	/**
	 * Global JS variable name for the localized settings object.
	 *
	 * @return string
	 */
	protected function localize_var_name(): string {
		$studly = str_replace( ' ', '', ucwords( str_replace( '_', ' ', $this->get_slug() ) ) );
		return 'wcpay' . $studly . 'NoticeSettings';
	}

	/**
	 * $_GET marker that triggers the CTA handler.
	 *
	 * @return string
	 */
	protected function cta_query_arg(): string {
		return 'wcpay-' . str_replace( '_', '-', $this->get_slug() ) . '-cta';
	}

	/**
	 * $_GET marker that triggers the dismiss handler.
	 *
	 * @return string
	 */
	protected function hide_query_arg(): string {
		return 'wcpay-hide-' . str_replace( '_', '-', $this->get_slug() ) . '-notice';
	}

	/**
	 * $_GET marker that triggers the snooze handler.
	 *
	 * @return string
	 */
	protected function snooze_query_arg(): string {
		return 'wcpay-snooze-' . str_replace( '_', '-', $this->get_slug() ) . '-notice';
	}

	/**
	 * Nonce action for the CTA URL.
	 *
	 * @return string
	 */
	protected function cta_nonce_action(): string {
		return "wcpay_{$this->get_slug()}_cta_nonce";
	}

	/**
	 * Nonce action for the dismiss URL.
	 *
	 * @return string
	 */
	protected function hide_nonce_action(): string {
		return "wcpay_hide_{$this->get_slug()}_notice_nonce";
	}

	/**
	 * Nonce action for the snooze URL.
	 *
	 * @return string
	 */
	protected function snooze_nonce_action(): string {
		return "wcpay_snooze_{$this->get_slug()}_notice_nonce";
	}

	/**
	 * $_GET arg name carrying the CTA nonce.
	 *
	 * @return string
	 */
	protected function cta_nonce_arg(): string {
		return "_wcpay_{$this->get_slug()}_cta_nonce";
	}

	/**
	 * $_GET arg name carrying the dismiss nonce.
	 *
	 * @return string
	 */
	protected function hide_nonce_arg(): string {
		return "_wcpay_{$this->get_slug()}_notice_nonce";
	}

	/**
	 * $_GET arg name carrying the snooze nonce.
	 *
	 * @return string
	 */
	protected function snooze_nonce_arg(): string {
		return "_wcpay_snooze_{$this->get_slug()}_notice_nonce";
	}

	/**
	 * Tracks event recorded the first time the user sees this notice.
	 *
	 * @return string
	 */
	protected function shown_event_name(): string {
		return "wcpay_{$this->get_slug()}_notice_shown";
	}

	/**
	 * Tracks event recorded when the user dismisses this notice.
	 *
	 * @return string
	 */
	protected function dismissed_event_name(): string {
		return "wcpay_{$this->get_slug()}_notice_dismissed";
	}

	/**
	 * Tracks event recorded when the user snoozes this notice.
	 *
	 * @return string
	 */
	protected function snoozed_event_name(): string {
		return "wcpay_{$this->get_slug()}_notice_snoozed";
	}

	/**
	 * Tracks event recorded when the user clicks the CTA.
	 *
	 * @return string
	 */
	protected function cta_event_name(): string {
		return "wcpay_{$this->get_slug()}_notice_cta_clicked";
	}

	/**
	 * Transient-cached wrapper around compute_eligibility(). Subclasses should
	 * never call compute_eligibility() directly so the cache stays consistent.
	 *
	 * @return bool
	 */
	protected function is_eligible(): bool {
		$cached = get_transient( $this->eligibility_transient_key() );
		if ( false !== $cached ) {
			return '1' === $cached;
		}
		$eligible = $this->compute_eligibility();
		set_transient( $this->eligibility_transient_key(), $eligible ? '1' : '0', HOUR_IN_SECONDS );
		return $eligible;
	}

	/**
	 * Validates an admin-init action request: $_GET marker present, current
	 * user can manage_woocommerce, and nonce verifies.
	 *
	 * @param string $query_arg    The $_GET marker query arg.
	 * @param string $nonce_arg    The $_GET nonce arg name.
	 * @param string $nonce_action The nonce action.
	 * @return bool
	 */
	protected function verify_action_request( string $query_arg, string $nonce_arg, string $nonce_action ): bool {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET[ $query_arg ] ) || ! isset( $_GET[ $nonce_arg ] ) ) {
			return false;
		}
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return false;
		}
		if ( ! wp_verify_nonce( wc_clean( wp_unslash( $_GET[ $nonce_arg ] ) ), $nonce_action ) ) {
			return false;
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		return true;
	}

	/**
	 * Records a Tracks event immediately. Used in redirect-and-exit handlers
	 * where the queued WC_Tracks flush won't fire.
	 *
	 * @param string $event      Event name.
	 * @param array  $properties Event properties.
	 * @return void
	 */
	protected function record_tracks_event( string $event, array $properties = [] ): void {
		if ( class_exists( 'WC_Tracks' ) ) {
			WC_Tracks::record_event( $event, $properties );
		}
	}

	/**
	 * Drops the eligibility transient so the next request re-evaluates from
	 * scratch. Used by subclasses that hook external signals (order
	 * completion, account refresh, etc.) to invalidate the cache.
	 *
	 * @return void
	 */
	protected function delete_eligibility_cache(): void {
		delete_transient( $this->eligibility_transient_key() );
	}

	/**
	 * Default cache invalidator wired to external signals.
	 *
	 * @return void
	 */
	public function invalidate_cache(): void {
		$this->delete_eligibility_cache();
	}

	/**
	 * Standard terminal-CTA flow: record the cta_clicked event, mark the
	 * notice as dismissed for this user, then redirect to the destination.
	 * Subclasses with non-terminal CTAs (test-to-live's "flip mode" variant)
	 * should not call this — they record their own event and redirect directly.
	 *
	 * @param string $destination_url Where to send the user after the CTA.
	 * @param array  $tracks_props    Properties attached to the cta_clicked event.
	 * @return void
	 */
	protected function record_dismissal_and_redirect( string $destination_url, array $tracks_props = [] ): void {
		$this->record_tracks_event( $this->cta_event_name(), $tracks_props );
		update_user_meta( get_current_user_id(), $this->dismissed_meta_key(), time() );
		wp_safe_redirect( $destination_url );
		exit;
	}

	/**
	 * Backs should_show(). Kept separate so the public method is purely a
	 * memoization wrapper; subclasses overriding the visibility decision can
	 * override just this method.
	 *
	 * @return bool
	 */
	protected function compute_should_show(): bool {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return false;
		}
		if ( ! $this->is_applicable() ) {
			return false;
		}
		if ( $this->is_dismissed() ) {
			return false;
		}
		if ( $this->supports_snooze() && $this->is_snoozed() ) {
			return false;
		}
		return $this->is_eligible();
	}

	/**
	 * Per-notice guards evaluated before the dismissal, snooze, and eligibility
	 * checks. Override to add cheap short-circuits that must run before the
	 * (potentially expensive) eligibility query. Default: no extra guards.
	 *
	 * @return bool
	 */
	protected function is_applicable(): bool {
		return true;
	}

	/**
	 * Whether the current user has dismissed this notice. Override for notices
	 * that key dismissal on something other than the shared dismissed meta key
	 * (e.g. a per-stage marker).
	 *
	 * @return bool
	 */
	protected function is_dismissed(): bool {
		return (bool) get_user_meta( get_current_user_id(), $this->dismissed_meta_key(), true );
	}

	/**
	 * Whether the current user is within the snooze window. Only consulted when
	 * supports_snooze() returns true.
	 *
	 * @return bool
	 */
	protected function is_snoozed(): bool {
		$snoozed_at = (int) get_user_meta( get_current_user_id(), $this->snoozed_meta_key(), true );
		return $snoozed_at && time() < $snoozed_at + $this->snooze_window_days() * DAY_IN_SECONDS;
	}

	/**
	 * Number of days the notice stays hidden after a snooze. Override to change
	 * the snooze window; defaults to one week.
	 *
	 * @return int
	 */
	protected function snooze_window_days(): int {
		return 7;
	}

	/**
	 * Builds a nonce-protected action URL with the notice's marker query arg.
	 *
	 * @param string $query_arg    Marker query arg the handler checks for.
	 * @param string $nonce_action Nonce action.
	 * @param string $nonce_arg    Nonce $_GET arg name.
	 * @return string
	 */
	private function build_action_url( string $query_arg, string $nonce_action, string $nonce_arg ): string {
		return wp_nonce_url(
			add_query_arg(
				array_merge( [ $query_arg => '1' ], $this->get_extra_action_query_args() )
			),
			$nonce_action,
			$nonce_arg
		);
	}

	/**
	 * Records the impression Tracks event the first time the user sees the
	 * notice, then writes the user_meta marker so subsequent views don't
	 * re-record. Idempotent — safe to call from both `enqueue_script()` and
	 * `maybe_show()`.
	 *
	 * @return void
	 */
	private function record_impression_if_first(): void {
		if ( ! get_user_meta( get_current_user_id(), $this->shown_meta_key(), true ) ) {
			$this->record_tracks_event( $this->shown_event_name(), $this->get_impression_tracks_props() );
			update_user_meta( get_current_user_id(), $this->shown_meta_key(), true );
		}
	}

	/**
	 * Whether the current admin screen can host a notice.
	 *
	 * The eligibility check runs an order query, so enqueue_script() gates on
	 * this first to avoid that cost on screens where no notice renders
	 * (WOOPMNT-6240).
	 *
	 * @return bool
	 */
	private function is_notice_screen(): bool {
		$screen = get_current_screen();
		return ! $screen || in_array( $screen->id, wc_get_screen_ids(), true ) || wc_admin_is_registered_page();
	}
}
