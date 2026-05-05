<?php
/**
 * Class WC_Payments_Admin_Banner_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;

/**
 * WC_Payments_Admin_Banner unit tests.
 */
class WC_Payments_Admin_Banner_Test extends WCPAY_UnitTestCase {

	/**
	 * Order created during notice tests; cleaned up in tear_down_notice_global_state().
	 *
	 * @var int|null
	 */
	private $test_order_id = null;

	// -------------------------------------------------------------------------
	// should_show_test_to_live_notice tests
	// -------------------------------------------------------------------------

	/**
	 * Creates a WC_Payments_Admin_Banner instance with all conditions eligible to
	 * show the test-to-live notice, using fresh mocks so individual tests can
	 * configure each condition independently without stubbing conflicts.
	 */
	private function make_admin_banner_for_notice_test(
		bool $is_connected = true,
		bool $is_account_valid = true,
		bool $is_test_drive = false,
		bool $payments_enabled = true,
		bool $is_live = false
	): WC_Payments_Admin_Banner {
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
		$mock_account->method( 'get_is_live' )->willReturn( $is_live );

		return new WC_Payments_Admin_Banner( $mock_gateway, $mock_account );
	}

	/**
	 * Sets non-mock global state for the notice eligibility tests.
	 * Call before make_admin_banner_for_notice_test().
	 */
	private function set_up_notice_global_state( int $days_in_test_mode = 8, bool $has_orders = true ): void {
		delete_transient( WC_Payments_Admin_Banner::TRANSIENT_TEST_TO_LIVE_NOTICE_ELIGIBLE );

		// live() is the only Mode method that clears dev_mode. Calling test() alone
		// leaves dev_mode set if a prior test called dev() without a full live() reset.
		WC_Payments::mode()->live();
		WC_Payments::mode()->test();

		$admin_user = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_user );
		update_option( WC_Payments_Onboarding_Service::TEST_MODE_ENABLED_DATE_OPTION, time() - $days_in_test_mode * DAY_IN_SECONDS );

