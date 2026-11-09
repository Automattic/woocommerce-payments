<?php
/**
 * Tests for retiring queued activation reminders.
 *
 * @package WooCommerce\Payments\Tests
 */

namespace unit\migrations;

use WCPay\Migrations\Retire_Post_Kyc_Activation_Emails;
use WCPAY_UnitTestCase;

/**
 * Exercises cleanup against the real Action Scheduler store.
 */
class Retire_Post_Kyc_Activation_Emails_Test extends WCPAY_UnitTestCase {

	public function tear_down(): void {
		as_unschedule_all_actions( 'wcpay_post_kyc_activation_email_send' );
		as_unschedule_all_actions( 'wcpay_cleanup_test_unrelated' );
		delete_transient( 'wcpay_test_to_live_eligible' );
		delete_transient( 'wcpay_post_kyc_activation_eligible' );
		parent::tear_down();
	}

	public function test_cancels_only_retired_stages_in_the_payments_group(): void {
		foreach ( [ 7, 14, 30 ] as $stage ) {
			foreach ( [ time() - DAY_IN_SECONDS, time() + DAY_IN_SECONDS ] as $timestamp ) {
				as_schedule_single_action( $timestamp, 'wcpay_post_kyc_activation_email_send', [ $stage ], 'woocommerce-payments' );
			}
		}
		as_schedule_single_action( time() + 3600, 'wcpay_cleanup_test_unrelated', [], 'woocommerce-payments' );
		as_schedule_single_action( time() + 3600, 'wcpay_post_kyc_activation_email_send', [ 7 ], 'another-plugin' );
		update_option( 'wcpay_post_kyc_activation_email_sent_stages', [ 7 ] );
		update_option( 'wcpay_post_kyc_activation_emails_scheduled', '1' );
		update_option( 'woocommerce_woocommerce_payments_wcpay_post_kyc_activation_settings', [ 'enabled' => 'no' ] );

		$migration = new Retire_Post_Kyc_Activation_Emails();
		$migration->migrate();
		$migration->migrate();

		foreach ( [ 7, 14, 30 ] as $stage ) {
			$this->assertFalse( as_has_scheduled_action( 'wcpay_post_kyc_activation_email_send', [ $stage ], 'woocommerce-payments' ) );
		}
		$this->assertTrue( as_has_scheduled_action( 'wcpay_cleanup_test_unrelated', [], 'woocommerce-payments' ) );
		$this->assertTrue( as_has_scheduled_action( 'wcpay_post_kyc_activation_email_send', [ 7 ], 'another-plugin' ) );
		$this->assertSame( [ 7 ], get_option( 'wcpay_post_kyc_activation_email_sent_stages' ) );
		$this->assertSame( '1', get_option( 'wcpay_post_kyc_activation_emails_scheduled' ) );
		$this->assertSame( [ 'enabled' => 'no' ], get_option( 'woocommerce_woocommerce_payments_wcpay_post_kyc_activation_settings' ) );
	}

	public function test_upgrade_cancels_pending_reminders(): void {
		update_option( 'woocommerce_woocommerce_payments_version', '11.2.0' );
		as_schedule_single_action( time() + 3600, 'wcpay_post_kyc_activation_email_send', [ 14 ], 'woocommerce-payments' );

		( new Retire_Post_Kyc_Activation_Emails() )->maybe_migrate();

		$this->assertFalse( as_has_scheduled_action( 'wcpay_post_kyc_activation_email_send', [ 14 ], 'woocommerce-payments' ) );
	}

	public function test_plugin_upgrade_hook_cancels_pending_reminders_before_updating_version(): void {
		update_option( 'woocommerce_woocommerce_payments_version', '11.0.0' );
		as_schedule_single_action( time() + 3600, 'wcpay_post_kyc_activation_email_send', [ 14 ], 'woocommerce-payments' );

		\WC_Payments::install_actions();

		$this->assertFalse( as_has_scheduled_action( 'wcpay_post_kyc_activation_email_send', [ 14 ], 'woocommerce-payments' ) );
		$this->assertSame( WCPAY_VERSION_NUMBER, get_option( 'woocommerce_woocommerce_payments_version' ) );
	}

	public function test_upgrade_clears_the_retired_notice_caches(): void {
		set_transient( 'wcpay_test_to_live_eligible', '1', HOUR_IN_SECONDS );
		set_transient( 'wcpay_post_kyc_activation_eligible', '1', HOUR_IN_SECONDS );

		( new Retire_Post_Kyc_Activation_Emails() )->migrate();

		$this->assertFalse( get_transient( 'wcpay_test_to_live_eligible' ) );
		$this->assertFalse( get_transient( 'wcpay_post_kyc_activation_eligible' ) );
	}

	public function test_fresh_install_cleanup_is_harmless(): void {
		delete_option( 'woocommerce_woocommerce_payments_version' );

		( new Retire_Post_Kyc_Activation_Emails() )->maybe_migrate();

		$this->assertFalse( as_has_scheduled_action( 'wcpay_post_kyc_activation_email_send', [ 7 ], 'woocommerce-payments' ) );
	}
}
