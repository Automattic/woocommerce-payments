<?php
/**
 * Class WC_Payments_Abstract_Admin_Banner_Test
 *
 * @package WooCommerce\Payments\Tests
 */

/**
 * WC_Payments_Abstract_Admin_Banner unit tests.
 *
 * The slug for the inline fixture is `'fixture'`. Tests assert against literal
 * derived strings (e.g. `'wcpay_fixture_notice_dismissed'`) so the slug-to-key
 * mapping is verified end-to-end without an explicit derivation test.
 */
class WC_Payments_Abstract_Admin_Banner_Test extends WCPAY_UnitTestCase {

	/** @var WC_Payments_Abstract_Admin_Banner */
	private $banner;

	/** @var int */
	private $admin_user_id;

	public function set_up(): void {
		parent::set_up();
		$this->admin_user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $this->admin_user_id );
		$this->banner = $this->make_fixture();
	}

	public function tear_down(): void {
		delete_user_meta( $this->admin_user_id, 'wcpay_fixture_notice_dismissed' );
		delete_user_meta( $this->admin_user_id, 'wcpay_fixture_notice_snoozed' );
		delete_user_meta( $this->admin_user_id, 'wcpay_fixture_notice_shown' );
		delete_transient( 'wcpay_fixture_eligible' );
		parent::tear_down();
	}

	// ---- Slug-derived naming -------------------------------------------------

	public function test_multi_word_slug_derives_kebab_and_camel_correctly(): void {
		$multi = new class() extends WC_Payments_Abstract_Admin_Banner {
			/** @return string */
			protected function get_slug(): string {
				return 'multi_word_thing';
			}
			/** @return bool */
			protected function compute_eligibility(): bool {
				return true;
			}
			/** @return void */
			public function handle_cta(): void {}
			/** @return string */
			public function expose_mount_div_id(): string {
				return $this->mount_div_id();
			}
			/** @return string */
			public function expose_localize_var(): string {
				return $this->localize_var_name();
			}
		};

		$this->assertSame( 'wcpay-multi-word-thing-notice', $multi->expose_mount_div_id() );
		$this->assertSame( 'wcpayMultiWordThingNoticeSettings', $multi->expose_localize_var() );
	}

	// ---- should_show ---------------------------------------------------------

	public function test_should_show_returns_true_when_eligible(): void {
		$this->assertTrue( $this->banner->should_show() );
	}

	public function test_should_show_returns_false_when_user_lacks_capability(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'subscriber' ] ) );
		$this->assertFalse( $this->banner->should_show() );
	}

	public function test_should_show_returns_false_when_dismissed(): void {
		update_user_meta( $this->admin_user_id, 'wcpay_fixture_notice_dismissed', time() );
		$this->assertFalse( $this->banner->should_show() );
	}

	public function test_should_show_returns_false_within_snooze_window(): void {
		update_user_meta( $this->admin_user_id, 'wcpay_fixture_notice_snoozed', time() );
		$this->assertFalse( $this->banner->should_show() );
	}

	public function test_should_show_returns_true_after_snooze_window_expires(): void {
		update_user_meta( $this->admin_user_id, 'wcpay_fixture_notice_snoozed', time() - 8 * DAY_IN_SECONDS );
		$this->assertTrue( $this->banner->should_show() );
	}

	public function test_should_show_ignores_snooze_when_banner_opts_out(): void {
		$this->banner->allow_snooze = false;
		update_user_meta( $this->admin_user_id, 'wcpay_fixture_notice_snoozed', time() );
		$this->assertTrue( $this->banner->should_show() );
	}

	public function test_should_show_memoizes_compute_within_request(): void {
		$this->banner->should_show();
		$this->banner->should_show();
		$this->banner->should_show();
		$this->assertSame( 1, $this->banner->compute_calls );
	}

	// ---- Eligibility transient cache -----------------------------------------

	public function test_eligibility_transient_caches_across_instances(): void {
		$this->banner->should_show();
		$this->assertSame( '1', get_transient( 'wcpay_fixture_eligible' ) );

		// Fresh instance with the predicate flipped — cached '1' wins.
		$other              = $this->make_fixture();
		$other->eligibility = false;
		$this->assertTrue( $other->should_show() );
		$this->assertSame( 0, $other->compute_calls, 'compute_eligibility() must not run when the transient is populated.' );
	}

	// ---- maybe_show ----------------------------------------------------------

	public function test_maybe_show_outputs_mount_div_when_eligible(): void {
		ob_start();
		$this->banner->maybe_show();
		$this->assertSame( '<div id="wcpay-fixture-notice"></div>', ob_get_clean() );
	}

	public function test_maybe_show_emits_nothing_when_ineligible(): void {
		update_user_meta( $this->admin_user_id, 'wcpay_fixture_notice_dismissed', time() );

		ob_start();
		$this->banner->maybe_show();
		$this->assertSame( '', ob_get_clean() );
	}

	public function test_maybe_show_records_impression_once_per_user(): void {
		ob_start();
		$this->banner->maybe_show();
		$this->banner->maybe_show();
		ob_end_clean();
		$this->assertNotEmpty( get_user_meta( $this->admin_user_id, 'wcpay_fixture_notice_shown', true ) );
	}

	// ---- hide_notice ---------------------------------------------------------

	public function test_hide_notice_writes_dismissed_meta_and_redirects(): void {
		$_GET['wcpay-hide-fixture-notice']   = '1';
		$_GET['_wcpay_fixture_notice_nonce'] = wp_create_nonce( 'wcpay_hide_fixture_notice_nonce' );

		$this->assert_handler_redirects( fn() => $this->banner->hide_notice() );

		$this->assertNotEmpty( get_user_meta( $this->admin_user_id, 'wcpay_fixture_notice_dismissed', true ) );
		unset( $_GET['wcpay-hide-fixture-notice'], $_GET['_wcpay_fixture_notice_nonce'] );
	}

	public function test_hide_notice_bails_without_marker_query_arg(): void {
		$this->banner->hide_notice();
		$this->assertSame( '', get_user_meta( $this->admin_user_id, 'wcpay_fixture_notice_dismissed', true ) );
	}

	public function test_hide_notice_bails_on_bad_nonce(): void {
		$_GET['wcpay-hide-fixture-notice']   = '1';
		$_GET['_wcpay_fixture_notice_nonce'] = 'not-a-valid-nonce';

		$this->banner->hide_notice();

		$this->assertSame( '', get_user_meta( $this->admin_user_id, 'wcpay_fixture_notice_dismissed', true ) );
		unset( $_GET['wcpay-hide-fixture-notice'], $_GET['_wcpay_fixture_notice_nonce'] );
	}

	public function test_hide_notice_bails_without_capability(): void {
		$subscriber = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $subscriber );

		$_GET['wcpay-hide-fixture-notice']   = '1';
		$_GET['_wcpay_fixture_notice_nonce'] = wp_create_nonce( 'wcpay_hide_fixture_notice_nonce' );

		$this->banner->hide_notice();

		$this->assertSame( '', get_user_meta( $subscriber, 'wcpay_fixture_notice_dismissed', true ) );
		unset( $_GET['wcpay-hide-fixture-notice'], $_GET['_wcpay_fixture_notice_nonce'] );
	}

	// ---- snooze_notice -------------------------------------------------------

	public function test_snooze_notice_writes_snoozed_meta_and_redirects(): void {
		$_GET['wcpay-snooze-fixture-notice']        = '1';
		$_GET['_wcpay_snooze_fixture_notice_nonce'] = wp_create_nonce( 'wcpay_snooze_fixture_notice_nonce' );

		$this->assert_handler_redirects( fn() => $this->banner->snooze_notice() );

		$this->assertNotEmpty( get_user_meta( $this->admin_user_id, 'wcpay_fixture_notice_snoozed', true ) );
		unset( $_GET['wcpay-snooze-fixture-notice'], $_GET['_wcpay_snooze_fixture_notice_nonce'] );
	}

	public function test_snooze_notice_is_no_op_when_banner_opts_out(): void {
		$this->banner->allow_snooze                 = false;
		$_GET['wcpay-snooze-fixture-notice']        = '1';
		$_GET['_wcpay_snooze_fixture_notice_nonce'] = wp_create_nonce( 'wcpay_snooze_fixture_notice_nonce' );

		$this->banner->snooze_notice();

		$this->assertSame( '', get_user_meta( $this->admin_user_id, 'wcpay_fixture_notice_snoozed', true ) );
		unset( $_GET['wcpay-snooze-fixture-notice'], $_GET['_wcpay_snooze_fixture_notice_nonce'] );
	}

	// ---- init_hooks / init_global_hooks --------------------------------------

	public function test_init_hooks_registers_admin_init_and_enqueue_handlers(): void {
		$this->banner->init_hooks();

		$this->assertNotFalse( has_action( 'admin_init', [ $this->banner, 'hide_notice' ] ) );
		$this->assertNotFalse( has_action( 'admin_init', [ $this->banner, 'snooze_notice' ] ) );
		$this->assertNotFalse( has_action( 'admin_init', [ $this->banner, 'handle_cta' ] ) );
		$this->assertNotFalse( has_action( 'admin_enqueue_scripts', [ $this->banner, 'register_script' ] ) );
		$this->assertNotFalse( has_action( 'admin_enqueue_scripts', [ $this->banner, 'enqueue_script' ] ) );

		$this->cleanup_admin_init_hooks( $this->banner );
	}

	public function test_init_hooks_skips_snooze_for_banners_that_opt_out(): void {
		$this->banner->allow_snooze = false;
		$this->banner->init_hooks();

		$this->assertFalse( has_action( 'admin_init', [ $this->banner, 'snooze_notice' ] ) );

		$this->cleanup_admin_init_hooks( $this->banner );
	}

	public function test_init_hooks_registers_sections_hook_on_wc_settings(): void {
		$_GET['page'] = 'wc-settings';
		$_GET['tab']  = 'checkout';

		$this->banner->init_hooks();

		$this->assertNotFalse( has_action( 'woocommerce_sections_checkout', [ $this->banner, 'maybe_show' ] ) );

		remove_action( 'woocommerce_sections_checkout', [ $this->banner, 'maybe_show' ] );
		$this->cleanup_admin_init_hooks( $this->banner );
		unset( $_GET['page'], $_GET['tab'] );
	}

	// ---- Helpers --------------------------------------------------------------

	/**
	 * Returns a fresh anonymous-class fixture with `eligibility`, `compute_calls`,
	 * and `allow_snooze` knobs the tests mutate. Slug is `'fixture'` so every
	 * derived key follows the predictable `wcpay_fixture_*` shape.
	 *
	 * @return WC_Payments_Abstract_Admin_Banner
	 */
	private function make_fixture(): WC_Payments_Abstract_Admin_Banner {
		return new class() extends WC_Payments_Abstract_Admin_Banner {
			/** @var bool */
			public $eligibility = true;

			/** @var int */
			public $compute_calls = 0;

			/** @var bool */
			public $allow_snooze = true;

			/** @return string */
			protected function get_slug(): string {
				return 'fixture';
			}

			/** @return bool */
			protected function compute_eligibility(): bool {
				++$this->compute_calls;
				return $this->eligibility;
			}

			/** @return bool */
			protected function supports_snooze(): bool {
				return $this->allow_snooze;
			}

			/** @return void */
			public function handle_cta(): void {}
		};
	}

	/** @return void */
	private function cleanup_admin_init_hooks( WC_Payments_Abstract_Admin_Banner $banner ): void {
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