		if ( $has_orders ) {
			$order = wc_create_order();
			$order->set_payment_method( 'woocommerce_payments' );
			$order->set_status( 'completed' );
			$order->update_meta_data( WC_Payments_Order_Service::WCPAY_MODE_META_KEY, \WCPay\Constants\Order_Mode::TEST );
			$order->save();
			$this->test_order_id = $order->get_id();
		}
	}

	private function tear_down_notice_global_state(): void {
		WC_Payments::mode()->live();
		delete_option( WC_Payments_Onboarding_Service::TEST_MODE_ENABLED_DATE_OPTION );
		delete_user_meta( get_current_user_id(), WC_Payments_Admin_Banner::USER_META_TEST_TO_LIVE_NOTICE_DISMISSED );
		delete_user_meta( get_current_user_id(), WC_Payments_Admin_Banner::USER_META_TEST_TO_LIVE_NOTICE_SNOOZED );
		delete_transient( WC_Payments_Admin_Banner::TRANSIENT_TEST_TO_LIVE_NOTICE_ELIGIBLE );

		if ( null !== $this->test_order_id ) {
			$order = wc_get_order( $this->test_order_id );
			if ( $order ) {
				$order->delete( true );
			}
			$this->test_order_id = null;
		}
	}

	public function test_should_show_test_to_live_notice_returns_true_when_all_conditions_met(): void {
		$this->set_up_notice_global_state();
		$banner = $this->make_admin_banner_for_notice_test();

		$this->assertTrue( $banner->should_show_test_to_live_notice() );

		$this->tear_down_notice_global_state();
	}

	public function test_should_show_test_to_live_notice_returns_false_when_user_cannot_manage_woocommerce(): void {
		$this->set_up_notice_global_state();
		$subscriber = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $subscriber );
		$banner = $this->make_admin_banner_for_notice_test();

		$this->assertFalse( $banner->should_show_test_to_live_notice() );

		$this->tear_down_notice_global_state();
	}

	public function test_should_show_test_to_live_notice_returns_false_when_not_connected(): void {
		$this->set_up_notice_global_state();
		$banner = $this->make_admin_banner_for_notice_test( false );

		$this->assertFalse( $banner->should_show_test_to_live_notice() );

		$this->tear_down_notice_global_state();
	}

	public function test_should_show_test_to_live_notice_returns_false_when_account_invalid(): void {
		$this->set_up_notice_global_state();
		$banner = $this->make_admin_banner_for_notice_test( true, false );

		$this->assertFalse( $banner->should_show_test_to_live_notice() );

		$this->tear_down_notice_global_state();
	}

	public function test_should_show_test_to_live_notice_returns_false_for_test_drive_account(): void {
		$this->set_up_notice_global_state();
		$banner = $this->make_admin_banner_for_notice_test( true, true, true );

		$this->assertFalse( $banner->should_show_test_to_live_notice() );

		$this->tear_down_notice_global_state();
	}

	public function test_should_show_test_to_live_notice_returns_false_when_payments_not_enabled(): void {
		$this->set_up_notice_global_state();
		$banner = $this->make_admin_banner_for_notice_test( true, true, false, false );

		$this->assertFalse( $banner->should_show_test_to_live_notice() );

		$this->tear_down_notice_global_state();
	}

	public function test_should_show_test_to_live_notice_returns_false_when_not_in_test_mode(): void {
		$this->set_up_notice_global_state();
		WC_Payments::mode()->live();
		$banner = $this->make_admin_banner_for_notice_test();

		$this->assertFalse( $banner->should_show_test_to_live_notice() );

		$this->tear_down_notice_global_state();
	}

	public function test_should_show_test_to_live_notice_returns_false_in_dev_mode(): void {
		$this->set_up_notice_global_state();
		WC_Payments::mode()->dev();
		$banner = $this->make_admin_banner_for_notice_test();

		$this->assertFalse( $banner->should_show_test_to_live_notice() );

		$this->tear_down_notice_global_state();
	}

	public function test_should_show_test_to_live_notice_returns_false_when_threshold_not_reached(): void {
		$this->set_up_notice_global_state( 3 );
		$banner = $this->make_admin_banner_for_notice_test();

		$this->assertFalse( $banner->should_show_test_to_live_notice() );

		$this->tear_down_notice_global_state();
	}

	public function test_should_show_test_to_live_notice_returns_false_when_no_enabled_date(): void {
		$this->set_up_notice_global_state();
		delete_option( WC_Payments_Onboarding_Service::TEST_MODE_ENABLED_DATE_OPTION );
		$banner = $this->make_admin_banner_for_notice_test();

		$this->assertFalse( $banner->should_show_test_to_live_notice() );

		$this->tear_down_notice_global_state();
	}

	public function test_should_show_test_to_live_notice_returns_false_when_no_wcpay_orders(): void {
		$this->set_up_notice_global_state( 8, false );
		$banner = $this->make_admin_banner_for_notice_test();

		$this->assertFalse( $banner->should_show_test_to_live_notice() );

		$this->tear_down_notice_global_state();
	}

	public function test_should_show_test_to_live_notice_returns_false_when_dismissed(): void {
		$this->set_up_notice_global_state();
		update_user_meta( get_current_user_id(), WC_Payments_Admin_Banner::USER_META_TEST_TO_LIVE_NOTICE_DISMISSED, time() );
		$banner = $this->make_admin_banner_for_notice_test();

		$this->assertFalse( $banner->should_show_test_to_live_notice() );

		$this->tear_down_notice_global_state();
	}

	public function test_should_show_test_to_live_notice_returns_false_when_snoozed(): void {
		$this->set_up_notice_global_state();
		update_user_meta( get_current_user_id(), WC_Payments_Admin_Banner::USER_META_TEST_TO_LIVE_NOTICE_SNOOZED, time() );
		$banner = $this->make_admin_banner_for_notice_test();

		$this->assertFalse( $banner->should_show_test_to_live_notice() );

		$this->tear_down_notice_global_state();
	}

	public function test_should_show_test_to_live_notice_returns_true_when_snooze_expired(): void {
		$this->set_up_notice_global_state();
		$eight_days_ago = time() - 8 * DAY_IN_SECONDS;
		update_user_meta( get_current_user_id(), WC_Payments_Admin_Banner::USER_META_TEST_TO_LIVE_NOTICE_SNOOZED, $eight_days_ago );
		$banner = $this->make_admin_banner_for_notice_test();

		$this->assertTrue( $banner->should_show_test_to_live_notice() );

		$this->tear_down_notice_global_state();
	}

	public function test_handle_test_to_live_notice_cta_redirects_when_account_is_live(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$_GET['wcpay-test-to-live-cta']        = '1';
		$_GET['_wcpay_test_to_live_cta_nonce'] = wp_create_nonce( 'wcpay_test_to_live_cta_nonce' );

		$banner             = $this->make_admin_banner_for_notice_test( true, true, false, true, true );
		$redirect_intercept = function () {
			throw new \Exception( 'redirect' );
		};
		add_filter( 'wp_redirect', $redirect_intercept );
		try {
			$banner->handle_test_to_live_notice_cta();
		} catch ( \Exception $e ) {
			$this->assertSame( 'redirect', $e->getMessage() );
		}
		remove_filter( 'wp_redirect', $redirect_intercept );

		unset( $_GET['wcpay-test-to-live-cta'], $_GET['_wcpay_test_to_live_cta_nonce'] );
	}

	public function test_handle_test_to_live_notice_cta_redirects_to_onboarding_when_account_not_live(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$_GET['wcpay-test-to-live-cta']        = '1';
		$_GET['_wcpay_test_to_live_cta_nonce'] = wp_create_nonce( 'wcpay_test_to_live_cta_nonce' );

		$banner             = $this->make_admin_banner_for_notice_test();
		$redirect_intercept = function () {
			throw new \Exception( 'redirect' );
		};
		add_filter( 'wp_redirect', $redirect_intercept );
		try {
			$banner->handle_test_to_live_notice_cta();
		} catch ( \Exception $e ) {
			$this->assertSame( 'redirect', $e->getMessage() );
		}
		remove_filter( 'wp_redirect', $redirect_intercept );

		unset( $_GET['wcpay-test-to-live-cta'], $_GET['_wcpay_test_to_live_cta_nonce'] );
	}

	public function test_hide_test_to_live_notice_sets_dismissed_meta_and_redirects(): void {
		$admin_user = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_user );

		$_GET['wcpay-hide-test-to-live-notice']   = '1';
		$_GET['_wcpay_test_to_live_notice_nonce'] = wp_create_nonce( 'wcpay_hide_test_to_live_notice_nonce' );

		$banner             = $this->make_admin_banner_for_notice_test();
		$redirect_intercept = function () {
			throw new \Exception( 'redirect' );
		};
		add_filter( 'wp_redirect', $redirect_intercept );
		try {
			$banner->hide_test_to_live_notice();
		} catch ( \Exception $e ) {
			$this->assertSame( 'redirect', $e->getMessage() );
		}
		remove_filter( 'wp_redirect', $redirect_intercept );

		$this->assertNotEmpty( get_user_meta( $admin_user, WC_Payments_Admin_Banner::USER_META_TEST_TO_LIVE_NOTICE_DISMISSED, true ) );

		delete_user_meta( $admin_user, WC_Payments_Admin_Banner::USER_META_TEST_TO_LIVE_NOTICE_DISMISSED );
		unset( $_GET['wcpay-hide-test-to-live-notice'], $_GET['_wcpay_test_to_live_notice_nonce'] );
	}

	public function test_snooze_test_to_live_notice_sets_snoozed_meta_and_redirects(): void {
		$admin_user = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_user );

		$_GET['wcpay-snooze-test-to-live-notice']        = '1';
		$_GET['_wcpay_snooze_test_to_live_notice_nonce'] = wp_create_nonce( 'wcpay_snooze_test_to_live_notice_nonce' );

		$banner             = $this->make_admin_banner_for_notice_test();
		$redirect_intercept = function () {
			throw new \Exception( 'redirect' );
		};
		add_filter( 'wp_redirect', $redirect_intercept );
		try {
			$banner->snooze_test_to_live_notice();
		} catch ( \Exception $e ) {
			$this->assertSame( 'redirect', $e->getMessage() );
		}
		remove_filter( 'wp_redirect', $redirect_intercept );

		$this->assertNotEmpty( get_user_meta( get_current_user_id(), WC_Payments_Admin_Banner::USER_META_TEST_TO_LIVE_NOTICE_SNOOZED, true ) );

		delete_user_meta( get_current_user_id(), WC_Payments_Admin_Banner::USER_META_TEST_TO_LIVE_NOTICE_SNOOZED );
		unset( $_GET['wcpay-snooze-test-to-live-notice'], $_GET['_wcpay_snooze_test_to_live_notice_nonce'] );
	}

	public function test_should_show_test_to_live_notice_caches_expensive_checks(): void {
		$this->set_up_notice_global_state();

		$mock_account = $this->getMockBuilder( WC_Payments_Account::class )
			->disableOriginalConstructor()
			->getMock();
		$mock_account->expects( $this->once() )
			->method( 'get_account_status_data' )
			->willReturn(
				[
					'testDrive'       => false,
					'paymentsEnabled' => true,
				]
			);
		$mock_account->method( 'is_stripe_account_valid' )->willReturn( true );

		$mock_gateway = $this->getMockBuilder( WC_Payment_Gateway_WCPay::class )
			->disableOriginalConstructor()
			->getMock();
		$mock_gateway->method( 'is_connected' )->willReturn( true );

		$banner = new WC_Payments_Admin_Banner( $mock_gateway, $mock_account );

		$banner->should_show_test_to_live_notice();
		$banner->should_show_test_to_live_notice();

		$this->tear_down_notice_global_state();
	}

	// -------------------------------------------------------------------------
	// init_hooks — test-to-live notice hook registration
	// -------------------------------------------------------------------------

	public function test_init_hooks_registers_sections_hook_for_active_tab(): void {
		$_GET['page'] = 'wc-settings';
		$_GET['tab']  = 'general';
		$banner       = $this->make_admin_banner_for_notice_test();

		$banner->init_hooks();

		$this->assertNotFalse(
			has_action( 'woocommerce_sections_general', [ $banner, 'maybe_show_test_to_live_notice' ] )
		);

		remove_action( 'woocommerce_sections_general', [ $banner, 'maybe_show_test_to_live_notice' ] );
		unset( $_GET['page'], $_GET['tab'] );
	}

	public function test_init_hooks_registers_sections_hook_for_checkout_tab(): void {
		$_GET['page'] = 'wc-settings';
		$_GET['tab']  = 'checkout';
		$banner       = $this->make_admin_banner_for_notice_test();

		$banner->init_hooks();

		$this->assertNotFalse(
			has_action( 'woocommerce_sections_checkout', [ $banner, 'maybe_show_test_to_live_notice' ] )
		);

		remove_action( 'woocommerce_sections_checkout', [ $banner, 'maybe_show_test_to_live_notice' ] );
		unset( $_GET['page'], $_GET['tab'] );
	}

	public function test_init_hooks_defaults_to_general_tab_when_no_tab_param(): void {
		$_GET['page'] = 'wc-settings';
		unset( $_GET['tab'] );
		$banner = $this->make_admin_banner_for_notice_test();

		$banner->init_hooks();

		$this->assertNotFalse(
			has_action( 'woocommerce_sections_general', [ $banner, 'maybe_show_test_to_live_notice' ] )
		);

		remove_action( 'woocommerce_sections_general', [ $banner, 'maybe_show_test_to_live_notice' ] );
		unset( $_GET['page'] );
	}

	public function test_init_hooks_does_not_register_notice_when_not_on_wc_settings_page(): void {
		$_GET['page'] = 'wc-admin';
		$_GET['tab']  = 'checkout';
		$banner       = $this->make_admin_banner_for_notice_test();

		$banner->init_hooks();

		$this->assertFalse(
			has_action( 'woocommerce_sections_checkout', [ $banner, 'maybe_show_test_to_live_notice' ] )
		);

		unset( $_GET['page'], $_GET['tab'] );
	}

	public function test_init_hooks_does_not_register_notice_when_page_param_absent(): void {
		unset( $_GET['page'] );
		$banner = $this->make_admin_banner_for_notice_test();

		$banner->init_hooks();

		$this->assertFalse(
			has_action( 'woocommerce_sections_general', [ $banner, 'maybe_show_test_to_live_notice' ] )
		);
	}

	public function test_maybe_show_test_to_live_notice_outputs_container_div(): void {
		$this->set_up_notice_global_state();
		$banner = $this->make_admin_banner_for_notice_test();

		ob_start();
		$banner->maybe_show_test_to_live_notice();
		$output = ob_get_clean();

		$this->assertStringContainsString( '<div id="wcpay-test-to-live-notice">', $output );

		$this->tear_down_notice_global_state();
	}

	// -------------------------------------------------------------------------
	// should_show_one_and_done_notice tests
	// -------------------------------------------------------------------------

	/**
	 * Order IDs created during one-and-done tests; cleaned up in tear_down_one_and_done_global_state().
	 *
	 * @var int[]
	 */
	private $one_and_done_order_ids = [];

	/**
	 * Sets non-mock global state for the one-and-done notice eligibility tests.
	 * Call before make_admin_banner_for_notice_test( ..., $is_live = true ).
	 */
	private function set_up_one_and_done_global_state( int $live_order_count = 1, int $first_order_age_days = 8 ): void {
		delete_transient( WC_Payments_Admin_Banner::TRANSIENT_ONE_AND_DONE_NOTICE_ELIGIBLE );

		// Reset Mode to live (one-and-done requires the merchant to be transacting live, not in test or dev).
		WC_Payments::mode()->live();

		$admin_user = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_user );

		for ( $i = 0; $i < $live_order_count; $i++ ) {
			$order = wc_create_order();
			$order->set_payment_method( 'woocommerce_payments' );
			$order->set_status( 'completed' );
			$order->update_meta_data( WC_Payments_Order_Service::WCPAY_MODE_META_KEY, \WCPay\Constants\Order_Mode::PRODUCTION );
			// Stagger ages so the first (oldest) order is exactly $first_order_age_days old.
			$order->set_date_created( time() - ( $first_order_age_days - $i ) * DAY_IN_SECONDS );
			$order->save();
			$this->one_and_done_order_ids[] = $order->get_id();
		}
	}

	private function tear_down_one_and_done_global_state(): void {
		WC_Payments::mode()->live();
		delete_user_meta( get_current_user_id(), WC_Payments_Admin_Banner::USER_META_ONE_AND_DONE_NOTICE_DISMISSED );
		delete_user_meta( get_current_user_id(), WC_Payments_Admin_Banner::USER_META_ONE_AND_DONE_NOTICE_SNOOZED );
		delete_user_meta( get_current_user_id(), WC_Payments_Admin_Banner::USER_META_ONE_AND_DONE_NOTICE_SHOWN );
		delete_transient( WC_Payments_Admin_Banner::TRANSIENT_ONE_AND_DONE_NOTICE_ELIGIBLE );

		foreach ( $this->one_and_done_order_ids as $order_id ) {
			$order = wc_get_order( $order_id );
			if ( $order ) {
				$order->delete( true );
			}
		}
		$this->one_and_done_order_ids = [];
	}

	public function test_should_show_one_and_done_notice_returns_true_when_all_conditions_met(): void {
		$this->set_up_one_and_done_global_state();
		$banner = $this->make_admin_banner_for_notice_test( true, true, false, true, true );

		$this->assertTrue( $banner->should_show_one_and_done_notice() );

		$this->tear_down_one_and_done_global_state();
	}

	public function test_should_show_one_and_done_notice_returns_false_when_user_cannot_manage_woocommerce(): void {
		$this->set_up_one_and_done_global_state();
		$subscriber = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $subscriber );
		$banner = $this->make_admin_banner_for_notice_test( true, true, false, true, true );

		$this->assertFalse( $banner->should_show_one_and_done_notice() );

		$this->tear_down_one_and_done_global_state();
	}

	public function test_should_show_one_and_done_notice_returns_false_when_not_connected(): void {
		$this->set_up_one_and_done_global_state();
		$banner = $this->make_admin_banner_for_notice_test( false, true, false, true, true );

		$this->assertFalse( $banner->should_show_one_and_done_notice() );

		$this->tear_down_one_and_done_global_state();
	}

	public function test_should_show_one_and_done_notice_returns_false_when_account_invalid(): void {
		$this->set_up_one_and_done_global_state();
		$banner = $this->make_admin_banner_for_notice_test( true, false, false, true, true );

		$this->assertFalse( $banner->should_show_one_and_done_notice() );

		$this->tear_down_one_and_done_global_state();
	}

	public function test_should_show_one_and_done_notice_returns_false_for_test_drive_account(): void {
		$this->set_up_one_and_done_global_state();
		$banner = $this->make_admin_banner_for_notice_test( true, true, true, true, true );

		$this->assertFalse( $banner->should_show_one_and_done_notice() );

		$this->tear_down_one_and_done_global_state();
	}

	public function test_should_show_one_and_done_notice_returns_false_when_payments_not_enabled(): void {
		$this->set_up_one_and_done_global_state();
		$banner = $this->make_admin_banner_for_notice_test( true, true, false, false, true );

		$this->assertFalse( $banner->should_show_one_and_done_notice() );

		$this->tear_down_one_and_done_global_state();
	}

	public function test_should_show_one_and_done_notice_returns_false_when_account_not_live(): void {
		$this->set_up_one_and_done_global_state();
		$banner = $this->make_admin_banner_for_notice_test( true, true, false, true, false );

		$this->assertFalse( $banner->should_show_one_and_done_notice() );

		$this->tear_down_one_and_done_global_state();
	}

	public function test_should_show_one_and_done_notice_returns_false_in_test_mode(): void {
		$this->set_up_one_and_done_global_state();
		WC_Payments::mode()->test();
		$banner = $this->make_admin_banner_for_notice_test( true, true, false, true, true );

		$this->assertFalse( $banner->should_show_one_and_done_notice() );

		$this->tear_down_one_and_done_global_state();
	}

	public function test_should_show_one_and_done_notice_returns_false_in_dev_mode(): void {
		$this->set_up_one_and_done_global_state();
		WC_Payments::mode()->dev();
		$banner = $this->make_admin_banner_for_notice_test( true, true, false, true, true );

		$this->assertFalse( $banner->should_show_one_and_done_notice() );

		$this->tear_down_one_and_done_global_state();
	}

	public function test_should_show_one_and_done_notice_returns_false_when_no_live_orders(): void {
		$this->set_up_one_and_done_global_state( 0 );
		$banner = $this->make_admin_banner_for_notice_test( true, true, false, true, true );

		$this->assertFalse( $banner->should_show_one_and_done_notice() );

		$this->tear_down_one_and_done_global_state();
	}

	public function test_should_show_one_and_done_notice_returns_false_when_two_live_orders(): void {
		$this->set_up_one_and_done_global_state( 2 );
		$banner = $this->make_admin_banner_for_notice_test( true, true, false, true, true );

		$this->assertFalse( $banner->should_show_one_and_done_notice() );

		$this->tear_down_one_and_done_global_state();
	}

	public function test_should_show_one_and_done_notice_returns_false_when_first_order_too_recent(): void {
		$this->set_up_one_and_done_global_state( 1, 3 );
		$banner = $this->make_admin_banner_for_notice_test( true, true, false, true, true );

		$this->assertFalse( $banner->should_show_one_and_done_notice() );

		$this->tear_down_one_and_done_global_state();
	}

	public function test_should_show_one_and_done_notice_ignores_test_mode_orders(): void {
		// One live order at threshold age — should be eligible.
		$this->set_up_one_and_done_global_state();

		// A test-mode WCPay order also exists; test orders must not count toward the live count.
		$test_order = wc_create_order();
		$test_order->set_payment_method( 'woocommerce_payments' );
		$test_order->set_status( 'completed' );
		$test_order->update_meta_data( WC_Payments_Order_Service::WCPAY_MODE_META_KEY, \WCPay\Constants\Order_Mode::TEST );
		$test_order->save();
		$this->one_and_done_order_ids[] = $test_order->get_id();

		$banner = $this->make_admin_banner_for_notice_test( true, true, false, true, true );

		$this->assertTrue( $banner->should_show_one_and_done_notice() );

		$this->tear_down_one_and_done_global_state();
	}

	public function test_should_show_one_and_done_notice_returns_false_when_dismissed(): void {
		$this->set_up_one_and_done_global_state();
		update_user_meta( get_current_user_id(), WC_Payments_Admin_Banner::USER_META_ONE_AND_DONE_NOTICE_DISMISSED, time() );
		$banner = $this->make_admin_banner_for_notice_test( true, true, false, true, true );

		$this->assertFalse( $banner->should_show_one_and_done_notice() );

		$this->tear_down_one_and_done_global_state();
	}

	public function test_should_show_one_and_done_notice_returns_false_when_snoozed(): void {
		$this->set_up_one_and_done_global_state();
		update_user_meta( get_current_user_id(), WC_Payments_Admin_Banner::USER_META_ONE_AND_DONE_NOTICE_SNOOZED, time() );
		$banner = $this->make_admin_banner_for_notice_test( true, true, false, true, true );

		$this->assertFalse( $banner->should_show_one_and_done_notice() );

		$this->tear_down_one_and_done_global_state();
	}

	public function test_should_show_one_and_done_notice_returns_true_when_snooze_expired(): void {
		$this->set_up_one_and_done_global_state();
		$eight_days_ago = time() - 8 * DAY_IN_SECONDS;
		update_user_meta( get_current_user_id(), WC_Payments_Admin_Banner::USER_META_ONE_AND_DONE_NOTICE_SNOOZED, $eight_days_ago );
		$banner = $this->make_admin_banner_for_notice_test( true, true, false, true, true );

		$this->assertTrue( $banner->should_show_one_and_done_notice() );

		$this->tear_down_one_and_done_global_state();
	}

	public function test_handle_one_and_done_notice_cta_redirects_to_marketing(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$_GET['wcpay-one-and-done-cta']        = '1';
		$_GET['_wcpay_one_and_done_cta_nonce'] = wp_create_nonce( 'wcpay_one_and_done_cta_nonce' );

		$banner             = $this->make_admin_banner_for_notice_test( true, true, false, true, true );
		$captured_url       = null;
		$redirect_intercept = function ( $location ) use ( &$captured_url ) {
			$captured_url = $location;
			throw new \Exception( 'redirect' );
		};
		add_filter( 'wp_redirect', $redirect_intercept );
		try {
			$banner->handle_one_and_done_notice_cta();
		} catch ( \Exception $e ) {
			$this->assertSame( 'redirect', $e->getMessage() );
		}
		remove_filter( 'wp_redirect', $redirect_intercept );

		$this->assertStringContainsString( 'page=wc-admin', $captured_url );
		$this->assertStringContainsString( 'path=/marketing', $captured_url );

		unset( $_GET['wcpay-one-and-done-cta'], $_GET['_wcpay_one_and_done_cta_nonce'] );
	}

	public function test_hide_one_and_done_notice_sets_dismissed_meta_and_redirects(): void {
		$admin_user = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_user );

		$_GET['wcpay-hide-one-and-done-notice']   = '1';
		$_GET['_wcpay_one_and_done_notice_nonce'] = wp_create_nonce( 'wcpay_hide_one_and_done_notice_nonce' );

		$banner             = $this->make_admin_banner_for_notice_test( true, true, false, true, true );
		$redirect_intercept = function () {
			throw new \Exception( 'redirect' );
		};
		add_filter( 'wp_redirect', $redirect_intercept );
		try {
			$banner->hide_one_and_done_notice();
		} catch ( \Exception $e ) {
			$this->assertSame( 'redirect', $e->getMessage() );
		}
		remove_filter( 'wp_redirect', $redirect_intercept );

		$this->assertNotEmpty( get_user_meta( $admin_user, WC_Payments_Admin_Banner::USER_META_ONE_AND_DONE_NOTICE_DISMISSED, true ) );

		delete_user_meta( $admin_user, WC_Payments_Admin_Banner::USER_META_ONE_AND_DONE_NOTICE_DISMISSED );
		unset( $_GET['wcpay-hide-one-and-done-notice'], $_GET['_wcpay_one_and_done_notice_nonce'] );
	}

	public function test_snooze_one_and_done_notice_sets_snoozed_meta_and_redirects(): void {
		$admin_user = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_user );

		$_GET['wcpay-snooze-one-and-done-notice']        = '1';
		$_GET['_wcpay_snooze_one_and_done_notice_nonce'] = wp_create_nonce( 'wcpay_snooze_one_and_done_notice_nonce' );

		$banner             = $this->make_admin_banner_for_notice_test( true, true, false, true, true );
		$redirect_intercept = function () {
			throw new \Exception( 'redirect' );
		};
		add_filter( 'wp_redirect', $redirect_intercept );
		try {
			$banner->snooze_one_and_done_notice();
		} catch ( \Exception $e ) {
			$this->assertSame( 'redirect', $e->getMessage() );
		}
		remove_filter( 'wp_redirect', $redirect_intercept );

		$this->assertNotEmpty( get_user_meta( get_current_user_id(), WC_Payments_Admin_Banner::USER_META_ONE_AND_DONE_NOTICE_SNOOZED, true ) );

		delete_user_meta( get_current_user_id(), WC_Payments_Admin_Banner::USER_META_ONE_AND_DONE_NOTICE_SNOOZED );
		unset( $_GET['wcpay-snooze-one-and-done-notice'], $_GET['_wcpay_snooze_one_and_done_notice_nonce'] );
	}

	public function test_maybe_show_one_and_done_notice_outputs_container_div(): void {
		$this->set_up_one_and_done_global_state();
		$banner = $this->make_admin_banner_for_notice_test( true, true, false, true, true );

		ob_start();
		$banner->maybe_show_one_and_done_notice();
		$output = ob_get_clean();

		$this->assertStringContainsString( '<div id="wcpay-one-and-done-notice">', $output );

		$this->tear_down_one_and_done_global_state();
	}

	public function test_maybe_show_one_and_done_notice_only_records_impression_once_per_user(): void {
		$this->set_up_one_and_done_global_state();
		$banner = $this->make_admin_banner_for_notice_test( true, true, false, true, true );

		ob_start();
		$banner->maybe_show_one_and_done_notice();
		$banner->maybe_show_one_and_done_notice();
		ob_end_clean();

		$this->assertNotEmpty( get_user_meta( get_current_user_id(), WC_Payments_Admin_Banner::USER_META_ONE_AND_DONE_NOTICE_SHOWN, true ) );

		$this->tear_down_one_and_done_global_state();
	}

	public function test_invalidate_one_and_done_notice_cache_on_order_drops_transient_for_live_wcpay_order(): void {
		$this->set_up_one_and_done_global_state();
		set_transient( WC_Payments_Admin_Banner::TRANSIENT_ONE_AND_DONE_NOTICE_ELIGIBLE, '1', HOUR_IN_SECONDS );

		$order = wc_create_order();
		$order->set_payment_method( 'woocommerce_payments' );
		$order->update_meta_data( WC_Payments_Order_Service::WCPAY_MODE_META_KEY, \WCPay\Constants\Order_Mode::PRODUCTION );
		$order->save();
		$this->one_and_done_order_ids[] = $order->get_id();

		$banner = $this->make_admin_banner_for_notice_test( true, true, false, true, true );
		$banner->invalidate_one_and_done_notice_cache_on_order( $order->get_id() );

		$this->assertFalse( get_transient( WC_Payments_Admin_Banner::TRANSIENT_ONE_AND_DONE_NOTICE_ELIGIBLE ) );

		$this->tear_down_one_and_done_global_state();
	}

	public function test_invalidate_one_and_done_notice_cache_on_order_ignores_test_mode_orders(): void {
		$this->set_up_one_and_done_global_state();
		set_transient( WC_Payments_Admin_Banner::TRANSIENT_ONE_AND_DONE_NOTICE_ELIGIBLE, '1', HOUR_IN_SECONDS );

		$order = wc_create_order();
		$order->set_payment_method( 'woocommerce_payments' );
		$order->update_meta_data( WC_Payments_Order_Service::WCPAY_MODE_META_KEY, \WCPay\Constants\Order_Mode::TEST );
		$order->save();
		$this->one_and_done_order_ids[] = $order->get_id();

		$banner = $this->make_admin_banner_for_notice_test( true, true, false, true, true );
		$banner->invalidate_one_and_done_notice_cache_on_order( $order->get_id() );

		$this->assertSame( '1', get_transient( WC_Payments_Admin_Banner::TRANSIENT_ONE_AND_DONE_NOTICE_ELIGIBLE ) );

		$this->tear_down_one_and_done_global_state();
	}

	public function test_invalidate_one_and_done_notice_cache_on_order_ignores_non_wcpay_orders(): void {
		$this->set_up_one_and_done_global_state();
		set_transient( WC_Payments_Admin_Banner::TRANSIENT_ONE_AND_DONE_NOTICE_ELIGIBLE, '1', HOUR_IN_SECONDS );

		$order = wc_create_order();
		$order->set_payment_method( 'cheque' );
		$order->save();
		$this->one_and_done_order_ids[] = $order->get_id();

		$banner = $this->make_admin_banner_for_notice_test( true, true, false, true, true );
		$banner->invalidate_one_and_done_notice_cache_on_order( $order->get_id() );

		$this->assertSame( '1', get_transient( WC_Payments_Admin_Banner::TRANSIENT_ONE_AND_DONE_NOTICE_ELIGIBLE ) );

		$this->tear_down_one_and_done_global_state();
	}

	public function test_init_hooks_registers_one_and_done_sections_hook(): void {
		$_GET['page'] = 'wc-settings';
		$_GET['tab']  = 'general';
		$banner       = $this->make_admin_banner_for_notice_test( true, true, false, true, true );

		$banner->init_hooks();

		$this->assertNotFalse(
			has_action( 'woocommerce_sections_general', [ $banner, 'maybe_show_one_and_done_notice' ] )
		);

		remove_action( 'woocommerce_sections_general', [ $banner, 'maybe_show_one_and_done_notice' ] );
		remove_action( 'woocommerce_sections_general', [ $banner, 'maybe_show_test_to_live_notice' ] );
		unset( $_GET['page'], $_GET['tab'] );
	}
}
