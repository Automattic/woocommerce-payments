<?php
/**
 * Test-to-live activation banner.
 *
 * @package WooCommerce\Payments\Admin
 */

use WCPay\Constants\Order_Mode;

defined( 'ABSPATH' ) || exit;

/**
 * Nudges merchants who have been in test mode for at least 7 days and have
 * processed at least one test WooPayments order toward going live. CTA either
 * flips the mode flag (when a live Stripe account is already connected) or
 * routes the merchant through the live-onboarding flow.
 */
class WC_Payments_Test_To_Live_Banner extends WC_Payments_Abstract_Admin_Banner {

	/**
	 * Number of days a merchant must have been in test mode before the nudge is shown.
	 *
	 * @var int
	 */
	const DAYS_THRESHOLD = 7;

	/**
	 * Gateway service.
	 *
	 * @var WC_Payment_Gateway_WCPay
	 */
	private $wcpay_gateway;

	/**
	 * Account service.
	 *
	 * @var WC_Payments_Account
	 */
	private $account;

	/**
	 * Constructor.
	 *
	 * @param WC_Payment_Gateway_WCPay $wcpay_gateway WCPay gateway.
	 * @param WC_Payments_Account      $account       Account service.
	 */
	public function __construct( WC_Payment_Gateway_WCPay $wcpay_gateway, WC_Payments_Account $account ) {
		$this->wcpay_gateway = $wcpay_gateway;
		$this->account       = $account;
	}

	/**
	 * Handles the "Go live" CTA.
	 *
	 * Two paths:
	 *   - If a live Stripe account is already connected, flip the gateway and
	 *     onboarding test_mode flags off directly and redirect back to the
	 *     current page (the banner disappears because eligibility recomputes).
	 *   - Otherwise route to the live-onboarding flow with the appropriate
	 *     `from` / `source` query args.
	 *
	 * Does not call the base's `record_dismissal_and_redirect()` because the
	 * banner self-clears via the eligibility predicate rather than a per-user
	 * dismissed-meta flag.
	 *
	 * @return void
	 */
	public function handle_cta(): void {
		if ( ! $this->verify_action_request( $this->cta_query_arg(), $this->cta_nonce_arg(), $this->cta_nonce_action() ) ) {
			return;
		}

		if ( $this->account->get_is_live() ) {
			$this->record_tracks_event( $this->cta_event_name(), [ 'path' => 'switch_mode' ] );

			$this->wcpay_gateway->update_option( 'test_mode', 'no' );
			WC_Payments_Onboarding_Service::set_test_mode( false );

			wp_safe_redirect( remove_query_arg( [ $this->cta_query_arg(), $this->cta_nonce_arg() ] ) );
			exit;
		}

		$this->record_tracks_event( $this->cta_event_name(), [ 'path' => 'onboarding' ] );

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
		exit;
	}

	/**
	 * Snake_case identifier.

	 * @return string
	 */
	protected function get_slug(): string {
		return 'test_to_live';
	}

	/**
	 * Eligibility predicate.
	 *
	 * Conditions:
	 * - Account is connected and valid.
	 * - Account is not a test-drive account.
	 * - Payments are enabled.
	 * - Mode is test (and not dev).
	 * - Test mode has been enabled for at least DAYS_THRESHOLD days.
	 * - At least one test-mode WooPayments order has been completed or processed.
	 *
	 * @return bool
	 */
	protected function compute_eligibility(): bool {
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
		if ( ! $enabled_date || time() < $enabled_date + self::DAYS_THRESHOLD * DAY_IN_SECONDS ) {
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
}
