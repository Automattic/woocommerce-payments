<?php
/**
 * Class WC_Payments_Admin_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;

/**
 * WC_Payments_Admin unit tests.
 */
class WC_Payments_Admin_Test extends WP_UnitTestCase {

	/**
	 * @var WC_Payments_Account|MockObject
	 */
	private $mock_account;

	/**
	 * @var WC_Payment_Gateway_WCPay|MockObject
	 */
	private $mock_gateway;

	/**
	 * @var WC_Payments_Admin
	 */
	private $payments_admin;

	public function setUp() {
		global $menu, $submenu;

		$menu    = null;
		$submenu = null;

		$mock_api_client = $this->getMockBuilder( WC_Payments_API_Client::class )
			->disableOriginalConstructor()
			->getMock();

		$this->mock_gateway = $this->getMockBuilder( WC_Payment_Gateway_WCPay::class )
			->disableOriginalConstructor()
			->getMock();

		$this->mock_account = $this->getMockBuilder( WC_Payments_Account::class )
			->disableOriginalConstructor()
			->getMock();

		$this->payments_admin = new WC_Payments_Admin( $mock_api_client, $this->mock_gateway, $this->mock_account );
	}

	public function tearDown() {
		unset( $_GET );
		set_current_screen( 'front' );
		parent::tearDown();
	}

	public function test_it_renders_settings_badge_if_upe_settings_preview_is_enabled_and_upe_is_not() {
		global $submenu;

		$this->mock_current_user_is_admin();

		update_option( '_wcpay_feature_upe_settings_preview', '1' );
		update_option( '_wcpay_feature_upe', '0' );

		// Make sure we render the menu with submenu items.
		$this->mock_account->method( 'try_is_stripe_connected' )->willReturn( true );
		$this->payments_admin->add_payments_menu();

		$item_names_by_urls = wp_list_pluck( $submenu['wc-admin&path=/payments/overview'], 0, 2 );
		$settings_item_name = $item_names_by_urls[ WC_Payment_Gateway_WCPay::get_settings_url() ];

		$this->assertEquals( 'Settings' . WC_Payments_Admin::MENU_NOTIFICATION_BADGE, $settings_item_name );
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

		update_option( '_wcpay_feature_upe_settings_preview', $is_upe_settings_preview_enabled ? '1' : '0' );
		update_option( '_wcpay_feature_upe', $is_upe_enabled ? '1' : '0' );

		// Make sure we render the menu with submenu items.
		$this->mock_account->method( 'try_is_stripe_connected' )->willReturn( true );
		$this->payments_admin->add_payments_menu();

		$item_names_by_urls = wp_list_pluck( $submenu['wc-admin&path=/payments/overview'], 0, 2 );
		$settings_item_name = $item_names_by_urls[ WC_Payment_Gateway_WCPay::get_settings_url() ];

		$this->assertEquals( 'Settings', $settings_item_name );
	}

	public function test_it_does_not_render_payments_badge_if_stripe_is_connected() {
		global $menu;
		$this->mock_current_user_is_admin();

		// Make sure we render the menu with submenu items.
		$this->mock_account->method( 'try_is_stripe_connected' )->willReturn( true );
		$this->payments_admin->add_payments_menu();

		$item_names_by_urls = wp_list_pluck( $menu, 0, 2 );
		$this->assertEquals( 'Payments', $item_names_by_urls['wc-admin&path=/payments/overview'] );
		$this->assertArrayNotHasKey( 'wc-admin&path=/payments/connect', $item_names_by_urls );
	}

	public function test_it_renders_payments_badge_if_activation_date_is_older_than_3_days_and_stripe_is_not_connected() {
		global $menu;
		$this->mock_current_user_is_admin();

		// Make sure we render the menu with submenu items.
		$this->mock_account->method( 'try_is_stripe_connected' )->willReturn( false );
		update_option( 'wcpay_activation_timestamp', time() - ( 3 * DAY_IN_SECONDS ) );
		$this->payments_admin->add_payments_menu();

		$item_names_by_urls = wp_list_pluck( $menu, 0, 2 );
		$this->assertEquals( 'Payments' . WC_Payments_Admin::MENU_NOTIFICATION_BADGE, $item_names_by_urls['wc-admin&path=/payments/connect'] );
		$this->assertArrayNotHasKey( 'wc-admin&path=/payments/overview', $item_names_by_urls );
	}

	public function test_it_does_not_render_payments_badge_if_activation_date_is_less_than_3_days() {
		global $menu;
		$this->mock_current_user_is_admin();

		// Make sure we render the menu with submenu items.
		$this->mock_account->method( 'try_is_stripe_connected' )->willReturn( false );
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
		$_GET = $get_params;

		$this->mock_account
			->method( 'is_stripe_connected' )
			->willReturn( $is_stripe_connected );

		$this->mock_account
			->expects( $this->exactly( $expected_times_redirect_called ) )
			->method( 'redirect_to_onboarding_page' );

		$this->payments_admin->maybe_redirect_to_onboarding();
	}

	public function test_it_should_show_apm_setup_if_on_wc_admin_page_and_upe_settings_preview_is_enabled() {
		$this->mock_account->method( 'is_stripe_connected' )->willReturn( true );
		$this->set_up_show_apm_task_test( true, true, [ 'foo' ] );

		$this->assertTrue( $this->payments_admin->is_page_eligible_for_additional_methods_setup_task() );
	}

	public function test_it_should_show_apm_setup_if_on_wc_admin_page_and_has_multiple_available_methods() {
		$this->mock_account->method( 'is_stripe_connected' )->willReturn( true );
		$this->set_up_show_apm_task_test( true, false, [ 'foo', 'bar' ] );

		$this->assertTrue( $this->payments_admin->is_page_eligible_for_additional_methods_setup_task() );
	}

	public function test_it_should_not_show_apm_setup_if_not_on_wc_admin_page() {
		$this->mock_account->method( 'is_stripe_connected' )->willReturn( true );
		$this->set_up_show_apm_task_test( false, true, [ 'foo', 'bar' ] );

		$this->assertFalse( $this->payments_admin->is_page_eligible_for_additional_methods_setup_task() );
	}

	public function test_it_should_not_show_apm_setup_if_single_method_is_available() {
		$this->mock_account->method( 'is_stripe_connected' )->willReturn( true );
		$this->set_up_show_apm_task_test( true, false, [ 'foo' ] );

		$this->assertFalse( $this->payments_admin->is_page_eligible_for_additional_methods_setup_task() );
	}

	public function test_it_should_not_show_apm_setup_if_account_is_disconnected() {
		$this->mock_account->method( 'is_stripe_connected' )->willReturn( false );
		$this->set_up_show_apm_task_test( true, true, [ 'foo' ] );

		$this->assertFalse( $this->payments_admin->is_page_eligible_for_additional_methods_setup_task() );
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

	private function set_up_show_apm_task_test( $is_wc_admin_page, $is_upe_preview_enabled, $available_methods ) {
		set_current_screen( 'foo' );

		add_filter(
			'woocommerce_navigation_is_registered_page',
			function () use ( $is_wc_admin_page ) {
				return $is_wc_admin_page;
			}
		);
		update_option( '_wcpay_feature_upe_settings_preview', $is_upe_preview_enabled ? '1' : '0' );
		$this->mock_gateway->method( 'get_upe_available_payment_methods' )->willReturn( $available_methods );
	}
}
