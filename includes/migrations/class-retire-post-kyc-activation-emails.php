<?php
/**
 * Retire queued first-sale reminders.
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Migrations;

defined( 'ABSPATH' ) || exit;

/**
 * Cancels the retired experiment's jobs without changing merchant preferences.
 *
 * @since 11.2.0
 */
class Retire_Post_Kyc_Activation_Emails {

	/**
	 * Queue cleanup when upgrading from a release with activation reminders.
	 */
	public function maybe_migrate() {
		$previous_version = get_option( 'woocommerce_woocommerce_payments_version', '' );
		if ( version_compare( '11.2.0', $previous_version, '>' ) ) {
			$this->migrate();
		}
	}

	/**
	 * Retry independently of the installed version, which may have advanced.
	 */
	public function retry_pending_cleanup(): void {
		if ( get_option( 'wcpay_post_kyc_activation_email_cleanup_pending' ) ) {
			$this->migrate();
		}
	}

	/**
	 * Cancel only the three retired stage jobs on the current site.
	 *
	 * Leave the marker set if the scheduler is unavailable or jobs remain.
	 * An already executing worker from the old plugin cannot be recalled.
	 */
	public function migrate(): void {
		delete_transient( 'wcpay_test_to_live_eligible' );
		delete_transient( 'wcpay_post_kyc_activation_eligible' );

		update_option( 'wcpay_post_kyc_activation_email_cleanup_pending', '1', false );

		if ( ! function_exists( 'as_unschedule_all_actions' ) || ! class_exists( '\ActionScheduler' ) || ! \ActionScheduler::is_initialized() ) {
			return;
		}

		foreach ( [ 7, 14, 30 ] as $stage ) {
			as_unschedule_all_actions( 'wcpay_post_kyc_activation_email_send', [ $stage ], 'woocommerce-payments' );
		}

		// Action Scheduler catches cancellation errors internally, so verify cleanup.
		foreach ( [ 7, 14, 30 ] as $stage ) {
			if ( as_has_scheduled_action( 'wcpay_post_kyc_activation_email_send', [ $stage ], 'woocommerce-payments' ) ) {
				return;
			}
		}

		delete_option( 'wcpay_post_kyc_activation_email_cleanup_pending' );
	}
}
