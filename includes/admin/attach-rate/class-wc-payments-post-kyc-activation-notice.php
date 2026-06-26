<?php
/**
 * Post-KYC activation notice.
 *
 * @package WooCommerce\Payments\Admin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Nudges merchants who have completed KYC but haven't yet taken their first
 * live WooPayments sale. Three stages (7, 14, 30 days after KYC completion);
 * each stage has its own dismissed/shown user_meta marker so a user who
 * dismisses the day-7 reminder still sees day-14 and day-30. Notice window
 * closes at day 60.
 *
 * No snooze flow — only dismiss + CTA (and dismiss is per-stage).
 */
class WC_Payments_Post_Kyc_Activation_Notice extends WC_Payments_Abstract_Admin_Notice {

	/**
	 * Days after KYC completion during which the notice may be shown. Past this
	 * window, the eligibility machinery short-circuits.
	 *
	 * @var int
	 */
	const NOTICE_WINDOW_DAYS = 60;

	/**
	 * Stage thresholds in days since KYC. The user_meta dismissed/shown keys
	 * are suffixed with the current stage so per-stage dismissals don't carry
	 * over to later reminders.
	 *
	 * @var int[]
	 */
	const STAGES = [ 7, 14, 30 ];

	/**
	 * User meta prefix; the full key is built by appending `{stage}_dismissed`
	 * or `{stage}_shown`.
	 *
	 * @var string
	 */
	const USER_META_PREFIX = 'wcpay_post_kyc_activation_';

	/**
	 * Eligibility transient key.
	 *
	 * @var string
	 */
	const TRANSIENT_ELIGIBLE = 'wcpay_post_kyc_activation_eligible';

	/**
	 * Adds the account-refresh cache invalidator on top of the base wiring.
	 *
	 * @return void
	 */
	public function init_hooks(): void {
		parent::init_hooks();
		add_action( 'woocommerce_payments_account_refreshed', [ $this, 'invalidate_cache' ] );
	}

	/**
	 * Registers this notice's non-admin-context hooks.
	 *
	 * @return void
	 */
	public function init_global_hooks(): void {
		add_action(
			'add_option_' . WC_Payments_Order_Service::HAS_LIVE_SALE_OPTION,
			[ $this, 'invalidate_cache' ]
		);
	}

	/**
	 * Returns the current nudge stage (7, 14, or 30) based on days elapsed
	 * since KYC completion. Returns null if the KYC date isn't recorded, fewer
	 * than 7 days have passed, or the notice window has expired.
	 *
	 * @return int|null
	 */
	public function get_current_stage(): ?int {
		$kyc_date = (int) get_option( WC_Payments_Account::KYC_COMPLETION_DATE_OPTION, 0 );
		if ( ! $kyc_date ) {
			return null;
		}

		$days_elapsed = (int) floor( ( time() - $kyc_date ) / DAY_IN_SECONDS );

		if ( $days_elapsed >= self::NOTICE_WINDOW_DAYS ) {
			return null;
		}
		if ( $days_elapsed >= 30 ) {
			return 30;
		}
		if ( $days_elapsed >= 14 ) {
			return 14;
		}
		if ( $days_elapsed >= 7 ) {
			return 7;
		}
		return null;
	}

