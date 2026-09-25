<?php
/**
 * Compatibility surface for the retired first-sale reminder service.
 *
 * @package WooCommerce\Payments
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable -- Preserve legacy parameter names.

/**
 * First-sale reminders have been retired. All entry points are inert.
 *
 * @deprecated 11.2.0 Retained for callers during the deprecation window.
 */
class WC_Payments_Post_Kyc_Activation_Email_Service {

	const SEND_HOOK           = 'wcpay_post_kyc_activation_email_send';
	const EMAIL_SENT_OPTION   = 'wcpay_post_kyc_activation_email_sent_stages';
	const SCHEDULED_OPTION    = 'wcpay_post_kyc_activation_emails_scheduled';
	const STAGE_DAYS          = [ 7, 14, 30 ];
	const STALE_GRACE_SECONDS = 7 * DAY_IN_SECONDS;

	/**
	 * Legacy constructor.
	 *
	 * @param WC_Payments_Account       $account Account service.
	 * @param WC_Payment_Gateway_WCPay  $wcpay_gateway Gateway.
	 * @param WC_Payments_Order_Service $order_service Order service.
	 */
	public function __construct( WC_Payments_Account $account, WC_Payment_Gateway_WCPay $wcpay_gateway, WC_Payments_Order_Service $order_service ) {}

	/** No hooks are registered for the retired experiment. */
	public function init_hooks(): void {}

	/**
	 * No new reminders are scheduled.
	 *
	 * @param string $option_name Legacy option name.
	 * @param mixed  $value Legacy timestamp.
	 */
	public function schedule_stage_emails( $option_name, $value ): void {}

	/**
	 * No reminder is sent, including for a previously queued job.
	 *
	 * @param int $stage Legacy reminder stage.
	 */
	public function send_email_for_stage( $stage ): void {}

	/**
	 * No merchant is eligible for the retired experiment.
	 *
	 * @return bool
	 */
	public function is_eligible(): bool {
		return false;
	}

	/** Previously delivered links remain valid without click tracking. */
	public function maybe_track_cta_click(): void {}
}
