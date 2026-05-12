<?php
/**
 * Houses WooPayments admin banners.
 *
 * Banners defined here pair their PHP-side plumbing with the `BannerNotice`
 * React component on the front end: PHP decides whether to render and emits
 * a container `<div>`; the React bundle hydrates into it using settings
 * passed via `wp_localize_script`.
 *
 * @package WooCommerce\Payments\Admin
 */

use WCPay\Constants\Order_Mode;

defined( 'ABSPATH' ) || exit;

/**
 * WC_Payments_Admin_Banner Class.
 */
class WC_Payments_Admin_Banner {

	/**
	 * User meta key used to record when an admin dismissed the test-to-live nudge.
	 *
	 * @var string
	 */
	const USER_META_TEST_TO_LIVE_NOTICE_DISMISSED = 'wcpay_test_to_live_notice_dismissed';

	/**
	 * User meta key used to record when an admin snoozed the test-to-live nudge.
	 *
	 * @var string
	 */
	const USER_META_TEST_TO_LIVE_NOTICE_SNOOZED = 'wcpay_test_to_live_notice_snoozed';

	/**
	 * User meta key used to record that the test-to-live nudge impression has been tracked.
	 *
	 * @var string
	 */
	const USER_META_TEST_TO_LIVE_NOTICE_SHOWN = 'wcpay_test_to_live_notice_shown';

	/**
	 * Number of days a merchant must have been in test mode before the nudge is shown.
	 *
	 * @var int
	 */
	const TEST_TO_LIVE_NOTICE_DAYS_THRESHOLD = 7;

	/**
	 * Number of days the test-to-live nudge stays hidden after being snoozed.
	 *
	 * @var int
	 */
	const TEST_TO_LIVE_NOTICE_SNOOZE_DAYS = 7;

	/**
	 * Transient caching the result of the account-status and order eligibility check.
	 *
	 * @var string
	 */
	const TRANSIENT_TEST_TO_LIVE_NOTICE_ELIGIBLE = 'wcpay_test_to_live_eligible';

	/**
	 * User meta key prefix for the Post-KYC activation notice. Combine with
	 * the stage day number (7, 14, or 30) and '_dismissed' or '_shown' to form the
	 * full key, e.g. 'wcpay_post_kyc_activation_7_dismissed' or
	 * 'wcpay_post_kyc_activation_7_shown'.
	 *
	 * @var string
	 */
	const USER_META_POST_KYC_ACTIVATION_PREFIX = 'wcpay_post_kyc_activation_';

	/**
	 * Number of days after KYC completion during which the Post-KYC activation nudge
	 * may be shown. Past this window, the nudge is no longer eligible and the eligibility
	 * machinery (including the live-sale order query) is short-circuited entirely.
	 *
	 * @var int
	 */
	const POST_KYC_ACTIVATION_NOTICE_WINDOW_DAYS = 60;

	/**
	 * WCPay Gateway instance to get information regarding WooCommerce Payments setup.
	 *
	 * @var WC_Payment_Gateway_WCPay
	 */
	private $wcpay_gateway;

	/**
	 * WC_Payments_Account instance to get information about the account.
	 *
	 * @var WC_Payments_Account
	 */
	private $account;

	/**
	 * Per-request memo for should_show_post_kyc_activation_notice().
	 * Same banner instance is reused across admin_enqueue_scripts and
	 * woocommerce_sections_{$tab}, so a per-request cache is safe.
	 *
	 * @var bool|null
	 */
	private $should_show_post_kyc_activation_notice_memo = null;

	/**
	 * Per-request memo of `should_show_test_to_live_notice()`. The same instance is
	 * reused across both the `admin_enqueue_scripts` and the `woocommerce_sections_*`
	 * callbacks, so a single user_meta + transient pass per request is enough.
	 *
	 * @var ?bool
	 */
	private $should_show_test_to_live_notice_memo = null;

	/**
	 * Constructor.
	 *
	 * @param WC_Payment_Gateway_WCPay $wcpay_gateway WCPay Gateway instance.
	 * @param WC_Payments_Account      $account       Account service instance.
	 */
	public function __construct( WC_Payment_Gateway_WCPay $wcpay_gateway, WC_Payments_Account $account ) {
		$this->wcpay_gateway = $wcpay_gateway;
		$this->account       = $account;
	}

