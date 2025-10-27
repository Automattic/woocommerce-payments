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

		$this->assertSame(
			add_query_arg(
				[
					'page'                        => 'wc-settings',
					'tab'                         => 'checkout',
					'section'                     => 'woocommerce_payments',
					'wcpay_subscription_disabled' => '1',
				],
				admin_url( 'admin.php' )
			),
			$this->disabler->redirected_to
		);

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
			add_query_arg(
				[
					'page'                        => 'wc-settings',
					'tab'                         => 'checkout',
					'section'                     => 'woocommerce_payments',
					'wcpay_subscription_disabled' => '1',
				],
				admin_url( 'admin.php' )
			),
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
			add_query_arg(
				[
					'page'                        => 'wc-settings',
					'tab'                         => 'checkout',
					'section'                     => 'woocommerce_payments',
					'wcpay_subscription_disabled' => '1',
				],
				admin_url( 'admin.php' )
			),
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
			add_query_arg(
				[
					'page'                        => 'wc-settings',
					'tab'                         => 'checkout',
					'section'                     => 'woocommerce_payments',
					'wcpay_subscription_disabled' => '1',
				],
				admin_url( 'admin.php' )
			),
			$this->disabler->redirected_to,
			'Should block editing subscription via direct post ID URL'
		);

		// Clean up.
		unset( $_GET['post'], $_GET['action'] );
		wp_delete_post( $subscription_id, true );
		set_current_screen( 'front' );
	}

	/**
	 * Verify that editing a subscription product is blocked.
	 */
	public function test_block_subscription_product_edit() {
		if ( ! class_exists( 'WC_Product_Subscription' ) ) {
			$this->markTestSkipped( 'WC_Product_Subscription class not available.' );
		}

		require_once ABSPATH . 'wp-admin/includes/screen.php';
		require_once ABSPATH . 'wp-admin/includes/template.php';

		// Create a subscription product.
		$product = new WC_Product_Subscription();
		$product->set_props(
			[
				'name'          => 'Dummy Subscription Product',
				'regular_price' => 10,
				'price'         => 10,
			]
		);
		$product->save();
		$product_id = $product->get_id();

		// Simulate accessing the product edit screen via post ID.
		$_GET['post']   = $product_id;
		$_GET['action'] = 'edit';

		set_current_screen( 'product' );
		$screen = get_current_screen();

		$this->disabler->maybe_block_admin_subscription_screen( $screen );

		$this->assertSame(
			add_query_arg(
				[
					'page'                        => 'wc-settings',
					'tab'                         => 'checkout',
					'section'                     => 'woocommerce_payments',
					'wcpay_subscription_disabled' => '1',
				],
				admin_url( 'admin.php' )
			),
			$this->disabler->redirected_to,
			'Should block editing subscription products via direct post ID URL'
		);

		// Clean up.
		unset( $_GET['post'], $_GET['action'] );
		wp_delete_post( $product_id, true );
		set_current_screen( 'front' );
	}

	/**
	 * Verify that editing a regular (non-subscription) product is NOT blocked.
	 */
	public function test_does_not_block_regular_product_edit() {
		require_once ABSPATH . 'wp-admin/includes/screen.php';
		require_once ABSPATH . 'wp-admin/includes/template.php';

		// Create a simple product.
		$product    = WC_Helper_Product::create_simple_product();
		$product_id = $product->get_id();

		// Simulate accessing the product edit screen via post ID.
		$_GET['post']   = $product_id;
		$_GET['action'] = 'edit';

		set_current_screen( 'product' );
		$screen = get_current_screen();

		$this->disabler->maybe_block_admin_subscription_screen( $screen );

		$this->assertNull(
			$this->disabler->redirected_to,
			'Should NOT block editing regular products'
		);

		// Clean up.
		unset( $_GET['post'], $_GET['action'] );
		wp_delete_post( $product_id, true );
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

	/**
	 * Verify that the redirect URL includes the notice query parameter.
	 */
	public function test_redirect_includes_notice_parameter() {
		if ( ! class_exists( 'WC_Subscription' ) ) {
			$this->markTestSkipped( 'WC_Subscription class not available.' );
		}

		require_once ABSPATH . 'wp-admin/includes/screen.php';
		require_once ABSPATH . 'wp-admin/includes/template.php';

		$subscription_id = $this->factory->post->create(
			[
				'post_type'   => 'shop_subscription',
				'post_status' => 'publish',
			]
		);

		$_GET['post']   = $subscription_id;
		$_GET['action'] = 'edit';

		set_current_screen( 'shop_subscription' );
		$screen = get_current_screen();

		$this->disabler->maybe_block_admin_subscription_screen( $screen );

		$this->assertStringContainsString(
			'wcpay_subscription_disabled=1',
			$this->disabler->redirected_to,
			'Redirect URL should contain notice parameter'
		);

		unset( $_GET['post'], $_GET['action'] );
		wp_delete_post( $subscription_id, true );
		set_current_screen( 'front' );
	}

	/**
	 * Verify that the admin notice is displayed when the query parameter is set.
	 */
	public function test_display_subscription_disabled_notice() {
		$_GET['wcpay_subscription_disabled'] = '1';
		$_GET['page']                        = 'wc-settings';
		$_GET['section']                     = 'woocommerce_payments';

		ob_start();
		$this->disabler->display_subscription_disabled_notice();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'To access your subscriptions data and keep managing recurring payments', $output );
		$this->assertStringContainsString( 'Built-in support for subscriptions is no longer available in WooPayments.', $output );
		$this->assertStringContainsString( 'WooCommerce Subscriptions', $output );
		$this->assertStringContainsString( 'woocommerce.com/products/woocommerce-subscriptions', $output );
		$this->assertStringNotContainsString( 'is-dismissible', $output, 'Notice should not be dismissible' );

		unset( $_GET['wcpay_subscription_disabled'], $_GET['page'], $_GET['section'] );
	}

	/**
	 * Verify that the notice is not displayed without the query parameter.
	 */
	public function test_display_subscription_disabled_notice_not_shown_without_parameter() {
		unset( $_GET['wcpay_subscription_disabled'] );

		ob_start();
		$this->disabler->display_subscription_disabled_notice();
		$output = ob_get_clean();

		$this->assertEmpty( $output, 'Should not display notice when query parameter is not set' );
	}

	/**
	 * Verify that the notice is not displayed on non-WooPayments settings pages.
	 */
	public function test_display_subscription_disabled_notice_not_shown_on_other_pages() {
		$_GET['wcpay_subscription_disabled'] = '1';
		$_GET['page']                        = 'other-page';

		ob_start();
		$this->disabler->display_subscription_disabled_notice();
		$output = ob_get_clean();

		$this->assertEmpty( $output, 'Should not display notice on non-WooPayments settings pages' );

		unset( $_GET['wcpay_subscription_disabled'], $_GET['page'] );
	}

	/**
	 * Verify that subscription products cannot be purchased.
	 */
	public function test_make_subscription_products_unpurchasable() {
		if ( ! class_exists( 'WC_Product_Subscription' ) ) {
			$this->markTestSkipped( 'WC_Product_Subscription class not available.' );
		}

		$this->disabler->init_hooks();

		// Create subscription product.
		$subscription_product = new WC_Product_Subscription();
		$subscription_product->set_props(
			[
				'name'          => 'Test Subscription',
				'regular_price' => 10,
				'price'         => 10,
			]
		);
		$subscription_product->save();

		// Verify subscription product is not purchasable.
		$this->assertFalse(
			$subscription_product->is_purchasable(),
			'Subscription product should not be purchasable'
		);

		// Verify regular products are still purchasable.
		$regular_product = WC_Helper_Product::create_simple_product();
		$this->assertTrue(
			$regular_product->is_purchasable(),
			'Regular products should still be purchasable'
		);

		// Cleanup.
		wp_delete_post( $subscription_product->get_id(), true );
		wp_delete_post( $regular_product->get_id(), true );
	}

	/**
	 * Verify that variable subscription products cannot be purchased.
	 */
	public function test_make_variable_subscription_products_unpurchasable() {
		if ( ! class_exists( 'WC_Product_Variable_Subscription' ) ) {
			$this->markTestSkipped( 'WC_Product_Variable_Subscription class not available.' );
		}

		$this->disabler->init_hooks();

		// Create variable subscription product.
		$variable_subscription = new WC_Product_Variable_Subscription();
		$variable_subscription->set_props(
			[
				'name' => 'Test Variable Subscription',
			]
		);
		$variable_subscription->save();

		// Verify variable subscription is not purchasable.
		$this->assertFalse(
			$variable_subscription->is_purchasable(),
			'Variable subscription product should not be purchasable'
		);

		// Cleanup.
		wp_delete_post( $variable_subscription->get_id(), true );
	}

	/**
	 * Verify that subscription products are filtered from admin product search.
	 */
	public function test_filter_admin_product_search() {
		if ( ! class_exists( 'WC_Product_Subscription' ) ) {
			$this->markTestSkipped( 'WC_Product_Subscription class not available.' );
		}

		$this->disabler->init_hooks();

		// Create subscription product.
		$subscription_product = new WC_Product_Subscription();
		$subscription_product->set_props(
			[
				'name'          => 'Test Subscription',
				'regular_price' => 10,
				'price'         => 10,
			]
		);
		$subscription_product->save();

		// Create regular product.
		$regular_product = WC_Helper_Product::create_simple_product();

		// Simulate product search results containing both products.
		$search_results = [
			$subscription_product->get_id() => 'Test Subscription',
			$regular_product->get_id()      => $regular_product->get_name(),
		];

		// Apply filter.
		$filtered_results = $this->disabler->filter_admin_product_search( $search_results );

		// Verify subscription product is removed.
		$this->assertArrayNotHasKey(
			$subscription_product->get_id(),
			$filtered_results,
			'Subscription product should be removed from search results'
		);

		// Verify regular product remains.
		$this->assertArrayHasKey(
			$regular_product->get_id(),
			$filtered_results,
			'Regular product should remain in search results'
		);

		// Cleanup.
		wp_delete_post( $subscription_product->get_id(), true );
		wp_delete_post( $regular_product->get_id(), true );
	}

	/**
	 * Verify that adding subscription products to admin orders is blocked with validation error.
	 */
	public function test_validate_admin_order_item_blocks_subscriptions() {
		if ( ! class_exists( 'WC_Product_Subscription' ) ) {
			$this->markTestSkipped( 'WC_Product_Subscription class not available.' );
		}

		$this->disabler->init_hooks();

		// Create subscription product.
		$subscription_product = new WC_Product_Subscription();
		$subscription_product->set_props(
			[
				'name'          => 'Test Subscription',
				'regular_price' => 10,
				'price'         => 10,
			]
		);
		$subscription_product->save();

		// Create mock order.
		$order = WC_Helper_Order::create_order();

		// Attempt to validate adding subscription to order.
		$validation_error = new WP_Error();
		$result           = $this->disabler->validate_admin_order_item(
			$validation_error,
			$subscription_product,
			$order,
			1
		);

		// Verify error is returned.
		$this->assertInstanceOf( 'WP_Error', $result, 'Should return WP_Error for subscription products' );
		$this->assertEquals(
			'subscription_not_allowed_in_admin_order',
			$result->get_error_code(),
			'Error code should match'
		);
		$this->assertStringContainsString(
			'Subscription products cannot be added to orders',
			$result->get_error_message(),
			'Error message should explain why subscription cannot be added'
		);

		// Cleanup.
		wp_delete_post( $subscription_product->get_id(), true );
		wp_delete_post( $order->get_id(), true );
	}

	/**
	 * Verify that regular products pass admin order validation.
	 */
	public function test_validate_admin_order_item_allows_regular_products() {
		$this->disabler->init_hooks();

		// Create regular product.
		$regular_product = WC_Helper_Product::create_simple_product();

		// Create mock order.
		$order = WC_Helper_Order::create_order();

		// Attempt to validate adding regular product to order.
		$validation_error = new WP_Error();
		$result           = $this->disabler->validate_admin_order_item(
			$validation_error,
			$regular_product,
			$order,
			1
		);

		// Verify no error is returned (should return the original empty WP_Error).
		$this->assertInstanceOf( 'WP_Error', $result, 'Should return WP_Error object' );
		$this->assertEmpty(
			$result->get_error_codes(),
			'Should not have any error codes for regular products'
		);

		// Cleanup.
		wp_delete_post( $regular_product->get_id(), true );
		wp_delete_post( $order->get_id(), true );
	}

	/**
	 * Verify that order-pay endpoint redirects when given a subscription ID.
	 */
	public function test_order_pay_redirects_for_subscription_id() {
		if ( ! class_exists( 'WC_Subscription' ) ) {
			$this->markTestSkipped( 'WC_Subscription class not available.' );
		}

		global $wp;

		// Create a subscription post.
		$subscription_id = $this->factory->post->create(
			[
				'post_type'   => 'shop_subscription',
				'post_status' => 'wc-active',
			]
		);

		// Simulate the order-pay endpoint with subscription ID.
		$wp->query_vars['order-pay'] = $subscription_id;

		$this->disabler->init_hooks();
		$this->disabler->maybe_redirect_account_endpoints();

		// Verify redirect occurred (should not be null).
		$this->assertNotNull(
			$this->disabler->redirected_to,
			'Should redirect to My Account when order-pay contains subscription ID'
		);

		// Cleanup.
		unset( $wp->query_vars['order-pay'] );
		wp_delete_post( $subscription_id, true );
	}

	/**
	 * Verify that order-pay endpoint does NOT redirect when given a regular order ID.
	 */
	public function test_order_pay_allows_regular_order_id() {
		global $wp;

		// Create a regular order.
		$order = WC_Helper_Order::create_order();

		// Simulate the order-pay endpoint with order ID.
		$wp->query_vars['order-pay'] = $order->get_id();

		$this->disabler->init_hooks();
		$this->disabler->maybe_redirect_account_endpoints();

		// Verify NO redirect occurred.
		$this->assertNull(
			$this->disabler->redirected_to,
			'Should NOT redirect when order-pay contains regular order ID'
		);

		// Cleanup.
		unset( $wp->query_vars['order-pay'] );
		wp_delete_post( $order->get_id(), true );
	}

	/**
	 * Verify that order-pay redirects when change_payment_method parameter contains subscription ID.
	 *
	 * This simulates the flow when WooCommerce Subscriptions redirects from
	 * /my-account/subscription-payment-method/765 to /checkout/order-pay/765/?change_payment_method=765
	 */
	public function test_order_pay_redirects_for_change_payment_method_with_subscription_id() {
		if ( ! class_exists( 'WC_Subscription' ) ) {
			$this->markTestSkipped( 'WC_Subscription class not available.' );
		}

		global $wp;

		// Create a subscription post.
		$subscription_id = $this->factory->post->create(
			[
				'post_type'   => 'shop_subscription',
				'post_status' => 'wc-active',
			]
		);

		// Create a regular order (could be the parent order or renewal order).
		$order = WC_Helper_Order::create_order();

		// Simulate the order-pay endpoint with change_payment_method parameter.
		// This is what WC Subscriptions does when redirecting.
		$wp->query_vars['order-pay']   = $order->get_id();
		$_GET['change_payment_method'] = $subscription_id;
		$_GET['pay_for_order']         = 'true';
		$_GET['key']                   = 'test_key';

		$this->disabler->init_hooks();
		$this->disabler->maybe_redirect_account_endpoints();

		// Verify redirect occurred due to subscription ID in change_payment_method (should not be null).
		$this->assertNotNull(
			$this->disabler->redirected_to,
			'Should redirect to My Account when change_payment_method parameter contains subscription ID'
		);

		// Cleanup.
		unset( $wp->query_vars['order-pay'], $_GET['change_payment_method'], $_GET['pay_for_order'], $_GET['key'] );
		wp_delete_post( $subscription_id, true );
		wp_delete_post( $order->get_id(), true );
	}

	/**
	 * Verify that order-pay does NOT redirect when change_payment_method contains order ID.
	 */
	public function test_order_pay_allows_change_payment_method_with_order_id() {
		global $wp;

		// Create two regular orders.
		$order1 = WC_Helper_Order::create_order();
		$order2 = WC_Helper_Order::create_order();

		// Simulate order-pay with change_payment_method for a regular order.
		$wp->query_vars['order-pay']   = $order1->get_id();
		$_GET['change_payment_method'] = $order2->get_id();
		$_GET['pay_for_order']         = 'true';
		$_GET['key']                   = 'test_key';

		$this->disabler->init_hooks();
		$this->disabler->maybe_redirect_account_endpoints();

		// Verify NO redirect occurred (regular order IDs are allowed).
		$this->assertNull(
			$this->disabler->redirected_to,
			'Should NOT redirect when change_payment_method contains regular order ID'
		);

		// Cleanup.
		unset( $wp->query_vars['order-pay'], $_GET['change_payment_method'], $_GET['pay_for_order'], $_GET['key'] );
		wp_delete_post( $order1->get_id(), true );
		wp_delete_post( $order2->get_id(), true );
	}

	/**
	 * Verify that subscription endpoints are redirected during pre_get_posts.
	 *
	 * This tests the early redirect that happens BEFORE WooCommerce Subscriptions
	 * can redirect to the order-pay endpoint.
	 */
	public function test_pre_get_posts_redirects_subscription_payment_method_endpoint() {
		update_option( 'woocommerce_myaccount_subscription_payment_method_endpoint', 'subscription-payment-method' );

		// Create a main query with subscription-payment-method endpoint.
		$query_args = [
			'subscription-payment-method' => 765,
		];
		$query      = new WP_Query( $query_args );

		// Need to manually set this since we're not running a full WordPress request.
		global $wp_the_query;
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Required for testing is_main_query() in isolation.
		$wp_the_query = $query;

		$this->disabler->init_hooks();
		$this->disabler->maybe_redirect_subscription_endpoints( $query );

		// Verify redirect occurred (should not be null).
		$this->assertNotNull(
			$this->disabler->redirected_to,
			'Should redirect subscription-payment-method endpoint during pre_get_posts'
		);

		// Clean up.
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restoring original state.
		$wp_the_query = null;
	}

	/**
	 * Verify that non-main queries are not redirected.
	 */
	public function test_pre_get_posts_ignores_non_main_queries() {
		update_option( 'woocommerce_myaccount_subscription_payment_method_endpoint', 'subscription-payment-method' );

		// Create a non-main query (not setting as global query).
		$query_args = [
			'subscription-payment-method' => 765,
		];
		$query      = new WP_Query( $query_args );

		// Don't set as main query - it's a secondary query.

		$this->disabler->init_hooks();
		$this->disabler->maybe_redirect_subscription_endpoints( $query );

		// Verify NO redirect occurred (non-main queries should be ignored).
		$this->assertNull(
			$this->disabler->redirected_to,
			'Should NOT redirect non-main queries'
		);
	}

	/**
	 * Verify that the Related Orders meta box is removed from order edit screens.
	 */
	public function test_remove_related_orders_meta_box_removes_subscription_meta_box() {
		global $wp_meta_boxes;

		// Mock the wcs_get_page_screen_id function.
		if ( ! function_exists( 'wcs_get_page_screen_id' ) ) {
			function wcs_get_page_screen_id( $type ) {
				return 'wc-orders--shop_order';
			}
		}

		$screen_id = 'wc-orders--shop_order';

		// Simulate WCS adding the Related Orders meta box.
		add_meta_box(
			'subscription_renewal_orders',
			'Related Orders',
			'__return_true',
			$screen_id,
			'normal',
			'low'
		);

		// Verify the meta box was added.
		$this->assertArrayHasKey(
			'subscription_renewal_orders',
			$wp_meta_boxes[ $screen_id ]['normal']['low'],
			'Related Orders meta box should be added by WCS'
		);

		// Call our remove method.
		$this->disabler->remove_related_orders_meta_box( 'shop_order', null );

		// Verify the meta box was removed (marked as false by remove_meta_box).
		// WordPress's remove_meta_box() sets the meta box to false rather than unsetting it.
		$this->assertFalse(
			$wp_meta_boxes[ $screen_id ]['normal']['low']['subscription_renewal_orders'],
			'Related Orders meta box should be removed (set to false) by disabler'
		);

		// Clean up global.
		unset( $wp_meta_boxes[ $screen_id ] );
	}

	/**
	 * Verify that remove_related_orders_meta_box does nothing when WCS functions are not available.
	 */
	public function test_remove_related_orders_meta_box_does_nothing_without_wcs() {
		global $wp_meta_boxes;

		// Temporarily rename the function to simulate it not existing.
		if ( function_exists( 'wcs_get_page_screen_id' ) ) {
			$this->markTestSkipped( 'Cannot test missing function when it exists in test environment' );
		}

		$screen_id = 'wc-orders--shop_order';

		// Add a meta box.
		add_meta_box(
			'subscription_renewal_orders',
			'Related Orders',
			'__return_true',
			$screen_id,
			'normal',
			'low'
		);

		// Verify the meta box was added.
		$this->assertArrayHasKey(
			'subscription_renewal_orders',
			$wp_meta_boxes[ $screen_id ]['normal']['low'],
			'Related Orders meta box should be added'
		);

		// Call our remove method (should do nothing without WCS functions).
		$this->disabler->remove_related_orders_meta_box( 'shop_order', null );

		// Verify the meta box was NOT removed (function should exit early).
		$this->assertArrayHasKey(
			'subscription_renewal_orders',
			$wp_meta_boxes[ $screen_id ]['normal']['low'],
			'Related Orders meta box should remain when WCS functions not available'
		);

		// Clean up global.
		unset( $wp_meta_boxes[ $screen_id ] );
	}
}
