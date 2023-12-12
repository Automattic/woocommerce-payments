<?php
/**
 * Class WC_Payments_Admin_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Database_Cache;

/**
 * WC_Payments_Admin unit tests.
 */
class WC_Payments_Admin_Test extends WCPAY_UnitTestCase {

	/**
	 * @var WC_Payments_Account|MockObject
	 */
	private $mock_account;

	/**
	 * @var WC_Payment_Gateway_WCPay|MockObject
	 */
	private $mock_gateway;

	/**
	 * Mock WC_Payments_API_Client.
	 *
	 * @var WC_Payments_API_Client|MockObject
	 */
	private $mock_api_client;

	/**
	 * Mock Onboarding Service.
	 *
	 * @var WC_Payments_Onboarding_Service|MockObject;
	 */
	private $mock_onboarding_service;

	/**
	 * Mock Order Service.
	 *
	 * @var WC_Payments_Order_Service|MockObject;
	 */
	private $mock_order_service;

	/**
	 * Mock Incentives Service.
	 *
	 * @var WC_Payments_Incentives_Service|MockObject;
	 */
	private $mock_incentives_service;

	/**
	 * Mock Fraud Service.
	 *
	 * @var WC_Payments_Fraud_Service|MockObject;
	 */
	private $mock_fraud_service;

	/**
	 * Mock database cache.
	 *
	 * @var Database_Cache|MockObject;
	 */
	private $mock_database_cache;

	/**
	 * @var WC_Payments_Admin
	 */
	private $payments_admin;

	public function set_up() {
		global $menu, $submenu;

		$menu    = null; // phpcs:ignore: WordPress.WP.GlobalVariablesOverride.Prohibited
		$submenu = null; // phpcs:ignore: WordPress.WP.GlobalVariablesOverride.Prohibited

		$this->mock_api_client = $this->getMockBuilder( WC_Payments_API_Client::class )
			->disableOriginalConstructor()
			->getMock();

		$this->mock_gateway = $this->getMockBuilder( WC_Payment_Gateway_WCPay::class )
			->disableOriginalConstructor()
			->getMock();

		$this->mock_account = $this->getMockBuilder( WC_Payments_Account::class )
			->disableOriginalConstructor()
			->getMock();

		$this->mock_onboarding_service = $this->getMockBuilder( WC_Payments_Onboarding_Service::class )
			->disableOriginalConstructor()
			->getMock();

		$this->mock_order_service = $this->getMockBuilder( WC_Payments_Order_Service::class )
			->disableOriginalConstructor()
			->getMock();

		$this->mock_incentives_service = $this->getMockBuilder( WC_Payments_Incentives_Service::class )
			->disableOriginalConstructor()
			->getMock();

		$this->mock_fraud_service = $this->getMockBuilder( WC_Payments_Fraud_Service::class )
			->disableOriginalConstructor()
			->getMock();

		$this->mock_database_cache = $this->getMockBuilder( Database_Cache::class )
			->disableOriginalConstructor()
			->getMock();

		$this->mock_account->method( 'get_capital' )->willReturn(
			[
				'loans'              => [],
				'has_active_loan'    => false,
				'has_previous_loans' => false,
			]
		);

		$this->payments_admin = new WC_Payments_Admin(
			$this->mock_api_client,
			$this->mock_gateway,
			$this->mock_account,
			$this->mock_onboarding_service,
			$this->mock_order_service,
			$this->mock_incentives_service,
			$this->mock_fraud_service,
			$this->mock_database_cache
		);
	}

	public function tear_down() {
		unset( $_GET );
		set_current_screen( 'front' );
		parent::tear_down();
	}