	/**
	 * Registers hooks that must fire regardless of request context.
	 *
	 * The one-and-done recovery banner relies on `woocommerce_payment_complete`
	 * and `woocommerce_order_status_completed` to drop its eligibility transient
	 * the moment a real 2nd transaction lands. Those events fire from frontend
	 * checkout (storefront, `is_admin()` false) and from Stripe webhook REST
	 * handlers (also `is_admin()` false), so registering them inside
	 * init_hooks() — which is only called in admin context — would silently miss
	 * the production paths the cache invalidation is meant to cover.
	 *
	 * Call this on every request; init_hooks() stays admin-gated for the
	 * render/CTA/dismiss/snooze surface.
	 *
	 * @return void
	 */
	public function init_global_hooks(): void {}

	/**
	 * Registers hooks for every banner managed by this class.
	 *
	 * @return void
	 */
	public function init_hooks(): void {
		// Test-to-live activation nudge.
		add_action( 'admin_init', [ $this, 'hide_test_to_live_notice' ] );
		add_action( 'admin_init', [ $this, 'snooze_test_to_live_notice' ] );
		add_action( 'admin_init', [ $this, 'handle_test_to_live_notice_cta' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'register_banner_scripts' ], 9 );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_test_to_live_notice_script' ] );

		add_action( 'admin_init', [ $this, 'hide_post_kyc_activation_notice' ] );
		add_action( 'admin_init', [ $this, 'handle_post_kyc_activation_notice_cta' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_post_kyc_activation_notice_script' ] );
		add_action( 'woocommerce_payments_account_refreshed', [ $this, 'invalidate_post_kyc_activation_notice_cache' ] );

		// Hook into the active WooCommerce settings tab so the divs are injected
		// inside the page content — after the tab/section navigation but before
		// the settings form — bypassing the WC Admin notice interception.
		if ( isset( $_GET['page'] ) && 'wc-settings' === sanitize_key( wp_unslash( $_GET['page'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			add_action( "woocommerce_sections_{$tab}", [ $this, 'maybe_show_test_to_live_notice' ] );
			add_action( "woocommerce_sections_{$tab}", [ $this, 'maybe_show_post_kyc_activation_notice' ] );
		}
	}

	/**
	 * Registers React scripts and styles for every banner managed by this class.
	 * Hooked at admin_enqueue_scripts priority 9 so the handles are available for
	 * the per-banner conditional enqueue methods that run at the default priority.
	 *
	 * @return void
	 */
	public function register_banner_scripts(): void {
		WC_Payments::register_script_with_dependencies( 'WCPAY_TEST_TO_LIVE_NOTICE', 'dist/wc-payments-test-to-live-notice' );
		wp_set_script_translations( 'WCPAY_TEST_TO_LIVE_NOTICE', 'woocommerce-payments' );

		WC_Payments_Utils::register_style(
			'WCPAY_TEST_TO_LIVE_NOTICE',
			plugins_url( 'dist/wc-payments-test-to-live-notice.css', WCPAY_PLUGIN_FILE ),
			[],
			WC_Payments::get_file_version( 'dist/wc-payments-test-to-live-notice.css' ),
			'all'
		);

		WC_Payments::register_script_with_dependencies( 'WCPAY_POST_KYC_ACTIVATION_NOTICE', 'dist/wc-payments-post-kyc-activation-notice' );
		wp_set_script_translations( 'WCPAY_POST_KYC_ACTIVATION_NOTICE', 'woocommerce-payments' );

		WC_Payments_Utils::register_style(
			'WCPAY_POST_KYC_ACTIVATION_NOTICE',
			plugins_url( 'dist/wc-payments-post-kyc-activation-notice.css', WCPAY_PLUGIN_FILE ),
			[],
			WC_Payments::get_file_version( 'dist/wc-payments-post-kyc-activation-notice.css' ),
			'all'
		);
	}

	/**
	 * Enqueue the test-to-live notice React bundle when the notice is eligible to be shown.
	 *
	 * @return void
	 */
	public function enqueue_test_to_live_notice_script() {
		if ( ! $this->should_show_test_to_live_notice() ) {
			return;
		}

		$screen = get_current_screen();
		if ( $screen && ! in_array( $screen->id, wc_get_screen_ids(), true ) && ! wc_admin_is_registered_page() ) {
			return;
		}

		if ( ! get_user_meta( get_current_user_id(), self::USER_META_TEST_TO_LIVE_NOTICE_SHOWN, true ) ) {
			$this->record_tracks_event( 'wcpay_test_to_live_notice_shown' );
			update_user_meta( get_current_user_id(), self::USER_META_TEST_TO_LIVE_NOTICE_SHOWN, true );
		}

		wp_localize_script(
			'WCPAY_TEST_TO_LIVE_NOTICE',
			'wcpayTestToLiveNoticeSettings',
			[
				'ctaUrl'     => wp_nonce_url(
					add_query_arg( 'wcpay-test-to-live-cta', '1' ),
					'wcpay_test_to_live_cta_nonce',
					'_wcpay_test_to_live_cta_nonce'
				),
				'dismissUrl' => wp_nonce_url(
					add_query_arg( 'wcpay-hide-test-to-live-notice', '1' ),
					'wcpay_hide_test_to_live_notice_nonce',
					'_wcpay_test_to_live_notice_nonce'
				),
				'snoozeUrl'  => wp_nonce_url(
					add_query_arg( 'wcpay-snooze-test-to-live-notice', '1' ),
					'wcpay_snooze_test_to_live_notice_nonce',
					'_wcpay_snooze_test_to_live_notice_nonce'
				),
			]
		);

		wp_enqueue_script( 'WCPAY_TEST_TO_LIVE_NOTICE' );
		wp_enqueue_style( 'WCPAY_TEST_TO_LIVE_NOTICE' );
	}

	/**
	 * Whether to show the test-to-live nudge to the current user.
	 *
	 * Requires: manage_woocommerce capability, connected account with payments
	 * enabled, test mode active for at least TEST_TO_LIVE_NOTICE_DAYS_THRESHOLD
	 * days, at least one WooPayments order, and no active dismiss or snooze.
	 *
	 * @return bool
	 */
	public function should_show_test_to_live_notice(): bool {
		if ( null !== $this->should_show_test_to_live_notice_memo ) {
			return $this->should_show_test_to_live_notice_memo;
		}

		$this->should_show_test_to_live_notice_memo = $this->compute_should_show_test_to_live_notice();

		return $this->should_show_test_to_live_notice_memo;
	}

	/**
	 * Output the React mount point for the test-to-live activation nudge. The
	 * notice itself is rendered client-side from `client/entrypoints/test-to-live-notice/`.
	 *
	 * @return void
	 */
	public function maybe_show_test_to_live_notice() {
		if ( ! $this->should_show_test_to_live_notice() ) {
			return;
		}

		echo '<div id="wcpay-test-to-live-notice"></div>';
	}

	/**
	 * Handles the "Go live" CTA from the test-to-live notice.
	 *
	 * If the merchant already has a live Stripe account, flips the mode flag directly.
	 * Otherwise redirects them through the live onboarding flow.
	 *
	 * Fires on admin_init so the redirect happens before any output.
	 *
	 * @return void
	 */
	public function handle_test_to_live_notice_cta() {
		if ( ! isset( $_GET['wcpay-test-to-live-cta'] ) || ! isset( $_GET['_wcpay_test_to_live_cta_nonce'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		if ( ! wp_verify_nonce( wc_clean( wp_unslash( $_GET['_wcpay_test_to_live_cta_nonce'] ) ), 'wcpay_test_to_live_cta_nonce' ) ) {
			return;
		}

		if ( $this->account->get_is_live() ) {
			$this->record_tracks_event( 'wcpay_test_to_live_notice_cta_clicked', [ 'path' => 'switch_mode' ] );

			$this->wcpay_gateway->update_option( 'test_mode', 'no' );
			WC_Payments_Onboarding_Service::set_test_mode( false );

			wp_safe_redirect( remove_query_arg( [ 'wcpay-test-to-live-cta', '_wcpay_test_to_live_cta_nonce' ] ) );
		} else {
			$this->record_tracks_event( 'wcpay_test_to_live_notice_cta_clicked', [ 'path' => 'onboarding' ] );

			wp_safe_redirect(
				add_query_arg(
					[
						'page'   => 'wc-settings',
						'tab'    => 'checkout',
						'path'   => '/woopayments/onboarding',
						'from'   => WC_Payments_Onboarding_Service::FROM_TEST_TO_LIVE,
						'source' => WC_Payments_Onboarding_Service::SOURCE_WCPAY_SETUP_LIVE_PAYMENTS,
					],
					admin_url( 'admin.php' )
				)
			);
		}

		exit;
	}

	/**
	 * Persists the test-to-live notice dismissal in user meta when the dismiss link is followed.
	 *
	 * @return void
	 */
	public function hide_test_to_live_notice() {
		if ( ! isset( $_GET['wcpay-hide-test-to-live-notice'] ) || ! isset( $_GET['_wcpay_test_to_live_notice_nonce'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		if ( ! wp_verify_nonce( wc_clean( wp_unslash( $_GET['_wcpay_test_to_live_notice_nonce'] ) ), 'wcpay_hide_test_to_live_notice_nonce' ) ) {
			return;
		}

		$this->record_tracks_event( 'wcpay_test_to_live_notice_dismissed' );

		update_user_meta( get_current_user_id(), self::USER_META_TEST_TO_LIVE_NOTICE_DISMISSED, time() );

		wp_safe_redirect( remove_query_arg( [ 'wcpay-hide-test-to-live-notice', '_wcpay_test_to_live_notice_nonce' ] ) );
		exit;
	}

	/**
	 * Records the snooze timestamp in user meta when the snooze link is followed.
	 *
	 * The notice will be suppressed for TEST_TO_LIVE_NOTICE_SNOOZE_DAYS days.
	 *
	 * @return void
	 */
	public function snooze_test_to_live_notice() {
		if ( ! isset( $_GET['wcpay-snooze-test-to-live-notice'] ) || ! isset( $_GET['_wcpay_snooze_test_to_live_notice_nonce'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		if ( ! wp_verify_nonce( wc_clean( wp_unslash( $_GET['_wcpay_snooze_test_to_live_notice_nonce'] ) ), 'wcpay_snooze_test_to_live_notice_nonce' ) ) {
			return;
		}

		$this->record_tracks_event( 'wcpay_test_to_live_notice_snoozed' );

		update_user_meta( get_current_user_id(), self::USER_META_TEST_TO_LIVE_NOTICE_SNOOZED, time() );

		wp_safe_redirect( remove_query_arg( [ 'wcpay-snooze-test-to-live-notice', '_wcpay_snooze_test_to_live_notice_nonce' ] ) );
		exit;
	}

	/**
	 * Whether the test-to-live notice is globally eligible to be shown.
	 * Checks the cache first, populates the cache if not found, then returns the result.
	 *
	 * @return bool
	 */
	private function is_test_to_live_notice_eligible_to_be_shown(): bool {
		$cached = get_transient( self::TRANSIENT_TEST_TO_LIVE_NOTICE_ELIGIBLE );
		if ( false !== $cached ) {
			return '1' === $cached;
		}

		$eligible = $this->compute_test_to_live_notice_eligibility();
		set_transient( self::TRANSIENT_TEST_TO_LIVE_NOTICE_ELIGIBLE, $eligible ? '1' : '0', HOUR_IN_SECONDS );

		return $eligible;
	}

	/**
	 * Compute the eligibility for the test-to-live notice.
	 * Conditions:
	 * - Account is connected and valid.
	 * - Account is not a test drive account.
	 * - Payments are enabled.
	 * - Test mode is active.
	 * - Test mode is not in development mode.
	 * - At least one WooPayments order has been completed or processed.
	 *
	 * @return bool True if the notice should be shown, false otherwise.
	 */
	private function compute_test_to_live_notice_eligibility(): bool {
		if ( ! $this->wcpay_gateway->is_connected() || ! $this->account->is_stripe_account_valid() ) {
			return false;
		}

		$account_status = $this->account->get_account_status_data();

		if ( ! empty( $account_status['testDrive'] ) ) {
			return false;
		}

		if ( empty( $account_status['paymentsEnabled'] ) ) {
			return false;
		}

		if ( ! WC_Payments::mode()->is_test() ) {
			return false;
		}

		if ( WC_Payments::mode()->is_dev() ) {
			return false;
		}

		$enabled_date = (int) get_option( WC_Payments_Onboarding_Service::TEST_MODE_ENABLED_DATE_OPTION, 0 );
		if ( ! $enabled_date || time() < $enabled_date + self::TEST_TO_LIVE_NOTICE_DAYS_THRESHOLD * DAY_IN_SECONDS ) {
			return false;
		}

		$orders = wc_get_orders(
			[
				'payment_method' => 'woocommerce_payments',
				'limit'          => 1,
				'return'         => 'ids',
				'status'         => [ 'wc-completed', 'wc-processing' ],
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_key'       => WC_Payments_Order_Service::WCPAY_MODE_META_KEY,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'meta_value'     => Order_Mode::TEST,
			]
		);

		return ! empty( $orders );
	}

	// -------------------------------------------------------------------------
	// Post-KYC activation notice
	// -------------------------------------------------------------------------

	/**
	 * Enqueues the Post-KYC activation notice script and style when eligible.
	 *
	 * @return void
	 */
	public function enqueue_post_kyc_activation_notice_script(): void {
		$screen = get_current_screen();
		if ( $screen && ! in_array( $screen->id, wc_get_screen_ids(), true ) && ! wc_admin_is_registered_page() ) {
			return;
		}

		if ( ! $this->should_show_post_kyc_activation_notice() ) {
			return;
		}

		$stage      = $this->get_post_kyc_activation_stage();
		$shown_meta = self::USER_META_POST_KYC_ACTIVATION_PREFIX . $stage . '_shown';

		if ( ! get_user_meta( get_current_user_id(), $shown_meta, true ) ) {
			$this->record_tracks_event( 'wcpay_post_kyc_activation_notice_shown', [ 'stage' => $stage ] );
			update_user_meta( get_current_user_id(), $shown_meta, true );
		}

		wp_localize_script(
			'WCPAY_POST_KYC_ACTIVATION_NOTICE',
			'wcpayPostKycActivationNoticeSettings',
			[
				'stage'      => $stage,
				'ctaUrl'     => wp_nonce_url(
					add_query_arg(
						[
							'wcpay-post-kyc-activation-cta' => '1',
							'wcpay_stage' => $stage,
						]
					),
					'wcpay_post_kyc_activation_cta_nonce',
					'_wcpay_post_kyc_activation_cta_nonce'
				),
				'dismissUrl' => wp_nonce_url(
					add_query_arg(
						[
							'wcpay-hide-post-kyc-activation-notice' => '1',
							'wcpay_stage' => $stage,
						]
					),
					'wcpay_hide_post_kyc_activation_notice_nonce',
					'_wcpay_post_kyc_activation_notice_nonce'
				),
			]
		);

		wp_enqueue_script( 'WCPAY_POST_KYC_ACTIVATION_NOTICE' );
		wp_enqueue_style( 'WCPAY_POST_KYC_ACTIVATION_NOTICE' );
	}

	/**
	 * Renders the mount point div for the Post-KYC activation notice.
	 * Hooked to woocommerce_sections_{$tab} so it appears inside the page content
	 * area on WooCommerce settings pages.
	 *
	 * @return void
	 */
	public function maybe_show_post_kyc_activation_notice(): void {
		if ( ! $this->should_show_post_kyc_activation_notice() ) {
			return;
		}

		echo '<div id="wcpay-post-kyc-activation-notice"></div>';
	}

	/**
	 * Persists the per-stage dismissal in user meta when the dismiss link is followed.
	 *
	 * @return void
	 */
	public function hide_post_kyc_activation_notice(): void {
		if ( ! isset( $_GET['wcpay-hide-post-kyc-activation-notice'] ) || ! isset( $_GET['_wcpay_post_kyc_activation_notice_nonce'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		if ( ! wp_verify_nonce( wc_clean( wp_unslash( $_GET['_wcpay_post_kyc_activation_notice_nonce'] ) ), 'wcpay_hide_post_kyc_activation_notice_nonce' ) ) {
			return;
		}

		$stage = isset( $_GET['wcpay_stage'] ) ? (int) $_GET['wcpay_stage'] : 0;
		if ( ! in_array( $stage, [ 7, 14, 30 ], true ) ) {
			return;
		}

		$this->record_tracks_event( 'wcpay_post_kyc_activation_notice_dismissed', [ 'stage' => $stage ] );

		update_user_meta( get_current_user_id(), self::USER_META_POST_KYC_ACTIVATION_PREFIX . $stage . '_dismissed', true );

		wp_safe_redirect( remove_query_arg( [ 'wcpay-hide-post-kyc-activation-notice', '_wcpay_post_kyc_activation_notice_nonce', 'wcpay_stage' ] ) );
		exit;
	}

	/**
	 * Records the CTA-clicked Tracks event, persists the per-stage dismissal,
	 * and redirects to the WC Admin Marketing Hub.
	 *
	 * Hooked on admin_init so the redirect lands before any output and the
	 * Tracks event flushes synchronously via record_tracks_event() ahead of
	 * the wp_safe_redirect() + exit.
	 *
	 * @return void
	 */
	public function handle_post_kyc_activation_notice_cta(): void {
		if ( ! isset( $_GET['wcpay-post-kyc-activation-cta'] ) || ! isset( $_GET['_wcpay_post_kyc_activation_cta_nonce'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		if ( ! wp_verify_nonce( wc_clean( wp_unslash( $_GET['_wcpay_post_kyc_activation_cta_nonce'] ) ), 'wcpay_post_kyc_activation_cta_nonce' ) ) {
			return;
		}

		$stage = isset( $_GET['wcpay_stage'] ) ? (int) $_GET['wcpay_stage'] : 0;
		if ( ! in_array( $stage, [ 7, 14, 30 ], true ) ) {
			return;
		}

		$this->record_tracks_event( 'wcpay_post_kyc_activation_notice_cta_clicked', [ 'stage' => $stage ] );

		update_user_meta( get_current_user_id(), self::USER_META_POST_KYC_ACTIVATION_PREFIX . $stage . '_dismissed', true );

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
	 * Whether the Post-KYC activation notice should be shown to the current user.
	 * Memoized per-request — the same banner instance is reused across the
	 * admin_enqueue_scripts and woocommerce_sections_{$tab} callbacks.
	 *
	 * @return bool
	 */
	public function should_show_post_kyc_activation_notice(): bool {
		if ( null === $this->should_show_post_kyc_activation_notice_memo ) {
			$this->should_show_post_kyc_activation_notice_memo = $this->compute_should_show_post_kyc_activation_notice();
		}

		return $this->should_show_post_kyc_activation_notice_memo;
	}

	/**
	 * Returns the current nudge stage (7, 14, or 30) based on days elapsed since KYC completion,
	 * or null if the KYC date is not recorded yet, fewer than 7 days have passed, or the
	 * notice window has expired.
	 *
	 * @return int|null
	 */
	public function get_post_kyc_activation_stage(): ?int {
		$kyc_date = (int) get_option( WC_Payments_Account::KYC_COMPLETION_DATE_OPTION, 0 );
		if ( ! $kyc_date ) {
			return null;
		}

		$days_elapsed = (int) floor( ( time() - $kyc_date ) / DAY_IN_SECONDS );

		if ( $days_elapsed >= self::POST_KYC_ACTIVATION_NOTICE_WINDOW_DAYS ) {
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
	 * Evaluates whether the Post-KYC activation notice should be shown to the current user.
	 *
	 * @return bool
	 */
	private function compute_should_show_post_kyc_activation_notice(): bool {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return false;
		}

		if ( get_option( WC_Payments_Order_Service::HAS_LIVE_SALE_OPTION ) ) {
			return false;
		}

		$stage = $this->get_post_kyc_activation_stage();
		if ( null === $stage ) {
			return false;
		}

		if ( get_user_meta( get_current_user_id(), self::USER_META_POST_KYC_ACTIVATION_PREFIX . $stage . '_dismissed', true ) ) {
			return false;
		}

		return $this->is_post_kyc_activation_notice_eligible();
	}

	/**
	 * Returns whether the Post-KYC activation notice is eligible to be shown, with a one-hour transient cache.
	 *
	 * @return bool
	 */
	private function is_post_kyc_activation_notice_eligible(): bool {
		$cached = get_transient( WC_Payments_Account::POST_KYC_ACTIVATION_ELIGIBLE_TRANSIENT );
		if ( false !== $cached ) {
			return '1' === $cached;
		}

		$eligible = $this->compute_post_kyc_activation_eligibility();
		set_transient( WC_Payments_Account::POST_KYC_ACTIVATION_ELIGIBLE_TRANSIENT, $eligible ? '1' : '0', HOUR_IN_SECONDS );

		return $eligible;
	}

	/**
	 * Evaluates all eligibility conditions for the Post-KYC activation notice.
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
	private function compute_post_kyc_activation_eligibility(): bool {
		if ( ! $this->wcpay_gateway->is_connected() || ! $this->account->is_stripe_account_valid() ) {
			return false;
		}

		$account_status = $this->account->get_account_status_data();

		if ( ! empty( $account_status['testDrive'] ) ) {
			return false;
		}

		if ( empty( $account_status['paymentsEnabled'] ) ) {
			return false;
		}

		if ( WC_Payments::mode()->is_test() || WC_Payments::mode()->is_dev() ) {
			return false;
		}

		if ( ! get_option( WC_Payments_Account::KYC_COMPLETION_DATE_OPTION ) ) {
			return false;
		}

		return ! $this->store_has_live_sale();
	}

	/**
	 * Clears the Post-KYC activation eligibility transient, forcing re-evaluation on the next request.
	 *
	 * @return void
	 */
	public function invalidate_post_kyc_activation_notice_cache(): void {
		delete_transient( WC_Payments_Account::POST_KYC_ACTIVATION_ELIGIBLE_TRANSIENT );
	}

	/**
	 * Returns whether the store has had at least one live (production) WooPayments sale.
	 *
	 * Reads a one-way option set by `WC_Payments_Order_Service::maybe_record_first_live_sale()`;
	 * falls back to a single `wc_get_orders` meta query if the option has not been
	 * populated yet (e.g., for stores that took their first live sale before this
	 * feature shipped).
	 *
	 * @return bool
	 */
	private function store_has_live_sale(): bool {
		if ( get_option( WC_Payments_Order_Service::HAS_LIVE_SALE_OPTION ) ) {
			return true;
		}

		$orders = wc_get_orders(
			[
				'payment_method' => 'woocommerce_payments',
				'limit'          => 1,
				'return'         => 'ids',
				'status'         => [ 'wc-completed', 'wc-processing' ],
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_key'       => WC_Payments_Order_Service::WCPAY_MODE_META_KEY,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'meta_value'     => Order_Mode::PRODUCTION,
			]
		);

		if ( ! empty( $orders ) ) {
			update_option( WC_Payments_Order_Service::HAS_LIVE_SALE_OPTION, '1', true );
			return true;
		}

		return false;
	}

	/**
	 * Backs `should_show_test_to_live_notice()`. Kept separate so the public method
	 * is purely a memoization wrapper.
	 *
	 * @return bool
	 */
	private function compute_should_show_test_to_live_notice(): bool {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return false;
		}

		if ( get_user_meta( get_current_user_id(), self::USER_META_TEST_TO_LIVE_NOTICE_DISMISSED, true ) ) {
			return false;
		}

		$snoozed_at = (int) get_user_meta( get_current_user_id(), self::USER_META_TEST_TO_LIVE_NOTICE_SNOOZED, true );
		if ( $snoozed_at && time() < $snoozed_at + self::TEST_TO_LIVE_NOTICE_SNOOZE_DAYS * DAY_IN_SECONDS ) {
			return false;
		}

		return $this->is_test_to_live_notice_eligible_to_be_shown();
	}

	/**
	 * Records a Tracks event.
	 * Immediately via WC_Tracks::record_event() instead of the WC_Tracks queue.
	 * The queue is flushed in admin_footer or shutdown — neither of which is reached in redirect
	 * handlers that call wp_safe_redirect() + exit, so queued events would be silently lost.
	 *
	 * @param string $event      Event name.
	 * @param array  $properties Event properties.
	 * @return void
	 */
	private function record_tracks_event( string $event, array $properties = [] ): void {
		if ( class_exists( 'WC_Tracks' ) ) {
			WC_Tracks::record_event( $event, $properties );
		}
	}
}
