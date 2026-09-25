<?php
/**
 * Compatibility surface for a retired activation notice.
 *
 * @package WooCommerce\Payments\Admin
 */

defined( 'ABSPATH' ) || exit;

/**
 * The activation notice has been retired. Public entry points are inert.
 *
 * @deprecated 11.2.0 Retained for callers during the deprecation window.
 */
class WC_Payments_Post_Kyc_Activation_Notice extends WC_Payments_Abstract_Admin_Notice {

	const NOTICE_WINDOW_DAYS = 60;
	const STAGES             = [ 7, 14, 30 ];
	const USER_META_PREFIX   = 'wcpay_post_kyc_activation_';
	const TRANSIENT_ELIGIBLE = 'wcpay_post_kyc_activation_eligible';

	/** No admin handlers are registered. */
	public function init_hooks(): void {}

	/** No order or account handlers are registered. */
	public function init_global_hooks(): void {}

	/**
	 * The retired notice never renders, even when an old eligibility cache exists.
	 *
	 * @return bool
	 */
	public function should_show(): bool {
		return false;
	}

	/** Register inert handles for extensions that depend on the old assets. */
	public function register_script(): void {
		WC_Payments_Admin_Notices::register_retired_notice_assets();
	}

	/** The retired CTA does not change mode or redirect. */
	public function handle_cta(): void {}

	/** Preserve historic dismissal markers. */
	public function hide_notice(): void {}

	/** Preserve historic snooze markers. */
	public function snooze_notice(): void {}

	/**
	 * Legacy identifier.
	 *
	 * @return string
	 */
	protected function get_slug(): string {
		return 'post_kyc_activation';
	}

	/**
	 * No merchant is eligible for the retired experiment.
	 *
	 * @return bool
	 */
	protected function compute_eligibility(): bool {
		return false;
	}

	/**
	 * No reminder stage is active.
	 *
	 * @return int|null
	 */
	public function get_current_stage(): ?int {
		return null;
	}
}
