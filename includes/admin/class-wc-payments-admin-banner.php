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
	 * Registers hooks for every banner managed by this class.
	 *
	 * @return void
	 */
	public function init_hooks(): void {
		add_action( 'admin_init', [ $this, 'hide_test_to_live_notice' ] );
		add_action( 'admin_init', [ $this, 'snooze_test_to_live_notice' ] );
		add_action( 'admin_init', [ $this, 'handle_test_to_live_notice_cta' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'register_test_to_live_notice_script' ], 9 );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_test_to_live_notice_script' ] );
		add_action( 'update_option_' . WC_Payments_Onboarding_Service::TEST_MODE_OPTION, [ $this, 'invalidate_test_to_live_notice_cache' ] );

		// Hook into the active WooCommerce settings tab so the div is injected
		// inside the page content — after the tab/section navigation but before
		// the settings form — bypassing the WC Admin notice interception.
		if ( isset( $_GET['page'] ) && 'wc-settings' === sanitize_key( wp_unslash( $_GET['page'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			add_action( "woocommerce_sections_{$tab}", [ $this, 'maybe_show_test_to_live_notice' ] );
		}
	}

	/**
	 * Register the test-to-live notice React script and style.
	 *
	 * @return void
	 */
	public function register_test_to_live_notice_script(): void {
		WC_Payments::register_script_with_dependencies( 'WCPAY_TEST_TO_LIVE_NOTICE', 'dist/wc-payments-test-to-live-notice' );
		wp_set_script_translations( 'WCPAY_TEST_TO_LIVE_NOTICE', 'woocommerce-payments' );

		WC_Payments_Utils::register_style(
			'WCPAY_TEST_TO_LIVE_NOTICE',
			plugins_url( 'dist/wc-payments-test-to-live-notice.css', WCPAY_PLUGIN_FILE ),
			[],
			WC_Payments::get_file_version( 'dist/wc-payments-test-to-live-notice.css' ),
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
	 * Drops the eligibility transient so the next request re-evaluates from scratch.
	 * Hooked to test-mode option changes.
	 *
	 * @return void
	 */
	public function invalidate_test_to_live_notice_cache(): void {
		delete_transient( self::TRANSIENT_TEST_TO_LIVE_NOTICE_ELIGIBLE );
	}

	/**
	 * Render the test-to-live activation nudge on the WP-admin dashboard.
	 *
	 * @return void
	 */
	public function maybe_show_test_to_live_notice() {
		if ( ! $this->should_show_test_to_live_notice() ) {
			return;
		}

		if ( ! get_user_meta( get_current_user_id(), self::USER_META_TEST_TO_LIVE_NOTICE_SHOWN, true ) ) {
			$this->record_tracks_event( 'wcpay_test_to_live_notice_shown' );
			update_user_meta( get_current_user_id(), self::USER_META_TEST_TO_LIVE_NOTICE_SHOWN, true );
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
