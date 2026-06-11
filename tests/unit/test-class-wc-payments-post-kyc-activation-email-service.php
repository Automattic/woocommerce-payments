<?php
/**
 * Class WC_Payments_Post_Kyc_Activation_Email_Service_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\Constants\Order_Mode;

/**
 * WC_Payments_Post_Kyc_Activation_Email_Service unit tests.
 */
class WC_Payments_Post_Kyc_Activation_Email_Service_Test extends WCPAY_UnitTestCase {

	/**
	 * Order created during a test; cleaned up in tear_down_state().
	 *
	 * @var int|null
	 */
	private $test_order_id = null;

	public function tear_down(): void {
		$this->tear_down_state();
		parent::tear_down();
	}

	/**
	 * Builds a service instance with all conditions eligible by default. Each bool
	 * can be flipped to make the corresponding eligibility check fail.
	 */
	private function make_service(
		bool $is_connected = true,
		bool $is_account_valid = true,
		bool $is_test_drive = false,
		bool $payments_enabled = true
	): WC_Payments_Post_Kyc_Activation_Email_Service {
		$mock_gateway = $this->getMockBuilder( WC_Payment_Gateway_WCPay::class )
			->disableOriginalConstructor()
			->getMock();
		$mock_gateway->method( 'is_connected' )->willReturn( $is_connected );

		$mock_account = $this->getMockBuilder( WC_Payments_Account::class )
			->disableOriginalConstructor()
			->getMock();
		$mock_account->method( 'is_stripe_account_valid' )->willReturn( $is_account_valid );
		$mock_account->method( 'get_account_status_data' )->willReturn(
			[
				'testDrive'       => $is_test_drive,
				'paymentsEnabled' => $payments_enabled,
			]
		);

		$order_service = new WC_Payments_Order_Service( $this->createMock( WC_Payments_API_Client::class ) );

		return new WC_Payments_Post_Kyc_Activation_Email_Service( $mock_account, $mock_gateway, $order_service );
	}

	/**
	 * Sets options needed for an eligible state.
	 */
	private function set_up_eligible_state(): void {
		WC_Payments::mode()->live();
		update_option( WC_Payments_Account::KYC_COMPLETION_DATE_OPTION, time() - 8 * DAY_IN_SECONDS );
		delete_option( WC_Payments_Order_Service::HAS_LIVE_SALE_OPTION );
		delete_option( WC_Payments_Post_Kyc_Activation_Email_Service::EMAIL_SENT_OPTION );
		delete_option( WC_Payments_Post_Kyc_Activation_Email_Service::SCHEDULED_OPTION );

		// Action Scheduler entries persist across tests; clear any leftovers
		// so this test sees an empty queue regardless of prior test order.
		foreach ( WC_Payments_Post_Kyc_Activation_Email_Service::STAGE_DAYS as $stage ) {
			as_unschedule_all_actions( WC_Payments_Post_Kyc_Activation_Email_Service::SEND_HOOK, [ $stage ], 'woocommerce-payments' );
		}
	}

	private function tear_down_state(): void {
		WC_Payments::mode()->live();
		delete_option( WC_Payments_Account::KYC_COMPLETION_DATE_OPTION );
		delete_option( WC_Payments_Order_Service::HAS_LIVE_SALE_OPTION );
		delete_option( WC_Payments_Post_Kyc_Activation_Email_Service::EMAIL_SENT_OPTION );
		delete_option( WC_Payments_Post_Kyc_Activation_Email_Service::SCHEDULED_OPTION );

		// Action Scheduler entries persist across tests; unschedule any leftovers
		// so subsequent tests start with a clean queue.
		foreach ( WC_Payments_Post_Kyc_Activation_Email_Service::STAGE_DAYS as $stage ) {
			as_unschedule_all_actions( WC_Payments_Post_Kyc_Activation_Email_Service::SEND_HOOK, [ $stage ], 'woocommerce-payments' );
		}

		if ( null !== $this->test_order_id ) {
			$order = wc_get_order( $this->test_order_id );
			if ( $order ) {
				$order->delete( true );
			}
			$this->test_order_id = null;
		}
	}

	// -------------------------------------------------------------------------
	// is_eligible tests
	// -------------------------------------------------------------------------

