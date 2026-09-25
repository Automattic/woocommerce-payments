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
	 * Cancel the three retired stage jobs on the current site.
	 *
	 * A job that survives cancellation completes through the inert
	 * wcpay_post_kyc_activation_email_send handler without sending.
	 */
	public function migrate(): void {
		delete_transient( 'wcpay_test_to_live_eligible' );
		delete_transient( 'wcpay_post_kyc_activation_eligible' );

		foreach ( [ 7, 14, 30 ] as $stage ) {
			as_unschedule_all_actions( 'wcpay_post_kyc_activation_email_send', [ $stage ], 'woocommerce-payments' );
		}
	}
}
