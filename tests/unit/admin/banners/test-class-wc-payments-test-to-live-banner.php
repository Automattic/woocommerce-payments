<?php
/**
 * Class WC_Payments_Test_To_Live_Banner_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\Constants\Order_Mode;

/**
 * WC_Payments_Test_To_Live_Banner unit tests.
 *
 * Keys this banner owns (all slug-derived from `'test_to_live'`):
 *   - dismissed user_meta:   wcpay_test_to_live_notice_dismissed
 *   - snoozed user_meta:     wcpay_test_to_live_notice_snoozed
 *   - shown user_meta:       wcpay_test_to_live_notice_shown
 *   - eligibility transient: wcpay_test_to_live_eligible
 */
class WC_Payments_Test_To_Live_Banner_Test extends WCPAY_UnitTestCase {

	/** @var int[] */
	private $created_order_ids = [];

	/** @var int */
	private $admin_user_id;

	public function set_up(): void {
		parent::set_up();
		// live() is the only Mode method that clears dev_mode. Calling test()
		// alone leaves dev_mode set if a prior test called dev() without a
		// full live() reset.
		WC_Payments::mode()->live();
		WC_Payments::mode()->test();
		$this->admin_user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $this->admin_user_id );
		// Default: eight days in test mode — past the 7-day eligibility threshold.
		update_option( WC_Payments_Onboarding_Service::TEST_MODE_ENABLED_DATE_OPTION, time() - 8 * DAY_IN_SECONDS );
	}

	public function tear_down(): void {
		WC_Payments::mode()->live();
		delete_option( WC_Payments_Onboarding_Service::TEST_MODE_ENABLED_DATE_OPTION );
		delete_user_meta( get_current_user_id(), 'wcpay_test_to_live_notice_dismissed' );
		delete_user_meta( get_current_user_id(), 'wcpay_test_to_live_notice_snoozed' );
		delete_user_meta( get_current_user_id(), 'wcpay_test_to_live_notice_shown' );
		delete_transient( 'wcpay_test_to_live_eligible' );

		foreach ( $this->created_order_ids as $order_id ) {
			$order = wc_get_order( $order_id );
			if ( $order ) {
				$order->delete( true );
			}
		}
		$this->created_order_ids = [];

		parent::tear_down();
	}

	// ---- should_show: eligibility predicate -----------------------------------

	public function test_should_show_returns_true_when_all_conditions_met(): void {
		$this->create_wcpay_test_order();
		$banner = $this->make_banner();

		$this->assertTrue( $banner->should_show() );
	}

	public function test_should_show_returns_false_when_user_lacks_capability(): void {
		$this->create_wcpay_test_order();
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'subscriber' ] ) );
		$banner = $this->make_banner();

		$this->assertFalse( $banner->should_show() );
	}

	public function test_should_show_returns_false_when_gateway_not_connected(): void {
		$this->create_wcpay_test_order();
		$banner = $this->make_banner( false );

		$this->assertFalse( $banner->should_show() );
	}

	public function test_should_show_returns_false_when_account_invalid(): void {
		$this->create_wcpay_test_order();
		$banner = $this->make_banner( true, false );

		$this->assertFalse( $banner->should_show() );
	}

	public function test_should_show_returns_false_for_test_drive_account(): void {
		$this->create_wcpay_test_order();
		$banner = $this->make_banner( true, true, true );

		$this->assertFalse( $banner->should_show() );
	}

	public function test_should_show_returns_false_when_payments_not_enabled(): void {
		$this->create_wcpay_test_order();
		$banner = $this->make_banner( true, true, false, false );

		$this->assertFalse( $banner->should_show() );
	}

	public function test_should_show_returns_false_when_not_in_test_mode(): void {
		$this->create_wcpay_test_order();
		WC_Payments::mode()->live();
		$banner = $this->make_banner();

		$this->assertFalse( $banner->should_show() );
	}

	public function test_should_show_returns_false_in_dev_mode(): void {
		$this->create_wcpay_test_order();
		WC_Payments::mode()->dev();
		$banner = $this->make_banner();

		$this->assertFalse( $banner->should_show() );
	}

	public function test_should_show_returns_false_when_threshold_not_reached(): void {
		$this->create_wcpay_test_order();
		update_option( WC_Payments_Onboarding_Service::TEST_MODE_ENABLED_DATE_OPTION, time() - 3 * DAY_IN_SECONDS );
		$banner = $this->make_banner();

		$this->assertFalse( $banner->should_show() );
	}

	public function test_should_show_returns_false_when_no_enabled_date(): void {
		$this->create_wcpay_test_order();
		delete_option( WC_Payments_Onboarding_Service::TEST_MODE_ENABLED_DATE_OPTION );
		$banner = $this->make_banner();

		$this->assertFalse( $banner->should_show() );
	}

	public function test_should_show_returns_false_when_no_wcpay_orders(): void {
		$banner = $this->make_banner();

		$this->assertFalse( $banner->should_show() );
	}

	// ---- should_show: dismiss / snooze gating --------------------------------

	public function test_should_show_returns_false_when_dismissed(): void {
		$this->create_wcpay_test_order();
		update_user_meta( $this->admin_user_id, 'wcpay_test_to_live_notice_dismissed', time() );
		$banner = $this->make_banner();

		$this->assertFalse( $banner->should_show() );
	}

	public function test_should_show_returns_false_when_snoozed(): void {
		$this->create_wcpay_test_order();
		update_user_meta( $this->admin_user_id, 'wcpay_test_to_live_notice_snoozed', time() );
		$banner = $this->make_banner();

		$this->assertFalse( $banner->should_show() );
	}

	public function test_should_show_returns_true_when_snooze_expired(): void {
		$this->create_wcpay_test_order();
		update_user_meta( $this->admin_user_id, 'wcpay_test_to_live_notice_snoozed', time() - 8 * DAY_IN_SECONDS );
		$banner = $this->make_banner();

		$this->assertTrue( $banner->should_show() );
	}

	public function test_should_show_memoizes_expensive_check(): void {
		$this->create_wcpay_test_order();

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

		$banner = new WC_Payments_Test_To_Live_Banner( $mock_gateway, $mock_account );

		$banner->should_show();
		$banner->should_show();
	}

	// ---- maybe_show ----------------------------------------------------------

	public function test_maybe_show_outputs_mount_div_when_eligible(): void {
		$this->create_wcpay_test_order();
		$banner = $this->make_banner();

		ob_start();
		$banner->maybe_show();
		$this->assertStringContainsString( '<div id="wcpay-test-to-live-notice">', ob_get_clean() );
	}

	// ---- hide_notice / snooze_notice -----------------------------------------

	public function test_hide_notice_writes_dismissed_meta_and_redirects(): void {
		$_GET['wcpay-hide-test-to-live-notice']   = '1';
		$_GET['_wcpay_test_to_live_notice_nonce'] = wp_create_nonce( 'wcpay_hide_test_to_live_notice_nonce' );

		$this->assert_handler_redirects( fn() => $this->make_banner()->hide_notice() );

		$this->assertNotEmpty( get_user_meta( $this->admin_user_id, 'wcpay_test_to_live_notice_dismissed', true ) );

		unset( $_GET['wcpay-hide-test-to-live-notice'], $_GET['_wcpay_test_to_live_notice_nonce'] );
	}

	public function test_snooze_notice_writes_snoozed_meta_and_redirects(): void {
		$_GET['wcpay-snooze-test-to-live-notice']        = '1';
		$_GET['_wcpay_snooze_test_to_live_notice_nonce'] = wp_create_nonce( 'wcpay_snooze_test_to_live_notice_nonce' );

		$this->assert_handler_redirects( fn() => $this->make_banner()->snooze_notice() );

		$this->assertNotEmpty( get_user_meta( $this->admin_user_id, 'wcpay_test_to_live_notice_snoozed', true ) );

		unset( $_GET['wcpay-snooze-test-to-live-notice'], $_GET['_wcpay_snooze_test_to_live_notice_nonce'] );
	}

	// ---- handle_cta: two-path logic ------------------------------------------

	public function test_handle_cta_redirects_when_account_is_live(): void {
		$_GET['wcpay-test-to-live-cta']        = '1';
		$_GET['_wcpay_test_to_live_cta_nonce'] = wp_create_nonce( 'wcpay_test_to_live_cta_nonce' );

		$this->assert_handler_redirects( fn() => $this->make_banner( true, true, false, true, true )->handle_cta() );

		unset( $_GET['wcpay-test-to-live-cta'], $_GET['_wcpay_test_to_live_cta_nonce'] );
	}

	public function test_handle_cta_redirects_to_onboarding_when_account_not_live(): void {
		$_GET['wcpay-test-to-live-cta']        = '1';
		$_GET['_wcpay_test_to_live_cta_nonce'] = wp_create_nonce( 'wcpay_test_to_live_cta_nonce' );

		$captured_url = null;
		$intercept    = function ( $location ) use ( &$captured_url ) {
			$captured_url = $location;
			throw new \Exception( 'redirect' );
		};
		add_filter( 'wp_redirect', $intercept );
		try {
			$this->make_banner()->handle_cta();
		} catch ( \Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- Redirect intercept throws to short-circuit exit().
			// Expected.
		}
		remove_filter( 'wp_redirect', $intercept );

		$this->assertStringContainsString( '/woopayments/onboarding', urldecode( $captured_url ) );

		unset( $_GET['wcpay-test-to-live-cta'], $_GET['_wcpay_test_to_live_cta_nonce'] );
	}

	// ---- init_hooks ----------------------------------------------------------

	public function test_init_hooks_registers_sections_hook_on_wc_settings(): void {
		$_GET['page'] = 'wc-settings';
		$_GET['tab']  = 'checkout';
		$banner       = $this->make_banner();

		$banner->init_hooks();

		$this->assertNotFalse( has_action( 'woocommerce_sections_checkout', [ $banner, 'maybe_show' ] ) );

		remove_action( 'woocommerce_sections_checkout', [ $banner, 'maybe_show' ] );
		$this->cleanup_admin_init_hooks( $banner );
		unset( $_GET['page'], $_GET['tab'] );
	}

	public function test_init_hooks_does_not_register_sections_hook_outside_wc_settings(): void {
		$_GET['page'] = 'wc-admin';
		$banner       = $this->make_banner();

		$banner->init_hooks();

		$this->assertFalse( has_action( 'woocommerce_sections_general', [ $banner, 'maybe_show' ] ) );

		$this->cleanup_admin_init_hooks( $banner );
		unset( $_GET['page'] );
	}

	// ---- Helpers --------------------------------------------------------------

	/**
	 * Builds a banner instance with mocked gateway/account. Defaults satisfy
	 * every eligibility branch except mode/test-order/dismiss/snooze, which are
	 * driven by setUp + test-body mutations.
	 *
	 * @param bool $is_connected     Whether the gateway reports as connected.
	 * @param bool $is_account_valid Whether the Stripe account reports as valid.
	 * @param bool $is_test_drive    Whether the account is a test-drive account.
	 * @param bool $payments_enabled Whether payments are enabled on the account.
	 * @param bool $is_live          Whether the account already has a live Stripe identity (drives CTA forking).
	 * @return WC_Payments_Test_To_Live_Banner
	 */
	private function make_banner(
		bool $is_connected = true,
		bool $is_account_valid = true,
		bool $is_test_drive = false,
		bool $payments_enabled = true,
		bool $is_live = false
	): WC_Payments_Test_To_Live_Banner {
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

		return new WC_Payments_Test_To_Live_Banner( $mock_gateway, $mock_account );
	}

	/**
	 * Creates a single completed test-mode WooPayments order — the minimum the
	 * eligibility predicate needs.
	 *
	 * @return int Order ID.
	 */
	private function create_wcpay_test_order(): int {
		$order = wc_create_order();
		$order->set_payment_method( 'woocommerce_payments' );
		$order->set_status( 'completed' );
		$order->update_meta_data( WC_Payments_Order_Service::WCPAY_MODE_META_KEY, Order_Mode::TEST );
		$order->save();
		$this->created_order_ids[] = $order->get_id();
		return $order->get_id();
	}

	/** @return void */
	private function cleanup_admin_init_hooks( WC_Payments_Test_To_Live_Banner $banner ): void {
		remove_action( 'admin_init', [ $banner, 'hide_notice' ] );
		remove_action( 'admin_init', [ $banner, 'snooze_notice' ] );
		remove_action( 'admin_init', [ $banner, 'handle_cta' ] );
		remove_action( 'admin_enqueue_scripts', [ $banner, 'register_script' ], 9 );
		remove_action( 'admin_enqueue_scripts', [ $banner, 'enqueue_script' ] );
	}

	/**
	 * Runs a handler under a wp_redirect intercept and asserts the handler tried to redirect.
	 *
	 * @param callable $invoke The handler invocation.
	 * @return void
	 */
	private function assert_handler_redirects( callable $invoke ): void {
		$redirected = false;
		$intercept  = function () use ( &$redirected ) {
			$redirected = true;
			throw new \Exception( 'redirect' );
		};
		add_filter( 'wp_redirect', $intercept );
		try {
			$invoke();
		} catch ( \Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- Redirect intercept throws to short-circuit exit().
			// Expected.
		}
		remove_filter( 'wp_redirect', $intercept );

		$this->assertTrue( $redirected, 'Expected wp_safe_redirect() to be called.' );
	}
}
