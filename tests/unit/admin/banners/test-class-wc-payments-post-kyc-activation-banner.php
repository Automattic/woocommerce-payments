<?php
/**
 * Class WC_Payments_Post_Kyc_Activation_Banner_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\Constants\Order_Mode;

/**
 * WC_Payments_Post_Kyc_Activation_Banner unit tests.
 *
 * Keys this banner owns:
 *   - dismissed user_meta (per stage): wcpay_post_kyc_activation_{stage}_dismissed
 *   - shown user_meta (per stage):     wcpay_post_kyc_activation_{stage}_shown
 *   - eligibility transient:           wcpay_post_kyc_activation_eligible
 */
class WC_Payments_Post_Kyc_Activation_Banner_Test extends WCPAY_UnitTestCase {

	/** @var int[] */
	private $created_order_ids = [];

	/** @var int */
	private $admin_user_id;

	public function set_up(): void {
		parent::set_up();
		WC_Payments::mode()->live();
		$this->admin_user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $this->admin_user_id );
		// Default: 8 days post-KYC (active stage = 7), no live sale.
		update_option( WC_Payments_Account::KYC_COMPLETION_DATE_OPTION, time() - 8 * DAY_IN_SECONDS );
		delete_option( WC_Payments_Order_Service::HAS_LIVE_SALE_OPTION );
		delete_transient( WC_Payments_Post_Kyc_Activation_Banner::TRANSIENT_ELIGIBLE );
	}

	public function tear_down(): void {
		WC_Payments::mode()->live();
		delete_option( WC_Payments_Account::KYC_COMPLETION_DATE_OPTION );
		delete_option( WC_Payments_Order_Service::HAS_LIVE_SALE_OPTION );
		delete_transient( WC_Payments_Post_Kyc_Activation_Banner::TRANSIENT_ELIGIBLE );

		foreach ( [ 7, 14, 30 ] as $stage ) {
			delete_user_meta( get_current_user_id(), 'wcpay_post_kyc_activation_' . $stage . '_dismissed' );
			delete_user_meta( get_current_user_id(), 'wcpay_post_kyc_activation_' . $stage . '_shown' );
		}

		foreach ( $this->created_order_ids as $order_id ) {
			$order = wc_get_order( $order_id );
			if ( $order ) {
				$order->delete( true );
			}
		}
		$this->created_order_ids = [];

		parent::tear_down();
	}

	// ---- get_current_stage ---------------------------------------------------

	public function test_get_current_stage_returns_null_when_no_kyc_date(): void {
		delete_option( WC_Payments_Account::KYC_COMPLETION_DATE_OPTION );
		$this->assertNull( $this->make_banner()->get_current_stage() );
	}

	public function test_get_current_stage_returns_null_before_day_7(): void {
		update_option( WC_Payments_Account::KYC_COMPLETION_DATE_OPTION, time() - 3 * DAY_IN_SECONDS );
		$this->assertNull( $this->make_banner()->get_current_stage() );
	}

	public function test_get_current_stage_returns_7_between_day_7_and_13(): void {
		update_option( WC_Payments_Account::KYC_COMPLETION_DATE_OPTION, time() - 7 * DAY_IN_SECONDS );
		$this->assertSame( 7, $this->make_banner()->get_current_stage() );
	}

	public function test_get_current_stage_returns_14_between_day_14_and_29(): void {
		update_option( WC_Payments_Account::KYC_COMPLETION_DATE_OPTION, time() - 14 * DAY_IN_SECONDS );
		$this->assertSame( 14, $this->make_banner()->get_current_stage() );
	}

	public function test_get_current_stage_returns_30_at_and_after_day_30(): void {
		update_option( WC_Payments_Account::KYC_COMPLETION_DATE_OPTION, time() - 45 * DAY_IN_SECONDS );
		$this->assertSame( 30, $this->make_banner()->get_current_stage() );
	}

	public function test_get_current_stage_returns_null_after_window_closes(): void {
		update_option( WC_Payments_Account::KYC_COMPLETION_DATE_OPTION, time() - 90 * DAY_IN_SECONDS );
		$this->assertNull( $this->make_banner()->get_current_stage() );
	}

	// ---- should_show: eligibility predicate ----------------------------------

	public function test_should_show_returns_true_when_all_conditions_met(): void {
		$this->assertTrue( $this->make_banner()->should_show() );
	}

	public function test_should_show_returns_false_when_no_kyc_date(): void {
		delete_option( WC_Payments_Account::KYC_COMPLETION_DATE_OPTION );
		$this->assertFalse( $this->make_banner()->should_show() );
	}

	public function test_should_show_returns_false_before_day_7(): void {
		update_option( WC_Payments_Account::KYC_COMPLETION_DATE_OPTION, time() - 3 * DAY_IN_SECONDS );
		$this->assertFalse( $this->make_banner()->should_show() );
	}

	public function test_should_show_returns_false_when_active_stage_dismissed(): void {
		update_user_meta( $this->admin_user_id, 'wcpay_post_kyc_activation_7_dismissed', true );
		$this->assertFalse( $this->make_banner()->should_show() );
	}

	public function test_should_show_returns_false_when_user_lacks_capability(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'subscriber' ] ) );
		$this->assertFalse( $this->make_banner()->should_show() );
	}

	public function test_should_show_returns_false_when_not_connected(): void {
		$this->assertFalse( $this->make_banner( false )->should_show() );
	}

	public function test_should_show_returns_false_for_test_drive_account(): void {
		$this->assertFalse( $this->make_banner( true, true, true )->should_show() );
	}

	public function test_should_show_returns_false_when_payments_not_enabled(): void {
		$this->assertFalse( $this->make_banner( true, true, false, false )->should_show() );
	}

	public function test_should_show_returns_false_in_test_mode(): void {
		WC_Payments::mode()->test();
		$this->assertFalse( $this->make_banner()->should_show() );
	}

	public function test_should_show_returns_false_when_live_sale_recorded(): void {
		update_option( WC_Payments_Order_Service::HAS_LIVE_SALE_OPTION, '1' );
		$this->assertFalse( $this->make_banner()->should_show() );
	}

	public function test_should_show_returns_true_when_only_test_orders_present(): void {
		$order = wc_create_order();
		$order->set_payment_method( 'woocommerce_payments' );
		$order->set_status( 'completed' );
		$order->update_meta_data( WC_Payments_Order_Service::WCPAY_MODE_META_KEY, Order_Mode::TEST );
		$order->save();
		$this->created_order_ids[] = $order->get_id();

		$this->assertTrue( $this->make_banner()->should_show() );
	}

	public function test_should_show_short_circuits_before_transient_when_live_sale_recorded(): void {
		update_option( WC_Payments_Order_Service::HAS_LIVE_SALE_OPTION, '1' );

		$transient_reads = 0;
		$counter         = function ( $value ) use ( &$transient_reads ) {
			++$transient_reads;
			return $value;
		};
		add_filter( 'pre_transient_' . WC_Payments_Post_Kyc_Activation_Banner::TRANSIENT_ELIGIBLE, $counter );

		$this->assertFalse( $this->make_banner()->should_show() );

		remove_filter( 'pre_transient_' . WC_Payments_Post_Kyc_Activation_Banner::TRANSIENT_ELIGIBLE, $counter );

		$this->assertSame( 0, $transient_reads, 'compute_should_show() must short-circuit before reaching the eligibility transient when a live sale exists.' );
	}

	public function test_should_show_memoizes_within_request(): void {
		$banner = $this->make_banner();

		$dismissal_reads = 0;
		$counter         = function ( $value, $object_id, $meta_key ) use ( &$dismissal_reads ) {
			if ( 'wcpay_post_kyc_activation_7_dismissed' === $meta_key ) {
				++$dismissal_reads;
			}
			return $value;
		};
		add_filter( 'get_user_metadata', $counter, 10, 3 );

		$banner->should_show();
		$banner->should_show();
		$banner->should_show();

		remove_filter( 'get_user_metadata', $counter, 10 );

		$this->assertSame( 1, $dismissal_reads );
	}

	// ---- hide_notice: per-stage dismiss --------------------------------------

	public function test_hide_notice_writes_stage_dismissed_meta_and_redirects(): void {
		$_GET['wcpay-hide-post-kyc-activation-notice']   = '1';
		$_GET['_wcpay_post_kyc_activation_notice_nonce'] = wp_create_nonce( 'wcpay_hide_post_kyc_activation_notice_nonce' );
		$_GET['wcpay_stage']                             = '7';

		$this->assert_handler_redirects( fn() => $this->make_banner()->hide_notice() );

		$this->assertNotEmpty( get_user_meta( $this->admin_user_id, 'wcpay_post_kyc_activation_7_dismissed', true ) );

		unset( $_GET['wcpay-hide-post-kyc-activation-notice'], $_GET['_wcpay_post_kyc_activation_notice_nonce'], $_GET['wcpay_stage'] );
	}

	public function test_hide_notice_respects_url_stage_when_active_stage_advanced(): void {
		// Banner was rendered at stage 7 but the page sat open until stage 14
		// became active. The dismiss must record against the URL's stage (7),
		// not the active stage (14).
		update_option( WC_Payments_Account::KYC_COMPLETION_DATE_OPTION, time() - 14 * DAY_IN_SECONDS );

		$_GET['wcpay-hide-post-kyc-activation-notice']   = '1';
		$_GET['_wcpay_post_kyc_activation_notice_nonce'] = wp_create_nonce( 'wcpay_hide_post_kyc_activation_notice_nonce' );
		$_GET['wcpay_stage']                             = '7';

		$this->assert_handler_redirects( fn() => $this->make_banner()->hide_notice() );

		$this->assertNotEmpty( get_user_meta( $this->admin_user_id, 'wcpay_post_kyc_activation_7_dismissed', true ) );
		$this->assertEmpty( get_user_meta( $this->admin_user_id, 'wcpay_post_kyc_activation_14_dismissed', true ) );

		unset( $_GET['wcpay-hide-post-kyc-activation-notice'], $_GET['_wcpay_post_kyc_activation_notice_nonce'], $_GET['wcpay_stage'] );
	}

	public function test_hide_notice_ignores_invalid_stage(): void {
		$_GET['wcpay-hide-post-kyc-activation-notice']   = '1';
		$_GET['_wcpay_post_kyc_activation_notice_nonce'] = wp_create_nonce( 'wcpay_hide_post_kyc_activation_notice_nonce' );
		$_GET['wcpay_stage']                             = '99';

		$this->make_banner()->hide_notice();

		foreach ( [ 7, 14, 30, 99 ] as $stage ) {
			$this->assertEmpty( get_user_meta( $this->admin_user_id, 'wcpay_post_kyc_activation_' . $stage . '_dismissed', true ) );
		}

		unset( $_GET['wcpay-hide-post-kyc-activation-notice'], $_GET['_wcpay_post_kyc_activation_notice_nonce'], $_GET['wcpay_stage'] );
	}

	public function test_hide_notice_ignores_missing_stage(): void {
		$_GET['wcpay-hide-post-kyc-activation-notice']   = '1';
		$_GET['_wcpay_post_kyc_activation_notice_nonce'] = wp_create_nonce( 'wcpay_hide_post_kyc_activation_notice_nonce' );

		$this->make_banner()->hide_notice();

		foreach ( [ 7, 14, 30 ] as $stage ) {
			$this->assertEmpty( get_user_meta( $this->admin_user_id, 'wcpay_post_kyc_activation_' . $stage . '_dismissed', true ) );
		}

		unset( $_GET['wcpay-hide-post-kyc-activation-notice'], $_GET['_wcpay_post_kyc_activation_notice_nonce'] );
	}

	public function test_hide_notice_ignores_invalid_nonce(): void {
		$_GET['wcpay-hide-post-kyc-activation-notice']   = '1';
		$_GET['_wcpay_post_kyc_activation_notice_nonce'] = 'bad-nonce';
		$_GET['wcpay_stage']                             = '7';

		$this->make_banner()->hide_notice();

		$this->assertEmpty( get_user_meta( $this->admin_user_id, 'wcpay_post_kyc_activation_7_dismissed', true ) );

		unset( $_GET['wcpay-hide-post-kyc-activation-notice'], $_GET['_wcpay_post_kyc_activation_notice_nonce'], $_GET['wcpay_stage'] );
	}

	// ---- handle_cta ----------------------------------------------------------

	public function test_handle_cta_records_dismissal_and_redirects_to_marketing(): void {
		$_GET['wcpay-post-kyc-activation-cta']        = '1';
		$_GET['_wcpay_post_kyc_activation_cta_nonce'] = wp_create_nonce( 'wcpay_post_kyc_activation_cta_nonce' );
		$_GET['wcpay_stage']                          = '7';

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

		$this->assertStringContainsString( 'page=wc-admin', (string) $captured_url );
		$this->assertStringContainsString( 'path=/marketing', urldecode( (string) $captured_url ) );
		$this->assertNotEmpty( get_user_meta( $this->admin_user_id, 'wcpay_post_kyc_activation_7_dismissed', true ) );

		unset( $_GET['wcpay-post-kyc-activation-cta'], $_GET['_wcpay_post_kyc_activation_cta_nonce'], $_GET['wcpay_stage'] );
	}

	public function test_handle_cta_ignores_invalid_stage(): void {
		$_GET['wcpay-post-kyc-activation-cta']        = '1';
		$_GET['_wcpay_post_kyc_activation_cta_nonce'] = wp_create_nonce( 'wcpay_post_kyc_activation_cta_nonce' );
		$_GET['wcpay_stage']                          = '99';

		$this->make_banner()->handle_cta();

		foreach ( [ 7, 14, 30, 99 ] as $stage ) {
			$this->assertEmpty( get_user_meta( $this->admin_user_id, 'wcpay_post_kyc_activation_' . $stage . '_dismissed', true ) );
		}

		unset( $_GET['wcpay-post-kyc-activation-cta'], $_GET['_wcpay_post_kyc_activation_cta_nonce'], $_GET['wcpay_stage'] );
	}

	public function test_handle_cta_ignores_invalid_nonce(): void {
		$_GET['wcpay-post-kyc-activation-cta']        = '1';
		$_GET['_wcpay_post_kyc_activation_cta_nonce'] = 'bad-nonce';
		$_GET['wcpay_stage']                          = '7';

		$this->make_banner()->handle_cta();

		$this->assertEmpty( get_user_meta( $this->admin_user_id, 'wcpay_post_kyc_activation_7_dismissed', true ) );

		unset( $_GET['wcpay-post-kyc-activation-cta'], $_GET['_wcpay_post_kyc_activation_cta_nonce'], $_GET['wcpay_stage'] );
	}

	// ---- maybe_show ----------------------------------------------------------

	public function test_maybe_show_outputs_container_div(): void {
		ob_start();
		$this->make_banner()->maybe_show();
		$this->assertStringContainsString( '<div id="wcpay-post-kyc-activation-notice">', ob_get_clean() );
	}

	// ---- enqueue_script impression tracking ----------------------------------

	public function test_enqueue_script_records_impression_for_active_stage(): void {
		// No screen filter so set_current_screen() isn't needed; impression
		// firing only requires the user be eligible.
		$this->make_banner()->enqueue_script();

		$this->assertNotEmpty( get_user_meta( $this->admin_user_id, 'wcpay_post_kyc_activation_7_shown', true ) );
	}

	// ---- invalidate_cache ----------------------------------------------------

	public function test_invalidate_cache_drops_transient(): void {
		set_transient( WC_Payments_Post_Kyc_Activation_Banner::TRANSIENT_ELIGIBLE, '1', HOUR_IN_SECONDS );

		$this->make_banner()->invalidate_cache();

		$this->assertFalse( get_transient( WC_Payments_Post_Kyc_Activation_Banner::TRANSIENT_ELIGIBLE ) );
	}

	// ---- Helpers --------------------------------------------------------------

	/**
	 * Builds a banner instance with mocked gateway/account.
	 *
	 * @param bool $is_connected     Whether the gateway reports as connected.
	 * @param bool $is_account_valid Whether the Stripe account reports as valid.
	 * @param bool $is_test_drive    Whether the account is a test-drive account.
	 * @param bool $payments_enabled Whether payments are enabled on the account.
	 * @return WC_Payments_Post_Kyc_Activation_Banner
	 */
	private function make_banner(
		bool $is_connected = true,
		bool $is_account_valid = true,
		bool $is_test_drive = false,
		bool $payments_enabled = true
	): WC_Payments_Post_Kyc_Activation_Banner {
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

		return new WC_Payments_Post_Kyc_Activation_Banner( $mock_gateway, $mock_account );
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