	public function test_is_eligible_returns_true_when_all_conditions_met(): void {
		$this->set_up_eligible_state();
		$service = $this->make_service();

		$this->assertTrue( $service->is_eligible() );
	}

	public function test_is_eligible_returns_false_when_gateway_not_connected(): void {
		$this->set_up_eligible_state();
		$service = $this->make_service( false );

		$this->assertFalse( $service->is_eligible() );
	}

	public function test_is_eligible_returns_false_when_account_invalid(): void {
		$this->set_up_eligible_state();
		$service = $this->make_service( true, false );

		$this->assertFalse( $service->is_eligible() );
	}

	public function test_is_eligible_returns_false_for_test_drive_account(): void {
		$this->set_up_eligible_state();
		$service = $this->make_service( true, true, true );

		$this->assertFalse( $service->is_eligible() );
	}

	public function test_is_eligible_returns_false_when_payments_not_enabled(): void {
		$this->set_up_eligible_state();
		$service = $this->make_service( true, true, false, false );

		$this->assertFalse( $service->is_eligible() );
	}

	public function test_is_eligible_returns_false_in_test_mode(): void {
		$this->set_up_eligible_state();
		WC_Payments::mode()->test();
		$service = $this->make_service();

		$this->assertFalse( $service->is_eligible() );
	}

	public function test_is_eligible_returns_false_in_dev_mode(): void {
		$this->set_up_eligible_state();
		WC_Payments::mode()->dev();
		$service = $this->make_service();

		$this->assertFalse( $service->is_eligible() );
	}

	public function test_is_eligible_returns_false_when_no_kyc_date(): void {
		$this->set_up_eligible_state();
		delete_option( WC_Payments_Account::KYC_COMPLETION_DATE_OPTION );
		$service = $this->make_service();

		$this->assertFalse( $service->is_eligible() );
	}

	public function test_is_eligible_returns_false_when_sticky_live_sale_flag_set(): void {
		$this->set_up_eligible_state();
		update_option( WC_Payments_Order_Service::HAS_LIVE_SALE_OPTION, '1' );
		$service = $this->make_service();

		$this->assertFalse( $service->is_eligible() );
	}

	public function test_is_eligible_returns_false_when_live_order_found_via_fallback_and_writes_sticky_flag(): void {
		$this->set_up_eligible_state();

		$order = wc_create_order();
		$order->set_payment_method( 'woocommerce_payments' );
		$order->set_status( 'completed' );
		$order->update_meta_data( WC_Payments_Order_Service::WCPAY_MODE_META_KEY, Order_Mode::PRODUCTION );
		$order->save();
		$this->test_order_id = $order->get_id();

		$service = $this->make_service();

		$this->assertFalse( $service->is_eligible() );
		$this->assertSame( '1', get_option( WC_Payments_Order_Service::HAS_LIVE_SALE_OPTION ) );
	}

	public function test_is_eligible_ignores_test_mode_orders(): void {
		$this->set_up_eligible_state();

		$order = wc_create_order();
		$order->set_payment_method( 'woocommerce_payments' );
		$order->set_status( 'completed' );
		$order->update_meta_data( WC_Payments_Order_Service::WCPAY_MODE_META_KEY, Order_Mode::TEST );
		$order->save();
		$this->test_order_id = $order->get_id();

		$service = $this->make_service();

		$this->assertTrue( $service->is_eligible() );
	}

	// -------------------------------------------------------------------------
	// schedule_stage_emails tests
	// -------------------------------------------------------------------------

	public function test_schedule_stage_emails_schedules_three_actions_for_fresh_kyc(): void {
		$this->set_up_eligible_state();
		delete_option( WC_Payments_Post_Kyc_Activation_Email_Service::SCHEDULED_OPTION );
		$service = $this->make_service();

		$service->schedule_stage_emails( WC_Payments_Account::KYC_COMPLETION_DATE_OPTION, time() );

		foreach ( WC_Payments_Post_Kyc_Activation_Email_Service::STAGE_DAYS as $stage ) {
			$this->assertNotFalse(
				as_next_scheduled_action( WC_Payments_Post_Kyc_Activation_Email_Service::SEND_HOOK, [ $stage ], 'woocommerce-payments' ),
				"Expected stage $stage to be scheduled."
			);
		}

		$this->assertSame( '1', get_option( WC_Payments_Post_Kyc_Activation_Email_Service::SCHEDULED_OPTION ) );
	}

