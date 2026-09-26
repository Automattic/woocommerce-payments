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
		delete_option( 'wcpay_post_kyc_activation_email_cleanup_pending' );
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
		$this->assertFalse( get_option( 'wcpay_post_kyc_activation_email_cleanup_pending' ) );
	}

	public function test_upgrade_cancels_pending_reminders(): void {
		update_option( 'woocommerce_woocommerce_payments_version', '11.1.0' );
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

	public function test_cleanup_waits_for_scheduler_then_retries_after_version_advanced(): void {
		as_schedule_single_action( time() + 3600, 'wcpay_post_kyc_activation_email_send', [ 30 ], 'woocommerce-payments' );
		$initialized = new \ReflectionProperty( \ActionScheduler::class, 'data_store_initialized' );
		$initialized->setAccessible( true );
		$previous = $initialized->getValue();
		try {
			$initialized->setValue( null, false );
			( new Retire_Post_Kyc_Activation_Emails() )->migrate();
			$this->assertSame( '1', get_option( 'wcpay_post_kyc_activation_email_cleanup_pending' ) );
		} finally {
			$initialized->setValue( null, $previous );
		}
		update_option( 'woocommerce_woocommerce_payments_version', '11.2.0' );

		/** This action is documented in ActionScheduler::init(). */
		do_action( 'action_scheduler_init' );

		$this->assertFalse( as_has_scheduled_action( 'wcpay_post_kyc_activation_email_send', [ 30 ], 'woocommerce-payments' ) );
		$this->assertFalse( get_option( 'wcpay_post_kyc_activation_email_cleanup_pending' ) );
	}

	public function test_fresh_install_cleanup_is_harmless(): void {
		delete_option( 'woocommerce_woocommerce_payments_version' );

		( new Retire_Post_Kyc_Activation_Emails() )->maybe_migrate();

		$this->assertFalse( get_option( 'wcpay_post_kyc_activation_email_cleanup_pending' ) );
		$this->assertFalse( as_has_scheduled_action( 'wcpay_post_kyc_activation_email_send', [ 7 ], 'woocommerce-payments' ) );
	}

	public function test_cleanup_retries_when_action_scheduler_catches_a_cancellation_failure(): void {
		$action_id = as_schedule_single_action( time() + 3600, 'wcpay_post_kyc_activation_email_send', [ 14 ], 'woocommerce-payments' );
		$store     = \ActionScheduler::store();
		$failing   = $this->getMockBuilder( get_class( $store ) )->onlyMethods( [ 'cancel_action' ] )->getMock();
		$failing->expects( $this->once() )->method( 'cancel_action' )->willThrowException( new \RuntimeException( 'Temporary cancellation failure.' ) );
		$property = new \ReflectionProperty( \ActionScheduler_Store::class, 'store' );
		$property->setAccessible( true );
		try {
			$property->setValue( null, $failing );
			( new Retire_Post_Kyc_Activation_Emails() )->migrate();
		} finally {
			$property->setValue( null, $store );
		}

		$this->assertSame( \ActionScheduler_Store::STATUS_PENDING, $store->get_status( $action_id ) );
		$this->assertSame( '1', get_option( 'wcpay_post_kyc_activation_email_cleanup_pending' ) );

		( new Retire_Post_Kyc_Activation_Emails() )->retry_pending_cleanup();

		$this->assertSame( \ActionScheduler_Store::STATUS_CANCELED, $store->get_status( $action_id ) );
		$this->assertFalse( get_option( 'wcpay_post_kyc_activation_email_cleanup_pending' ) );
	}

	/** @group ms-required */
	public function test_cleanup_does_not_touch_another_sites_queue(): void {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Requires the WordPress multisite test configuration.' );
		}
		as_schedule_single_action( time() + 3600, 'wcpay_post_kyc_activation_email_send', [ 7 ], 'woocommerce-payments' );
		$other_site = self::factory()->blog->create();
		switch_to_blog( $other_site );
		try {
			( new \ActionScheduler_StoreSchema() )->register_tables();
			( new \ActionScheduler_LoggerSchema() )->register_tables();
			as_schedule_single_action( time() + 3600, 'wcpay_post_kyc_activation_email_send', [ 14 ], 'woocommerce-payments' );
			update_option( 'wcpay_post_kyc_activation_email_cleanup_pending', '1' );
		} finally {
			restore_current_blog();
		}

		( new Retire_Post_Kyc_Activation_Emails() )->migrate();
		$this->assertFalse( as_has_scheduled_action( 'wcpay_post_kyc_activation_email_send', [ 7 ], 'woocommerce-payments' ) );

		switch_to_blog( $other_site );
		try {
			$this->assertTrue( as_has_scheduled_action( 'wcpay_post_kyc_activation_email_send', [ 14 ], 'woocommerce-payments' ) );
			$this->assertSame( '1', get_option( 'wcpay_post_kyc_activation_email_cleanup_pending' ) );
			( new Retire_Post_Kyc_Activation_Emails() )->retry_pending_cleanup();
			$this->assertFalse( as_has_scheduled_action( 'wcpay_post_kyc_activation_email_send', [ 14 ], 'woocommerce-payments' ) );
			$this->assertFalse( get_option( 'wcpay_post_kyc_activation_email_cleanup_pending' ) );
		} finally {
			restore_current_blog();
		}
	}
}
