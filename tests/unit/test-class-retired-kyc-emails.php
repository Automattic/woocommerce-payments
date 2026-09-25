<?php
/**
 * Regression tests for retired first-sale reminders.
 *
 * @package WooCommerce\Payments\Tests
 */

/**
 * Retired entry points remain callable without sending or scheduling mail.
 */
class Retired_Kyc_Emails_Test extends WCPAY_UnitTestCase {

	public function tear_down(): void {
		as_unschedule_all_actions( 'wcpay_post_kyc_activation_email_send' );
		parent::tear_down();
	}

	public function test_recording_kyc_no_longer_schedules_emails(): void {
		delete_option( 'wcpay_kyc_completion_date' );
		delete_option( 'wcpay_post_kyc_activation_emails_scheduled' );

		add_option( 'wcpay_kyc_completion_date', time() );

		foreach ( [ 7, 14, 30 ] as $stage ) {
			$this->assertFalse( as_has_scheduled_action( 'wcpay_post_kyc_activation_email_send', [ $stage ], 'woocommerce-payments' ) );
		}
	}

	public function test_email_is_not_registered_in_woocommerce(): void {
		/** This filter is documented in WooCommerce's WC_Emails::init(). */
		$emails = apply_filters( 'woocommerce_email_classes', [] );

		$this->assertArrayNotHasKey( 'WC_Payments_Email_Post_Kyc_Activation', $emails );
		$this->assertArrayHasKey( 'WC_Payments_Email_IPP_Receipt', $emails );
	}

	public function test_legacy_email_trigger_cannot_send(): void {
		if ( ! class_exists( 'WC_Payments_Email_Post_Kyc_Activation' ) ) {
			require_once WCPAY_ABSPATH . 'includes/emails/class-wc-payments-email-post-kyc-activation.php';
		}
		$email = $this->getMockBuilder( WC_Payments_Email_Post_Kyc_Activation::class )->onlyMethods( [ 'send' ] )->getMock();
		$email->expects( $this->never() )->method( 'send' );

		foreach ( [ 7, 14, 30 ] as $stage ) {
			$this->assertFalse( $email->trigger( $stage ) );
		}
		$this->assertSame( '', $email->get_content_html() );
		$this->assertSame( '', $email->get_content_plain() );
	}

	public function test_residual_action_completes_without_sending(): void {
		$action_id = as_schedule_single_action( time() - 60, 'wcpay_post_kyc_activation_email_send', [ 7 ], 'woocommerce-payments' );

		ActionScheduler_QueueRunner::instance()->process_action( $action_id, 'test' );

		$this->assertSame( 'complete', ActionScheduler::store()->get_status( $action_id ) );
		$this->assertFalse( get_option( 'wcpay_post_kyc_activation_email_sent_stages' ) );
	}
}