	public function test_schedule_stage_emails_skips_when_already_scheduled(): void {
		$this->set_up_eligible_state();
		update_option( WC_Payments_Post_Kyc_Activation_Email_Service::SCHEDULED_OPTION, '1' );
		$service = $this->make_service();

		$service->schedule_stage_emails( WC_Payments_Account::KYC_COMPLETION_DATE_OPTION, time() );

		foreach ( WC_Payments_Post_Kyc_Activation_Email_Service::STAGE_DAYS as $stage ) {
			$this->assertFalse(
				as_next_scheduled_action( WC_Payments_Post_Kyc_Activation_Email_Service::SEND_HOOK, [ $stage ], 'woocommerce-payments' )
			);
		}
	}

	public function test_schedule_stage_emails_skips_stale_stages(): void {
		$this->set_up_eligible_state();
		delete_option( WC_Payments_Post_Kyc_Activation_Email_Service::SCHEDULED_OPTION );
		$service = $this->make_service();

		// KYC date 60 days ago — all three send_at points are well past STALE_GRACE_SECONDS (7d).
		$service->schedule_stage_emails( WC_Payments_Account::KYC_COMPLETION_DATE_OPTION, time() - 60 * DAY_IN_SECONDS );

		foreach ( WC_Payments_Post_Kyc_Activation_Email_Service::STAGE_DAYS as $stage ) {
			$this->assertFalse(
				as_next_scheduled_action( WC_Payments_Post_Kyc_Activation_Email_Service::SEND_HOOK, [ $stage ], 'woocommerce-payments' ),
				"Stage $stage should have been skipped as stale."
			);
		}

		// Marker is still set so we don't retry forever.
		$this->assertSame( '1', get_option( WC_Payments_Post_Kyc_Activation_Email_Service::SCHEDULED_OPTION ) );
	}

	public function test_schedule_stage_emails_skips_when_value_is_zero(): void {
		$this->set_up_eligible_state();
		delete_option( WC_Payments_Post_Kyc_Activation_Email_Service::SCHEDULED_OPTION );
		$service = $this->make_service();

		$service->schedule_stage_emails( WC_Payments_Account::KYC_COMPLETION_DATE_OPTION, 0 );

		foreach ( WC_Payments_Post_Kyc_Activation_Email_Service::STAGE_DAYS as $stage ) {
			$this->assertFalse(
				as_next_scheduled_action( WC_Payments_Post_Kyc_Activation_Email_Service::SEND_HOOK, [ $stage ], 'woocommerce-payments' )
			);
		}

		$this->assertFalse( get_option( WC_Payments_Post_Kyc_Activation_Email_Service::SCHEDULED_OPTION ) );
	}

	// -------------------------------------------------------------------------
	// send_email_for_stage tests
	// -------------------------------------------------------------------------

	public function test_send_email_for_stage_bails_on_invalid_stage(): void {
		$this->set_up_eligible_state();
		$service = $this->make_service();

		$service->send_email_for_stage( 99 );

		$this->assertFalse( get_option( WC_Payments_Post_Kyc_Activation_Email_Service::EMAIL_SENT_OPTION ) );
	}

	public function test_send_email_for_stage_bails_on_already_sent_stage(): void {
		$this->set_up_eligible_state();
		update_option( WC_Payments_Post_Kyc_Activation_Email_Service::EMAIL_SENT_OPTION, [ 7 ] );
		$service = $this->make_service();

		$service->send_email_for_stage( 7 );

		// The marker stayed exactly at the pre-set value (no double-append).
		$this->assertSame( [ 7 ], get_option( WC_Payments_Post_Kyc_Activation_Email_Service::EMAIL_SENT_OPTION ) );
	}

	public function test_send_email_for_stage_bails_when_ineligible(): void {
		$this->set_up_eligible_state();
		// Render ineligible by clearing KYC date.
		delete_option( WC_Payments_Account::KYC_COMPLETION_DATE_OPTION );
		$service = $this->make_service();

		$service->send_email_for_stage( 7 );

		$this->assertFalse( get_option( WC_Payments_Post_Kyc_Activation_Email_Service::EMAIL_SENT_OPTION ) );
	}

