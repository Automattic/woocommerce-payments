<?php
/**
 * Class WC_Payments_Subscriptions_Disabler_Test
 *
 * @package WooPayments
 */

/**
 * WC_Payments_Subscriptions_Disabler unit tests.
 */
class WC_Payments_Subscriptions_Disabler_Test extends WCPAY_UnitTestCase {

	/**
	 * Test double that allows intercepting redirects.
	 *
	 * @var WC_Payments_Subscriptions_Disabler
	 */
	private $disabler;

	/**
	 * Creates the test double for the disabler.
	 */
	public function set_up() {
		parent::set_up();

		require_once WCPAY_ABSPATH . 'includes/subscriptions/class-wc-payments-subscriptions-disabler.php';

		if ( ! class_exists( 'WP_Screen' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
		}

		$this->disabler = new class() extends WC_Payments_Subscriptions_Disabler {
			/**
			 * Captured redirect destination.
			 *
			 * @var string|null
			 */
			public $redirected_to = null;

			/**
			 * Flag indicating whether account endpoint check should pass.
			 *
			 * @var bool
			 */
			private $should_match_endpoint = false;

			/**
			 * Override redirect to capture destination instead of exiting.
			 *
			 * @param string $target Target URL.
			 * @return void
			 */
			protected function redirect( $target ) {
				$this->redirected_to = $target;
			}

			/**
			 * Allow tests to toggle endpoint matching.
			 *
			 * @param bool $value Whether endpoint should be treated as matched.
			 * @return void
			 */
			public function set_should_match_endpoint( $value ) {
				$this->should_match_endpoint = (bool) $value;
			}

			/**
			 * Override endpoint matcher.
			 *
			 * @param string $endpoint Endpoint slug.
			 * @return bool
			 */
			protected function is_endpoint_url( $endpoint ) {
				unset( $endpoint );

				return $this->should_match_endpoint;
			}
		};
	}

	/**
	 * Reset redirect capture after each test.
	 */
	public function tear_down() {
		$this->disabler->redirected_to = null;
		$this->disabler->set_should_match_endpoint( false );
		parent::tear_down();
	}

	/**
	 * Ensure the account menu subscriptions entry is removed.
	 */
	public function test_remove_account_menu_item_removes_subscription_entry() {
		update_option( 'woocommerce_myaccount_subscriptions_endpoint', 'subscriptions' );

		$menu_items = [
			'dashboard'     => 'Dashboard',
			'orders'        => 'Orders',
			'subscriptions' => 'Subscriptions',
		];

		$filtered_items = $this->disabler->remove_account_menu_item( $menu_items );

		$this->assertArrayNotHasKey( 'subscriptions', $filtered_items );
		$this->assertSame( [ 'dashboard', 'orders' ], array_keys( $filtered_items ) );
	}

	/**
	 * Ensure subscription product types are removed from selector.
	 */
	public function test_filter_product_type_selector_removes_subscription_types() {
		$types = [
			'simple'                => 'Simple product',
			'subscription'          => 'Simple subscription',
			'variable'              => 'Variable product',
			'variable-subscription' => 'Variable subscription',
		];

		$result = $this->disabler->filter_product_type_selector( $types );

		$this->assertArrayHasKey( 'simple', $result );
		$this->assertArrayHasKey( 'variable', $result );
		$this->assertArrayNotHasKey( 'subscription', $result );
		$this->assertArrayNotHasKey( 'variable-subscription', $result );
	}

	/**
	 * Ensure subscription WooCommerce settings tab is removed.
	 */
	public function test_filter_settings_tabs_removes_subscription_tab() {
		$tabs = [
			'general'       => 'General',
			'subscriptions' => 'Subscriptions',
			'payments'      => 'Payments',
		];

		$result = $this->disabler->filter_settings_tabs( $tabs );

		$this->assertArrayHasKey( 'general', $result );
		$this->assertArrayHasKey( 'payments', $result );
		$this->assertArrayNotHasKey( 'subscriptions', $result );
	}

	/**
	 * Ensure direct navigation to the subscriptions settings tab is redirected.
	 */
	public function test_maybe_redirect_settings_tab_redirects_subscription_tab() {
		$_GET['page'] = 'wc-settings';
		$_GET['tab']  = 'subscriptions';

		$this->disabler->maybe_redirect_settings_tab();

		$this->assertSame(
			add_query_arg(
				[
					'page' => 'wc-settings',
					'tab'  => 'general',
				],
				admin_url( 'admin.php' )
			),
			$this->disabler->redirected_to
		);

		unset( $_GET['page'], $_GET['tab'] );
	}

	/**
	 * Ensure non-subscription tabs ignore settings redirect logic.
	 */
	public function test_maybe_redirect_settings_tab_ignores_other_tabs() {
		$_GET['page'] = 'wc-settings';
		$_GET['tab']  = 'payments';

		$this->disabler->maybe_redirect_settings_tab();

		$this->assertNull( $this->disabler->redirected_to );

		unset( $_GET['page'], $_GET['tab'] );
	}

	/**
	 * Ensure related subscriptions section is removed from order detail view.
	 */
	public function test_remove_related_subscriptions_section() {
		if ( ! class_exists( 'WC_Subscriptions_Order' ) ) {
			$this->markTestSkipped( 'Subscriptions core not available.' );
		}

		add_action(
			'woocommerce_order_details_after_order_table',
			[ 'WC_Subscriptions_Order', 'add_subscriptions_to_view_order_templates' ],
			10,
			1
		);

		$this->assertNotFalse(
			has_action(
				'woocommerce_order_details_after_order_table',
				[ 'WC_Subscriptions_Order', 'add_subscriptions_to_view_order_templates' ]
			)
		);

		$this->disabler->remove_related_subscriptions_section();

		$this->assertFalse(
			has_action(
				'woocommerce_order_details_after_order_table',
				[ 'WC_Subscriptions_Order', 'add_subscriptions_to_view_order_templates' ]
			)
		);
	}

	/**
	 * Verify that admin subscription list screens are redirected away.
	 */
	public function test_maybe_block_admin_subscription_screen_redirects() {
		require_once ABSPATH . 'wp-admin/includes/screen.php';
		require_once ABSPATH . 'wp-admin/includes/template.php';

		set_current_screen( 'edit-shop_subscription' );

		$screen = get_current_screen();

		$this->disabler->maybe_block_admin_subscription_screen( $screen );

		$this->assertSame( admin_url( 'admin.php?page=wc-admin' ), $this->disabler->redirected_to );

		set_current_screen( 'front' );
	}

	/**
	 * Verify that editing an individual subscription is blocked.
	 */
	public function test_maybe_block_admin_subscription_edit_screen() {
		require_once ABSPATH . 'wp-admin/includes/screen.php';
		require_once ABSPATH . 'wp-admin/includes/template.php';

		// Test with CPT edit screen.
		set_current_screen( 'shop_subscription' );

		$screen = get_current_screen();

		$this->disabler->maybe_block_admin_subscription_screen( $screen );

		$this->assertSame(
			admin_url( 'admin.php?page=wc-admin' ),
			$this->disabler->redirected_to,
			'Should block editing individual subscriptions (CPT)'
		);

		// Reset for next test.
		$this->disabler->redirected_to = null;

		// Test with HPOS edit screen.
		set_current_screen( 'wc-orders--shop_subscription' );

		$screen = get_current_screen();

		$this->disabler->maybe_block_admin_subscription_screen( $screen );

		$this->assertSame(
			admin_url( 'admin.php?page=wc-admin' ),
			$this->disabler->redirected_to,
			'Should block editing individual subscriptions (HPOS)'
		);

		set_current_screen( 'front' );
	}

	/**
	 * Verify that direct post.php?post=X&action=edit URLs are blocked for subscriptions.
	 */
	public function test_block_subscription_edit_via_post_id() {
		if ( ! class_exists( 'WC_Subscription' ) ) {
			$this->markTestSkipped( 'WC_Subscription class not available.' );
		}

		require_once ABSPATH . 'wp-admin/includes/screen.php';
		require_once ABSPATH . 'wp-admin/includes/template.php';

		// Create a mock subscription post.
		$subscription_id = $this->factory->post->create(
			[
				'post_type'   => 'shop_subscription',
				'post_status' => 'publish',
			]
		);

		// Simulate accessing the edit screen via post ID.
		$_GET['post']   = $subscription_id;
		$_GET['action'] = 'edit';

		set_current_screen( 'shop_subscription' );
		$screen = get_current_screen();

		$this->disabler->maybe_block_admin_subscription_screen( $screen );

		$this->assertSame(
			admin_url( 'admin.php?page=wc-admin' ),
			$this->disabler->redirected_to,
			'Should block editing subscription via direct post ID URL'
		);

		// Clean up.
		unset( $_GET['post'], $_GET['action'] );
		wp_delete_post( $subscription_id, true );
		set_current_screen( 'front' );
	}

	/**
	 * Verify that accessing subscription endpoints on My Account redirects to the dashboard.
	 */
	public function test_maybe_redirect_account_endpoints_redirects_to_my_account_page() {
		update_option( 'permalink_structure', '/%postname%/' );

		$page_id = $this->factory->post->create(
			[
				'post_title'  => 'My account',
				'post_name'   => 'my-account',
				'post_type'   => 'page',
				'post_status' => 'publish',
			]
		);

		update_option( 'woocommerce_myaccount_page_id', $page_id );
		update_option( 'woocommerce_myaccount_subscriptions_endpoint', 'subscriptions' );

		$account_url = get_permalink( $page_id );

		// Simulate visiting the pretty permalinks endpoint.
		$this->go_to( trailingslashit( $account_url ) . 'subscriptions/' );

		$this->disabler->set_should_match_endpoint( true );

		$this->disabler->maybe_redirect_account_endpoints();

		$this->assertSame( $account_url, $this->disabler->redirected_to );
	}

	/**
	 * Verify that the disabler does NOT interfere with renewal order creation hooks.
	 *
	 * This is a critical test to ensure Stripe Billing renewals continue to work
	 * when the UI is disabled.
	 */
	public function test_disabler_does_not_hook_into_renewal_order_creation() {
		// First, register a test hook to simulate renewal logic being active.
		$test_callback = function () {
			return true;
		};
		add_action( 'woocommerce_renewal_order_payment_complete', $test_callback );

		// Verify the hook exists before disabler runs.
		$this->assertIsInt(
			has_action( 'woocommerce_renewal_order_payment_complete', $test_callback ),
			'Renewal hook should exist before disabler init'
		);

		// Initialize the disabler.
		$this->disabler->init_hooks();

		// Verify the renewal hook still exists and was NOT removed by disabler.
		$this->assertIsInt(
			has_action( 'woocommerce_renewal_order_payment_complete', $test_callback ),
			'Disabler should NOT remove the renewal order payment complete hook'
		);

		// Clean up.
		remove_action( 'woocommerce_renewal_order_payment_complete', $test_callback );
	}

	/**
	 * Verify that the disabler does NOT hook into payment processing.
	 *
	 * Payment processing must continue to work for renewals to succeed.
	 */
	public function test_disabler_does_not_hook_into_payment_processing() {
		// Register test hooks to simulate payment processing being active.
		$payment_callback  = function () {};
		$checkout_callback = function () {};

		add_action( 'woocommerce_subscription_payment_complete', $payment_callback );
		add_action( 'woocommerce_checkout_subscription_created', $checkout_callback );

		// Initialize the disabler.
		$this->disabler->init_hooks();

		// Verify payment complete hook still exists (not removed by disabler).
		$this->assertIsInt(
			has_action( 'woocommerce_subscription_payment_complete', $payment_callback ),
			'Disabler should NOT remove subscription payment complete hook'
		);

		// Verify checkout subscription creation hook still exists.
		$this->assertIsInt(
			has_action( 'woocommerce_checkout_subscription_created', $checkout_callback ),
			'Disabler should NOT remove checkout subscription created hook'
		);

		// Clean up.
		remove_action( 'woocommerce_subscription_payment_complete', $payment_callback );
		remove_action( 'woocommerce_checkout_subscription_created', $checkout_callback );
	}

	/**
	 * Verify that the disabler does NOT hook into subscription status changes.
	 *
	 * Status changes are critical for renewal processing and subscription lifecycle.
	 */
	public function test_disabler_does_not_hook_into_status_changes() {
		$this->disabler->init_hooks();

		// List of critical status change hooks that should NOT be affected.
		$status_hooks = [
			'woocommerce_subscription_status_cancelled',
			'woocommerce_subscription_status_expired',
			'woocommerce_subscription_status_on-hold',
			'woocommerce_subscription_status_active',
			'woocommerce_subscription_status_pending-cancel',
		];

		foreach ( $status_hooks as $hook ) {
			$this->assertFalse(
				has_action( $hook ),
				"Disabler should NOT hook into {$hook}"
			);
		}
	}

	/**
	 * Verify that disabler only hooks into UI-layer actions and filters.
	 *
	 * This test documents all hooks the disabler DOES use to confirm they're
	 * all UI/presentation related and not backend functionality.
	 */
	public function test_disabler_only_hooks_into_ui_layer() {
		// Set admin context for admin hooks to be registered.
		set_current_screen( 'dashboard' );

		$this->disabler->init_hooks();

		// Admin UI hooks that SHOULD be present (only when is_admin()).
		$hook_priority = has_action( 'admin_menu', [ $this->disabler, 'remove_admin_menu_items' ] );
		$this->assertNotFalse(
			$hook_priority,
			'Disabler should hook into admin_menu to remove UI elements'
		);

		$hook_priority = has_action( 'current_screen', [ $this->disabler, 'maybe_block_admin_subscription_screen' ] );
		$this->assertNotFalse(
			$hook_priority,
			'Disabler should hook into current_screen to block admin access'
		);

		$hook_priority = has_filter( 'product_type_selector', [ $this->disabler, 'filter_product_type_selector' ] );
		$this->assertNotFalse(
			$hook_priority,
			'Disabler should hook into product_type_selector to hide subscription types'
		);

		$hook_priority = has_filter( 'woocommerce_settings_tabs_array', [ $this->disabler, 'filter_settings_tabs' ] );
		$this->assertNotFalse(
			$hook_priority,
			'Disabler should hook into settings tabs to remove subscription tab'
		);

		// Frontend UI hooks that SHOULD be present (these run regardless of is_admin()).
		$hook_priority = has_filter( 'woocommerce_account_menu_items', [ $this->disabler, 'remove_account_menu_item' ] );
		$this->assertNotFalse(
			$hook_priority,
			'Disabler should hook into account menu to remove subscription links'
		);

		$hook_priority = has_action( 'template_redirect', [ $this->disabler, 'maybe_redirect_account_endpoints' ] );
		$this->assertNotFalse(
			$hook_priority,
			'Disabler should hook into template_redirect to block customer access'
		);

		$hook_priority = has_action( 'init', [ $this->disabler, 'remove_related_subscriptions_section' ] );
		$this->assertNotFalse(
			$hook_priority,
			'Disabler should hook into init to remove subscription display sections'
		);

		// Clean up.
		set_current_screen( 'front' );
	}

	/**
	 * Integration test: Verify wcs_create_renewal_order can still be called when disabler is active.
	 *
	 * This simulates what happens during a Stripe webhook when invoice.paid is received.
	 */
	public function test_renewal_order_creation_works_with_disabler_active() {
		if ( ! class_exists( 'WC_Subscription' ) ) {
			$this->markTestSkipped( 'WC_Subscription class not available.' );
		}

		// Initialize the disabler (simulating production state).
		$this->disabler->init_hooks();

		// Create a mock subscription.
		$mock_subscription = $this->getMockBuilder( 'WC_Subscription' )
			->disableOriginalConstructor()
			->getMock();

		$mock_subscription->method( 'get_id' )->willReturn( 123 );

		// Track if wcs_create_renewal_order was called successfully.
		$renewal_order_created = false;

		// Mock the wcs_create_renewal_order function.
		WC_Subscriptions::wcs_create_renewal_order(
			function ( $subscription ) use ( &$renewal_order_created ) {
				$renewal_order_created = true;
				return WC_Helper_Order::create_order();
			}
		);

		// Simulate renewal order creation (what happens in webhook handler).
		$renewal_order = wcs_create_renewal_order( $mock_subscription );

		// Verify renewal order was created successfully.
		$this->assertTrue( $renewal_order_created, 'Renewal order should be created even with disabler active' );
		$this->assertInstanceOf( 'WC_Order', $renewal_order, 'Should return a valid WC_Order object' );
	}

	/**
	 * Verify that AJAX requests are not blocked by screen blocking.
	 *
	 * This ensures backend operations (like webhook processing) continue to work.
	 *
	 */
	public function test_admin_screen_blocking_skips_ajax_requests() {
		if ( ! class_exists( 'WP_Screen' ) ) {
			$this->markTestSkipped( 'WP_Screen class not available.' );
		}

		require_once ABSPATH . 'wp-admin/includes/screen.php';
		require_once ABSPATH . 'wp-admin/includes/template.php';

		// Simulate AJAX request.
		add_filter( 'wp_doing_ajax', '__return_true' );
		set_current_screen( 'edit-shop_subscription' );

		$screen = get_current_screen();
		$this->disabler->maybe_block_admin_subscription_screen( $screen );

		// Should NOT redirect during AJAX.
		$this->assertNull( $this->disabler->redirected_to, 'Should not redirect during AJAX requests' );

		remove_filter( 'wp_doing_ajax', '__return_true' );

		set_current_screen( 'front' );
	}
}