	/**
	 * Handles the CTA click. Validates the stage from $_GET, records a
	 * per-stage Tracks event, marks this stage dismissed, and redirects to
	 * the WC Admin Marketing Hub.
	 *
	 * @return void
	 */
	public function handle_cta(): void {
		$naming = $this->naming();
		if ( ! $this->verify_action_request( $naming->cta_query_arg(), $naming->cta_nonce_arg(), $naming->cta_nonce_action() ) ) {
			return;
		}
		$stage = $this->stage_from_request();
		if ( null === $stage ) {
			return;
		}

		$this->record_tracks_event( $naming->cta_event_name(), [ 'stage' => $stage ] );
		update_user_meta( get_current_user_id(), $this->dismissed_meta_key_for_stage( $stage ), true );

		wp_safe_redirect(
			add_query_arg(
				[
					'page' => 'wc-admin',
					'path' => '/marketing',
				],
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Persists the per-stage dismissal. Overrides the base because the meta
	 * key is stage-suffixed and the Tracks event needs the stage property.
	 *
	 * @return void
	 */
	public function hide_notice(): void {
		$naming = $this->naming();
		if ( ! $this->verify_action_request( $naming->hide_query_arg(), $naming->hide_nonce_arg(), $naming->hide_nonce_action() ) ) {
			return;
		}
		$stage = $this->stage_from_request();
		if ( null === $stage ) {
			return;
		}

		$this->record_tracks_event( $naming->dismissed_event_name(), [ 'stage' => $stage ] );
		update_user_meta( get_current_user_id(), $this->dismissed_meta_key_for_stage( $stage ), true );

		wp_safe_redirect( remove_query_arg( [ $naming->hide_query_arg(), $naming->hide_nonce_arg(), 'wcpay_stage' ] ) );
		exit;
	}

	/**
	 * Snake_case identifier.
	 *
	 * @return string
	 */
	protected function get_slug(): string {
		return 'post_kyc_activation';
	}

	/**
	 * Pin the eligibility transient key to the public TRANSIENT_ELIGIBLE
	 * constant so the onboarding service's cache invalidation and the base's
	 * cache lookup share one source of truth.
	 *
	 * @return array<string, string>
	 */
	protected function naming_overrides(): array {
		return [ 'eligibility_transient_key' => self::TRANSIENT_ELIGIBLE ];
	}

	/**
	 * Eligibility predicate.
	 *
	 * Conditions:
	 * - Account is connected and valid.
	 * - Not a test-drive account.
	 * - Payments are enabled.
	 * - Plugin is in live mode (not test, not dev).
	 * - KYC completion date has been recorded.
	 * - Merchant has no live WooPayments orders yet.
	 *
	 * @return bool
	 */
	protected function compute_eligibility(): bool {
		if ( ! $this->has_active_payments_account() ) {
			return false;
		}

		if ( WC_Payments::mode()->is_test() || WC_Payments::mode()->is_dev() ) {
			return false;
		}

		if ( ! get_option( WC_Payments_Account::KYC_COMPLETION_DATE_OPTION ) ) {
			return false;
		}

		return ! WC_Payments::get_order_service()->has_live_sale();
	}

	/**
	 * Override: no snooze for this notice.
	 *
	 * @return bool
	 */
	protected function supports_snooze(): bool {
		return false;
	}

	/**
	 * Override: short-circuit before the eligibility query when a live sale
	 * already exists or no nudge stage is currently active. Keeps the
	 * eligibility transient + order query from running for those branches.
	 *
	 * @return bool
	 */
	protected function is_applicable(): bool {
		return ! get_option( WC_Payments_Order_Service::HAS_LIVE_SALE_OPTION )
			&& null !== $this->get_current_stage();
	}

	/**
	 * Override: dismissal is tracked per stage rather than via the shared
	 * dismissed meta key.
	 *
	 * @return bool
	 */
	protected function is_dismissed(): bool {
		$stage = $this->get_current_stage();
		if ( null === $stage ) {
			return false;
		}
		return (bool) get_user_meta( get_current_user_id(), $this->dismissed_meta_key_for_stage( $stage ), true );
	}

	/**
	 * Override: stage-suffixed shown meta key. Returns the slug default when
	 * no stage is currently active so base-class internals stay well-defined.
	 *
	 * @return string
	 */
	protected function shown_meta_key(): string {
		$stage = $this->get_current_stage();
		return null === $stage
			? parent::shown_meta_key()
			: self::USER_META_PREFIX . $stage . '_shown';
	}

	/**
	 * Override: thread the current stage through CTA / dismiss URLs.
	 *
	 * @return array<string, scalar>
	 */
	protected function get_extra_action_query_args(): array {
		$stage = $this->get_current_stage();
		return null === $stage ? [] : [ 'wcpay_stage' => $stage ];
	}

	/**
	 * Override: thread the current stage into the React entrypoint's settings.
	 *
	 * @return array<string, mixed>
	 */
	protected function get_extra_localize_data(): array {
		$stage = $this->get_current_stage();
		return null === $stage ? [] : [ 'stage' => $stage ];
	}

	/**
	 * Override: attach the stage to the impression Tracks event.
	 *
	 * @return array<string, scalar>
	 */
	protected function get_impression_tracks_props(): array {
		$stage = $this->get_current_stage();
		return null === $stage ? [] : [ 'stage' => $stage ];
	}

	/**
	 * Builds the dismissed user_meta key for a specific stage.
	 *
	 * @param int $stage Stage day (7, 14, or 30).
	 * @return string
	 */
	private function dismissed_meta_key_for_stage( int $stage ): string {
		return self::USER_META_PREFIX . $stage . '_dismissed';
	}

	/**
	 * Reads the `wcpay_stage` value from $_GET. Returns null if absent or not a
	 * recognized stage.
	 *
	 * @return int|null
	 */
	private function stage_from_request(): ?int {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$stage = isset( $_GET['wcpay_stage'] ) ? (int) $_GET['wcpay_stage'] : 0;
		return in_array( $stage, self::STAGES, true ) ? $stage : null;
	}
}