	public function test_send_email_for_stage_bails_when_action_fires_too_late(): void {
		$this->set_up_eligible_state();
		// KYC was 15 days ago — stage-7 send time was 8 days ago, past the 7-day stale grace.
		update_option( WC_Payments_Account::KYC_COMPLETION_DATE_OPTION, time() - 15 * DAY_IN_SECONDS );
		$service = $this->make_service();

		$service->send_email_for_stage( 7 );

		$this->assertFalse( get_option( WC_Payments_Post_Kyc_Activation_Email_Service::EMAIL_SENT_OPTION ) );
	}

	public function test_send_email_for_stage_triggers_email_and_marks_sent_on_success(): void {
		$this->set_up_eligible_state();

		// Plugin init registers the email class via the woocommerce_email_classes
		// filter; this assertion locks in that the registration is in place.
		$emails = WC()->mailer()->get_emails();
		$this->assertInstanceOf(
			WC_Payments_Email_Post_Kyc_Activation::class,
			$emails['WC_Payments_Email_Post_Kyc_Activation'] ?? null
		);

		add_filter( 'pre_wp_mail', '__return_true' );

		$service = $this->make_service();
		$service->send_email_for_stage( 7 );

		remove_filter( 'pre_wp_mail', '__return_true' );

		$this->assertSame( [ 7 ], get_option( WC_Payments_Post_Kyc_Activation_Email_Service::EMAIL_SENT_OPTION ) );
	}

	public function test_send_email_for_stage_does_not_mark_stage_when_email_is_disabled(): void {
		$this->set_up_eligible_state();

		// Merchant opted out via WC > Settings > Emails. The handler bails
		// before calling trigger() and EMAIL_SENT_OPTION is left untouched —
		// it tracks only stages where an email was actually delivered.
		add_filter( 'woocommerce_email_enabled_wcpay_post_kyc_activation', '__return_false' );

		$this->make_service()->send_email_for_stage( 7 );

		remove_filter( 'woocommerce_email_enabled_wcpay_post_kyc_activation', '__return_false' );

		$this->assertFalse( get_option( WC_Payments_Post_Kyc_Activation_Email_Service::EMAIL_SENT_OPTION ) );
	}

	public function test_send_email_for_stage_does_not_mark_stage_when_mailer_fails(): void {
		$this->set_up_eligible_state();

		// Email is enabled and has a recipient, but wp_mail returns false.
		// The stage must stay unconsumed so the failure shows up as a
		// `wcpay_post_kyc_activation_email_send_failed` tracks event instead
		// of being silently swallowed.
		add_filter( 'pre_wp_mail', '__return_false' );

		$this->make_service()->send_email_for_stage( 7 );

		remove_filter( 'pre_wp_mail', '__return_false' );

		$this->assertFalse( get_option( WC_Payments_Post_Kyc_Activation_Email_Service::EMAIL_SENT_OPTION ) );
	}

	// -------------------------------------------------------------------------
	// maybe_track_cta_click tests
	// -------------------------------------------------------------------------

	public function test_maybe_track_cta_click_bails_when_referrer_param_absent(): void {
		$admin_user = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_user );

		$redirected = false;
		$intercept  = function () use ( &$redirected ) {
			$redirected = true;
			throw new \Exception( 'redirect' );
		};
		add_filter( 'wp_redirect', $intercept );
		try {
			$this->make_service()->maybe_track_cta_click();
		} catch ( \Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- Bail-path tests; intercept filter only throws if maybe_track_cta_click() unexpectedly reaches wp_safe_redirect().
			// Should not redirect.
		}
		remove_filter( 'wp_redirect', $intercept );

