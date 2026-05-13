<?php
/**
 * Class WC_Payments_Post_Kyc_Activation_Email_Service
 *
 * @package WooCommerce\Payments
 */

defined( 'ABSPATH' ) || exit;

/**
 * Schedules and sends post-KYC activation emails on day 7, 14, and 30 to merchants
 * who have not yet made their first live WooPayments sale.
 *
 * Three one-off Action Scheduler actions are scheduled per store the first time the
 * KYC completion date is recorded — calculated as kyc_date + 7d / +14d / +30d. Each
 * action handler re-checks eligibility at fire time and bails if the merchant has
 * since made a sale, gone back to test mode, etc.
 */
class WC_Payments_Post_Kyc_Activation_Email_Service {

	const SEND_HOOK = 'wcpay_post_kyc_activation_email_send';

	const EMAIL_SENT_OPTION = 'wcpay_post_kyc_activation_email_sent_stages';

	const SCHEDULED_OPTION = 'wcpay_post_kyc_activation_emails_scheduled';

	const STAGE_DAYS = [ 7, 14, 30 ];

	/**
	 * Stages whose calculated send time is more than this far in the past are skipped at scheduling time
	 * — avoids spamming long-since-approved merchants with stale activation nudges.
	 */
	const STALE_GRACE_SECONDS = 7 * DAY_IN_SECONDS;

	/**
	 * Account service instance.
	 *
	 * @var WC_Payments_Account
	 */
	private $account;

	/**
	 * WCPay gateway instance.
	 *
	 * @var WC_Payment_Gateway_WCPay
	 */
	private $wcpay_gateway;

	/**
	 * Order service instance.
	 *
	 * @var WC_Payments_Order_Service
	 */
	private $order_service;

	/**
	 * Constructor.
	 *
	 * @param WC_Payments_Account       $account       Account service.
	 * @param WC_Payment_Gateway_WCPay  $wcpay_gateway WCPay gateway.
	 * @param WC_Payments_Order_Service $order_service Order service.
	 */
	public function __construct( WC_Payments_Account $account, WC_Payment_Gateway_WCPay $wcpay_gateway, WC_Payments_Order_Service $order_service ) {
		$this->account       = $account;
		$this->wcpay_gateway = $wcpay_gateway;
		$this->order_service = $order_service;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init_hooks(): void {
		// Schedule the three one-off send actions the first time KYC completion is recorded.
		add_action( 'add_option_' . WC_Payments_Account::KYC_COMPLETION_DATE_OPTION, [ $this, 'schedule_stage_emails' ], 10, 2 );
		// Handler for each scheduled stage send.
		add_action( self::SEND_HOOK, [ $this, 'send_email_for_stage' ] );
		// Tracks click-throughs from the email CTA into the Marketing Hub.
		add_action( 'admin_init', [ $this, 'maybe_track_cta_click' ] );
	}

	/**
	 * Schedule day-7, day-14, and day-30 send actions when the KYC completion date is first recorded.
	 * Stages whose calculated send time is more than STALE_GRACE_SECONDS in the past are skipped.
	 *
	 * @param string $option_name The option name fired by the add_option_* action.
	 * @param mixed  $value       The option value.
	 * @return void
	 */
	public function schedule_stage_emails( $option_name, $value ): void {
		if ( ! function_exists( 'as_schedule_single_action' ) ) {
			return;
		}

		if ( get_option( self::SCHEDULED_OPTION ) ) {
			return;
		}

		$kyc_date = (int) $value;
		if ( ! $kyc_date ) {
			return;
		}

		$now = time();
		foreach ( self::STAGE_DAYS as $stage ) {
			$send_at = $kyc_date + $stage * DAY_IN_SECONDS;

			if ( $send_at < $now - self::STALE_GRACE_SECONDS ) {
				continue;
			}

			as_schedule_single_action( max( $send_at, $now + 60 ), self::SEND_HOOK, [ $stage ], 'woocommerce-payments' );
		}

		update_option( self::SCHEDULED_OPTION, '1', false );
	}

	/**
	 * Action Scheduler handler — fires once per stage at the scheduled time.
	 *
	 * @param int $stage The stage day (7, 14, or 30).
	 * @return void
	 */
	public function send_email_for_stage( $stage ): void {
		$stage = (int) $stage;

		if ( ! in_array( $stage, self::STAGE_DAYS, true ) ) {
			return;
		}

		$sent_stages = (array) get_option( self::EMAIL_SENT_OPTION, [] );
		if ( in_array( $stage, $sent_stages, true ) ) {
			return;
		}

		// Re-check staleness at fire time in case Action Scheduler runs the
		// action well after its scheduled time (e.g. WP-Cron stalled for weeks).
		$kyc_date = (int) get_option( WC_Payments_Account::KYC_COMPLETION_DATE_OPTION, 0 );
		if ( ! $kyc_date ) {
			return;
		}
		if ( time() > $kyc_date + $stage * DAY_IN_SECONDS + self::STALE_GRACE_SECONDS ) {
			return;
		}

		if ( ! $this->is_eligible() ) {
			return;
		}

		if ( ! function_exists( 'WC' ) || ! WC()->mailer() ) {
			return;
		}

		$emails = WC()->mailer()->get_emails();
		$email  = $emails['WC_Payments_Email_Post_Kyc_Activation'] ?? null;

		if ( ! $email instanceof WC_Payments_Email_Post_Kyc_Activation ) {
			return;
		}

		// Merchant opted out — email disabled in WC > Settings > Emails, or
		// no recipient. Nothing to send; let the stage expire silently.
		if ( ! $email->is_enabled() || ! $email->get_recipient() ) {
			return;
		}

		if ( ! $email->trigger( $stage ) ) {
			// Mailer rejected the send (e.g. SMTP failure). Leave the stage
			// unconsumed so the drop surfaces via the
			// `wcpay_post_kyc_activation_email_send_failed` tracks event
			// rather than being silently swallowed.
			return;
		}

		$sent_stages[] = $stage;
		update_option( self::EMAIL_SENT_OPTION, array_values( array_unique( $sent_stages ) ), false );
	}

	/**
	 * Eligibility check — mirrors the in-app banner logic.
	 *
	 * @return bool
	 */
	public function is_eligible(): bool {
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

		return ! $this->order_service->has_live_sale();
	}

	/**
	 * Fires `wcpay_post_kyc_activation_email_cta_clicked` when an admin lands on
	 * Marketing Hub via the email CTA, then strips the marker query args so a
	 * page refresh doesn't double-count the click.
	 *
	 * @return void
	 */
	public function maybe_track_cta_click(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['wcpay_referrer'] ) || 'post_kyc_email' !== $_GET['wcpay_referrer'] ) {
			return;
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$stage = isset( $_GET['wcpay_referrer_stage'] ) ? (int) $_GET['wcpay_referrer_stage'] : 0;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( ! in_array( $stage, self::STAGE_DAYS, true ) ) {
			return;
		}

		if ( class_exists( 'WC_Tracks' ) ) {
			WC_Tracks::record_event( 'wcpay_post_kyc_activation_email_cta_clicked', [ 'stage' => $stage ] );
		}

		wp_safe_redirect( remove_query_arg( [ 'wcpay_referrer', 'wcpay_referrer_stage' ] ) );
		exit;
	}
}
