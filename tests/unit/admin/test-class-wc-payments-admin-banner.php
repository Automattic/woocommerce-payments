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

		return new WC_Payments_Admin_Banner( $mock_gateway, $mock_account, $this->make_order_service() );
	}

	/**
	 * Builds a real WC_Payments_Order_Service with a mocked API client. The
	 * banner only calls has_live_sale() on it, which reads options and runs
	 * wc_get_orders — no API surface — so the real implementation runs
	 * against the same options/orders each test already sets up.
	 */
	private function make_order_service(): WC_Payments_Order_Service {
		$mock_api_client = $this->getMockBuilder( WC_Payments_API_Client::class )
			->disableOriginalConstructor()
			->getMock();
		return new WC_Payments_Order_Service( $mock_api_client );
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

		$banner = new WC_Payments_Admin_Banner( $mock_gateway, $mock_account, $this->make_order_service() );

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
		delete_option( WC_Payments_Admin_Banner::OPTION_ONE_AND_DONE_PERMANENTLY_INELIGIBLE );

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

		// ≥2 live WCPay orders is irreversible — flag must be set so subsequent
		// requests short-circuit before re-running the order query.
		$this->assertSame(
			'1',
			get_option( WC_Payments_Admin_Banner::OPTION_ONE_AND_DONE_PERMANENTLY_INELIGIBLE )
		);

		$this->tear_down_one_and_done_global_state();
	}

	public function test_should_show_one_and_done_notice_returns_false_when_one_wcpay_and_one_non_wcpay(): void {
		// 1 WooPayments live order (from the helper) + 1 cheque order = 2 real-
		// customer transactions at the store level. The merchant has activity
		// through other channels, so the banner shouldn't show.
		$this->set_up_one_and_done_global_state();

		$cheque = wc_create_order();
		$cheque->set_payment_method( 'cheque' );
		$cheque->set_status( 'completed' );
		$cheque->save();
		$this->one_and_done_order_ids[] = $cheque->get_id();

		$banner = $this->make_admin_banner_for_notice_test( true, true, false, true, true );

		$this->assertFalse( $banner->should_show_one_and_done_notice() );

		// Non-WCPay order present — also irreversible, flag must be set.
		$this->assertSame(
			'1',
			get_option( WC_Payments_Admin_Banner::OPTION_ONE_AND_DONE_PERMANENTLY_INELIGIBLE )
		);

		$this->tear_down_one_and_done_global_state();
	}

	public function test_should_show_one_and_done_notice_returns_false_when_only_order_is_non_wcpay(): void {
		// 0 WooPayments orders, 1 cheque order. The merchant hasn't used
		// WooPayments yet, so this banner's prompt doesn't apply (a different
		// "use WooPayments" banner would be the right surface, not this one).
		$this->set_up_one_and_done_global_state( 0 );

		$cheque = wc_create_order();
		$cheque->set_payment_method( 'cheque' );
		$cheque->set_status( 'completed' );
		$cheque->save();
		$this->one_and_done_order_ids[] = $cheque->get_id();

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

	public function test_should_show_one_and_done_notice_eligible_with_many_test_mode_orders(): void {
		// L793 regression: with the previous 20-row PHP scan, 19+ test-mode orders
		// could saturate the window and silently exclude an eligible merchant.
		// The new query filters server-side on `_wcpay_mode = PRODUCTION` so test
		// orders are never returned regardless of count.
		$this->set_up_one_and_done_global_state();

		for ( $i = 0; $i < 25; $i++ ) {
			$test_order = wc_create_order();
			$test_order->set_payment_method( 'woocommerce_payments' );
			$test_order->set_status( 'completed' );
			$test_order->update_meta_data( WC_Payments_Order_Service::WCPAY_MODE_META_KEY, \WCPay\Constants\Order_Mode::TEST );
			$test_order->save();
			$this->one_and_done_order_ids[] = $test_order->get_id();
		}

		$banner = $this->make_admin_banner_for_notice_test( true, true, false, true, true );

		$this->assertTrue( $banner->should_show_one_and_done_notice() );
		$this->assertFalse(
			(bool) get_option( WC_Payments_Admin_Banner::OPTION_ONE_AND_DONE_PERMANENTLY_INELIGIBLE ),
			'Test-mode orders alone must not set the permanent ineligibility flag.'
		);

		$this->tear_down_one_and_done_global_state();
	}

	public function test_one_and_done_permanent_ineligible_flag_short_circuits_eligibility_check(): void {
		// Once the flag is set, subsequent calls must short-circuit before reading
		// the transient or running any query. We verify by setting the flag on a
		// store that would otherwise be eligible.
		$this->set_up_one_and_done_global_state();
		update_option( WC_Payments_Admin_Banner::OPTION_ONE_AND_DONE_PERMANENTLY_INELIGIBLE, '1' );

		$banner = $this->make_admin_banner_for_notice_test( true, true, false, true, true );

		$this->assertFalse( $banner->should_show_one_and_done_notice() );

		$this->tear_down_one_and_done_global_state();
	}

	public function test_should_show_one_and_done_notice_does_not_set_permanent_flag_for_reversible_disqualifiers(): void {
		// In dev mode, payments-disabled, account-not-live, etc., the merchant
		// could still recover into the cohort once the underlying state changes.
		// The permanent flag must NOT be set for any of these reversible cases.
		$this->set_up_one_and_done_global_state();
		WC_Payments::mode()->dev();

		$banner = $this->make_admin_banner_for_notice_test( true, true, false, true, true );
		$this->assertFalse( $banner->should_show_one_and_done_notice() );
		$this->assertFalse(
			(bool) get_option( WC_Payments_Admin_Banner::OPTION_ONE_AND_DONE_PERMANENTLY_INELIGIBLE ),
			'Dev mode is reversible — permanent flag must not be set.'
		);

		$this->tear_down_one_and_done_global_state();
	}

	public function test_should_show_one_and_done_notice_does_not_set_permanent_flag_for_recent_first_order(): void {
		// Order age <7 days is reversible (the order ages over time). Permanent
		// flag must not be set when the only disqualifier is age.
		$this->set_up_one_and_done_global_state( 1, 3 );
		$banner = $this->make_admin_banner_for_notice_test( true, true, false, true, true );

		$this->assertFalse( $banner->should_show_one_and_done_notice() );
		$this->assertFalse(
			(bool) get_option( WC_Payments_Admin_Banner::OPTION_ONE_AND_DONE_PERMANENTLY_INELIGIBLE ),
			'Order age <threshold is reversible — permanent flag must not be set.'
		);

		$this->tear_down_one_and_done_global_state();
	}

	public function test_should_show_one_and_done_notice_caches_expensive_checks(): void {
		// Memoization: even when called multiple times in the same request (once
		// from admin_enqueue_scripts and once from the woocommerce_sections_*
		// render hook), the public method must only run the expensive backing
		// computation once.
		$this->set_up_one_and_done_global_state();

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
		$mock_account->method( 'get_is_live' )->willReturn( true );

		$mock_gateway = $this->getMockBuilder( WC_Payment_Gateway_WCPay::class )
			->disableOriginalConstructor()
			->getMock();
		$mock_gateway->method( 'is_connected' )->willReturn( true );

		$banner = new WC_Payments_Admin_Banner( $mock_gateway, $mock_account, $this->make_order_service() );

		$banner->should_show_one_and_done_notice();
		$banner->should_show_one_and_done_notice();
		$banner->should_show_one_and_done_notice();

		$this->tear_down_one_and_done_global_state();
	}

	public function test_handle_one_and_done_notice_cta_redirects_to_marketing_and_suppresses_banner(): void {
		$admin_user = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_user );
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
		$this->assertNotEmpty( get_user_meta( $admin_user, WC_Payments_Admin_Banner::USER_META_ONE_AND_DONE_NOTICE_DISMISSED, true ) );

		delete_user_meta( $admin_user, WC_Payments_Admin_Banner::USER_META_ONE_AND_DONE_NOTICE_DISMISSED );
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

	public function test_invalidate_one_and_done_notice_cache_on_order_drops_transient_for_non_wcpay_orders(): void {
		// Non-WCPay orders count toward the "one real-customer order" predicate,
		// so they must invalidate the cache. A merchant who's already taking
		// cheque/COD/etc. orders is no longer "one and done" at the store level.
		$this->set_up_one_and_done_global_state();
		set_transient( WC_Payments_Admin_Banner::TRANSIENT_ONE_AND_DONE_NOTICE_ELIGIBLE, '1', HOUR_IN_SECONDS );

		$order = wc_create_order();
		$order->set_payment_method( 'cheque' );
		$order->save();
		$this->one_and_done_order_ids[] = $order->get_id();

		$banner = $this->make_admin_banner_for_notice_test( true, true, false, true, true );
		$banner->invalidate_one_and_done_notice_cache_on_order( $order->get_id() );

		$this->assertFalse( get_transient( WC_Payments_Admin_Banner::TRANSIENT_ONE_AND_DONE_NOTICE_ELIGIBLE ) );

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

	public function test_init_global_hooks_registers_order_completion_listeners(): void {
		$banner = $this->make_admin_banner_for_notice_test( true, true, false, true, true );

		$banner->init_global_hooks();

		$this->assertNotFalse(
			has_action( 'woocommerce_payment_complete', [ $banner, 'invalidate_one_and_done_notice_cache_on_order' ] ),
			'woocommerce_payment_complete must register globally so storefront checkout / REST webhook flows clear the eligibility transient.'
		);
		$this->assertNotFalse(
			has_action( 'woocommerce_order_status_completed', [ $banner, 'invalidate_one_and_done_notice_cache_on_order' ] ),
			'woocommerce_order_status_completed must register globally; order status transitions to completed can fire outside admin context.'
		);
		$this->assertNotFalse(
			has_action( 'woocommerce_order_status_processing', [ $banner, 'invalidate_one_and_done_notice_cache_on_order' ] ),
			'woocommerce_order_status_processing must register globally so cheque/COD/bank-transfer orders moving to processing also invalidate the cache.'
		);

		remove_action( 'woocommerce_payment_complete', [ $banner, 'invalidate_one_and_done_notice_cache_on_order' ] );
		remove_action( 'woocommerce_order_status_completed', [ $banner, 'invalidate_one_and_done_notice_cache_on_order' ] );
		remove_action( 'woocommerce_order_status_processing', [ $banner, 'invalidate_one_and_done_notice_cache_on_order' ] );
	}

	public function test_init_hooks_does_not_register_order_completion_listeners(): void {
		// Guards against regressing the global/admin hook split. Order-completion
		// hooks must be registered by init_global_hooks(), not init_hooks().
		$banner = $this->make_admin_banner_for_notice_test( true, true, false, true, true );

		$banner->init_hooks();

		$this->assertFalse(
			has_action( 'woocommerce_payment_complete', [ $banner, 'invalidate_one_and_done_notice_cache_on_order' ] )
		);
		$this->assertFalse(
			has_action( 'woocommerce_order_status_completed', [ $banner, 'invalidate_one_and_done_notice_cache_on_order' ] )
		);
		$this->assertFalse(
			has_action( 'woocommerce_order_status_processing', [ $banner, 'invalidate_one_and_done_notice_cache_on_order' ] )
		);
	}

	// -------------------------------------------------------------------------
	// Post-KYC activation notice helpers
	// -------------------------------------------------------------------------

	/**
	 * Sets global state for post-KYC notice eligibility tests.
	 *
	 * @param int  $days_since_kyc Days to subtract from now when setting the KYC completion date.
	 * @param bool $has_orders     Whether to create a WooPayments order.
	 */
	private function set_up_post_kyc_global_state( int $days_since_kyc = 8, bool $has_orders = false ): void {
		delete_transient( WC_Payments_Account::POST_KYC_ACTIVATION_ELIGIBLE_TRANSIENT );
		delete_option( WC_Payments_Order_Service::HAS_LIVE_SALE_OPTION );
		update_option( WC_Payments_Account::KYC_COMPLETION_DATE_OPTION, time() - $days_since_kyc * DAY_IN_SECONDS );

		WC_Payments::mode()->live();

		$admin_user = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_user );

		if ( $has_orders ) {
			$order = wc_create_order();
			$order->set_payment_method( 'woocommerce_payments' );
			$order->set_status( 'completed' );
			$order->update_meta_data( WC_Payments_Order_Service::WCPAY_MODE_META_KEY, \WCPay\Constants\Order_Mode::PRODUCTION );
			$order->save();
			$this->test_order_id = $order->get_id();
		}
	}

	private function tear_down_post_kyc_global_state(): void {
		WC_Payments::mode()->live();
		delete_option( WC_Payments_Account::KYC_COMPLETION_DATE_OPTION );
		delete_option( WC_Payments_Order_Service::HAS_LIVE_SALE_OPTION );
		delete_transient( WC_Payments_Account::POST_KYC_ACTIVATION_ELIGIBLE_TRANSIENT );

		foreach ( [ 7, 14, 30 ] as $stage ) {
			delete_user_meta( get_current_user_id(), WC_Payments_Admin_Banner::USER_META_POST_KYC_ACTIVATION_PREFIX . $stage . '_dismissed' );
			delete_user_meta( get_current_user_id(), WC_Payments_Admin_Banner::USER_META_POST_KYC_ACTIVATION_PREFIX . $stage . '_shown' );
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
	// get_post_kyc_activation_stage tests
	// -------------------------------------------------------------------------

	public function test_get_post_kyc_activation_stage_returns_null_when_no_date(): void {
		delete_option( WC_Payments_Account::KYC_COMPLETION_DATE_OPTION );
		$banner = $this->make_admin_banner_for_notice_test();

		$this->assertNull( $banner->get_post_kyc_activation_stage() );
	}

	public function test_get_post_kyc_activation_stage_returns_null_before_day_7(): void {
		update_option( WC_Payments_Account::KYC_COMPLETION_DATE_OPTION, time() - 3 * DAY_IN_SECONDS );
		$banner = $this->make_admin_banner_for_notice_test();

		$this->assertNull( $banner->get_post_kyc_activation_stage() );

		delete_option( WC_Payments_Account::KYC_COMPLETION_DATE_OPTION );
	}

	public function test_get_post_kyc_activation_stage_returns_7_between_day_7_and_13(): void {
		update_option( WC_Payments_Account::KYC_COMPLETION_DATE_OPTION, time() - 7 * DAY_IN_SECONDS );
		$banner = $this->make_admin_banner_for_notice_test();

		$this->assertSame( 7, $banner->get_post_kyc_activation_stage() );

		delete_option( WC_Payments_Account::KYC_COMPLETION_DATE_OPTION );
	}

	public function test_get_post_kyc_activation_stage_returns_14_between_day_14_and_29(): void {
		update_option( WC_Payments_Account::KYC_COMPLETION_DATE_OPTION, time() - 14 * DAY_IN_SECONDS );
		$banner = $this->make_admin_banner_for_notice_test();

		$this->assertSame( 14, $banner->get_post_kyc_activation_stage() );

		delete_option( WC_Payments_Account::KYC_COMPLETION_DATE_OPTION );
	}

	public function test_get_post_kyc_activation_stage_returns_30_at_and_after_day_30(): void {
		update_option( WC_Payments_Account::KYC_COMPLETION_DATE_OPTION, time() - 45 * DAY_IN_SECONDS );
		$banner = $this->make_admin_banner_for_notice_test();

		$this->assertSame( 30, $banner->get_post_kyc_activation_stage() );

		delete_option( WC_Payments_Account::KYC_COMPLETION_DATE_OPTION );
	}

	// -------------------------------------------------------------------------
	// should_show_post_kyc_activation_notice tests
	// -------------------------------------------------------------------------

	public function test_should_show_post_kyc_activation_notice_returns_true_when_all_conditions_met(): void {
		$this->set_up_post_kyc_global_state();
		$banner = $this->make_admin_banner_for_notice_test();

		$this->assertTrue( $banner->should_show_post_kyc_activation_notice() );

		$this->tear_down_post_kyc_global_state();
	}

	public function test_should_show_post_kyc_activation_notice_returns_false_when_no_kyc_date(): void {
		$this->set_up_post_kyc_global_state();
		delete_option( WC_Payments_Account::KYC_COMPLETION_DATE_OPTION );
		$banner = $this->make_admin_banner_for_notice_test();

		$this->assertFalse( $banner->should_show_post_kyc_activation_notice() );

		$this->tear_down_post_kyc_global_state();
	}

	public function test_should_show_post_kyc_activation_notice_returns_false_before_day_7(): void {
		$this->set_up_post_kyc_global_state( 3 );
		$banner = $this->make_admin_banner_for_notice_test();

		$this->assertFalse( $banner->should_show_post_kyc_activation_notice() );

		$this->tear_down_post_kyc_global_state();
	}

	public function test_should_show_post_kyc_activation_notice_returns_false_when_stage_dismissed(): void {
		$this->set_up_post_kyc_global_state();
		update_user_meta( get_current_user_id(), WC_Payments_Admin_Banner::USER_META_POST_KYC_ACTIVATION_PREFIX . '7_dismissed', true );
		$banner = $this->make_admin_banner_for_notice_test();

		$this->assertFalse( $banner->should_show_post_kyc_activation_notice() );

		$this->tear_down_post_kyc_global_state();
	}

	public function test_should_show_post_kyc_activation_notice_returns_false_when_user_lacks_capability(): void {
		$this->set_up_post_kyc_global_state();
		$subscriber = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $subscriber );
		$banner = $this->make_admin_banner_for_notice_test();

		$this->assertFalse( $banner->should_show_post_kyc_activation_notice() );

		$this->tear_down_post_kyc_global_state();
	}

	public function test_should_show_post_kyc_activation_notice_returns_false_when_not_connected(): void {
		$this->set_up_post_kyc_global_state();
		$banner = $this->make_admin_banner_for_notice_test( false );

		$this->assertFalse( $banner->should_show_post_kyc_activation_notice() );

		$this->tear_down_post_kyc_global_state();
	}

	public function test_should_show_post_kyc_activation_notice_returns_false_for_test_drive(): void {
		$this->set_up_post_kyc_global_state();
		$banner = $this->make_admin_banner_for_notice_test( true, true, true );

		$this->assertFalse( $banner->should_show_post_kyc_activation_notice() );

		$this->tear_down_post_kyc_global_state();
	}

	public function test_should_show_post_kyc_activation_notice_returns_false_when_payments_not_enabled(): void {
		$this->set_up_post_kyc_global_state();
		$banner = $this->make_admin_banner_for_notice_test( true, true, false, false );

		$this->assertFalse( $banner->should_show_post_kyc_activation_notice() );

		$this->tear_down_post_kyc_global_state();
	}

	public function test_should_show_post_kyc_activation_notice_returns_false_in_test_mode(): void {
		$this->set_up_post_kyc_global_state();
		WC_Payments::mode()->test();
		$banner = $this->make_admin_banner_for_notice_test();

		$this->assertFalse( $banner->should_show_post_kyc_activation_notice() );

		$this->tear_down_post_kyc_global_state();
	}

	public function test_should_show_post_kyc_activation_notice_returns_false_when_merchant_has_orders(): void {
		$this->set_up_post_kyc_global_state( 8, true );
		$banner = $this->make_admin_banner_for_notice_test();

		$this->assertFalse( $banner->should_show_post_kyc_activation_notice() );

		$this->tear_down_post_kyc_global_state();
	}

	public function test_should_show_post_kyc_activation_notice_returns_true_when_merchant_only_has_test_orders(): void {
		$this->set_up_post_kyc_global_state();

		$order = wc_create_order();
		$order->set_payment_method( 'woocommerce_payments' );
		$order->set_status( 'completed' );
		$order->update_meta_data( WC_Payments_Order_Service::WCPAY_MODE_META_KEY, \WCPay\Constants\Order_Mode::TEST );
		$order->save();
		$this->test_order_id = $order->get_id();

		$banner = $this->make_admin_banner_for_notice_test();

		$this->assertTrue( $banner->should_show_post_kyc_activation_notice() );

		$this->tear_down_post_kyc_global_state();
	}

	public function test_should_show_post_kyc_activation_notice_memoizes_per_request(): void {
		$this->set_up_post_kyc_global_state();
		$banner = $this->make_admin_banner_for_notice_test();

		$dismissal_key   = WC_Payments_Admin_Banner::USER_META_POST_KYC_ACTIVATION_PREFIX . '7_dismissed';
		$dismissal_reads = 0;
		$counter         = function ( $value, $object_id, $meta_key ) use ( &$dismissal_reads, $dismissal_key ) {
			if ( $meta_key === $dismissal_key ) {
				++$dismissal_reads;
			}
			return $value;
		};
		add_filter( 'get_user_metadata', $counter, 10, 3 );

		$banner->should_show_post_kyc_activation_notice();
		$banner->should_show_post_kyc_activation_notice();

		remove_filter( 'get_user_metadata', $counter, 10 );

		$this->assertSame( 1, $dismissal_reads );

		$this->tear_down_post_kyc_global_state();
	}

	public function test_should_show_post_kyc_activation_notice_short_circuits_when_first_live_sale_recorded(): void {
		$this->set_up_post_kyc_global_state();
		update_option( WC_Payments_Order_Service::HAS_LIVE_SALE_OPTION, '1' );

		$banner = $this->make_admin_banner_for_notice_test();

		$transient_reads = 0;
		$counter         = function ( $value ) use ( &$transient_reads ) {
			++$transient_reads;
			return $value;
		};
		add_filter( 'pre_transient_' . WC_Payments_Account::POST_KYC_ACTIVATION_ELIGIBLE_TRANSIENT, $counter );

		$this->assertFalse( $banner->should_show_post_kyc_activation_notice() );

		remove_filter( 'pre_transient_' . WC_Payments_Account::POST_KYC_ACTIVATION_ELIGIBLE_TRANSIENT, $counter );

		$this->assertSame( 0, $transient_reads );

		$this->tear_down_post_kyc_global_state();
	}

	// -------------------------------------------------------------------------
	// hide_post_kyc_activation_notice tests
	// -------------------------------------------------------------------------

	public function test_hide_post_kyc_activation_notice_sets_dismissed_meta_and_tracks_event(): void {
		$this->set_up_post_kyc_global_state();

		$_GET['wcpay-hide-post-kyc-activation-notice']   = '1';
		$_GET['_wcpay_post_kyc_activation_notice_nonce'] = wp_create_nonce( 'wcpay_hide_post_kyc_activation_notice_nonce' );
		$_GET['wcpay_stage']                             = '7';

		$banner             = $this->make_admin_banner_for_notice_test();
		$redirect_intercept = function () {
			throw new \Exception( 'redirect' );
		};
		add_filter( 'wp_redirect', $redirect_intercept );
		try {
			$banner->hide_post_kyc_activation_notice();
		} catch ( \Exception $e ) {
			$this->assertSame( 'redirect', $e->getMessage() );
		}
		remove_filter( 'wp_redirect', $redirect_intercept );

		$dismissed = get_user_meta( get_current_user_id(), WC_Payments_Admin_Banner::USER_META_POST_KYC_ACTIVATION_PREFIX . '7_dismissed', true );
		$this->assertNotEmpty( $dismissed );

		unset( $_GET['wcpay-hide-post-kyc-activation-notice'], $_GET['_wcpay_post_kyc_activation_notice_nonce'], $_GET['wcpay_stage'] );

		$this->tear_down_post_kyc_global_state();
	}

	public function test_hide_post_kyc_activation_notice_records_url_stage_when_active_stage_has_advanced(): void {
		$this->set_up_post_kyc_global_state();

		// Banner was rendered at stage 7 but the page sat open until stage 14 became active.
		update_option( WC_Payments_Account::KYC_COMPLETION_DATE_OPTION, time() - 14 * DAY_IN_SECONDS );

		$_GET['wcpay-hide-post-kyc-activation-notice']   = '1';
		$_GET['_wcpay_post_kyc_activation_notice_nonce'] = wp_create_nonce( 'wcpay_hide_post_kyc_activation_notice_nonce' );
		$_GET['wcpay_stage']                             = '7';

		$banner             = $this->make_admin_banner_for_notice_test();
		$redirect_intercept = function () {
			throw new \Exception( 'redirect' );
		};
		add_filter( 'wp_redirect', $redirect_intercept );
		try {
			$banner->hide_post_kyc_activation_notice();
		} catch ( \Exception $e ) {
			$this->assertSame( 'redirect', $e->getMessage() );
		}
		remove_filter( 'wp_redirect', $redirect_intercept );

		$this->assertNotEmpty(
			get_user_meta( get_current_user_id(), WC_Payments_Admin_Banner::USER_META_POST_KYC_ACTIVATION_PREFIX . '7_dismissed', true )
		);
		$this->assertEmpty(
			get_user_meta( get_current_user_id(), WC_Payments_Admin_Banner::USER_META_POST_KYC_ACTIVATION_PREFIX . '14_dismissed', true )
		);

		unset( $_GET['wcpay-hide-post-kyc-activation-notice'], $_GET['_wcpay_post_kyc_activation_notice_nonce'], $_GET['wcpay_stage'] );
		$this->tear_down_post_kyc_global_state();
	}

	public function test_hide_post_kyc_activation_notice_ignores_invalid_stage(): void {
		$this->set_up_post_kyc_global_state();

		$_GET['wcpay-hide-post-kyc-activation-notice']   = '1';
		$_GET['_wcpay_post_kyc_activation_notice_nonce'] = wp_create_nonce( 'wcpay_hide_post_kyc_activation_notice_nonce' );
		$_GET['wcpay_stage']                             = '99';

		$banner = $this->make_admin_banner_for_notice_test();
		$banner->hide_post_kyc_activation_notice();

		foreach ( [ 7, 14, 30, 99 ] as $stage ) {
			$this->assertEmpty(
				get_user_meta( get_current_user_id(), WC_Payments_Admin_Banner::USER_META_POST_KYC_ACTIVATION_PREFIX . $stage . '_dismissed', true )
			);
		}

		unset( $_GET['wcpay-hide-post-kyc-activation-notice'], $_GET['_wcpay_post_kyc_activation_notice_nonce'], $_GET['wcpay_stage'] );
		$this->tear_down_post_kyc_global_state();
	}

	public function test_hide_post_kyc_activation_notice_ignores_missing_stage(): void {
		$this->set_up_post_kyc_global_state();

		$_GET['wcpay-hide-post-kyc-activation-notice']   = '1';
		$_GET['_wcpay_post_kyc_activation_notice_nonce'] = wp_create_nonce( 'wcpay_hide_post_kyc_activation_notice_nonce' );
		unset( $_GET['wcpay_stage'] );

		$banner = $this->make_admin_banner_for_notice_test();
		$banner->hide_post_kyc_activation_notice();

		foreach ( [ 7, 14, 30 ] as $stage ) {
			$this->assertEmpty(
				get_user_meta( get_current_user_id(), WC_Payments_Admin_Banner::USER_META_POST_KYC_ACTIVATION_PREFIX . $stage . '_dismissed', true )
			);
		}

		unset( $_GET['wcpay-hide-post-kyc-activation-notice'], $_GET['_wcpay_post_kyc_activation_notice_nonce'] );
		$this->tear_down_post_kyc_global_state();
	}

	public function test_hide_post_kyc_activation_notice_ignores_missing_params(): void {
		$banner = $this->make_admin_banner_for_notice_test();
		unset( $_GET['wcpay-hide-post-kyc-activation-notice'] );

		$banner->hide_post_kyc_activation_notice();

		$this->assertEmpty( get_user_meta( get_current_user_id(), WC_Payments_Admin_Banner::USER_META_POST_KYC_ACTIVATION_PREFIX . '7_dismissed', true ) );
	}

	public function test_hide_post_kyc_activation_notice_ignores_invalid_nonce(): void {
		$this->set_up_post_kyc_global_state();

		$_GET['wcpay-hide-post-kyc-activation-notice']   = '1';
		$_GET['_wcpay_post_kyc_activation_notice_nonce'] = 'bad-nonce';

		$banner = $this->make_admin_banner_for_notice_test();
		$banner->hide_post_kyc_activation_notice();

		$this->assertEmpty( get_user_meta( get_current_user_id(), WC_Payments_Admin_Banner::USER_META_POST_KYC_ACTIVATION_PREFIX . '7_dismissed', true ) );

		unset( $_GET['wcpay-hide-post-kyc-activation-notice'], $_GET['_wcpay_post_kyc_activation_notice_nonce'] );
		$this->tear_down_post_kyc_global_state();
	}

	public function test_handle_post_kyc_activation_notice_cta_records_dismissal_and_redirects_to_marketing(): void {
		$this->set_up_post_kyc_global_state();

		$_GET['wcpay-post-kyc-activation-cta']        = '1';
		$_GET['_wcpay_post_kyc_activation_cta_nonce'] = wp_create_nonce( 'wcpay_post_kyc_activation_cta_nonce' );
		$_GET['wcpay_stage']                          = '7';

		$banner = $this->make_admin_banner_for_notice_test();

		$redirect_target    = null;
		$redirect_intercept = function ( $location ) use ( &$redirect_target ) {
			$redirect_target = $location;
			throw new \Exception( 'redirect' );
		};
		add_filter( 'wp_redirect', $redirect_intercept );
		try {
			$banner->handle_post_kyc_activation_notice_cta();
		} catch ( \Exception $e ) {
			$this->assertSame( 'redirect', $e->getMessage() );
		}
		remove_filter( 'wp_redirect', $redirect_intercept );

		$this->assertNotEmpty(
			get_user_meta( get_current_user_id(), WC_Payments_Admin_Banner::USER_META_POST_KYC_ACTIVATION_PREFIX . '7_dismissed', true )
		);
		$this->assertStringContainsString( 'page=wc-admin', (string) $redirect_target );
		$this->assertStringContainsString( 'path=/marketing', (string) $redirect_target );

		unset( $_GET['wcpay-post-kyc-activation-cta'], $_GET['_wcpay_post_kyc_activation_cta_nonce'], $_GET['wcpay_stage'] );
		$this->tear_down_post_kyc_global_state();
	}

	public function test_handle_post_kyc_activation_notice_cta_ignores_invalid_nonce(): void {
		$this->set_up_post_kyc_global_state();

		$_GET['wcpay-post-kyc-activation-cta']        = '1';
		$_GET['_wcpay_post_kyc_activation_cta_nonce'] = 'bad-nonce';
		$_GET['wcpay_stage']                          = '7';

		$banner = $this->make_admin_banner_for_notice_test();
		$banner->handle_post_kyc_activation_notice_cta();

		$this->assertEmpty( get_user_meta( get_current_user_id(), WC_Payments_Admin_Banner::USER_META_POST_KYC_ACTIVATION_PREFIX . '7_dismissed', true ) );

		unset( $_GET['wcpay-post-kyc-activation-cta'], $_GET['_wcpay_post_kyc_activation_cta_nonce'], $_GET['wcpay_stage'] );
		$this->tear_down_post_kyc_global_state();
	}

	public function test_handle_post_kyc_activation_notice_cta_ignores_invalid_stage(): void {
		$this->set_up_post_kyc_global_state();

		$_GET['wcpay-post-kyc-activation-cta']        = '1';
		$_GET['_wcpay_post_kyc_activation_cta_nonce'] = wp_create_nonce( 'wcpay_post_kyc_activation_cta_nonce' );
		$_GET['wcpay_stage']                          = '99';

		$banner = $this->make_admin_banner_for_notice_test();
		$banner->handle_post_kyc_activation_notice_cta();

		foreach ( [ 7, 14, 30, 99 ] as $stage ) {
			$this->assertEmpty(
				get_user_meta( get_current_user_id(), WC_Payments_Admin_Banner::USER_META_POST_KYC_ACTIVATION_PREFIX . $stage . '_dismissed', true )
			);
		}

		unset( $_GET['wcpay-post-kyc-activation-cta'], $_GET['_wcpay_post_kyc_activation_cta_nonce'], $_GET['wcpay_stage'] );
		$this->tear_down_post_kyc_global_state();
	}

	// -------------------------------------------------------------------------
	// maybe_show_post_kyc_activation_notice tests
	// -------------------------------------------------------------------------

	public function test_maybe_show_post_kyc_activation_notice_outputs_container_div(): void {
		$this->set_up_post_kyc_global_state();
		$banner = $this->make_admin_banner_for_notice_test();

		ob_start();
		$banner->maybe_show_post_kyc_activation_notice();
		$output = ob_get_clean();

		$this->assertStringContainsString( '<div id="wcpay-post-kyc-activation-notice">', $output );

		$this->tear_down_post_kyc_global_state();
	}

	public function test_enqueue_post_kyc_activation_notice_script_tracks_impression_once_per_stage(): void {
		$this->set_up_post_kyc_global_state();
		$banner = $this->make_admin_banner_for_notice_test();

		$banner->enqueue_post_kyc_activation_notice_script();

		$shown_meta = WC_Payments_Admin_Banner::USER_META_POST_KYC_ACTIVATION_PREFIX . '7_shown';
		$this->assertNotEmpty( get_user_meta( get_current_user_id(), $shown_meta, true ) );

		$this->tear_down_post_kyc_global_state();
	}

	public function test_invalidate_post_kyc_activation_notice_cache_deletes_transient(): void {
		set_transient( WC_Payments_Account::POST_KYC_ACTIVATION_ELIGIBLE_TRANSIENT, '1', HOUR_IN_SECONDS );

		$banner = $this->make_admin_banner_for_notice_test();
		$banner->invalidate_post_kyc_activation_notice_cache();

		$this->assertFalse( get_transient( WC_Payments_Account::POST_KYC_ACTIVATION_ELIGIBLE_TRANSIENT ) );
	}
}