		$this->assertFalse( $redirected );
	}

	public function test_maybe_track_cta_click_bails_when_referrer_value_does_not_match(): void {
		$admin_user = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_user );

		$_GET['wcpay_referrer']       = 'something_else';
		$_GET['wcpay_referrer_stage'] = '7';

		$redirected = false;
		$intercept  = function () use ( &$redirected ) {
			$redirected = true;
			throw new \Exception( 'redirect' );
		};
		add_filter( 'wp_redirect', $intercept );
		try {
			$this->make_service()->maybe_track_cta_click();
		} catch ( \Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- Bail-path tests; intercept filter only throws if maybe_track_cta_click() unexpectedly reaches wp_safe_redirect().
			// Should not redirect.
		}
		remove_filter( 'wp_redirect', $intercept );

		$this->assertFalse( $redirected );

		unset( $_GET['wcpay_referrer'], $_GET['wcpay_referrer_stage'] );
	}

	public function test_maybe_track_cta_click_bails_when_user_lacks_capability(): void {
		$subscriber = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $subscriber );

		$_GET['wcpay_referrer']       = 'post_kyc_email';
		$_GET['wcpay_referrer_stage'] = '7';

		$redirected = false;
		$intercept  = function () use ( &$redirected ) {
			$redirected = true;
			throw new \Exception( 'redirect' );
		};
		add_filter( 'wp_redirect', $intercept );
		try {
			$this->make_service()->maybe_track_cta_click();
		} catch ( \Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- Bail-path tests; intercept filter only throws if maybe_track_cta_click() unexpectedly reaches wp_safe_redirect().
			// Should not redirect.
		}
		remove_filter( 'wp_redirect', $intercept );

		$this->assertFalse( $redirected );

		unset( $_GET['wcpay_referrer'], $_GET['wcpay_referrer_stage'] );
	}

	public function test_maybe_track_cta_click_bails_when_stage_is_invalid(): void {
		$admin_user = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_user );

		$_GET['wcpay_referrer']       = 'post_kyc_email';
		$_GET['wcpay_referrer_stage'] = '99';

		$redirected = false;
		$intercept  = function () use ( &$redirected ) {
			$redirected = true;
			throw new \Exception( 'redirect' );
		};
		add_filter( 'wp_redirect', $intercept );
		try {
			$this->make_service()->maybe_track_cta_click();
		} catch ( \Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- Bail-path tests; intercept filter only throws if maybe_track_cta_click() unexpectedly reaches wp_safe_redirect().
			// Should not redirect.
		}
		remove_filter( 'wp_redirect', $intercept );

		$this->assertFalse( $redirected );

		unset( $_GET['wcpay_referrer'], $_GET['wcpay_referrer_stage'] );
	}

	public function test_maybe_track_cta_click_redirects_and_strips_marker_query_args_on_valid_request(): void {
		$admin_user = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_user );

		$_GET['wcpay_referrer']       = 'post_kyc_email';
		$_GET['wcpay_referrer_stage'] = '14';
		$_SERVER['REQUEST_URI']       = '/wp-admin/admin.php?page=wc-admin&path=/marketing&wcpay_referrer=post_kyc_email&wcpay_referrer_stage=14';

		$captured_url = null;
		$intercept    = function ( $location ) use ( &$captured_url ) {
			$captured_url = $location;
			throw new \Exception( 'redirect' );
		};
		add_filter( 'wp_redirect', $intercept );
		try {
			$this->make_service()->maybe_track_cta_click();
		} catch ( \Exception $e ) {
			$this->assertSame( 'redirect', $e->getMessage() );
		}
		remove_filter( 'wp_redirect', $intercept );

		$this->assertNotNull( $captured_url );
		$this->assertStringNotContainsString( 'wcpay_referrer=', $captured_url );
		$this->assertStringNotContainsString( 'wcpay_referrer_stage=', $captured_url );
		$this->assertStringContainsString( 'page=wc-admin', $captured_url );
		$this->assertStringContainsString( 'path=/marketing', urldecode( $captured_url ) );

		unset( $_GET['wcpay_referrer'], $_GET['wcpay_referrer_stage'], $_SERVER['REQUEST_URI'] );
	}

	public function test_init_hooks_registers_cta_click_handler(): void {
		$service = $this->make_service();
		$service->init_hooks();

		$this->assertNotFalse(
			has_action( 'admin_init', [ $service, 'maybe_track_cta_click' ] )
		);

		remove_action( 'admin_init', [ $service, 'maybe_track_cta_click' ] );
	}
}