	/**
	 * @dataProvider feature_flag_combinations_not_causing_settings_badge_render_provider
	 *
	 * @param bool $is_upe_settings_preview_enabled
	 * @param bool $is_upe_enabled
	 */
	public function test_it_does_not_render_settings_badge( $is_upe_settings_preview_enabled, $is_upe_enabled ) {
		global $submenu;

		$this->mock_current_user_is_admin();

		// Make sure we render the menu with submenu items.
		$this->mock_account->method( 'is_account_fully_onboarded' )->willReturn( true );
		$this->mock_account->method( 'is_stripe_connected' )->willReturn( true );
		$this->payments_admin->add_payments_menu();

		$item_names_by_urls = wp_list_pluck( $submenu['wc-admin&path=/payments/overview'], 0, 2 );
		$settings_item_name = $item_names_by_urls[ WC_Payments_Admin_Settings::get_settings_url() ];

		$this->assertEquals( 'Settings', $settings_item_name );
	}

	public function test_it_does_not_render_payments_badge_if_stripe_is_connected() {
		global $menu;
		$this->mock_current_user_is_admin();

		// Make sure we render the menu with submenu items.
		$this->mock_account->method( 'is_account_fully_onboarded' )->willReturn( true );
		$this->mock_account->method( 'is_stripe_connected' )->willReturn( true );
		$this->payments_admin->add_payments_menu();

		$item_names_by_urls = wp_list_pluck( $menu, 0, 2 );
		$this->assertEquals( 'Payments', $item_names_by_urls['wc-admin&path=/payments/overview'] );
		$this->assertArrayNotHasKey( 'wc-admin&path=/payments/connect', $item_names_by_urls );
	}

	public function test_it_refreshes_the_cache_if_get_param_exists() {
		global $menu;
		$this->mock_current_user_is_admin();
		$_GET = [
			'page'                   => 'wc-admin',
			'path'                   => '/payments/overview',
			'wcpay-connection-error' => '1',
		];

		// Make sure we render the menu with submenu items.
		$this->mock_account->method( 'is_account_fully_onboarded' )->willReturn( true );
		$this->mock_account->method( 'is_stripe_connected' )->willReturn( true );
		$this->mock_account->expects( $this->once() )->method( 'refresh_account_data' );
		$this->payments_admin->add_payments_menu();

		$item_names_by_urls = wp_list_pluck( $menu, 0, 2 );
		$this->assertEquals( 'Payments', $item_names_by_urls['wc-admin&path=/payments/overview'] );
		$this->assertArrayNotHasKey( 'wc-admin&path=/payments/connect', $item_names_by_urls );
	}

	public function test_it_renders_payments_badge_if_activation_date_is_older_than_3_days_and_stripe_is_not_connected() {
		global $menu;
		$this->mock_current_user_is_admin();

		// Make sure we render the menu without submenu items.
		$this->mock_account->method( 'is_account_fully_onboarded' )->willReturn( false );
		$this->mock_account->method( 'is_stripe_connected' )->willReturn( false );
		update_option( 'wcpay_activation_timestamp', time() - ( 3 * DAY_IN_SECONDS ) );
		$this->payments_admin->add_payments_menu();

		$item_names_by_urls = wp_list_pluck( $menu, 0, 2 );
		$this->assertEquals( 'Payments' . WC_Payments_Admin::MENU_NOTIFICATION_BADGE, $item_names_by_urls['wc-admin&path=/payments/connect'] );
		$this->assertArrayNotHasKey( 'wc-admin&path=/payments/overview', $item_names_by_urls );
	}

	public function test_it_does_not_render_payments_badge_if_activation_date_is_less_than_3_days() {
		global $menu;
		$this->mock_current_user_is_admin();

		// Make sure we render the menu without submenu items.
		$this->mock_account->method( 'is_account_fully_onboarded' )->willReturn( false );
		$this->mock_account->method( 'is_stripe_connected' )->willReturn( false );
		update_option( 'wcpay_menu_badge_hidden', 'no' );
		update_option( 'wcpay_activation_timestamp', time() - ( DAY_IN_SECONDS * 2 ) );
		$this->payments_admin->add_payments_menu();

		$item_names_by_urls = wp_list_pluck( $menu, 0, 2 );
		$this->assertEquals( 'Payments', $item_names_by_urls['wc-admin&path=/payments/connect'] );
		$this->assertArrayNotHasKey( 'wc-admin&path=/payments/overview', $item_names_by_urls );
	}

