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
	 * Verify that admin subscription screens are redirected away.
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
}
