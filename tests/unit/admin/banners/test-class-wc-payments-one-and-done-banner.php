<?php
/**
 * Class WC_Payments_One_And_Done_Banner_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\Constants\Order_Mode;

/**
 * WC_Payments_One_And_Done_Banner unit tests.
 *
 * Constants the subclass owns:
 *   - dismissed user_meta:  wcpay_one_and_done_notice_dismissed_at  (legacy, preserved)
 *   - snoozed user_meta:    wcpay_one_and_done_notice_snoozed_at    (legacy, preserved)
 *   - shown user_meta:      wcpay_one_and_done_notice_shown
 *   - eligibility transient: wcpay_one_and_done_eligible
 *   - permanent flag option: wcpay_one_and_done_permanently_ineligible
 */
class WC_Payments_One_And_Done_Banner_Test extends WCPAY_UnitTestCase {

	/** @var int[] */
	private $created_order_ids = [];

	/** @var int */
	private $admin_user_id;

	public function set_up(): void {
		parent::set_up();
		WC_Payments::mode()->live();
		$this->admin_user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $this->admin_user_id );
	}

	public function tear_down(): void {
		WC_Payments::mode()->live();
		delete_user_meta( get_current_user_id(), 'wcpay_one_and_done_notice_dismissed_at' );
		delete_user_meta( get_current_user_id(), 'wcpay_one_and_done_notice_snoozed_at' );
		delete_user_meta( get_current_user_id(), 'wcpay_one_and_done_notice_shown' );
		delete_transient( 'wcpay_one_and_done_eligible' );
		delete_option( WC_Payments_One_And_Done_Banner::OPTION_PERMANENTLY_INELIGIBLE );

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
		$this->create_wcpay_live_orders( 1, 8 );
		$banner = $this->make_banner();

		$this->assertTrue( $banner->should_show() );
	}

	public function test_should_show_returns_false_when_user_lacks_capability(): void {
		$this->create_wcpay_live_orders( 1, 8 );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'subscriber' ] ) );
		$banner = $this->make_banner();

		$this->assertFalse( $banner->should_show() );
	}

	public function test_should_show_returns_false_when_gateway_not_connected(): void {
		$this->create_wcpay_live_orders( 1, 8 );
		$banner = $this->make_banner( false );

		$this->assertFalse( $banner->should_show() );
	}

	public function test_should_show_returns_false_when_account_invalid(): void {
		$this->create_wcpay_live_orders( 1, 8 );
		$banner = $this->make_banner( true, false );

		$this->assertFalse( $banner->should_show() );
	}

	public function test_should_show_returns_false_for_test_drive_account(): void {
		$this->create_wcpay_live_orders( 1, 8 );
		$banner = $this->make_banner( true, true, true );

		$this->assertFalse( $banner->should_show() );
	}

	public function test_should_show_returns_false_when_payments_not_enabled(): void {
		$this->create_wcpay_live_orders( 1, 8 );
		$banner = $this->make_banner( true, true, false, false );

		$this->assertFalse( $banner->should_show() );
	}

	public function test_should_show_returns_false_when_account_not_live(): void {
		$this->create_wcpay_live_orders( 1, 8 );
		$banner = $this->make_banner( true, true, false, true, false );

		$this->assertFalse( $banner->should_show() );
	}

	public function test_should_show_returns_false_in_test_mode(): void {
		$this->create_wcpay_live_orders( 1, 8 );
		WC_Payments::mode()->test();
		$banner = $this->make_banner();

		$this->assertFalse( $banner->should_show() );
	}

	public function test_should_show_returns_false_in_dev_mode(): void {
		$this->create_wcpay_live_orders( 1, 8 );
		WC_Payments::mode()->dev();
		$banner = $this->make_banner();

		$this->assertFalse( $banner->should_show() );
	}

	public function test_should_show_returns_false_when_no_live_orders(): void {
		$banner = $this->make_banner();

		$this->assertFalse( $banner->should_show() );
	}

	public function test_should_show_returns_false_and_sets_permanent_flag_with_two_live_orders(): void {
		$this->create_wcpay_live_orders( 2, 8 );
		$banner = $this->make_banner();

		$this->assertFalse( $banner->should_show() );
		$this->assertSame(
			'1',
			get_option( WC_Payments_One_And_Done_Banner::OPTION_PERMANENTLY_INELIGIBLE ),
			'≥2 live WCPay orders is irreversible — flag must be set.'
		);
	}

	public function test_should_show_returns_false_and_sets_permanent_flag_with_one_wcpay_and_one_non_wcpay(): void {
		$this->create_wcpay_live_orders( 1, 8 );
		$this->create_order_with_payment_method( 'cheque' );
		$banner = $this->make_banner();

		$this->assertFalse( $banner->should_show() );
		$this->assertSame(
			'1',
			get_option( WC_Payments_One_And_Done_Banner::OPTION_PERMANENTLY_INELIGIBLE ),
			'Non-WCPay order present — also irreversible, flag must be set.'
		);
	}

	public function test_should_show_returns_false_when_only_order_is_non_wcpay(): void {
		$this->create_order_with_payment_method( 'cheque' );
		$banner = $this->make_banner();

		$this->assertFalse( $banner->should_show() );
	}

	public function test_should_show_returns_false_when_first_order_too_recent(): void {
		$this->create_wcpay_live_orders( 1, 3 );
		$banner = $this->make_banner();

		$this->assertFalse( $banner->should_show() );
	}

	public function test_should_show_ignores_test_mode_orders(): void {
		$this->create_wcpay_live_orders( 1, 8 );
		$this->create_wcpay_order( Order_Mode::TEST );
		$banner = $this->make_banner();

		$this->assertTrue( $banner->should_show() );
	}

	public function test_should_show_eligible_even_with_many_test_mode_orders(): void {
		// Regression: previously a 20-row PHP scan could be saturated by test orders.
		// New query filters server-side on _wcpay_mode = PRODUCTION.
		$this->create_wcpay_live_orders( 1, 8 );
		for ( $i = 0; $i < 25; $i++ ) {
			$this->create_wcpay_order( Order_Mode::TEST );
		}
		$banner = $this->make_banner();

		$this->assertTrue( $banner->should_show() );
		$this->assertFalse(
			(bool) get_option( WC_Payments_One_And_Done_Banner::OPTION_PERMANENTLY_INELIGIBLE ),
			'Test-mode orders alone must not set the permanent ineligibility flag.'
		);
	}

	// ---- should_show: dismiss / snooze gating --------------------------------

	public function test_should_show_returns_false_when_dismissed(): void {
		$this->create_wcpay_live_orders( 1, 8 );
		update_user_meta( $this->admin_user_id, 'wcpay_one_and_done_notice_dismissed_at', time() );
		$banner = $this->make_banner();

		$this->assertFalse( $banner->should_show() );
	}

	public function test_should_show_returns_false_when_snoozed(): void {
		$this->create_wcpay_live_orders( 1, 8 );
		update_user_meta( $this->admin_user_id, 'wcpay_one_and_done_notice_snoozed_at', time() );
		$banner = $this->make_banner();

		$this->assertFalse( $banner->should_show() );
	}

	public function test_should_show_returns_true_when_snooze_expired(): void {
		$this->create_wcpay_live_orders( 1, 8 );
		update_user_meta( $this->admin_user_id, 'wcpay_one_and_done_notice_snoozed_at', time() - 8 * DAY_IN_SECONDS );
		$banner = $this->make_banner();

		$this->assertTrue( $banner->should_show() );
	}

	// ---- should_show: permanent flag short-circuits --------------------------

	public function test_permanent_ineligible_flag_short_circuits_eligibility(): void {
		$this->create_wcpay_live_orders( 1, 8 );
		update_option( WC_Payments_One_And_Done_Banner::OPTION_PERMANENTLY_INELIGIBLE, '1' );
		$banner = $this->make_banner();

		$this->assertFalse( $banner->should_show() );
	}

	public function test_permanent_flag_not_set_for_reversible_disqualifiers(): void {
		$this->create_wcpay_live_orders( 1, 8 );
		WC_Payments::mode()->dev();
		$banner = $this->make_banner();

		$this->assertFalse( $banner->should_show() );
		$this->assertFalse(
			(bool) get_option( WC_Payments_One_And_Done_Banner::OPTION_PERMANENTLY_INELIGIBLE ),
			'Dev mode is reversible — permanent flag must not be set.'
		);
	}

	public function test_permanent_flag_not_set_for_recent_first_order(): void {
		$this->create_wcpay_live_orders( 1, 3 );
		$banner = $this->make_banner();

		$this->assertFalse( $banner->should_show() );
		$this->assertFalse(
			(bool) get_option( WC_Payments_One_And_Done_Banner::OPTION_PERMANENTLY_INELIGIBLE ),
			'Order age <threshold is reversible — permanent flag must not be set.'
		);
	}

	public function test_should_show_memoizes_expensive_check(): void {
		$this->create_wcpay_live_orders( 1, 8 );

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

		$banner = new WC_Payments_One_And_Done_Banner( $mock_gateway, $mock_account );

		$banner->should_show();
		$banner->should_show();
		$banner->should_show();
	}

	// ---- maybe_show ----------------------------------------------------------

	public function test_maybe_show_outputs_mount_div_when_eligible(): void {
		$this->create_wcpay_live_orders( 1, 8 );
		$banner = $this->make_banner();

		ob_start();
		$banner->maybe_show();
		$this->assertStringContainsString( '<div id="wcpay-one-and-done-notice">', ob_get_clean() );
	}

	public function test_maybe_show_records_impression_once_per_user(): void {
		$this->create_wcpay_live_orders( 1, 8 );
		$banner = $this->make_banner();

		ob_start();
		$banner->maybe_show();
		$banner->maybe_show();
		ob_end_clean();

		$this->assertNotEmpty( get_user_meta( $this->admin_user_id, 'wcpay_one_and_done_notice_shown', true ) );
	}

	// ---- hide_notice / snooze_notice / handle_cta ----------------------------

	public function test_hide_notice_writes_dismissed_meta_and_redirects(): void {
		$_GET['wcpay-hide-one-and-done-notice']   = '1';
		$_GET['_wcpay_one_and_done_notice_nonce'] = wp_create_nonce( 'wcpay_hide_one_and_done_notice_nonce' );

		$this->assert_handler_redirects( fn() => $this->make_banner()->hide_notice() );

		$this->assertNotEmpty( get_user_meta( $this->admin_user_id, 'wcpay_one_and_done_notice_dismissed_at', true ) );

		unset( $_GET['wcpay-hide-one-and-done-notice'], $_GET['_wcpay_one_and_done_notice_nonce'] );
	}

	public function test_snooze_notice_writes_snoozed_meta_and_redirects(): void {
		$_GET['wcpay-snooze-one-and-done-notice']        = '1';
		$_GET['_wcpay_snooze_one_and_done_notice_nonce'] = wp_create_nonce( 'wcpay_snooze_one_and_done_notice_nonce' );

		$this->assert_handler_redirects( fn() => $this->make_banner()->snooze_notice() );

		$this->assertNotEmpty( get_user_meta( $this->admin_user_id, 'wcpay_one_and_done_notice_snoozed_at', true ) );

		unset( $_GET['wcpay-snooze-one-and-done-notice'], $_GET['_wcpay_snooze_one_and_done_notice_nonce'] );
	}

	public function test_handle_cta_redirects_to_marketing_and_suppresses_banner(): void {
		$_GET['wcpay-one-and-done-cta']        = '1';
		$_GET['_wcpay_one_and_done_cta_nonce'] = wp_create_nonce( 'wcpay_one_and_done_cta_nonce' );

		$captured_url       = null;
		$redirect_intercept = function ( $location ) use ( &$captured_url ) {
			$captured_url = $location;
			throw new \Exception( 'redirect' );
		};
		add_filter( 'wp_redirect', $redirect_intercept );
		try {
			$this->make_banner()->handle_cta();
		} catch ( \Exception $e ) {
			$this->assertSame( 'redirect', $e->getMessage() );
		}
		remove_filter( 'wp_redirect', $redirect_intercept );

		$this->assertStringContainsString( 'page=wc-admin', $captured_url );
		$this->assertStringContainsString( 'path=/marketing', $captured_url );
		$this->assertNotEmpty( get_user_meta( $this->admin_user_id, 'wcpay_one_and_done_notice_dismissed_at', true ) );

		unset( $_GET['wcpay-one-and-done-cta'], $_GET['_wcpay_one_and_done_cta_nonce'] );
	}

	// ---- invalidate_cache_on_order -------------------------------------------

	public function test_invalidate_cache_drops_transient_for_live_wcpay_order(): void {
		set_transient( 'wcpay_one_and_done_eligible', '1', HOUR_IN_SECONDS );
		$order_id = $this->create_wcpay_order( Order_Mode::PRODUCTION );

		$this->make_banner()->invalidate_cache_on_order( $order_id );

		$this->assertFalse( get_transient( 'wcpay_one_and_done_eligible' ) );
	}

	public function test_invalidate_cache_ignores_test_mode_wcpay_orders(): void {
		set_transient( 'wcpay_one_and_done_eligible', '1', HOUR_IN_SECONDS );
		$order_id = $this->create_wcpay_order( Order_Mode::TEST );

		$this->make_banner()->invalidate_cache_on_order( $order_id );

		$this->assertSame( '1', get_transient( 'wcpay_one_and_done_eligible' ) );
	}

	public function test_invalidate_cache_drops_transient_for_non_wcpay_orders(): void {
		// Non-WCPay orders count toward the "one real-customer order" predicate,
		// so they must invalidate the cache.
		set_transient( 'wcpay_one_and_done_eligible', '1', HOUR_IN_SECONDS );
		$order_id = $this->create_order_with_payment_method( 'cheque' );

		$this->make_banner()->invalidate_cache_on_order( $order_id );

		$this->assertFalse( get_transient( 'wcpay_one_and_done_eligible' ) );
	}

	// ---- init_hooks / init_global_hooks --------------------------------------

	public function test_init_global_hooks_registers_order_completion_listeners(): void {
		$banner = $this->make_banner();

		$banner->init_global_hooks();

		$this->assertNotFalse( has_action( 'woocommerce_payment_complete', [ $banner, 'invalidate_cache_on_order' ] ) );
		$this->assertNotFalse( has_action( 'woocommerce_order_status_completed', [ $banner, 'invalidate_cache_on_order' ] ) );
		$this->assertNotFalse( has_action( 'woocommerce_order_status_processing', [ $banner, 'invalidate_cache_on_order' ] ) );

		remove_action( 'woocommerce_payment_complete', [ $banner, 'invalidate_cache_on_order' ] );
		remove_action( 'woocommerce_order_status_completed', [ $banner, 'invalidate_cache_on_order' ] );
		remove_action( 'woocommerce_order_status_processing', [ $banner, 'invalidate_cache_on_order' ] );
	}

	public function test_init_hooks_does_not_register_order_completion_listeners(): void {
		// Guards against regressing the global/admin hook split. Order-completion
		// hooks must be registered by init_global_hooks(), not init_hooks().
		$banner = $this->make_banner();

		$banner->init_hooks();

		$this->assertFalse( has_action( 'woocommerce_payment_complete', [ $banner, 'invalidate_cache_on_order' ] ) );
		$this->assertFalse( has_action( 'woocommerce_order_status_completed', [ $banner, 'invalidate_cache_on_order' ] ) );
		$this->assertFalse( has_action( 'woocommerce_order_status_processing', [ $banner, 'invalidate_cache_on_order' ] ) );

		remove_action( 'admin_init', [ $banner, 'hide_notice' ] );
		remove_action( 'admin_init', [ $banner, 'snooze_notice' ] );
		remove_action( 'admin_init', [ $banner, 'handle_cta' ] );
		remove_action( 'admin_enqueue_scripts', [ $banner, 'register_script' ], 9 );
		remove_action( 'admin_enqueue_scripts', [ $banner, 'enqueue_script' ] );
	}

	public function test_init_hooks_registers_sections_hook_on_wc_settings(): void {
		$_GET['page'] = 'wc-settings';
		$_GET['tab']  = 'general';
		$banner       = $this->make_banner();

		$banner->init_hooks();

		$this->assertNotFalse( has_action( 'woocommerce_sections_general', [ $banner, 'maybe_show' ] ) );

		remove_action( 'woocommerce_sections_general', [ $banner, 'maybe_show' ] );
		remove_action( 'admin_init', [ $banner, 'hide_notice' ] );
		remove_action( 'admin_init', [ $banner, 'snooze_notice' ] );
		remove_action( 'admin_init', [ $banner, 'handle_cta' ] );
		remove_action( 'admin_enqueue_scripts', [ $banner, 'register_script' ], 9 );
		remove_action( 'admin_enqueue_scripts', [ $banner, 'enqueue_script' ] );
		unset( $_GET['page'], $_GET['tab'] );
	}

	// ---- Helpers --------------------------------------------------------------

	/**
	 * Builds a banner instance with mocked gateway/account. Defaults satisfy
	 * every eligibility branch except the order-history ones, which are driven
	 * by real wc_create_order() calls in the test body.
	 *
	 * @param bool $is_connected     Whether the gateway reports as connected.
	 * @param bool $is_account_valid Whether the Stripe account reports as valid.
	 * @param bool $is_test_drive    Whether the account is a test-drive account.
	 * @param bool $payments_enabled Whether payments are enabled on the account.
	 * @param bool $is_live          Whether the account is live.
	 * @return WC_Payments_One_And_Done_Banner
	 */
	private function make_banner(
		bool $is_connected = true,
		bool $is_account_valid = true,
		bool $is_test_drive = false,
		bool $payments_enabled = true,
		bool $is_live = true
	): WC_Payments_One_And_Done_Banner {
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

		return new WC_Payments_One_And_Done_Banner( $mock_gateway, $mock_account );
	}

	/**
	 * Creates $count completed live-mode WooPayments orders, the first of
	 * which is exactly $first_order_age_days old.
	 *
	 * @param int $count                Number of orders to create.
	 * @param int $first_order_age_days Age (days) of the oldest order.
	 * @return void
	 */
	private function create_wcpay_live_orders( int $count, int $first_order_age_days ): void {
		for ( $i = 0; $i < $count; $i++ ) {
			$order = wc_create_order();
			$order->set_payment_method( 'woocommerce_payments' );
			$order->set_status( 'completed' );
			$order->update_meta_data( WC_Payments_Order_Service::WCPAY_MODE_META_KEY, Order_Mode::PRODUCTION );
			// Stagger ages so the first (oldest) order is exactly $first_order_age_days old.
			$order->set_date_created( time() - ( $first_order_age_days - $i ) * DAY_IN_SECONDS );
			$order->save();
			$this->created_order_ids[] = $order->get_id();
		}
	}

	/**
	 * Creates a single completed WooPayments order in the requested mode.
	 *
	 * @param string $mode Order_Mode constant value.
	 * @return int Order ID.
	 */
	private function create_wcpay_order( string $mode ): int {
		$order = wc_create_order();
		$order->set_payment_method( 'woocommerce_payments' );
		$order->set_status( 'completed' );
		$order->update_meta_data( WC_Payments_Order_Service::WCPAY_MODE_META_KEY, $mode );
		$order->save();
		$this->created_order_ids[] = $order->get_id();
		return $order->get_id();
	}

	/**
	 * Creates a single completed order paid via a non-WooPayments gateway.
	 *
	 * @param string $payment_method Gateway slug.
	 * @return int Order ID.
	 */
	private function create_order_with_payment_method( string $payment_method ): int {
		$order = wc_create_order();
		$order->set_payment_method( $payment_method );
		$order->set_status( 'completed' );
		$order->save();
		$this->created_order_ids[] = $order->get_id();
		return $order->get_id();
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