	public function feature_flag_combinations_not_causing_settings_badge_render_provider() {
		return [
			[ false, false ],
			[ false, true ],
			[ true, false ],
			[ true, true ],
		];
	}

	private function mock_current_user_is_admin() {
		$admin_user = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_user );
	}

	/**
	 * @dataProvider data_maybe_redirect_to_onboarding
	 */
	public function test_maybe_redirect_to_onboarding( $expected_times_redirect_called, $is_stripe_connected, $get_params ) {
		$this->mock_current_user_is_admin();
		$_GET = $get_params;

		$this->mock_account
			->method( 'is_stripe_connected' )
			->willReturn( $is_stripe_connected );

		$this->mock_account
			->expects( $this->exactly( $expected_times_redirect_called ) )
			->method( 'redirect_to_onboarding_welcome_page' );

		$this->payments_admin->maybe_redirect_to_onboarding();
	}

	/**
	 * Data provider for test_maybe_redirect_to_onboarding
	 */
	public function data_maybe_redirect_to_onboarding() {
		return [
			'no_get_params'        => [
				0,
				false,
				[],
			],
			'empty_page_param'     => [
				0,
				false,
				[
					'path' => '/payments/overview',
				],
			],
			'incorrect_page_param' => [
				0,
				false,
				[
					'page' => 'wc-settings',
					'path' => '/payments/overview',
				],
			],
			'empty_path_param'     => [
				0,
				false,
				[
					'page' => 'wc-admin',
				],
			],
			'incorrect_path_param' => [
				0,
				false,
				[
					'page' => 'wc-admin',
					'path' => '/payments/does-not-exist',
				],
			],
			'stripe_connected'     => [
				0,
				true,
				[
					'page' => 'wc-admin',
					'path' => '/payments/overview',
				],
			],
			'happy_path'           => [
				1,
				false,
				[
					'page' => 'wc-admin',
					'path' => '/payments/overview',
				],
			],
		];
	}

	/**
	 * @dataProvider data_maybe_redirect_overview_to_connect
	 */
	public function test_maybe_redirect_overview_to_connect( $expected_times_redirect_called, $is_wc_registered_page, $get_params ) {
		global $wp_actions;
		$this->mock_current_user_is_admin();
		// Avoid WP doing_it_wrong warnings.
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$wp_actions['current_screen'] = true;

		$_GET = $get_params;

		// Register the Payments > Connect page as the top level menu item.
		wc_admin_register_page(
			[
				'id'         => 'wc-payments',
				'title'      => __( 'Payments', 'woocommerce-payments' ),
				'capability' => 'manage_woocommerce',
				'path'       => '/payments/connect',
				'position'   => '55.7', // After WooCommerce & Product menu items.
				'icon'       => '',
				'nav_args'   => [
					'title'        => 'WooPayments',
					'is_category'  => false,
					'menuId'       => 'plugins',
					'is_top_level' => true,
				],
			]
		);

		// Whether the current page should be treated as a registered WC admin page or not.
		if ( $is_wc_registered_page ) {
			add_filter( 'woocommerce_navigation_is_registered_page', '__return_true', 999 );
		}

		$this->mock_account
			->expects( $this->exactly( $expected_times_redirect_called ) )
			->method( 'redirect_to_onboarding_welcome_page' );

		$this->payments_admin->maybe_redirect_overview_to_connect();

		remove_filter( 'woocommerce_navigation_is_registered_page', '__return_true', 999 );
	}

	/**
	 * Data provider for test_maybe_redirect_overview_to_connect
	 */
	public function data_maybe_redirect_overview_to_connect() {
		return [
			'no_get_params'        => [
				0,
				false,
				[],
			],
			'empty_page_param'     => [
				0,
				false,
				[
					'path' => '/payments/overview',
				],
			],
			'incorrect_page_param' => [
				0,
				false,
				[
					'page' => 'wc-settings',
					'path' => '/payments/overview',
				],
			],
			'empty_path_param'     => [
				0,
				false,
				[
					'page' => 'wc-admin',
				],
			],
			'incorrect_path_param' => [
				0,
				false,
				[
					'page' => 'wc-admin',
					'path' => '/payments/does-not-exist',
				],
			],
			'wc registered page'   => [
				0,
				true,
				[
					'page' => 'wc-admin',
					'path' => '/payments/overview',
				],
			],
			'happy_path'           => [
				1,
				false,
				[
					'page' => 'wc-admin',
					'path' => '/payments/overview',
				],
			],
		];
	}

	/**
	 * @dataProvider data_maybe_redirect_onboarding_flow_to_connect
	 */
	public function test_maybe_redirect_onboarding_flow_to_connect( $expected_times_redirect_called, $is_server_connected, $get_params ) {
		$this->mock_current_user_is_admin();
		$_GET = $get_params;

		$this->mock_api_client
			->method( 'is_server_connected' )
			->willReturn( $is_server_connected );

		$this->mock_account
			->expects( $this->exactly( $expected_times_redirect_called ) )
			->method( 'redirect_to_onboarding_welcome_page' );

		$this->payments_admin->maybe_redirect_onboarding_flow_to_connect();
	}

	/**
	 * Data provider for test_maybe_redirect_onboarding_flow_to_connect
	 */
	public function data_maybe_redirect_onboarding_flow_to_connect() {
		return [
			'no_get_params'        => [
				0,
				false,
				[],
			],
			'empty_page_param'     => [
				0,
				false,
				[
					'path' => '/payments/onboarding',
				],
			],
			'incorrect_page_param' => [
				0,
				false,
				[
					'page' => 'wc-settings',
					'path' => '/payments/onboarding',
				],
			],
			'empty_path_param'     => [
				0,
				false,
				[
					'page' => 'wc-admin',
				],
			],
			'incorrect_path_param' => [
				0,
				false,
				[
					'page' => 'wc-admin',
					'path' => '/payments/does-not-exist',
				],
			],
			'server_connected'     => [
				0,
				true,
				[
					'page' => 'wc-admin',
					'path' => '/payments/onboarding',
				],
			],
			'happy_path'           => [
				1,
				false,
				[
					'page' => 'wc-admin',
					'path' => '/payments/onboarding',
				],
			],
		];
	}

	/**
	 * Tests WC_Payments_Admin::add_disputes_notification_badge()
	 */
	public function test_disputes_notification_badge_display() {
		global $submenu;

		// Mock the database cache returning a set of disputes.
		$this->mock_database_cache
			->expects( $this->once() )
			->method( 'get_or_add' )
			->willReturn(
				[
					'needs_response'         => 1,
					'warning_needs_response' => 3,
					'won'                    => 2,
					'lost'                   => 10,
				]
			);

		$this->mock_current_user_is_admin();

		// Make sure we render the menu with submenu items.
		$this->mock_account->method( 'is_account_fully_onboarded' )->willReturn( true );
		$this->mock_account->method( 'is_stripe_connected' )->willReturn( true );
		$this->payments_admin->add_payments_menu();

		$item_names_by_urls = wp_list_pluck( $submenu[ WC_Payments_Admin::PAYMENTS_SUBMENU_SLUG ], 0, 2 );
		$dispute_query_args = [
			'page'   => 'wc-admin',
			'path'   => '%2Fpayments%2Fdisputes',
			'filter' => 'awaiting_response',
		];

		$dispute_url = admin_url( add_query_arg( $dispute_query_args, 'admin.php' ) );

		// Assert the submenu includes a disputes item that links directly to the disputes screen with the awaiting_response filter.
		$this->assertArrayHasKey( $dispute_url, $item_names_by_urls );

		// The expected badge content should include 4 disputes needing a response.
		$expected_badge = sprintf( WC_Payments_Admin::UNRESOLVED_NOTIFICATION_BADGE_FORMAT, 4 );

		$this->assertSame( 'Disputes' . $expected_badge, $item_names_by_urls[ $dispute_url ] );
	}

	/**
	 * Tests WC_Payments_Admin::add_disputes_notification_badge()
	 */
	public function test_disputes_notification_badge_no_display() {
		global $submenu;

		// Mock the database cache returning a set of disputes.
		$this->mock_database_cache
			->expects( $this->once() )
			->method( 'get_or_add' )
			->willReturn(
				[
					'won'  => 1,
					'lost' => 3,
				]
			);

		$this->mock_current_user_is_admin();

		// Make sure we render the menu with submenu items.
		$this->mock_account->method( 'is_account_fully_onboarded' )->willReturn( true );
		$this->mock_account->method( 'is_stripe_connected' )->willReturn( true );
		$this->payments_admin->add_payments_menu();

		$item_names_by_urls = wp_list_pluck( $submenu[ WC_Payments_Admin::PAYMENTS_SUBMENU_SLUG ], 0, 2 );
		$dispute_menu_item  = $item_names_by_urls['wc-admin&path=/payments/disputes'];

		$this->assertEquals( 'Disputes', $dispute_menu_item );
	}

	/**
	 * Tests WC_Payments_Admin::add_transactions_notification_badge()
	 */
	public function test_transactions_notification_badge_display() {
		global $submenu;

		update_option( \WC_Payments_Features::AUTH_AND_CAPTURE_FLAG_NAME, '1' );

		// Mock the manual capture setting as being enabled.
		$this->mock_gateway
			->expects( $this->once() )
			->method( 'get_option' )
			->with( 'manual_capture' )
			->willReturn( 'yes' );

		// Mock the database cache returning authorizations summary.
		$this->mock_database_cache
			->expects( $this->any() )
			->method( 'get_or_add' )
			->willReturn(
				[
					'count'          => 3,
					'currency'       => 'usd',
					'total'          => 5400,
					'all_currencies' => [
						'eur',
						'usd',
					],
				]
			);

		$this->mock_current_user_is_admin();

		// Make sure we render the menu with submenu items.
		$this->mock_account->method( 'is_account_fully_onboarded' )->willReturn( true );
		$this->mock_account->method( 'is_stripe_connected' )->willReturn( true );
		$this->payments_admin->add_payments_menu();

		$item_names_by_urls = wp_list_pluck( $submenu[ WC_Payments_Admin::PAYMENTS_SUBMENU_SLUG ], 0, 2 );

		$transactions_url = 'wc-admin&path=/payments/transactions';

		// Assert the submenu includes a transactions item that links directly to the Transactions screen.
		$this->assertArrayHasKey( $transactions_url, $item_names_by_urls );

		// The expected badge content should include 3 uncaptured transactions.
		$expected_badge = sprintf( WC_Payments_Admin::UNRESOLVED_NOTIFICATION_BADGE_FORMAT, 3 );

		$this->assertSame( 'Transactions' . $expected_badge, $item_names_by_urls[ $transactions_url ] );
	}

	/**
	 * Tests WC_Payments_Admin::add_transactions_notification_badge()
	 */
	public function test_transactions_notification_badge_no_display() {
		global $submenu;

		update_option( \WC_Payments_Features::AUTH_AND_CAPTURE_FLAG_NAME, '1' );

		// Mock the manual capture setting as being enabled.
		$this->mock_gateway
			->expects( $this->once() )
			->method( 'get_option' )
			->with( 'manual_capture' )
			->willReturn( 'yes' );

		// Mock the database cache returning authorizations summary.
		$this->mock_database_cache
			->expects( $this->any() )
			->method( 'get_or_add' )
			->willReturn(
				[
					'count' => 0,
					'total' => 0,
				]
			);

		$this->mock_current_user_is_admin();

		// Make sure we render the menu with submenu items.
		$this->mock_account->method( 'is_account_fully_onboarded' )->willReturn( true );
		$this->mock_account->method( 'is_stripe_connected' )->willReturn( true );
		$this->payments_admin->add_payments_menu();

		$item_names_by_urls     = wp_list_pluck( $submenu[ WC_Payments_Admin::PAYMENTS_SUBMENU_SLUG ], 0, 2 );
		$transactions_menu_item = $item_names_by_urls['wc-admin&path=/payments/transactions'];

		$this->assertSame( 'Transactions', $transactions_menu_item );
	}
}
