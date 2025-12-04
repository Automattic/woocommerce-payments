<?php
/**
 * Class WC_Payments_Admin_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Database_Cache;
use Automattic\Jetpack\Constants;

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
	 * Mock PM Promotions Service.
	 *
	 * @var WC_Payments_PM_Promotions_Service|MockObject;
	 */
	private $mock_pm_promotions_service;

	/**
	 * Mock database cache.
	 *
	 * @var Database_Cache|MockObject;
	 */
	private $mock_database_cache;

	/**
	 * Backup object of $GLOBALS['current_screen'].
	 *
	 * @var object
	 */
	private $current_screen_backup;

	/**
	 * @var WC_Payments_Admin
	 */
	private $payments_admin;

	public function set_up() {
		global $menu, $submenu;

		$menu    = null; // phpcs:ignore: WordPress.WP.GlobalVariablesOverride.Prohibited
		$submenu = null; // phpcs:ignore: WordPress.WP.GlobalVariablesOverride.Prohibited

		// Mock screen.
		$this->current_screen_backup = $GLOBALS['current_screen'] ?? null;
		$GLOBALS['current_screen']   = $this->get_screen_mock(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		if ( ! did_action( 'current_screen' ) ) {
			do_action( 'current_screen', $GLOBALS['current_screen'] ); // phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingHookComment
		}

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

		$this->mock_pm_promotions_service = $this->getMockBuilder( WC_Payments_PM_Promotions_Service::class )
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
			$this->mock_pm_promotions_service,
			$this->mock_fraud_service,
			$this->mock_database_cache
		);
	}

	public function tear_down() {
		// Restore screen backup.
		if ( $this->current_screen_backup ) {
			$GLOBALS['current_screen'] = $this->current_screen_backup; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		}

		parent::tear_down();
	}

	public function test_it_does_not_render_settings_badge(): void {
		global $submenu;

		$this->mock_current_user_is_admin();

		// Make sure we render the menu with submenu items.
		$this->mock_account->method( 'is_stripe_account_valid' )->willReturn( true );
		$this->mock_account->method( 'has_working_jetpack_connection' )->willReturn( true );
		$this->payments_admin->add_payments_menu();

		$item_names_by_urls = wp_list_pluck( $submenu['wc-admin&path=/payments/overview'], 0, 2 );
		$settings_item_name = $item_names_by_urls[ WC_Payments_Admin_Settings::get_settings_url() ];

		$this->assertEquals( 'Settings', $settings_item_name );
	}

	public function test_it_does_not_render_payments_badge_if_stripe_is_connected() {
		global $menu;
		$this->mock_current_user_is_admin();

		// Make sure we render the menu with submenu items.
		$this->mock_account->method( 'is_stripe_account_valid' )->willReturn( true );
		$this->mock_account->method( 'has_working_jetpack_connection' )->willReturn( true );
		$this->payments_admin->add_payments_menu();

		$item_names_by_urls = wp_list_pluck( $menu, 0, 2 );
		$this->assertEquals( 'Payments', $item_names_by_urls['wc-admin&path=/payments/overview'] );
		$this->assertArrayNotHasKey( 'wc-admin&path=/payments/connect', $item_names_by_urls );
	}

	public function test_it_renders_payments_badge_if_activation_date_is_older_than_3_days_and_stripe_is_not_connected() {
		global $menu;
		$this->mock_current_user_is_admin();

		// Make sure we render the menu without submenu items.
		$this->mock_account->method( 'is_stripe_account_valid' )->willReturn( false );
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
		$this->mock_account->method( 'is_stripe_account_valid' )->willReturn( false );
		update_option( 'wcpay_menu_badge_hidden', 'no' );
		update_option( 'wcpay_activation_timestamp', time() - ( DAY_IN_SECONDS * 2 ) );
		$this->payments_admin->add_payments_menu();

		$item_names_by_urls = wp_list_pluck( $menu, 0, 2 );
		$this->assertEquals( 'Payments', $item_names_by_urls['wc-admin&path=/payments/connect'] );
		$this->assertArrayNotHasKey( 'wc-admin&path=/payments/overview', $item_names_by_urls );
	}

	private function mock_current_user_is_admin() {
		$admin_user = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_user );
	}

	/**
	 * @dataProvider data_maybe_redirect_from_payments_admin_child_pages
	 */
	public function test_maybe_redirect_from_payments_admin_child_pages( $expected_times_redirect_called, $has_working_jetpack_connection, $is_stripe_account_valid, $get_params ) {
		$this->mock_current_user_is_admin();
		$this->payments_admin->add_payments_menu();

		$_GET = $get_params;

		$this->mock_account
			->method( 'has_working_jetpack_connection' )
			->willReturn( $has_working_jetpack_connection );

		$this->mock_account
			->method( 'is_stripe_account_valid' )
			->willReturn( $is_stripe_account_valid );

		$this->mock_account
			->expects( $this->exactly( $expected_times_redirect_called ) )
			->method( 'redirect_to_onboarding_welcome_page' );

		$this->payments_admin->maybe_redirect_from_payments_admin_child_pages();
	}

	/**
	 * Data provider for test_maybe_redirect_from_payments_admin_child_pages
	 */
	public function data_maybe_redirect_from_payments_admin_child_pages() {
		return [
			'no_get_params'        => [
				0,
				false,
				false,
				[],
			],
			'empty_page_param'     => [
				0,
				false,
				false,
				[
					'path' => '/payments/overview',
				],
			],
			'incorrect_page_param' => [
				0,
				false,
				false,
				[
					'page' => 'wc-settings',
					'path' => '/payments/overview',
				],
			],
			'empty_path_param'     => [
				0,
				false,
				false,
				[
					'page' => 'wc-admin',
				],
			],
			'incorrect_path_param' => [
				0,
				false,
				false,
				[
					'page' => 'wc-admin',
					'path' => '/payments/does-not-exist',
				],
			],
			'working Jetpack connection - invalid Stripe account' => [
				1,
				true,
				false,
				[
					'page' => 'wc-admin',
					'path' => '/payments/payouts',
				],
			],
			'not working Jetpack connection - valid Stripe account' => [
				1,
				false,
				true,
				[
					'page' => 'wc-admin',
					'path' => '/payments/payouts',
				],
			],
			'working Jetpack connection - valid Stripe account' => [
				0,
				true,
				true,
				[
					'page' => 'wc-admin',
					'path' => '/payments/transactions',
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
		$this->mock_account->method( 'is_stripe_account_valid' )->willReturn( true );
		$this->mock_account->method( 'has_working_jetpack_connection' )->willReturn( true );
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
		$this->mock_account->method( 'is_stripe_account_valid' )->willReturn( true );
		$this->mock_account->method( 'has_working_jetpack_connection' )->willReturn( true );
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
		$this->mock_account->method( 'is_stripe_account_valid' )->willReturn( true );
		$this->mock_account->method( 'has_working_jetpack_connection' )->willReturn( true );
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
		$this->mock_account->method( 'is_stripe_account_valid' )->willReturn( true );
		$this->mock_account->method( 'has_working_jetpack_connection' )->willReturn( true );
		$this->payments_admin->add_payments_menu();

		$item_names_by_urls     = wp_list_pluck( $submenu[ WC_Payments_Admin::PAYMENTS_SUBMENU_SLUG ], 0, 2 );
		$transactions_menu_item = $item_names_by_urls['wc-admin&path=/payments/transactions'];

		$this->assertSame( 'Transactions', $transactions_menu_item );
	}

	public function test_enqueue_wc_payment_settings_spotlight_does_not_enqueue_on_wrong_page() {
		global $wp_scripts, $wp_styles;

		// Arrange.
		$wp_scripts = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$wp_styles  = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		$_GET['page'] = 'wc-payments';
		$_GET['tab']  = 'products'; // Wrong WC settings tab.

		// Mock the current screen.
		$GLOBALS['current_screen']->id = 'woocommerce_page_wc-settings';

		// Mock the WooCommerce version to be at the minimum required version.
		Constants::set_constant( 'WC_VERSION', '9.9.2' );

		// Act.
		$this->payments_admin->enqueue_wc_payment_settings_spotlight();

		// Assert.
		$this->assertFalse( wp_script_is( 'WCPAY_WC_PAYMENTS_SETTINGS_SPOTLIGHT', 'enqueued' ) );
		$this->assertFalse( wp_style_is( 'WCPAY_WC_PAYMENTS_SETTINGS_SPOTLIGHT', 'enqueued' ) );

		// Clean up.
		unset( $_GET['page'], $_GET['tab'] );
		Constants::clear_constants();
	}

	public function test_enqueue_wc_payment_settings_spotlight_does_not_enqueue_on_old_wc_version() {
		global $wp_scripts, $wp_styles;

		// Arrange.
		$wp_scripts = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$wp_styles  = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		$_GET['page'] = 'wc-payments';
		$_GET['tab']  = 'checkout';

		// Mock the current screen.
		$GLOBALS['current_screen']->id = 'woocommerce_page_wc-settings';

		// Mock the WooCommerce version to NOT be at the minimum required version.
		Constants::set_constant( 'WC_VERSION', '9.9.1' );

		// Act.
		$this->payments_admin->enqueue_wc_payment_settings_spotlight();

		// Assert.
		$this->assertFalse( wp_script_is( 'WCPAY_WC_PAYMENTS_SETTINGS_SPOTLIGHT', 'enqueued' ) );
		$this->assertFalse( wp_style_is( 'WCPAY_WC_PAYMENTS_SETTINGS_SPOTLIGHT', 'enqueued' ) );

		// Clean up.
		unset( $_GET['page'], $_GET['tab'] );
		Constants::clear_constants();
	}

	/**
	 * Returns an object mocking what we need from \WP_Screen.
	 *
	 * @return object
	 */
	private function get_screen_mock(): object {
		$screen_mock = $this->getMockBuilder( \stdClass::class )->setMethods( [ 'in_admin', 'add_option' ] )->getMock();
		$screen_mock->method( 'in_admin' )->willReturn( true );
		foreach ( [ 'id', 'base', 'action', 'post_type' ] as $key ) {
			$screen_mock->{$key} = '';
		}

		return $screen_mock;
	}
}
