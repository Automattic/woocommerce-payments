<?php
/**
 * Class WC_Payments_Account_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use Automattic\WooCommerce\Admin\Notes\Notes;
use WCPay\Core\Server\Request\Get_Account;
use WCPay\Core\Server\Request\Get_Request;
use WCPay\Core\Server\Request\Update_Account;
use WCPay\Core\Server\Response;
use WCPay\Exceptions\API_Exception;
use WCPay\Database_Cache;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * WC_Payments_Account unit tests.
 */
class WC_Payments_Account_Test extends WCPAY_UnitTestCase {

	const NO_REQUIREMENTS      = false;
	const PENDING_REQUIREMENTS = true;

	/**
	 * System under test.
	 *
	 * @var WC_Payments_Account
	 */
	private $wcpay_account;

	/**
	 * Mock WC_Payments_API_Client.
	 *
	 * @var WC_Payments_API_Client|MockObject
	 */
	private $mock_api_client;

	/**
	 * Mock Database_Cache
	 *
	 * @var Database_Cache|MockObject
	 */
	private $mock_database_cache;

	/**
	 * Mock WC_Payments_Action_Scheduler_Service
	 *
	 * @var WC_Payments_Action_Scheduler_Service|MockObject
	 */
	private $mock_action_scheduler_service;

	/**
	 * Mock WC_Payments_Onboarding_Service.
	 *
	 * @var WC_Payments_Onboarding_Service|MockObject
	 */
	private $mock_onboarding_service;

	/**
	 * Mock WC_Payments_Redirect_Service.
	 *
	 * @var WC_Payments_Redirect_Service|MockObject
	 */
	private $mock_redirect_service;

	/**
	 * Pre-test setup
	 */
	public function set_up() {
		parent::set_up();

		// Set the request as if the user was in the Payments onboarding page so the "wp_redirect(); exit();" code doesn't run.
		$_GET = [
			'page' => 'wc-admin',
			'path' => '/payments/connect',
		];

		// Always start off with live mode. If you want another mode, you should set it in the test.
		WC_Payments::mode()->live();

		$this->mock_api_client               = $this->createMock( WC_Payments_API_Client::class );
		$this->mock_database_cache           = $this->createMock( Database_Cache::class );
		$this->mock_action_scheduler_service = $this->createMock( WC_Payments_Action_Scheduler_Service::class );
		$this->mock_onboarding_service       = $this->createMock( WC_Payments_Onboarding_Service::class );
		$this->mock_redirect_service         = $this->createMock( WC_Payments_Redirect_Service::class );

		$this->wcpay_account = new WC_Payments_Account( $this->mock_api_client, $this->mock_database_cache, $this->mock_action_scheduler_service, $this->mock_onboarding_service, $this->mock_redirect_service );
		$this->wcpay_account->init_hooks();
	}

	public function tear_down() {
		delete_transient( WC_Payments_Account::ONBOARDING_DISABLED_TRANSIENT );
		delete_option( WC_Payments_Onboarding_Service::TEST_MODE_OPTION );
		unset( $_GET );
		unset( $_REQUEST );
		parent::tear_down();
		delete_option( '_wcpay_feature_embedded_kyc' );
	}

	public function test_filters_registered_properly() {
		$this->assertNotFalse( has_action( 'admin_init', [ $this->wcpay_account, 'maybe_handle_onboarding' ] ), 'maybe_handle_onboarding action does not exist.' );
		$this->assertNotFalse( has_action( 'admin_init', [ $this->wcpay_account, 'maybe_redirect_after_plugin_activation' ] ), 'maybe_redirect_after_plugin_activation action does not exist.' );
		$this->assertNotFalse( has_action( 'admin_init', [ $this->wcpay_account, 'maybe_redirect_by_get_param' ] ), 'maybe_redirect_by_get_param action does not exist.' );
		$this->assertNotFalse( has_action( 'admin_init', [ $this->wcpay_account, 'maybe_redirect_from_settings_page' ] ), 'maybe_redirect_from_settings_page action does not exist.' );
		$this->assertNotFalse( has_action( 'admin_init', [ $this->wcpay_account, 'maybe_redirect_from_onboarding_wizard_page' ] ), 'maybe_redirect_from_onboarding_page action does not exist.' );
		$this->assertNotFalse( has_action( 'admin_init', [ $this->wcpay_account, 'maybe_redirect_from_connect_page' ] ), 'maybe_redirect_from_connect_page action does not exist.' );
		$this->assertNotFalse( has_action( 'admin_init', [ $this->wcpay_account, 'maybe_redirect_from_overview_page' ] ), 'maybe_redirect_from_overview_page action does not exist.' );
		$this->assertNotFalse( has_action( 'admin_init', [ $this->wcpay_account, 'maybe_activate_woopay' ] ), 'maybe_activate_woopay action does not exist.' );
		$this->assertNotFalse( has_action( 'woocommerce_payments_account_refreshed', [ $this->wcpay_account, 'handle_instant_deposits_inbox_note' ] ), 'handle_instant_deposits_inbox_note action does not exist.' );
		$this->assertNotFalse( has_action( 'woocommerce_payments_account_refreshed', [ $this->wcpay_account, 'handle_loan_approved_inbox_note' ] ), 'handle_loan_approved_inbox_note action does not exist.' );
		$this->assertNotFalse( has_action( 'wcpay_instant_deposit_reminder', [ $this->wcpay_account, 'handle_instant_deposits_inbox_reminder' ] ), 'handle_instant_deposits_inbox_reminder action does not exist.' );
		$this->assertNotFalse( has_filter( 'allowed_redirect_hosts', [ $this->wcpay_account, 'allowed_redirect_hosts' ] ), 'allowed_redirect_hooks filter does not exist.' );
		$this->assertNotFalse( has_action( 'jetpack_site_registered', [ $this->wcpay_account, 'clear_cache' ] ), 'jetpack_site_registered action does not exist.' );
		$this->assertNotFalse( has_action( 'updated_option', [ $this->wcpay_account, 'possibly_update_wcpay_account_locale' ] ), 'updated_option action does not exist.' );
		$this->assertNotFalse( has_action( 'woocommerce_woocommerce_payments_updated', [ $this->wcpay_account, 'clear_cache' ] ), 'woocommerce_woocommerce_payments_updated action does not exist.' );
	}

	public function test_maybe_handle_onboarding_unauthorized_user() {
		// Arrange.
		// We need to be in the WP admin dashboard.
		$this->set_is_admin( true );
		// Test as an editor user.
		$editor_user = $this->factory()->user->create( [ 'role' => 'editor' ] );
		wp_set_current_user( $editor_user );

		$_REQUEST['_wpnonce'] = wp_create_nonce( 'wcpay-login' );

		// Assert.
		$this->mock_redirect_service->expects( $this->never() )->method( 'redirect_to' );

		// Act.
		$this->wcpay_account->maybe_handle_onboarding();
	}

	public function test_maybe_handle_onboarding_stripe_login_links_partially_onboarded_account() {
		// Arrange.
		// We need to be in the WP admin dashboard.
		$this->set_is_admin( true );
		// Test as an admin user.
		wp_set_current_user( 1 );

		$_GET['wcpay-login']  = '1';
		$_REQUEST['_wpnonce'] = wp_create_nonce( 'wcpay-login' );

		// The WPCOM/Jetpack connection is in working order.
		$this->mock_jetpack_connection();

		$this->cache_account_details(
			[
				'account_id'        => 'acc_test',
				'is_live'           => true,
				'details_submitted' => false, // Hasn't finished initial KYC.
			]
		);

		// Assert.
		$this->mock_redirect_service->expects( $this->once() )->method( 'redirect_to_account_link' );

		// Act.
		$this->wcpay_account->maybe_handle_onboarding();
	}

	public function test_maybe_handle_onboarding_stripe_login_links() {
		// Arrange.
		// We need to be in the WP admin dashboard.
		$this->set_is_admin( true );
		// Test as an admin user.
		wp_set_current_user( 1 );

		$_GET['wcpay-login']  = '1';
		$_REQUEST['_wpnonce'] = wp_create_nonce( 'wcpay-login' );

		// The WPCOM/Jetpack connection is in working order.
		$this->mock_jetpack_connection();

		$this->cache_account_details(
			[
				'account_id'        => 'acc_test',
				'is_live'           => true,
				'details_submitted' => true, // Has finished initial KYC.
			]
		);

		// Assert.
		$this->mock_redirect_service->expects( $this->once() )->method( 'redirect_to_login' );

		// Act.
		$this->wcpay_account->maybe_handle_onboarding();
	}

	public function test_maybe_handle_onboarding_wpcom_reconnection() {
		// Arrange.
		// We need to be in the WP admin dashboard.
		$this->set_is_admin( true );
		// Test as an admin user.
		wp_set_current_user( 1 );

		$_GET['wcpay-reconnect-wpcom'] = '1';
		$_REQUEST['_wpnonce']          = wp_create_nonce( 'wcpay-reconnect-wpcom' );

		// Assert.
		$this->mock_api_client->expects( $this->once() )->method( 'start_server_connection' );

		// Act.
		$this->wcpay_account->maybe_handle_onboarding();
	}

	public function test_maybe_handle_onboarding_return_from_jetpack_connection_without_working_connection() {
		// Arrange.
		// We need to be in the WP admin dashboard.
		$this->set_is_admin( true );
		// Test as an admin user.
		wp_set_current_user( 1 );

		$_GET['wcpay-connect'] = 'connect-from';
		$_REQUEST['_wpnonce']  = wp_create_nonce( 'wcpay-connect' );
		// This is the flag indicating the return URL of the Jetpack connection flow.
		$_GET['wcpay-connect-jetpack-success'] = '1';
		// Jetpack will return to the Connect page.
		$_GET['page']   = 'wc-admin';
		$_GET['path']   = '/payments/connect';
		$_GET['source'] = WC_Payments_Onboarding_Service::SOURCE_WCADMIN_INCENTIVE_PAGE; // This should not matter.

		// It should not matter since the Jetpack connection takes precedence.
		$this->cache_account_details(
			[
				'account_id'        => 'acc_test',
				'is_live'           => true,
				'details_submitted' => true, // Has finished initial KYC.
			]
		);

		$this->mock_jetpack_connection( false );

		// Assert.
		$this->mock_redirect_service
			->expects( $this->once() )
			->method( 'redirect_to_connect_page' )
			->with(
				'Connection to WordPress.com failed. Please connect to WordPress.com to start using WooPayments.',
				WC_Payments_Onboarding_Service::FROM_WPCOM_CONNECTION,
				[
					'source' => WC_Payments_Onboarding_Service::SOURCE_WCADMIN_INCENTIVE_PAGE,
				]
			);

		// Act.
		$this->wcpay_account->maybe_handle_onboarding();
	}

	/**
	 * @dataProvider provider_onboarding_known_froms
	 */
	public function test_maybe_handle_onboarding_connect_from_known_from(
		$onboarding_from,
		$onboarding_source,
		$has_working_jetpack_connection,
		$is_stripe_connected,
		$create_test_drive_account,
		$expected_next_step
	) {

		// Arrange.
		// We need to be in the WP admin dashboard.
		$this->set_is_admin( true );
		// Test as an admin user.
		wp_set_current_user( 1 );

		$mock_redirect_service = $this->getMockBuilder( WC_Payments_Redirect_Service::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'redirect_to' ] )
			->getMock();
		$wcpay_account         = new WC_Payments_Account( $this->mock_api_client, $this->mock_database_cache, $this->mock_action_scheduler_service, $this->mock_onboarding_service, $mock_redirect_service );

		$_GET['wcpay-connect'] = 'connect-from';
		$_REQUEST['_wpnonce']  = wp_create_nonce( 'wcpay-connect' );
		// Set the request as if the user is on some bogus page. It doesn't matter.
		$_GET['page'] = 'wc-admin';
		$_GET['path'] = '/payments/some-bogus-page';

		$_GET['from']       = $onboarding_from;
		$_GET['source']     = $onboarding_source;
		$_GET['test_drive'] = $create_test_drive_account ? 'true' : null;

		$this->mock_jetpack_connection( $has_working_jetpack_connection );

		if ( $is_stripe_connected ) {
			$this->cache_account_details(
				[
					'account_id'        => 'acc_test',
					'is_live'           => true,
					'details_submitted' => true, // Has finished initial KYC.
					'capabilities'      => [ 'card_payments' => 'requested' ], // Has the minimum capabilities to be considered valid.
				]
			);
			// This should be in sync with the current account mode.
			WC_Payments_Onboarding_Service::set_test_mode( false );

			$this->mock_api_client
				->expects( $this->any() )
				->method( 'get_onboarding_data' )
				->willReturn( [ 'url' => false ] ); // This means that an account already exists in our platform.
		} elseif ( 'init_stripe_onboarding' !== $expected_next_step ) {
			$this->mock_api_client
				->expects( $this->any() )
				->method( 'get_onboarding_data' )
				->willReturn( [ 'url' => 'https://connect.stripe.com/something' ] );
		}

		// Assert.
		switch ( $expected_next_step ) {
			case 'start_jetpack_connection':
				$this->mock_api_client
					->expects( $this->once() )
					->method( 'start_server_connection' )
					->with(
						// The return URL should be a connect URL.
						$this->logicalAnd(
							$this->stringContains( 'wcpay-connect=connect-from' ),
							$this->stringContains( 'source=' . $onboarding_source ),
							$this->stringContains( 'wcpay-connect-jetpack-success=1' )
						)
					);
				break;
			case 'init_stripe_onboarding':
				$this->mock_api_client
					->expects( $this->never() )
					->method( 'start_server_connection' );
				if ( ! $create_test_drive_account ) {
					$this->mock_api_client
						->expects( $this->once() )
						->method( 'get_onboarding_data' )
						->willReturn( [ 'url' => 'https://connect.stripe.com/something' ] );
					$mock_redirect_service
						->expects( $this->once() )
						->method( 'redirect_to' )
						->with( 'https://connect.stripe.com/something' );
				} else {
					// For test-drive accounts, we expect the server to finish everything and get back no Stripe URL.
					// We expect no redirect since we will reply with a JSON.
					$this->mock_api_client
						->expects( $this->once() )
						->method( 'get_onboarding_data' )
						->willReturn( [ 'url' => false ] );

					$mock_redirect_service
						->expects( $this->never() )
						->method( 'redirect_to' );
				}
				break;
			case 'connect_page':
				$this->mock_api_client
					->expects( $this->never() )
					->method( 'start_server_connection' );
				$mock_redirect_service
					->expects( $this->once() )
					->method( 'redirect_to' )
					->with(
						$this->logicalAnd(
							$this->logicalOr(
								$this->stringContains( 'page=wc-admin&path=/payments/connect' ),
								$this->stringContains( 'page=wc-admin&path=%2Fpayments%2Fconnect' )
							),
							$this->stringContains( 'source=' . $onboarding_source ),
							$this->logicalNot( $this->stringContains( 'from=' . $onboarding_from ) )
						)
					);
				break;
			case 'onboarding_wizard':
				$this->mock_api_client
					->expects( $this->never() )
					->method( 'start_server_connection' );
				$mock_redirect_service
					->expects( $this->once() )
					->method( 'redirect_to' )
					->with(
						$this->logicalAnd(
							$this->logicalOr(
								$this->stringContains( 'page=wc-admin&path=/payments/onboarding' ),
								$this->stringContains( 'page=wc-admin&path=%2Fpayments%2Fonboarding' )
							),
							$this->stringContains( 'source=' . $onboarding_source )
						)
					);
				break;
			case 'overview_page':
				$this->mock_api_client
					->expects( $this->never() )
					->method( 'start_server_connection' );
				$mock_redirect_service
					->expects( $this->once() )
					->method( 'redirect_to' )
					->with(
						// It should redirect to an Overview page URL.
						$this->logicalOr(
							$this->stringContains( 'page=wc-admin&path=/payments/overview' ),
							$this->stringContains( 'page=wc-admin&path=%2Fpayments%2Foverview' )
						)
					);
				break;
			default:
				$this->fail( 'Unexpected redirect type: ' . $expected_next_step );
				break;
		}

		// Use this hack to be able to force WP to use wp_die instead of die, and intercept the wp_die() call.
		add_filter( 'wp_doing_ajax', '__return_true', 999 );
		add_filter(
			'wp_die_ajax_handler',
			function () {
				return '__return_null';
			},
			999
		);

		// Act.
		$wcpay_account->maybe_handle_onboarding();

		remove_all_filters( 'wp_doing_ajax' );
		remove_all_filters( 'wp_die_ajax_handler' );
	}

	/**
	 * Data provider for test_maybe_handle_onboarding_connect_from_known_from.
	 */
	public function provider_onboarding_known_froms() {
		return [
			'From Woo Payments task - no Jetpack connection, Stripe connected' => [
				WC_Payments_Onboarding_Service::FROM_WCADMIN_PAYMENTS_TASK,
				WC_Payments_Onboarding_Service::SOURCE_WCADMIN_PAYMENT_TASK,
				false,
				true,
				false,
				'connect_page',
			],
			'From Woo Payments task - Jetpack connection, Stripe not connected' => [
				WC_Payments_Onboarding_Service::FROM_WCADMIN_PAYMENTS_TASK,
				WC_Payments_Onboarding_Service::SOURCE_WCADMIN_PAYMENT_TASK,
				true,
				false,
				false,
				'connect_page',
			],
			'From Woo Payments task - Jetpack connection, Stripe connected' => [
				WC_Payments_Onboarding_Service::FROM_WCADMIN_PAYMENTS_TASK,
				WC_Payments_Onboarding_Service::SOURCE_WCADMIN_PAYMENT_TASK,
				true,
				true,
				false,
				'overview_page',
			],
			'From Connect page - no Jetpack connection, Stripe connected' => [
				WC_Payments_Onboarding_Service::FROM_CONNECT_PAGE,
				WC_Payments_Onboarding_Service::SOURCE_WCPAY_CONNECT_PAGE,
				false,
				true,
				false,
				'start_jetpack_connection',
			],
			'From Connect page - Jetpack connection, Stripe not connected' => [
				WC_Payments_Onboarding_Service::FROM_CONNECT_PAGE,
				WC_Payments_Onboarding_Service::SOURCE_WCADMIN_SETTINGS_PAGE, // Some other original source.
				true,
				false,
				false,
				'onboarding_wizard',
			],
			'From Connect page - Jetpack connection, Stripe connected' => [
				WC_Payments_Onboarding_Service::FROM_CONNECT_PAGE,
				WC_Payments_Onboarding_Service::SOURCE_WCADMIN_INCENTIVE_PAGE, // Some other original source.
				true,
				true,
				false,
				'overview_page',
			],
			'From Connect page - no Jetpack connection, Stripe connected - test-drive' => [
				WC_Payments_Onboarding_Service::FROM_CONNECT_PAGE,
				WC_Payments_Onboarding_Service::SOURCE_WCPAY_CONNECT_PAGE,
				false,
				true,
				true,
				'start_jetpack_connection',
			],
			'From Connect page - Jetpack connection, Stripe not connected - test-drive' => [
				WC_Payments_Onboarding_Service::FROM_CONNECT_PAGE,
				WC_Payments_Onboarding_Service::SOURCE_WCADMIN_SETTINGS_PAGE, // Some other original source.
				true,
				false,
				true,
				'init_stripe_onboarding',
			],
			'From Connect page - Jetpack connection, Stripe connected - test-drive' => [
				WC_Payments_Onboarding_Service::FROM_CONNECT_PAGE,
				WC_Payments_Onboarding_Service::SOURCE_WCADMIN_INCENTIVE_PAGE, // Some other original source.
				true,
				true,
				true,
				'overview_page',
			],
			'From Woo Payments Settings - no Jetpack connection, Stripe connected' => [
				WC_Payments_Onboarding_Service::FROM_WCADMIN_PAYMENTS_SETTINGS,
				WC_Payments_Onboarding_Service::SOURCE_WCADMIN_SETTINGS_PAGE,
				false,
				true,
				false,
				'connect_page',
			],
			'From Woo Payments Settings - Jetpack connection, Stripe not connected' => [
				WC_Payments_Onboarding_Service::FROM_WCADMIN_PAYMENTS_SETTINGS,
				WC_Payments_Onboarding_Service::SOURCE_WCADMIN_SETTINGS_PAGE,
				true,
				false,
				false,
				'connect_page',
			],
			'From Woo Payments Settings - Jetpack connection, Stripe connected' => [
				WC_Payments_Onboarding_Service::FROM_WCADMIN_PAYMENTS_SETTINGS,
				WC_Payments_Onboarding_Service::SOURCE_WCADMIN_SETTINGS_PAGE,
				true,
				true,
				false,
				'overview_page',
			],
			'From Incentive page - no Jetpack connection, Stripe connected' => [
				WC_Payments_Onboarding_Service::FROM_WCADMIN_INCENTIVE,
				WC_Payments_Onboarding_Service::SOURCE_WCADMIN_INCENTIVE_PAGE,
				false,
				true,
				false,
				'start_jetpack_connection',
			],
			'From Incentive page - Jetpack connection, Stripe not connected' => [
				WC_Payments_Onboarding_Service::FROM_WCADMIN_INCENTIVE,
				WC_Payments_Onboarding_Service::SOURCE_WCADMIN_INCENTIVE_PAGE,
				true,
				false,
				false,
				'onboarding_wizard',
			],
			'From Incentive page - Jetpack connection, Stripe connected' => [
				WC_Payments_Onboarding_Service::FROM_WCADMIN_INCENTIVE,
				WC_Payments_Onboarding_Service::SOURCE_WCADMIN_INCENTIVE_PAGE,
				true,
				true,
				false,
				'overview_page',
			],
			// This is a weird scenario that should not happen under normal circumstances.
			'From Onboarding wizard - no Jetpack connection, Stripe not connected' => [
				WC_Payments_Onboarding_Service::FROM_ONBOARDING_WIZARD,
				WC_Payments_Onboarding_Service::SOURCE_WCADMIN_INCENTIVE_PAGE,
				false,
				false,
				false,
				'connect_page',
			],
			'From Onboarding wizard - Jetpack connection, Stripe not connected' => [
				WC_Payments_Onboarding_Service::FROM_ONBOARDING_WIZARD,
				WC_Payments_Onboarding_Service::SOURCE_WCADMIN_INCENTIVE_PAGE,
				true,
				false,
				false,
				'init_stripe_onboarding',
			],
			'From Onboarding wizard - Jetpack connection, Stripe connected' => [
				WC_Payments_Onboarding_Service::FROM_ONBOARDING_WIZARD,
				WC_Payments_Onboarding_Service::SOURCE_WCADMIN_INCENTIVE_PAGE,
				true,
				true,
				false,
				'overview_page',
			],
		];
	}

	public function test_maybe_handle_onboarding_test_mode_to_live() {
		// Arrange.
		// We need to be in the WP admin dashboard.
		$this->set_is_admin( true );
		// Test as an admin user.
		wp_set_current_user( 1 );

		$_GET['wcpay-connect'] = 'connect-from';
		$_REQUEST['_wpnonce']  = wp_create_nonce( 'wcpay-connect' );
		// Set the request as if the user is on some bogus page. It doesn't matter.
		$_GET['page'] = 'wc-admin';
		$_GET['path'] = '/payments/some-bogus-page';
		// This should be ignored since we are moving from test to live.
		$_GET['test_mode'] = 'true';

		// This is the flag indicating the account should be switched from test to live mode.
		$_GET['wcpay-disable-onboarding-test-mode'] = 'true';

		// The Jetpack connection is in working order.
		$this->mock_jetpack_connection();

		$this->cache_account_details(
			[
				'account_id'        => 'acc_test',
				'is_live'           => false,
				'details_submitted' => true, // Has finished initial KYC.
				'capabilities'      => [ 'card_payments' => 'requested' ], // Has the minimum capabilities to be considered valid.
			]
		);
		// This should be in sync with the current account mode.
		WC_Payments_Onboarding_Service::set_test_mode( true );

		// We will use this so we can proceed after the account deletion step and
		// avoid ending up in the "everything OK" scenario.
		$this->mock_api_client
			->method( 'is_server_connected' )
			->willReturn( false );

		// Assert.
		// Test mode accounts get deleted.
		$this->mock_api_client
			->expects( $this->once() )
			->method( 'delete_account' )
			->with( true );
		$this->mock_redirect_service
			->expects( $this->once() )
			->method( 'redirect_to_onboarding_wizard' )
			->with(
				WC_Payments_Onboarding_Service::FROM_TEST_TO_LIVE,
				[
					'source' => WC_Payments_Onboarding_Service::SOURCE_WCPAY_SETUP_LIVE_PAYMENTS,
				]
			);

		// Act.
		$this->wcpay_account->maybe_handle_onboarding();

		// Assert more.
		// We should be in live mode now.
		$this->assertFalse( WC_Payments_Onboarding_Service::is_test_mode_enabled() );
	}

	public function test_maybe_handle_onboarding_reset_account() {
		// Arrange.
		// We need to be in the WP admin dashboard.
		$this->set_is_admin( true );
		// Test as an admin user.
		wp_set_current_user( 1 );

		$_GET['wcpay-connect'] = 'connect-from';
		$_REQUEST['_wpnonce']  = wp_create_nonce( 'wcpay-connect' );
		// Set the request as if the user is on some bogus page. It doesn't matter.
		$_GET['page'] = 'wc-admin';
		$_GET['path'] = '/payments/some-bogus-page';

		// This is the flag indicating the account should be reset.
		$_GET['wcpay-reset-account'] = 'true';

		$this->cache_account_details(
			[
				'account_id'        => 'acc_test',
				'is_live'           => true,
				'details_submitted' => true, // Has finished initial KYC.
				'capabilities'      => [ 'card_payments' => 'requested' ], // Has the minimum capabilities to be considered valid.
			]
		);
		// This should be in sync with the current account mode.
		WC_Payments_Onboarding_Service::set_test_mode( false );

		// Assert.
		$this->mock_api_client
			->expects( $this->once() )
			->method( 'delete_account' )
			->with( false );
		$this->mock_redirect_service
			->expects( $this->once() )
			->method( 'redirect_to_connect_page' )
			->with( null, WC_Payments_Onboarding_Service::FROM_RESET_ACCOUNT, [ 'source' => WC_Payments_Onboarding_Service::SOURCE_WCPAY_RESET_ACCOUNT ] );
		// We should be in live mode now.
		$this->assertFalse( WC_Payments_Onboarding_Service::is_test_mode_enabled() );

		// Act.
		$this->wcpay_account->maybe_handle_onboarding();
	}

	public function test_maybe_handle_onboarding_set_up_jetpack_connection() {
		// Arrange.
		// We need to be in the WP admin dashboard.
		$this->set_is_admin( true );
		// Test as an admin user.
		wp_set_current_user( 1 );

		$_GET['wcpay-connect'] = 'connect-from';
		$_REQUEST['_wpnonce']  = wp_create_nonce( 'wcpay-connect' );
		// Set the request as if the user is on some bogus page. It doesn't matter.
		$_GET['page']   = 'wc-admin';
		$_GET['path']   = '/payments/some-bogus-page';
		$_GET['source'] = WC_Payments_Onboarding_Service::SOURCE_WCADMIN_INCENTIVE_PAGE; // This should not matter but be carried over.
		// Make sure important flags are carried over.
		$_GET['promo']       = 'incentive_id';
		$_GET['progressive'] = 'true';
		$_GET['test_drive']  = 'true';
		$_GET['test_mode']   = '1'; // Some truthy value that will be carried over as `true`.

		// Even if we have connected account data, the Jetpack connection takes precedence.
		$this->cache_account_details(
			[
				'account_id'        => 'acc_test',
				'is_live'           => true,
				'details_submitted' => true, // Has finished initial KYC.
				'capabilities'      => [ 'card_payments' => 'requested' ], // Has the minimum capabilities to be considered valid.
			]
		);

		$this->mock_api_client
			->method( 'is_server_connected' )
			->willReturn( false );

		// Assert.
		$this->mock_api_client
			->expects( $this->once() )
			->method( 'start_server_connection' )
			->with(
				$this->logicalAnd(
					$this->stringContains( 'wcpay-connect=connect-from' ),
					$this->stringContains( 'wcpay-connect-jetpack-success=1' ),
					$this->stringContains( 'promo=incentive_id' ),
					$this->stringContains( 'progressive=true' ),
					$this->stringContains( 'test_drive=true' ),
					$this->stringContains( 'test_mode=true' ),
					$this->stringContains( 'from=' . WC_Payments_Onboarding_Service::FROM_WPCOM_CONNECTION ),
					$this->stringContains( 'source=' . WC_Payments_Onboarding_Service::SOURCE_WCADMIN_INCENTIVE_PAGE )
				)
			);

		// Act.
		$this->wcpay_account->maybe_handle_onboarding();
	}

	public function test_maybe_handle_onboarding_init_stripe_onboarding() {
		// Arrange.
		// We need to be in the WP admin dashboard.
		$this->set_is_admin( true );
		// Test as an admin user.
		wp_set_current_user( 1 );

		$_GET['wcpay-connect'] = 'connect-from';
		$_REQUEST['_wpnonce']  = wp_create_nonce( 'wcpay-connect' );
		// Set the request as if the user is on some bogus page. It doesn't matter.
		$_GET['page'] = 'wc-admin';
		$_GET['path'] = '/payments/some-bogus-page';
		// We need to come from the onboarding wizard to initialize an account!
		$_GET['from']   = WC_Payments_Onboarding_Service::FROM_ONBOARDING_WIZARD;
		$_GET['source'] = WC_Payments_Onboarding_Service::SOURCE_WCADMIN_INCENTIVE_PAGE;
		// Make sure important flags are carried over.
		$_GET['promo']       = 'incentive_id';
		$_GET['progressive'] = 'true';
		// There is no `test_mode` param and no test mode is set. It should end up as a live mode onboarding.

		// The Jetpack connection is in working order.
		$this->mock_jetpack_connection();

		$this->mock_database_cache
			->expects( $this->any() )
			->method( 'get_or_add' )
			->willReturn( [] ); // Empty array means no Stripe account connected.

		// Assert.
		$this->mock_redirect_service
			->expects( $this->never() )
			->method( 'redirect_to_overview_page' );
		$this->mock_redirect_service
			->expects( $this->never() )
			->method( 'redirect_to_connect_page' );
		$this->mock_redirect_service
			->expects( $this->never() )
			->method( 'redirect_to_onboarding_wizard' );

		$this->mock_api_client
			->expects( $this->once() )
			->method( 'get_onboarding_data' )
			->with(
				true, // Whether to onboard in live mode or not.
				$this->logicalAnd(
					// It should be a connect link.
					$this->stringContains( 'wcpay-connect=' ),
					// It should have the correct from.
					$this->stringContains( 'from=' . WC_Payments_Onboarding_Service::FROM_STRIPE ),
					// It should carry over contextual params.
					$this->stringContains( 'promo=incentive_id' ),
					$this->stringContains( 'progressive=true' )
				),
				$this->isType( 'array' ), // Site data.
				$this->isType( 'array' ), // User data.
				$this->isType( 'array' ), // Account data.
				$this->isType( 'array' ), // Actioned notes.
				true, // Progressive onboarding.
				false // Collect payout requirements.
			)
			->willReturn( [ 'url' => 'https://connect.stripe.com/something' ] );

		$this->mock_redirect_service
			->expects( $this->once() )
			->method( 'redirect_to' )
			->with( 'https://connect.stripe.com/something' );

		// Act.
		$this->wcpay_account->maybe_handle_onboarding();
	}

	public function test_maybe_handle_onboarding_init_embedded_kyc() {
		// Arrange.
		// We need to be in the WP admin dashboard.
		$this->set_is_admin( true );
		// Test as an admin user.
		wp_set_current_user( 1 );

		$_GET['wcpay-connect'] = 'connect-from';
		$_REQUEST['_wpnonce']  = wp_create_nonce( 'wcpay-connect' );
		// Set the request as if the user is on some bogus page. It doesn't matter.
		$_GET['page'] = 'wc-admin';
		$_GET['path'] = '/payments/some-bogus-page';
		// We need to come from the onboarding wizard to initialize an account!
		$_GET['from']   = WC_Payments_Onboarding_Service::FROM_ONBOARDING_WIZARD;
		$_GET['source'] = WC_Payments_Onboarding_Service::SOURCE_WCADMIN_INCENTIVE_PAGE;
		// Make sure important flags are carried over.
		$_GET['promo']       = 'incentive_id';
		$_GET['progressive'] = 'true';
		// There is no `test_mode` param and no test mode is set. It should end up as a live mode onboarding.

		// The Jetpack connection is in working order.
		$this->mock_jetpack_connection();

		$this->mock_database_cache
			->expects( $this->any() )
			->method( 'get_or_add' )
			->willReturn( [] ); // Empty array means no Stripe account connected.

		// Assert.
		$this->mock_redirect_service
			->expects( $this->never() )
			->method( 'redirect_to_overview_page' );
		$this->mock_redirect_service
			->expects( $this->never() )
			->method( 'redirect_to_connect_page' );
		$this->mock_redirect_service
			->expects( $this->never() )
			->method( 'redirect_to_onboarding_wizard' );

		update_option( '_wcpay_feature_embedded_kyc', '1' );

		// If embedded KYC is in progress, we expect different URL.
		$this->mock_onboarding_service
			->expects( $this->once() )
			->method( 'is_embedded_kyc_in_progress' )
			->willReturn( true );

		$this->mock_api_client
			->expects( $this->never() )
			->method( 'get_onboarding_data' );

		$this->mock_redirect_service
			->expects( $this->once() )
			->method( 'redirect_to' )
			->with(
				$this->logicalOr(
					$this->stringContains( 'page=wc-admin&path=/payments/onboarding/kyc' ),
					$this->stringContains( 'page=wc-admin&path=%2Fpayments%2Fonboarding%2Fkyc' )
				)
			);

		// Act.
		$this->wcpay_account->maybe_handle_onboarding();
	}

	public function test_maybe_handle_onboarding_init_stripe_onboarding_existing_account() {
		// Arrange.
		// We need to be in the WP admin dashboard.
		$this->set_is_admin( true );
		// Test as an admin user.
		wp_set_current_user( 1 );

		$_GET['wcpay-connect'] = 'connect-from';
		$_REQUEST['_wpnonce']  = wp_create_nonce( 'wcpay-connect' );
		// Set the request as if the user is on some bogus page. It doesn't matter.
		$_GET['page'] = 'wc-admin';
		$_GET['path'] = '/payments/some-bogus-page';
		// We need to come from the onboarding wizard to initialize an account!
		$_GET['from']   = WC_Payments_Onboarding_Service::FROM_ONBOARDING_WIZARD;
		$_GET['source'] = WC_Payments_Onboarding_Service::SOURCE_WCADMIN_INCENTIVE_PAGE; // This should not matter.
		// Make sure important flags are carried over.
		$_GET['promo']       = 'incentive_id';
		$_GET['progressive'] = 'true';
		// There is no `test_mode` param and no test mode is set.
		// It should end up as a live mode onboarding.

		// The Jetpack connection is in working order.
		$this->mock_jetpack_connection();

		$this->mock_database_cache
			->expects( $this->any() )
			->method( 'get_or_add' )
			->willReturn( [] ); // Empty array means no Stripe account connected.

		// Assert.
		$this->mock_redirect_service
			->expects( $this->never() )
			->method( 'redirect_to_overview_page' );
		$this->mock_redirect_service
			->expects( $this->never() )
			->method( 'redirect_to_connect_page' );
		$this->mock_redirect_service
			->expects( $this->never() )
			->method( 'redirect_to_onboarding_wizard' );

		$this->mock_api_client
			->expects( $this->once() )
			->method( 'get_onboarding_data' )
			->with(
				true, // Whether to onboard in live mode or not.
				$this->logicalAnd(
				// It should be a connect link.
					$this->stringContains( 'wcpay-connect=' ),
					// It should have the correct from.
					$this->stringContains( 'from=' . WC_Payments_Onboarding_Service::FROM_STRIPE ),
					// It should carry over contextual params.
					$this->stringContains( 'promo=incentive_id' ),
					$this->stringContains( 'progressive=true' )
				),
				$this->isType( 'array' ), // Site data.
				$this->isType( 'array' ), // User data.
				$this->isType( 'array' ), // Account data.
				$this->isType( 'array' ), // Actioned notes.
				true, // Progressive onboarding.
				false // Collect payout requirements.
			)
			->willReturn( [ 'url' => false ] ); // This means that an account already exits on the platform.

		$this->mock_redirect_service
			->expects( $this->once() )
			->method( 'redirect_to' )
			->with(
				$this->logicalAnd(
				// It should be a connect link.
					$this->stringContains( 'wcpay-connect=' ),
					// It should have the correct from.
					$this->stringContains( 'from=' . WC_Payments_Onboarding_Service::FROM_STRIPE ),
					// It should carry over contextual params.
					$this->stringContains( 'promo=incentive_id' ),
					$this->stringContains( 'progressive=true' ),
					// It should have the connection success flag.
					$this->stringContains( 'wcpay-connection-success=1' )
				)
			);

		// Act.
		$this->wcpay_account->maybe_handle_onboarding();
	}

	public function test_maybe_handle_onboarding_init_stripe_onboarding_another_onboarding_started() {
		// Arrange.
		// We need to be in the WP admin dashboard.
		$this->set_is_admin( true );
		// Test as an admin user.
		wp_set_current_user( 1 );

		$_GET['wcpay-connect'] = 'connect-from';
		$_REQUEST['_wpnonce']  = wp_create_nonce( 'wcpay-connect' );
		// Set the request as if the user is on some bogus page. It doesn't matter.
		$_GET['page'] = 'wc-admin';
		$_GET['path'] = '/payments/some-bogus-page';
		// We need to come from the onboarding wizard to initialize an account!
		$_GET['from']   = WC_Payments_Onboarding_Service::FROM_ONBOARDING_WIZARD;
		$_GET['source'] = WC_Payments_Onboarding_Service::SOURCE_WCADMIN_INCENTIVE_PAGE; // This should not matter.
		// Make sure important flags are carried over.
		$_GET['promo']       = 'incentive_id';
		$_GET['progressive'] = 'true';

		// There isn't another onboarding started.
		set_transient( WC_Payments_Account::ONBOARDING_STARTED_TRANSIENT, true, 10 );

		// The Jetpack connection is in working order.
		$this->mock_jetpack_connection();

		// Assert.
		$this->mock_redirect_service
			->expects( $this->atLeastOnce() )
			->method( 'redirect_to_connect_page' );

		$this->mock_api_client
			->expects( $this->never() )
			->method( 'get_onboarding_data' );

		// Act.
		$this->wcpay_account->maybe_handle_onboarding();
	}

	public function test_maybe_handle_onboarding_finalize_connection_via_connect_link_success_live_account() {
		// Arrange.
		// We need to be in the WP admin dashboard.
		$this->set_is_admin( true );
		// Test as an admin user.
		wp_set_current_user( 1 );

		// Make it a connect request.
		$_GET['wcpay-connect'] = 'connect-from';
		$_REQUEST['_wpnonce']  = wp_create_nonce( 'wcpay-connect' );
		// Make it a return from the Stripe KYC flow.
		$_GET['wcpay-state'] = 'bogus_state';
		$_GET['wcpay-mode']  = 'live';

		// Set the request as if the user is on some bogus page. It doesn't matter.
		$_GET['page']   = 'wc-admin';
		$_GET['path']   = '/payments/some-bogus-page';
		$_GET['from']   = WC_Payments_Onboarding_Service::FROM_STRIPE; // This should not matter.
		$_GET['source'] = WC_Payments_Onboarding_Service::SOURCE_WCADMIN_INCENTIVE_PAGE; // This should not matter.

		// The state of Jetpack connection should not matter.
		$this->mock_jetpack_connection( false );

		// The onboarding state should match.
		set_transient( WC_Payments_Account::ONBOARDING_STATE_TRANSIENT, 'bogus_state', 100 );

		// The gateway starts off with no enabled or test mode value.
		$gateway = WC_Payments::get_gateway();
		$gateway->update_option( 'enabled', '' );
		$gateway->update_option( 'test_mode', '' );

		// Assert.
		// We should redirect to the Overview page, not the Connect page.
		$this->mock_redirect_service
			->expects( $this->never() )
			->method( 'redirect_to_connect_page' );
		$this->mock_redirect_service
			->expects( $this->once() )
			->method( 'redirect_to_overview_page' )
			->with( WC_Payments_Onboarding_Service::FROM_STRIPE, $this->arrayHasKey( 'wcpay-connection-success' ) );

		// The account cache should be cleared.
		$this->mock_database_cache
			->expects( $this->once() )
			->method( 'delete' )
			->with( Database_Cache::ACCOUNT_KEY );

		// Act.
		$this->wcpay_account->maybe_handle_onboarding();

		// Assert.
		// The gateway is enabled and in the proper mode.
		$gateway = WC_Payments::get_gateway();
		$this->assertEquals( 'yes', $gateway->get_option( 'enabled' ) );
		$this->assertEquals( 'no', $gateway->get_option( 'test_mode' ) );

		// The state transient is deleted.
		$this->assertFalse( get_transient( WC_Payments_Account::ONBOARDING_STATE_TRANSIENT ) );
	}

	public function test_maybe_handle_onboarding_finalize_connection_via_non_connect_link_success_live_account() {
		// Arrange.
		// We need to be in the WP admin dashboard.
		$this->set_is_admin( true );
		// Test as an admin user.
		wp_set_current_user( 1 );

		// This is a non-connect request (i.e. don't set `wcpay-connect`).
		// Make it a return from the Stripe KYC flow.
		$_GET['wcpay-state'] = 'bogus_state';
		$_GET['wcpay-mode']  = 'live';

		// Set the request as if the user is on some bogus page. It doesn't matter.
		$_GET['page']   = 'wc-admin';
		$_GET['path']   = '/payments/some-bogus-page';
		$_GET['from']   = WC_Payments_Onboarding_Service::FROM_STRIPE; // This should not matter.
		$_GET['source'] = WC_Payments_Onboarding_Service::SOURCE_WCADMIN_INCENTIVE_PAGE; // This should not matter.

		// The state of Jetpack connection should not matter.
		$this->mock_jetpack_connection( false );

		// The onboarding state should match.
		set_transient( WC_Payments_Account::ONBOARDING_STATE_TRANSIENT, 'bogus_state', 100 );

		// The gateway starts off with no enabled or test mode value.
		$gateway = WC_Payments::get_gateway();
		$gateway->update_option( 'enabled', '' );
		$gateway->update_option( 'test_mode', '' );

		// Assert.
		// We should redirect to the Overview page, not the Connect page.
		$this->mock_redirect_service
			->expects( $this->never() )
			->method( 'redirect_to_connect_page' );
		$this->mock_redirect_service
			->expects( $this->once() )
			->method( 'redirect_to_overview_page' )
			->with( WC_Payments_Onboarding_Service::FROM_STRIPE, $this->arrayHasKey( 'wcpay-connection-success' ) );

		// The account cache should be cleared.
		$this->mock_database_cache
			->expects( $this->once() )
			->method( 'delete' )
			->with( Database_Cache::ACCOUNT_KEY );

		// Act.
		$this->wcpay_account->maybe_handle_onboarding();

		// Assert.
		// The gateway is enabled and in the proper mode.
		$gateway = WC_Payments::get_gateway();
		$this->assertEquals( 'yes', $gateway->get_option( 'enabled' ) );
		$this->assertEquals( 'no', $gateway->get_option( 'test_mode' ) );

		// The state transient is deleted.
		$this->assertFalse( get_transient( WC_Payments_Account::ONBOARDING_STATE_TRANSIENT ) );
	}

	public function test_maybe_handle_onboarding_finalize_connection_via_connect_link_success_test_account() {
		// Arrange.
		// We need to be in the WP admin dashboard.
		$this->set_is_admin( true );
		// Test as an admin user.
		wp_set_current_user( 1 );

		// Make it a connect request.
		$_GET['wcpay-connect'] = 'connect-from';
		$_REQUEST['_wpnonce']  = wp_create_nonce( 'wcpay-connect' );
		// Make it a return from the Stripe KYC flow.
		$_GET['wcpay-state'] = 'bogus_state';
		$_GET['wcpay-mode']  = 'test';

		// Set the request as if the user is on some bogus page. It doesn't matter.
		$_GET['page']   = 'wc-admin';
		$_GET['path']   = '/payments/some-bogus-page';
		$_GET['from']   = WC_Payments_Onboarding_Service::FROM_STRIPE; // This should not matter.
		$_GET['source'] = WC_Payments_Onboarding_Service::SOURCE_WCADMIN_INCENTIVE_PAGE; // This should not matter.

		// The state of Jetpack connection should not matter.
		$this->mock_jetpack_connection( false );

		// The onboarding state should match.
		set_transient( WC_Payments_Account::ONBOARDING_STATE_TRANSIENT, 'bogus_state', 100 );

		// The gateway starts off with no enabled or test mode value.
		$gateway = WC_Payments::get_gateway();
		$gateway->update_option( 'enabled', '' );
		$gateway->update_option( 'test_mode', '' );

		// Assert.
		// We should redirect to the Overview page, not the Connect page.
		$this->mock_redirect_service
			->expects( $this->never() )
			->method( 'redirect_to_connect_page' );
		$this->mock_redirect_service
			->expects( $this->once() )
			->method( 'redirect_to_overview_page' )
			->with( WC_Payments_Onboarding_Service::FROM_STRIPE, $this->arrayHasKey( 'wcpay-connection-success' ) );

		// The account cache should be cleared.
		$this->mock_database_cache
			->expects( $this->once() )
			->method( 'delete' )
			->with( Database_Cache::ACCOUNT_KEY );

		// Act.
		$this->wcpay_account->maybe_handle_onboarding();

		// Assert.
		// The gateway is enabled and in the proper mode.
		$gateway = WC_Payments::get_gateway();
		$this->assertEquals( 'yes', $gateway->get_option( 'enabled' ) );
		$this->assertEquals( 'yes', $gateway->get_option( 'test_mode' ) );

		// The state transient is deleted.
		$this->assertFalse( get_transient( WC_Payments_Account::ONBOARDING_STATE_TRANSIENT ) );
	}

	public function test_maybe_handle_onboarding_finalize_connection_via_connect_link_error_state_mismatch() {
		// Arrange.
		// We need to be in the WP admin dashboard.
		$this->set_is_admin( true );
		// Test as an admin user.
		wp_set_current_user( 1 );

		// Make it a connect request.
		$_GET['wcpay-connect'] = 'connect-from';
		$_REQUEST['_wpnonce']  = wp_create_nonce( 'wcpay-connect' );
		// Make it a return from the Stripe KYC flow.
		$_GET['wcpay-state'] = 'bogus_state';
		$_GET['wcpay-mode']  = 'live';

		// Set the request as if the user is on some bogus page. It doesn't matter.
		$_GET['page']   = 'wc-admin';
		$_GET['path']   = '/payments/some-bogus-page';
		$_GET['from']   = WC_Payments_Onboarding_Service::FROM_STRIPE; // This should not matter.
		$_GET['source'] = WC_Payments_Onboarding_Service::SOURCE_WCADMIN_INCENTIVE_PAGE; // This should not matter.

		// The state of Jetpack connection should not matter.
		$this->mock_jetpack_connection( false );

		// The onboarding state should NOT match.
		set_transient( WC_Payments_Account::ONBOARDING_STATE_TRANSIENT, 'bogus_state_mismatch', 100 );

		// The gateway starts off with no enabled or test mode value.
		$gateway = WC_Payments::get_gateway();
		$gateway->update_option( 'enabled', '' );
		$gateway->update_option( 'test_mode', '' );

		// Assert.
		// We should redirect to the Connect page with an error, not the Overview page.
		$this->mock_redirect_service
			->expects( $this->once() )
			->method( 'redirect_to_connect_page' )
			->with( 'There was a problem processing your account data. Please try again.' );
		$this->mock_redirect_service
			->expects( $this->never() )
			->method( 'redirect_to_overview_page' );

		// Act.
		$this->wcpay_account->maybe_handle_onboarding();

		// Assert.
		// The gateway is NOT enabled.
		$this->assertEquals( '', $gateway->get_option( 'enabled' ) );
		$this->assertEquals( '', $gateway->get_option( 'test_mode' ) );
	}

	public function test_maybe_handle_onboarding_finalize_connection_via_connect_link_connection_error() {
		// Arrange.
		// We need to be in the WP admin dashboard.
		$this->set_is_admin( true );
		// Test as an admin user.
		wp_set_current_user( 1 );

		// Make it a connect request.
		$_GET['wcpay-connect'] = 'connect-from';
		$_REQUEST['_wpnonce']  = wp_create_nonce( 'wcpay-connect' );
		// Make it a return from the Stripe KYC flow.
		$_GET['wcpay-state'] = 'bogus_state';
		$_GET['wcpay-mode']  = 'live';
		// Put in the connection error param.
		$_GET['wcpay-connection-error'] = '1';

		// Set the request as if the user is on some bogus page. It doesn't matter.
		$_GET['page']   = 'wc-admin';
		$_GET['path']   = '/payments/some-bogus-page';
		$_GET['from']   = WC_Payments_Onboarding_Service::FROM_STRIPE; // This should not matter.
		$_GET['source'] = WC_Payments_Onboarding_Service::SOURCE_WCADMIN_INCENTIVE_PAGE; // This should not matter.

		// The state of Jetpack connection should not matter.
		$this->mock_jetpack_connection( false );

		// The onboarding state should match.
		set_transient( WC_Payments_Account::ONBOARDING_STATE_TRANSIENT, 'bogus_state', 100 );

		// The gateway starts off with no enabled or test mode value.
		$gateway = WC_Payments::get_gateway();
		$gateway->update_option( 'enabled', '' );
		$gateway->update_option( 'test_mode', '' );

		// Assert.
		// We should redirect to the Connect page with an error parameter, not the Overview page.
		$this->mock_redirect_service
			->expects( $this->once() )
			->method( 'redirect_to_connect_page' )
			->with( '', WC_Payments_Onboarding_Service::FROM_STRIPE, $this->arrayHasKey( 'wcpay-connection-error' ) );
		$this->mock_redirect_service
			->expects( $this->never() )
			->method( 'redirect_to_overview_page' );

		// Act.
		$this->wcpay_account->maybe_handle_onboarding();

		// Assert.
		// The gateway is enabled in the proper mode.
		$this->assertEquals( 'yes', $gateway->get_option( 'enabled' ) );
		$this->assertEquals( 'no', $gateway->get_option( 'test_mode' ) );
	}

	public function test_maybe_redirect_after_plugin_activation_stripe_disconnected_redirects() {
		// The Jetpack connection is in working order.
		$this->mock_jetpack_connection();

		// Simulate the situation where the redirect has not happened yet.
		update_option( 'wcpay_should_redirect_to_onboarding', true );

		$this->mock_empty_cache();

		$cb = $this->create_can_manage_woocommerce_cap_override( true );
		add_filter( 'user_has_cap', $cb );

		$this->mock_wcpay_request( Get_Account::class )
			->expects( $this->once() )
			->method( 'format_response' )
			->willThrowException(
				new API_Exception( 'test', 'wcpay_account_not_found', 401 )
			);

		$this->assertTrue( $this->wcpay_account->maybe_redirect_after_plugin_activation() );
		$this->assertFalse( WC_Payments_Account::is_on_boarding_disabled() );
		// The option should be updated.
		$this->assertFalse( (bool) get_option( 'wcpay_should_redirect_to_onboarding', false ) );

		remove_filter( 'user_has_cap', $cb );
	}

	public function test_maybe_redirect_after_plugin_activation_stripe_disconnected_and_onboarding_disabled_redirects() {
		// The Jetpack connection is in working order.
		$this->mock_jetpack_connection();

		// Simulate the situation where the redirect has not happened yet.
		update_option( 'wcpay_should_redirect_to_onboarding', true );

		$this->mock_empty_cache();

		$cb = $this->create_can_manage_woocommerce_cap_override( true );
		add_filter( 'user_has_cap', $cb );

		$this->mock_wcpay_request( Get_Account::class )
			->expects( $this->once() )
			->method( 'format_response' )
			->willThrowException(
				new API_Exception(
					'test',
					'wcpay_on_boarding_disabled',
					401
				)
			);

		$this->assertTrue( $this->wcpay_account->maybe_redirect_after_plugin_activation() );
		$this->assertTrue( WC_Payments_Account::is_on_boarding_disabled() );
		// The option should be updated.
		$this->assertFalse( (bool) get_option( 'wcpay_should_redirect_to_onboarding', false ) );

		remove_filter( 'user_has_cap', $cb );
	}

	public function test_maybe_redirect_after_plugin_activation_account_error() {
		// The Jetpack connection is in working order.
		$this->mock_jetpack_connection();

		// Simulate the situation where the redirect has not happened yet.
		update_option( 'wcpay_should_redirect_to_onboarding', true );

		$this->mock_empty_cache();

		$cb = $this->create_can_manage_woocommerce_cap_override( true );
		add_filter( 'user_has_cap', $cb );

		$this->mock_wcpay_request( Get_Account::class )
			->expects( $this->once() )
			->method( 'format_response' )
			->willThrowException(
				new Exception()
			);

		$this->expectException( Exception::class );

		$this->assertTrue( $this->wcpay_account->maybe_redirect_after_plugin_activation() );
		// The option should be updated.
		$this->assertFalse( (bool) get_option( 'wcpay_should_redirect_to_onboarding', false ) );

		remove_filter( 'user_has_cap', $cb );
	}

	public function test_maybe_redirect_after_plugin_activation_account_valid() {
		// The Jetpack connection is in working order.
		$this->mock_jetpack_connection();

		// Simulate the situation where the redirect has not happened yet.
		update_option( 'wcpay_should_redirect_to_onboarding', true );

		$this->mock_empty_cache();

		$cb = $this->create_can_manage_woocommerce_cap_override( true );
		add_filter( 'user_has_cap', $cb );

		$this->mock_wcpay_request( Get_Account::class )
			->expects( $this->once() )
			->method( 'format_response' )
			->willReturn(
				new Response(
					[
						'account_id'               => 'acc_test',
						'live_publishable_key'     => 'pk_test_',
						'test_publishable_key'     => 'pk_live_',
						'has_pending_requirements' => true,
						'current_deadline'         => 12345,
						'is_live'                  => true,
						'details_submitted'        => true, // Has finished initial KYC.
						'capabilities'             => [ 'card_payments' => 'requested' ], // Has the minimum capabilities to be considered valid.
					]
				)
			);

		$this->assertFalse( $this->wcpay_account->maybe_redirect_after_plugin_activation() );
		// The option should be updated.
		$this->assertFalse( (bool) get_option( 'wcpay_should_redirect_to_onboarding', false ) );

		remove_filter( 'user_has_cap', $cb );
	}

	public function test_maybe_redirect_after_plugin_activation_with_non_admin_user() {
		// The Jetpack connection is in working order.
		$this->mock_jetpack_connection();

		// Simulate the situation where the redirect has not happened yet.
		update_option( 'wcpay_should_redirect_to_onboarding', true );

		$this->mock_empty_cache();

		$cb = $this->create_can_manage_woocommerce_cap_override( false );
		add_filter( 'user_has_cap', $cb );

		$this->mock_wcpay_request( Get_Account::class, 0 );

		$this->assertFalse( $this->wcpay_account->maybe_redirect_after_plugin_activation() );
		// The option should NOT be updated.
		$this->assertTrue( (bool) get_option( 'wcpay_should_redirect_to_onboarding', false ) );

		remove_filter( 'user_has_cap', $cb );
	}

	public function test_maybe_redirect_after_plugin_activation_checks_the_account_once() {
		// The Jetpack connection is in working order.
		$this->mock_jetpack_connection();

		// Simulate the situation where the redirect has not happened yet.
		update_option( 'wcpay_should_redirect_to_onboarding', true );

		$this->mock_empty_cache();

		$cb = $this->create_can_manage_woocommerce_cap_override( true );
		add_filter( 'user_has_cap', $cb );

		$this->mock_wcpay_request( Get_Account::class )
			->expects( $this->once() )
			->method( 'format_response' )
			->willReturn(
				new Response(
					[
						'account_id'               => 'acc_test',
						'live_publishable_key'     => 'pk_test_',
						'test_publishable_key'     => 'pk_live_',
						'has_pending_requirements' => true,
						'current_deadline'         => 12345,
						'is_live'                  => true,
						'details_submitted'        => true, // Has finished initial KYC.
						'capabilities'             => [ 'card_payments' => 'requested' ], // Has the minimum capabilities to be considered valid.
					]
				)
			);

		$this->assertFalse( $this->wcpay_account->maybe_redirect_after_plugin_activation() );
		// call the method twice but use the mock_wcpay_request to make sure the account has been retrieved only once.
		$this->assertFalse( $this->wcpay_account->maybe_redirect_after_plugin_activation() );
		// The option should be updated.
		$this->assertFalse( (bool) get_option( 'wcpay_should_redirect_to_onboarding', false ) );

		remove_filter( 'user_has_cap', $cb );
	}

	public function test_maybe_redirect_after_plugin_activation_returns_true_and_onboarding_re_enabled() {
		// The Jetpack connection is in working order.
		$this->mock_jetpack_connection();

		// We will call get_account_data twice. The first call will tell us no account is connected and that on-boarding
		// is disabled. The second call will just tell us that no account is connected (i.e. on-boarding was
		// re-enabled).
		$request1 = $this->mock_wcpay_request( Get_Account::class );
		$request1
			->expects( $this->once() )
			->method( 'format_response' )
			->willThrowException(
				new API_Exception(
					'test',
					'wcpay_on_boarding_disabled',
					401
				)
			);

		$request2 = $this->mock_wcpay_request( Get_Account::class );
		$request2->expects( $this->once() )
			->method( 'format_response' )
			->willReturn(
				new Response(
					[
						'account_id'               => 'acc_test',
						'live_publishable_key'     => 'pk_test_',
						'test_publishable_key'     => 'pk_live_',
						'has_pending_requirements' => true,
						'current_deadline'         => 12345,
						'is_live'                  => true,
					]
				)
			);

		// Simulate the account details cache not being there and then timing out.
		$this->mock_empty_cache();

		$cb = $this->create_can_manage_woocommerce_cap_override( true );
		add_filter( 'user_has_cap', $cb );

		// Simulate the situation where the redirect has not happened yet.
		update_option( 'wcpay_should_redirect_to_onboarding', true );

		// First call, on-boarding is disabled.
		$this->wcpay_account->maybe_redirect_after_plugin_activation();
		$this->assertTrue( WC_Payments_Account::is_on_boarding_disabled() );

		// Simulate the situation where the redirect has not happened yet.
		update_option( 'wcpay_should_redirect_to_onboarding', true );

		// Second call, on-boarding re-enabled.
		$this->wcpay_account->maybe_redirect_after_plugin_activation();
		$this->assertFalse( WC_Payments_Account::is_on_boarding_disabled() );

		remove_filter( 'user_has_cap', $cb );
	}

	public function test_maybe_redirect_to_wcpay_connect_do_redirect() {
		// Test as an admin user.
		wp_set_current_user( 1 );

		// Set the redirection parameter.
		$_GET['wcpay-connect-redirect'] = 1;

		$this->mock_redirect_service->expects( $this->once() )->method( 'redirect_to_wcpay_connect' );

		$this->wcpay_account->maybe_redirect_by_get_param();
	}

	public function test_maybe_redirect_to_wcpay_connect_unauthorized_user() {
		// Test as an editor user.
		$editor_user = $this->factory()->user->create( [ 'role' => 'editor' ] );
		wp_set_current_user( $editor_user );

		$this->mock_redirect_service->expects( $this->never() )->method( 'redirect_to' );

		$this->wcpay_account->maybe_redirect_by_get_param();
	}

	public function test_maybe_redirect_to_wcpay_connect_doing_ajax() {
		// Test as an admin user.
		wp_set_current_user( 1 );

		// Set the redirection parameter.
		$_GET['wcpay-connect-redirect'] = 1;

		// Simulate we're in an AJAX request.
		add_filter( 'wp_doing_ajax', '__return_true' );

		$this->mock_redirect_service->expects( $this->never() )->method( 'redirect_to' );

		$this->wcpay_account->maybe_redirect_by_get_param();

		// Cleaning up.
		remove_filter( 'wp_doing_ajax', '__return_true' );
	}

	public function test_maybe_redirect_to_wcpay_connect_wrong_page() {
		// Test as an admin user.
		wp_set_current_user( 1 );

		// Set the redirection parameter.
		$_GET['wcpay-connect-redirect'] = 1;

		$_GET['path'] = '/payments/overview';

		$this->mock_redirect_service->expects( $this->never() )->method( 'redirect_to' );

		$this->wcpay_account->maybe_redirect_by_get_param();
	}

	/**
	 * @dataProvider data_maybe_redirect_from_onboarding_page
	 */
	public function test_maybe_redirect_from_onboarding_page( $expected_redirect_to_count, $expected_method, $stripe_account_connected, $has_working_jetpack_connection, $get_params, $details_submitted = true ) {
		// Arrange.
		wp_set_current_user( 1 );
		$_GET = $get_params;

		if ( $stripe_account_connected ) {
			$this->cache_account_details(
				[
					'account_id'        => 'acc_test',
					'is_live'           => true,
					'details_submitted' => $details_submitted, // Whether it has finished initial KYC or not.
					'capabilities'      => [ 'card_payments' => 'requested' ], // Has the minimum capabilities to be considered valid.

				]
			);
		}

		$this->mock_jetpack_connection( $has_working_jetpack_connection );

		// Assert.
		$this->mock_redirect_service->expects( $this->exactly( $expected_redirect_to_count ) )->method( $expected_method );

		// Act.
		$this->wcpay_account->maybe_redirect_from_onboarding_wizard_page();
	}

	/**
	 * Data provider for test_maybe_redirect_from_onboarding_page
	 */
	public function data_maybe_redirect_from_onboarding_page() {
		return [
			'no_get_params'                             => [
				0,
				'redirect_to_connect_page',
				false,
				true,
				[],
			],
			'missing_param'                             => [
				0,
				'redirect_to_connect_page',
				false,
				true,
				[
					'page' => 'wc-admin',
				],
			],
			'incorrect_param'                           => [
				0,
				'redirect_to_connect_page',
				false,
				true,
				[
					'page' => 'wc-settings',
					'path' => '/payments/onboarding',
				],
			],
			'empty_path_param'                          => [
				0,
				'redirect_to_connect_page',
				false,
				true,
				[
					'page' => 'wc-admin',
				],
			],
			'incorrect_path_param'                      => [
				0,
				'redirect_to_connect_page',
				false,
				true,
				[
					'page' => 'wc-admin',
					'path' => '/payments/does-not-exist',
				],
			],
			'server_not_connected'                      => [
				1,
				'redirect_to_connect_page',
				false,
				false,
				[
					'page' => 'wc-admin',
					'path' => '/payments/onboarding',
				],
			],
			'stripe not connected'                      => [
				0,
				'redirect_to_connect_page',
				false,
				true,
				[
					'page' => 'wc-admin',
					'path' => '/payments/onboarding',
				],
			],
			'stripe connected, but partially onboarded' => [
				1,
				'redirect_to_connect_page',
				true,
				true,
				[
					'page' => 'wc-admin',
					'path' => '/payments/onboarding',
				],
				false,
			],
			'happy_path'                                => [
				1,
				'redirect_to_overview_page',
				true,
				true,
				[
					'page' => 'wc-admin',
					'path' => '/payments/onboarding',
				],
			],
		];
	}

	/**
	 * @dataProvider data_maybe_redirect_from_settings_page
	 */
	public function test_maybe_redirect_from_settings_page( $expected_redirect_to_count, $expected_method, $stripe_account_connected, $has_working_jetpack_connection, $get_params, $details_submitted = true ) {
		// Arrange.
		wp_set_current_user( 1 );
		$_GET = $get_params;

		if ( $stripe_account_connected ) {
			$this->cache_account_details(
				[
					'account_id'        => 'acc_test',
					'is_live'           => true,
					'details_submitted' => $details_submitted, // Whether it finished the initial KYC or not.
					'capabilities'      => [ 'card_payments' => 'requested' ], // Has the minimum capabilities to be considered valid.
				]
			);
		}

		$this->mock_jetpack_connection( $has_working_jetpack_connection );

		// Assert.
		$this->mock_redirect_service->expects( $this->exactly( $expected_redirect_to_count ) )->method( $expected_method );

		// Act.
		$this->wcpay_account->maybe_redirect_from_settings_page();
	}

	/**
	 * Data provider for test_maybe_redirect_from_settings_page
	 */
	public function data_maybe_redirect_from_settings_page() {
		return [
			'no_get_params'                            => [
				0,
				'redirect_to_connect_page',
				true,
				true,
				[],
			],
			'missing_param'                            => [
				0,
				'redirect_to_connect_page',
				true,
				true,
				[
					'page' => 'wc-settings',
					'tab'  => 'checkout',
				],
			],
			'incorrect_param'                          => [
				0,
				'redirect_to_connect_page',
				true,
				true,
				[
					'page'    => 'wc-settings',
					'tab'     => 'checkout',
					'section' => 'wrong_section',
				],
			],
			'server_not_connected'                     => [
				1,
				'redirect_to_connect_page',
				true,
				false,
				[
					'page'    => 'wc-settings',
					'tab'     => 'checkout',
					'section' => 'woocommerce_payments',
				],
			],
			'stripe_not_connected'                     => [
				1,
				'redirect_to_connect_page',
				false,
				true,
				[
					'page'    => 'wc-settings',
					'tab'     => 'checkout',
					'section' => 'woocommerce_payments',
				],
			],
			'stripe_connected_but_partially_onboarded' => [
				1,
				'redirect_to_connect_page',
				true,
				true,
				[
					'page'    => 'wc-settings',
					'tab'     => 'checkout',
					'section' => 'woocommerce_payments',
				],
				false,
			],
			'happy_path'                               => [
				0,
				'redirect_to_connect_page',
				true,
				true,
				[
					'page'    => 'wc-settings',
					'tab'     => 'checkout',
					'section' => 'woocommerce_payments',
				],
			],
		];
	}

	/**
	 * @dataProvider data_maybe_redirect_from_connect_page
	 */
	public function test_maybe_redirect_from_connect_page( $expected_redirect_to_count, $expected_method, $stripe_account_connected, $has_working_jetpack_connection, $get_params, $details_submitted = true ) {
		// Arrange.
		wp_set_current_user( 1 );
		$_GET = $get_params;

		if ( $stripe_account_connected ) {
			$this->cache_account_details(
				[
					'account_id'        => 'acc_test',
					'is_live'           => true,
					'details_submitted' => $details_submitted, // Whether it finished the initial KYC or not.
					'capabilities'      => [ 'card_payments' => 'requested' ], // Has the minimum capabilities to be considered valid.
				]
			);
		}

		$this->mock_jetpack_connection( $has_working_jetpack_connection );

		// Assert.
		$this->mock_redirect_service->expects( $this->exactly( $expected_redirect_to_count ) )->method( $expected_method );

		// Act.
		$this->wcpay_account->maybe_redirect_from_connect_page();
	}

	/**
	 * Data provider for test_maybe_redirect_from_connect_page
	 */
	public function data_maybe_redirect_from_connect_page() {
		return [
			'no_get_params'                            => [
				0,
				'redirect_to_overview_page',
				true,
				true,
				[],
			],
			'missing_param'                            => [
				0,
				'redirect_to_overview_page',
				true,
				true,
				[
					'page' => 'wc-admin',
				],
			],
			'incorrect_param'                          => [
				0,
				'redirect_to_overview_page',
				true,
				true,
				[
					'page' => 'wc-settings',
					'path' => '/payments/connect',
				],
			],
			'empty_path_param'                         => [
				0,
				'redirect_to_overview_page',
				true,
				true,
				[
					'page' => 'wc-admin',
				],
			],
			'incorrect_path_param'                     => [
				0,
				'redirect_to_overview_page',
				true,
				true,
				[
					'page' => 'wc-admin',
					'path' => '/payments/does-not-exist',
				],
			],
			'server_not_connected'                     => [
				0,
				'redirect_to_overview_page',
				true,
				false,
				[
					'page' => 'wc-admin',
					'path' => '/payments/connect',
				],
			],
			'stripe_not_connected'                     => [
				0,
				'redirect_to_overview_page',
				false,
				true,
				[
					'page' => 'wc-admin',
					'path' => '/payments/connect',
				],
			],
			'stripe_connected_but_partially_onboarded' => [
				0,
				'redirect_to_overview_page',
				true,
				true,
				[
					'page' => 'wc-admin',
					'path' => '/payments/connect',
				],
				false,
			],
			'happy_path'                               => [
				1,
				'redirect_to_overview_page',
				true,
				true,
				[
					'page' => 'wc-admin',
					'path' => '/payments/connect',
				],
			],
		];
	}

	/**
	 * @dataProvider data_maybe_redirect_from_overview_page
	 */
	public function test_maybe_redirect_from_overview_page( $expected_redirect_to_count, $expected_method, $stripe_account_connected, $has_working_jetpack_connection, $get_params, $details_submitted = true ) {
		// Arrange.
		wp_set_current_user( 1 );
		$_GET = $get_params;

		if ( $stripe_account_connected ) {
			$this->cache_account_details(
				[
					'account_id'        => 'acc_test',
					'is_live'           => true,
					'details_submitted' => $details_submitted, // Whether it finished the initial KYC or not.
					'capabilities'      => [ 'card_payments' => 'requested' ], // Has the minimum capabilities to be considered valid.
				]
			);
		}

		$this->mock_jetpack_connection( $has_working_jetpack_connection );

		// Assert.
		$this->mock_redirect_service->expects( $this->exactly( $expected_redirect_to_count ) )->method( $expected_method );

		// Act.
		$this->wcpay_account->maybe_redirect_from_overview_page();
	}

	/**
	 * Data provider for test_maybe_redirect_from_overview_page
	 */
	public function data_maybe_redirect_from_overview_page() {
		return [
			'no_get_params'                            => [
				0,
				'redirect_to_connect_page',
				true,
				true,
				[],
			],
			'missing_param'                            => [
				0,
				'redirect_to_connect_page',
				true,
				true,
				[
					'page' => 'wc-admin',
				],
			],
			'incorrect_param'                          => [
				0,
				'redirect_to_connect_page',
				true,
				true,
				[
					'page' => 'wc-settings',
					'path' => '/payments/connect',
				],
			],
			'empty_path_param'                         => [
				0,
				'redirect_to_connect_page',
				true,
				true,
				[
					'page' => 'wc-admin',
				],
			],
			'incorrect_path_param'                     => [
				0,
				'redirect_to_connect_page',
				true,
				true,
				[
					'page' => 'wc-admin',
					'path' => '/payments/does-not-exist',
				],
			],
			'server_not_connected'                     => [
				1,
				'redirect_to_connect_page',
				true,
				false,
				[
					'page' => 'wc-admin',
					'path' => '/payments/overview',
				],
			],
			'stripe_not_connected'                     => [
				1,
				'redirect_to_connect_page',
				false,
				true,
				[
					'page' => 'wc-admin',
					'path' => '/payments/overview',
				],
			],
			'stripe_connected_but_partially_onboarded' => [
				1,
				'redirect_to_connect_page',
				true,
				true,
				[
					'page' => 'wc-admin',
					'path' => '/payments/overview',
				],
				false,
			],
			'happy_path'                               => [
				0,
				'redirect_to_connect_page',
				true,
				true,
				[
					'page' => 'wc-admin',
					'path' => '/payments/overview',
				],
			],
		];
	}

	public function test_maybe_redirect_from_connect_page_redirects_and_maybe_redirect_from_overview_page_doesnt() {
		// Arrange.
		wp_set_current_user( 1 );

		// The account is valid.
		$this->cache_account_details(
			[
				'account_id'        => 'acc_test',
				'is_live'           => true,
				'details_submitted' => true, // Whether it finished the initial KYC or not.
				'capabilities'      => [ 'card_payments' => 'requested' ], // Has the minimum capabilities to be considered valid.
			]
		);

		// The WPCOM/Jetpack connection is in working order.
		$this->mock_jetpack_connection();

		// Assert.
		$this->mock_redirect_service->expects( $this->once() )->method( 'redirect_to_overview_page' );
		$this->mock_redirect_service->expects( $this->never() )->method( 'redirect_to_connect_page' );

		// Act.
		$_GET = [
			'page' => 'wc-admin',
			'path' => '/payments/connect',
		];
		$this->wcpay_account->maybe_redirect_from_connect_page();
		$_GET = [
			'page' => 'wc-admin',
			'path' => '/payments/overview',
		];
		$this->wcpay_account->maybe_redirect_from_overview_page();
	}

	/**
	 * Test that maybe_redirect_from_connect_page redirects and maybe_redirect_from_overview_page doesn't when
	 * either the WPCOM/Jetpack connection is not working or the account is not valid.
	 *
	 * @dataProvider data_test_maybe_redirect_from_overview_page_redirects_and_maybe_redirect_from_connect_page_doesnt
	 */
	public function test_maybe_redirect_from_overview_page_redirects_and_maybe_redirect_from_connect_page_doesnt( $has_working_jetpack_connection, $is_stripe_connected, $details_submitted, $required_capabilities ) {
		// Arrange.
		wp_set_current_user( 1 );

		if ( $is_stripe_connected ) {
			$this->cache_account_details(
				[
					'account_id'        => 'acc_test',
					'is_live'           => true,
					'details_submitted' => $details_submitted, // Whether it finished the initial KYC or not.
					'capabilities'      => $required_capabilities ? [ 'card_payments' => 'requested' ] : [],
				]
			);
		} else {
			$this->cache_account_details( [] );
		}

		$this->mock_jetpack_connection( $has_working_jetpack_connection );

		// Assert.
		$this->mock_redirect_service->expects( $this->once() )->method( 'redirect_to_connect_page' );
		$this->mock_redirect_service->expects( $this->never() )->method( 'redirect_to_overview_page' );

		// Act.
		$_GET = [
			'page' => 'wc-admin',
			'path' => '/payments/overview',
		];
		$this->wcpay_account->maybe_redirect_from_overview_page();
		$_GET = [
			'page' => 'wc-admin',
			'path' => '/payments/connect',
		];
		$this->wcpay_account->maybe_redirect_from_connect_page();
	}

	/**
	 * Data provider for data_test_maybe_redirect_from_overview_page_redirects_and_maybe_redirect_from_connect_page_doesnt.
	 *
	 * @return array[]
	 */
	public function data_test_maybe_redirect_from_overview_page_redirects_and_maybe_redirect_from_connect_page_doesnt() {
		return [
			'no_jetpack_connection'                      => [
				false,
				true,
				true,
				true,
			],
			'no_stripe_account'                          => [
				true,
				false,
				true,
				true,
			],
			'partially_onboarded_account'                => [
				true,
				true,
				false,
				true,
			],
			'account_with_missing_required_capabilities' => [
				true,
				true,
				true,
				false,
			],
		];
	}

	public function test_try_is_stripe_connected_returns_true_when_connected() {
		// The WPCOM/Jetpack connection is in working order.
		$this->mock_jetpack_connection();

		$this->mock_empty_cache();

		$this->mock_wcpay_request( Get_Account::class )
			->expects( $this->once() )
			->method( 'format_response' )
			->willReturn(
				new Response(
					[
						'account_id'               => 'acc_test',
						'live_publishable_key'     => 'pk_test_',
						'test_publishable_key'     => 'pk_live_',
						'has_pending_requirements' => true,
						'current_deadline'         => 12345,
						'is_live'                  => true,
					]
				)
			);

		$this->assertTrue( $this->wcpay_account->try_is_stripe_connected() );
	}

	/**
	 * @dataProvider data_has_working_jetpack_connection

	 */
	public function test_has_working_jetpack_connection( $server_connected, $has_owner, $expected ) {
		$this->mock_api_client
			->method( 'is_server_connected' )
			->willReturn( $server_connected );

		$this->mock_api_client
			->method( 'has_server_connection_owner' )
			->willReturn( $has_owner );

		$this->assertEquals( $expected, $this->wcpay_account->has_working_jetpack_connection() );
	}

	/**
	 * Data provider for test_has_working_jetpack_connection.
	 *
	 * @return array
	 */
	public function data_has_working_jetpack_connection(): array {
		return [
			'Not connected'                     => [
				false,
				true,
				false,
			],
			'Connected but no connection owner' => [
				true,
				false,
				false,
			],
			'Working connection'                => [
				true,
				true,
				true,
			],
		];
	}

	public function test_try_is_stripe_connected_throws() {
		// The Jetpack connection is in working order.
		$this->mock_jetpack_connection();

		$this->mock_empty_cache();

		$this->mock_wcpay_request( Get_Account::class )
			->expects( $this->once() )
			->method( 'format_response' )
			->willThrowException(
				new API_Exception( 'test', 'server_error', 500 )
			);

		// Server exception is masked by generic exception.
		$this->expectException( Exception::class );

		$this->wcpay_account->try_is_stripe_connected();
	}

	public function test_try_is_stripe_connected_returns_false() {
		// The WPCOM/Jetpack connection is in working order.
		$this->mock_jetpack_connection();

		$this->mock_empty_cache();

		$this->mock_wcpay_request( Get_Account::class )
			->expects( $this->once() )
			->method( 'format_response' )
			->willThrowException(
				new API_Exception( 'test', 'wcpay_account_not_found', 401 )
			);

		$this->assertFalse( $this->wcpay_account->try_is_stripe_connected() );
	}

	public function test_is_stripe_connected_returns_true_when_connected() {
		// The WPCOM/Jetpack connection is in working order.
		$this->mock_jetpack_connection();

		$this->mock_empty_cache();

		$this->mock_wcpay_request( Get_Account::class )
			->expects( $this->once() )
			->method( 'format_response' )
			->willReturn(
				new Response(
					[
						'account_id'               => 'acc_test',
						'live_publishable_key'     => 'pk_test_',
						'test_publishable_key'     => 'pk_live_',
						'has_pending_requirements' => true,
						'current_deadline'         => 12345,
						'is_live'                  => true,
					]
				)
			);

		$this->assertTrue( $this->wcpay_account->is_stripe_connected( false ) );
	}

	public function test_is_stripe_connected_returns_false_on_error() {
		// The WPCOM/Jetpack connection is in working order.
		$this->mock_jetpack_connection();

		$this->mock_empty_cache();

		$this->mock_wcpay_request( Get_Account::class )
			->expects( $this->once() )
			->method( 'format_response' )
			->willThrowException(
				new API_Exception( 'test', 'server_error', 500 )
			);

		$this->assertFalse( $this->wcpay_account->is_stripe_connected( false ) );
	}

	public function test_is_stripe_connected_returns_false_when_not_connected() {
		// The WPCOM/Jetpack connection is in working order.
		$this->mock_jetpack_connection();

		$this->mock_empty_cache();

		$this->mock_wcpay_request( Get_Account::class )
			->expects( $this->once() )
			->method( 'format_response' )
			->willThrowException(
				new API_Exception( 'test', 'wcpay_account_not_found', 401 )
			);

		$this->assertFalse( $this->wcpay_account->is_stripe_connected( false ) );
	}

	public function test_is_stripe_account_valid_when_not_connected() {
		// The Jetpack connection is in working order.
		$this->mock_jetpack_connection();

		$this->mock_empty_cache();

		$this->mock_wcpay_request( Get_Account::class )
			->expects( $this->once() )
			->method( 'format_response' )
			->willThrowException( new API_Exception( 'test', 'wcpay_mock', 500 ) );

		$this->assertFalse( $this->wcpay_account->is_stripe_account_valid() );
	}

	public function test_is_stripe_account_valid_when_empty_account_data() {
		// The Jetpack connection is in working order.
		$this->mock_jetpack_connection();

		$this->mock_empty_cache();

		$this->mock_wcpay_request( Get_Account::class )
			->expects( $this->once() )
			->method( 'format_response' )
			->willReturn(
				new Response( [] )
			);

		$this->assertFalse( $this->wcpay_account->is_stripe_account_valid() );
	}

	public function test_is_stripe_account_valid_when_details_not_submitted() {
		// The Jetpack connection is in working order.
		$this->mock_jetpack_connection();

		$this->mock_database_cache
			->expects( $this->any() )
			->method( 'get_or_add' )
			->willReturn(
				[
					'account_id'               => 'acc_test',
					'live_publishable_key'     => 'pk_live_',
					'test_publishable_key'     => 'pk_test_',
					'has_pending_requirements' => true,
					'current_deadline'         => 12345,
					'is_live'                  => true,
					'details_submitted'        => false, // Has NOT finished initial KYC.
				]
			);

		$this->assertFalse( $this->wcpay_account->is_stripe_account_valid() );
	}

	public function test_is_stripe_account_valid_when_capability_unrequested() {
		// The Jetpack connection is in working order.
		$this->mock_jetpack_connection();

		$this->mock_database_cache
			->expects( $this->any() )
			->method( 'get_or_add' )
			->willReturn(
				[
					'account_id'               => 'acc_test',
					'live_publishable_key'     => 'pk_live_',
					'test_publishable_key'     => 'pk_test_',
					'has_pending_requirements' => true,
					'current_deadline'         => 12345,
					'is_live'                  => true,
					'details_submitted'        => true, // Has finished initial KYC.
					'capabilities'             => [
						'card_payments' => 'unrequested',
					],
				]
			);

		$this->assertFalse( $this->wcpay_account->is_stripe_account_valid() );
	}

	public function test_is_stripe_account_valid_when_capability_requested() {
		// The Jetpack connection is in working order.
		$this->mock_jetpack_connection();

		$this->mock_database_cache
			->expects( $this->any() )
			->method( 'get_or_add' )
			->willReturn(
				[
					'account_id'               => 'acc_test',
					'live_publishable_key'     => 'pk_live_',
					'test_publishable_key'     => 'pk_test_',
					'has_pending_requirements' => true,
					'current_deadline'         => 12345,
					'is_live'                  => true,
					'details_submitted'        => true, // Has finished initial KYC.
					'capabilities'             => [
						'card_payments' => 'requested',
					],
				]
			);

		$this->assertTrue( $this->wcpay_account->is_stripe_account_valid() );
	}

	public function test_is_stripe_account_valid_when_capability_active() {
		// The Jetpack connection is in working order.
		$this->mock_jetpack_connection();

		$this->mock_database_cache
			->expects( $this->any() )
			->method( 'get_or_add' )
			->willReturn(
				[
					'account_id'               => 'acc_test',
					'live_publishable_key'     => 'pk_live_',
					'test_publishable_key'     => 'pk_test_',
					'has_pending_requirements' => true,
					'current_deadline'         => 12345,
					'is_live'                  => true,
					'details_submitted'        => true, // Has finished initial KYC.
					'capabilities'             => [
						'card_payments' => 'active',
					],
				]
			);

		$this->assertTrue( $this->wcpay_account->is_stripe_account_valid() );
	}

	public function test_is_stripe_account_valid_when_capability_pending_verification() {
		// The Jetpack connection is in working order.
		$this->mock_jetpack_connection();

		$this->mock_database_cache
			->expects( $this->any() )
			->method( 'get_or_add' )
			->willReturn(
				[
					'account_id'               => 'acc_test',
					'live_publishable_key'     => 'pk_live_',
					'test_publishable_key'     => 'pk_test_',
					'has_pending_requirements' => true,
					'current_deadline'         => 12345,
					'is_live'                  => true,
					'details_submitted'        => true, // Has finished initial KYC.
					'capabilities'             => [
						'card_payments' => 'pending_verification',
					],
				]
			);

		$this->assertTrue( $this->wcpay_account->is_stripe_account_valid() );
	}

	public function test_get_publishable_key_returns_for_live() {
		// The Jetpack connection is in working order.
		$this->mock_jetpack_connection();

		$this->mock_empty_cache();

		$this->mock_wcpay_request( Get_Account::class )
			->expects( $this->once() )
			->method( 'format_response' )
			->willReturn(
				new Response(
					[
						'account_id'               => 'acc_test',
						'live_publishable_key'     => 'pk_live_',
						'test_publishable_key'     => 'pk_test_',
						'has_pending_requirements' => true,
						'current_deadline'         => 12345,
						'is_live'                  => true,
					]
				)
			);

		$this->assertEquals( 'pk_live_', $this->wcpay_account->get_publishable_key( false ) );
	}

	public function test_get_publishable_key_returns_for_test() {
		// The Jetpack connection is in working order.
		$this->mock_jetpack_connection();

		$this->mock_empty_cache();

		$this->mock_wcpay_request( Get_Account::class )
			->expects( $this->once() )
			->method( 'format_response' )
			->willReturn(
				new Response(
					[
						'account_id'               => 'acc_test',
						'live_publishable_key'     => 'pk_live_',
						'test_publishable_key'     => 'pk_test_',
						'has_pending_requirements' => true,
						'current_deadline'         => 12345,
						'is_live'                  => true,
					]
				)
			);

		$this->assertEquals( 'pk_test_', $this->wcpay_account->get_publishable_key( true ) );
	}

	public function test_get_publishable_key_throws() {
		// The Jetpack connection is in working order.
		$this->mock_jetpack_connection();

		$this->mock_empty_cache();

		$this->mock_wcpay_request( Get_Account::class )
			->expects( $this->once() )
			->method( 'format_response' )
			->willThrowException(
				new API_Exception( 'test', 'test', 123 )
			);

		$this->assertNull( $this->wcpay_account->get_publishable_key( true ) );
	}

	public function test_get_stripe_account_id() {
		// The Jetpack connection is in working order.
		$this->mock_jetpack_connection();

		$this->mock_empty_cache();

		$this->mock_wcpay_request( Get_Account::class )
			->expects( $this->once() )
			->method( 'format_response' )
			->willReturn(
				new Response(
					[
						'account_id'               => 'acc_test',
						'live_publishable_key'     => 'pk_live_',
						'test_publishable_key'     => 'pk_test_',
						'has_pending_requirements' => true,
						'current_deadline'         => 12345,
						'is_live'                  => true,
					]
				)
			);

		$this->assertEquals( 'acc_test', $this->wcpay_account->get_stripe_account_id() );
	}

	public function test_get_stripe_account_id_throws() {
		// The Jetpack connection is in working order.
		$this->mock_jetpack_connection();

		$this->mock_empty_cache();

		$this->mock_wcpay_request( Get_Account::class )
			->expects( $this->once() )
			->method( 'format_response' )
			->willThrowException(
				new API_Exception( 'test', 'test', 123 )
			);

		$this->assertNull( $this->wcpay_account->get_stripe_account_id() );
	}

	public function test_try_is_stripe_connected_returns_true_when_connected_with_test_account_in_dev_mode() {
		// The Jetpack connection is in working order.
		$this->mock_jetpack_connection();

		// enable dev mode.
		WC_Payments::mode()->dev();

		// cache a dev account.
		$this->cache_account_details(
			[
				'account_id'               => 'acc_test',
				'live_publishable_key'     => 'pk_test_',
				'test_publishable_key'     => 'pk_live_',
				'has_pending_requirements' => true,
				'current_deadline'         => 12345,
				'is_live'                  => false,
			]
		);

		// cached value should be used and the api should never be called.
		$this->mock_wcpay_request( Get_Account::class, 0 );

		$this->assertTrue( $this->wcpay_account->try_is_stripe_connected() );
	}

	public function test_try_is_stripe_connected_returns_false_when_connected_with_dev_account_in_live_mode() {
		// The Jetpack connection is in working order.
		$this->mock_jetpack_connection();

		// disable dev mode.
		WC_Payments::mode()->live();

		// cache a dev account.
		$this->cache_account_details(
			[
				'account_id'               => 'acc_test',
				'live_publishable_key'     => 'pk_test_',
				'test_publishable_key'     => 'pk_live_',
				'has_pending_requirements' => true,
				'current_deadline'         => 12345,
				'is_live'                  => false,
			]
		);

		// cached value should be ignored and the api should return not-connected.
		$this->mock_wcpay_request( Get_Account::class )
			->expects( $this->once() )
			->method( 'format_response' )
			->willThrowException(
				new API_Exception( 'test', 'wcpay_account_not_found', 401 )
			);

		$this->assertFalse( $this->wcpay_account->try_is_stripe_connected() );
	}

	public function test_try_is_stripe_connected_returns_true_when_connected_with_live_account_in_dev_mode() {
		// The Jetpack connection is in working order.
		$this->mock_jetpack_connection();

		// enable dev mode.
		WC_Payments::mode()->dev();

		// cache a live account.
		$this->cache_account_details(
			[
				'account_id'               => 'acc_test',
				'live_publishable_key'     => 'pk_test_',
				'test_publishable_key'     => 'pk_live_',
				'has_pending_requirements' => true,
				'current_deadline'         => 12345,
				'is_live'                  => true,
			]
		);

		// cached live account should be used and the api shouldn't be called.
		$this->mock_wcpay_request( Get_Account::class, 0 );

		$this->assertTrue( $this->wcpay_account->try_is_stripe_connected() );
	}

	public function test_is_account_rejected_returns_true() {
		// The Jetpack connection is in working order.
		$this->mock_jetpack_connection();

		$this->mock_database_cache
			->expects( $this->any() )
			->method( 'get_or_add' )
			->willReturn(
				[
					'account_id'               => 'acc_test',
					'live_publishable_key'     => 'pk_test_',
					'test_publishable_key'     => 'pk_live_',
					'has_pending_requirements' => true,
					'current_deadline'         => 12345,
					'is_live'                  => true,
					'status'                   => 'rejected.tos',
				]
			);

		$this->assertTrue( $this->wcpay_account->is_account_rejected() );
	}

	public function test_is_account_rejected_returns_false_when_not_rejected() {
		// The Jetpack connection is in working order.
		$this->mock_jetpack_connection();

		$this->mock_database_cache
			->expects( $this->any() )
			->method( 'get_or_add' )
			->willReturn(
				[
					'account_id'               => 'acc_test',
					'live_publishable_key'     => 'pk_test_',
					'test_publishable_key'     => 'pk_live_',
					'has_pending_requirements' => true,
					'current_deadline'         => 12345,
					'is_live'                  => true,
					'status'                   => 'complete',
				]
			);

		$this->assertFalse( $this->wcpay_account->is_account_rejected() );
	}

	public function test_is_account_rejected_returns_false_on_error() {
		// The Jetpack connection is in working order.
		$this->mock_jetpack_connection();

		$this->mock_empty_cache();

		$this->mock_wcpay_request( Get_Account::class )
			->expects( $this->once() )
			->method( 'format_response' )
			->willThrowException( new API_Exception( 'test', 'wcpay_mock', 500 ) );

		$this->assertFalse( $this->wcpay_account->is_account_rejected() );
	}

	/**
	 * Test the is_details_submitted method.
	 *
	 * @param bool $details_submitted Whether details_submitted is true for the account.
	 *
	 * @return void
	 *
	 * @dataProvider is_details_submitted_provider
	 */
	public function test_is_details_submitted( bool $details_submitted ): void {
		// The Jetpack connection is in working order.
		$this->mock_jetpack_connection();

		$this->mock_database_cache
			->expects( $this->once() )
			->method( 'get_or_add' )
			->willReturn(
				[
					'account_id'               => 'acc_test',
					'live_publishable_key'     => 'pk_test_',
					'test_publishable_key'     => 'pk_live_',
					'has_pending_requirements' => true,
					'current_deadline'         => 12345,
					'is_live'                  => true,
					'status'                   => 'restricted',
					'details_submitted'        => $details_submitted,
				]
			);

		$this->assertEquals( $details_submitted, $this->wcpay_account->is_details_submitted() );
	}

	public function is_details_submitted_provider(): array {
		return [
			[ true ],
			[ false ],
		];
	}

	public function test_is_account_partially_onboarded_returns_false_if_account_not_connected() {
		// The Jetpack connection is in working order.
		$this->mock_jetpack_connection();

		$this->mock_database_cache
			->expects( $this->once() )
			->method( 'get_or_add' )
			->with(
				Database_Cache::ACCOUNT_KEY,
				$this->isType( 'callable' ),
				$this->isType( 'callable' ),
				true
			);

		$this->wcpay_account->refresh_account_data();
	}

	public function test_update_stripe_account() {
		$account = [
			'account_id'               => 'acc_test',
			'live_publishable_key'     => 'pk_test_',
			'test_publishable_key'     => 'pk_live_',
			'has_pending_requirements' => true,
			'current_deadline'         => 12345,
			'is_live'                  => true,
			'statement_descriptor'     => 'WCPAY',
		];

		$this->mock_database_cache
			->expects( $this->once() )
			->method( 'get' )
			->with( Database_Cache::ACCOUNT_KEY )
			->willReturn( $account );

		$new_descriptor   = 'WCPAY_DEV';
		$expected_account = array_merge(
			$account,
			[ 'statement_descriptor' => $new_descriptor ]
		);

		$request = $this->mock_wcpay_request( Update_Account::class );

		$request->expects( $this->once() )
			->method( 'set_statement_descriptor' )
			->with( $new_descriptor );

		$request->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( new Response( $expected_account ) );

		$this->mock_database_cache
			->expects( $this->once() )
			->method( 'add' )
			->with( Database_Cache::ACCOUNT_KEY, $expected_account );

		$this->wcpay_account->update_stripe_account( [ 'statement_descriptor' => $new_descriptor ] );
	}

	public function test_update_stripe_account_skipped() {
		$account = [
			'account_id'               => 'acc_test',
			'live_publishable_key'     => 'pk_test_',
			'test_publishable_key'     => 'pk_live_',
			'has_pending_requirements' => true,
			'current_deadline'         => 12345,
			'is_live'                  => true,
			'statement_descriptor'     => 'WCPAY',
		];

		$this->mock_database_cache
			->expects( $this->once() )
			->method( 'get' )
			->with( Database_Cache::ACCOUNT_KEY )
			->willReturn( $account );

		$this->mock_wcpay_request( Update_Account::class, 0 );
		$this->wcpay_account->update_stripe_account( [ 'statement_descriptor' => 'WCPAY' ] );
	}

	public function test_update_stripe_account_failed() {
		$account = [
			'account_id'               => 'acc_test',
			'live_publishable_key'     => 'pk_test_',
			'test_publishable_key'     => 'pk_live_',
			'has_pending_requirements' => true,
			'current_deadline'         => 12345,
			'is_live'                  => true,
			'statement_descriptor'     => 'WCPAY',
		];

		$this->mock_database_cache
			->expects( $this->once() )
			->method( 'get' )
			->with( Database_Cache::ACCOUNT_KEY )
			->willReturn( $account );

		$request = $this->mock_wcpay_request( Update_Account::class );
		$request->expects( $this->once() )
			->method( 'format_response' )
			->willThrowException(
				new API_Exception( 'test', 'bad_request', 400 )
			);

		$result = $this->wcpay_account->update_stripe_account( [ 'statement_descriptor' => 'WCPAY_DEV' ] );

		$this->assertInstanceOf( WP_Error::class, $result );
	}

	/**
	 * Test get_cached_account_data when we have already cached the "no account connected" response.
	 */
	public function test_get_cached_account_data_when_no_account_connected_and_result_cached() {
		// Setup the cache with expired account information.
		$this->cache_account_details( [] );

		// Wire up the API client mock to return updated account data.
		$this->mock_wcpay_request( Get_Account::class, 0 );

		// Fetch the account.
		$account = $this->wcpay_account->get_cached_account_data();

		// Assert that the "no account connected" special case is returned as expected.
		$this->assertSame( [], $account );
	}

	public function test_handle_instant_deposits_inbox_note() {
		if ( ! version_compare( WC_VERSION, '4.4.0', '>=' ) ) {
			$this->markTestSkipped( 'The used WC components are not backward compatible' );
			return;
		}
		$action_hook = 'wcpay_instant_deposit_reminder';
		$this->mock_action_scheduler_service
			->expects( $this->exactly( 2 ) )
			->method( 'pending_action_exists' )
			->withConsecutive( [ $action_hook ], [ $action_hook ] )
			->willReturnOnConsecutiveCalls( false, true );

		$this->mock_action_scheduler_service
			->expects( $this->once() )
			->method( 'schedule_job' )
			->with(
				$this->greaterThan( time() ),
				$action_hook
			);

		$account = [
			'is_live'                   => true,
			'instant_deposits_eligible' => true,
		];

		$this->wcpay_account->handle_instant_deposits_inbox_note( $account );

		$note_id = WC_Payments_Notes_Instant_Deposits_Eligible::NOTE_NAME;
		$this->assertNotSame( [], ( WC_Data_Store::load( 'admin-note' ) )->get_notes_with_name( $note_id ) );

		// Test to see if scheduled action was created.
		$this->assertTrue( $this->mock_action_scheduler_service->pending_action_exists( $action_hook ) );
	}

	public function test_handle_instant_deposits_inbox_note_not_eligible() {
		if ( ! version_compare( WC_VERSION, '4.4.0', '>=' ) ) {
			$this->markTestSkipped( 'The used WC components are not backward compatible' );
			return;
		}

		$account = [
			'is_live'                   => true,
			'instant_deposits_eligible' => false,
		];

		$this->wcpay_account->handle_instant_deposits_inbox_note( $account );

		$note_id = WC_Payments_Notes_Instant_Deposits_Eligible::NOTE_NAME;
		$this->assertSame( [], ( WC_Data_Store::load( 'admin-note' ) )->get_notes_with_name( $note_id ) );
	}

	public function test_handle_instant_deposits_inbox_reminder_will_not_schedule_job_if_pending_action_exist() {
		if ( ! version_compare( WC_VERSION, '4.4.0', '>=' ) ) {
			$this->markTestSkipped( 'The used WC components are not backward compatible' );
			return;
		}

		// The Jetpack connection is in working order.
		$this->mock_jetpack_connection();

		$account = [
			'is_live'                   => true,
			'instant_deposits_eligible' => true,
		];

		$this->cache_account_details( $account );
		$this->mock_action_scheduler_service
			->expects( $this->once() )
			->method( 'pending_action_exists' )
			->with( WC_Payments_Account::INSTANT_DEPOSITS_REMINDER_ACTION )
			->willReturn( true );

		$this->mock_action_scheduler_service
			->expects( $this->never() )
			->method( 'schedule_job' );

		$this->wcpay_account->handle_instant_deposits_inbox_reminder();
	}

	public function test_handle_instant_deposits_inbox_reminder() {
		if ( ! version_compare( WC_VERSION, '4.4.0', '>=' ) ) {
			$this->markTestSkipped( 'The used WC components are not backward compatible' );
			return;
		}

		// The Jetpack connection is in working order.
		$this->mock_jetpack_connection();

		$account = [
			'is_live'                   => true,
			'instant_deposits_eligible' => true,
		];

		$this->cache_account_details( $account );
		$this->mock_action_scheduler_service
			->expects( $this->once() )
			->method( 'pending_action_exists' )
			->with( WC_Payments_Account::INSTANT_DEPOSITS_REMINDER_ACTION )
			->willReturn( false );

		$this->mock_action_scheduler_service
			->expects( $this->once() )
			->method( 'schedule_job' )
			->willReturn(
				$this->greaterThan( time() ),
				WC_Payments_Account::INSTANT_DEPOSITS_REMINDER_ACTION
			);

		$this->wcpay_account->handle_instant_deposits_inbox_reminder();
	}

	public function loan_approved_no_action_account_states() {
		return [
			[ [] ],
			[ [ 'capital' => [] ] ],
			[ [ 'capital' => [ 'has_active_loan' => false ] ] ],
		];
	}

	/**
	 * @dataProvider loan_approved_no_action_account_states
	 */
	public function test_handle_loan_approved_inbox_note_not_created( $account ) {

		if ( ! version_compare( WC_VERSION, '4.4.0', '>=' ) ) {
			$this->markTestSkipped( 'The used WC components are not backward compatible' );
			return;
		}

		$this->wcpay_account->handle_loan_approved_inbox_note( $account );
		$note_id = WC_Payments_Notes_Loan_Approved::NOTE_NAME;
		$this->assertSame( [], ( WC_Data_Store::load( 'admin-note' ) )->get_notes_with_name( $note_id ) );
	}

	public function get_cached_account_loan_data() {
		return [
			'capital' => [ 'has_active_loan' => true ],
		];
	}

	public function test_handle_loan_approved_inbox_note_not_created_when_loan_summary_throws_exception() {
		if ( ! version_compare( WC_VERSION, '4.4.0', '>=' ) ) {
			$this->markTestSkipped( 'The used WC components are not backward compatible' );
			return;
		}

		$request = $this->mock_wcpay_request( Get_Request::class );
		$request->expects( $this->once() )
			->method( 'format_response' )
			->willThrowException( new API_Exception( 'test_exception', 0, 400 ) );

		$this->wcpay_account->handle_loan_approved_inbox_note( $this->get_cached_account_loan_data() );
		$note_id = WC_Payments_Notes_Loan_Approved::NOTE_NAME;
		$this->assertSame( [], ( WC_Data_Store::load( 'admin-note' ) )->get_notes_with_name( $note_id ) );
	}

	public function test_handle_loan_approved_inbox_note_not_created_when_loan_summary_returns_invalid_data() {
		if ( ! version_compare( WC_VERSION, '4.4.0', '>=' ) ) {
			$this->markTestSkipped( 'The used WC components are not backward compatible' );
			return;
		}

		$request = $this->mock_wcpay_request( Get_Request::class );
		$request->expects( $this->once() )
			->method( 'set_api' )
			->with( WC_Payments_API_Client::CAPITAL_API . '/active_loan_summary' );

		$request->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( [ 'test' ] );

		$this->wcpay_account->handle_loan_approved_inbox_note( $this->get_cached_account_loan_data() );
		$note_id = WC_Payments_Notes_Loan_Approved::NOTE_NAME;
		$this->assertSame( [], ( WC_Data_Store::load( 'admin-note' ) )->get_notes_with_name( $note_id ) );
	}

	public function test_handle_loan_approved_inbox_note_created_when_loan_summary_returns_valid_data() {
		if ( ! version_compare( WC_VERSION, '4.4.0', '>=' ) ) {
			$this->markTestSkipped( 'The used WC components are not backward compatible' );
			return;
		}

		$advance_amount           = 1234567;
		$formatted_advance_amount = wp_kses_normalize_entities( wp_strip_all_tags( wc_price( $advance_amount / 100 ) ) ); // Match it with note content sanitization process.
		$time                     = time();

		$request = $this->mock_wcpay_request( Get_Request::class );

		$request->expects( $this->once() )
			->method( 'format_response' )
			->willReturn(
				[
					'details' => [
						'advance_amount'      => $advance_amount,
						'advance_paid_out_at' => $time,
						'currency'            => 'USD',
					],
				]
			);

		$this->wcpay_account->handle_loan_approved_inbox_note( $this->get_cached_account_loan_data() );
		$note_id    = WC_Payments_Notes_Loan_Approved::NOTE_NAME;
		$data_store = WC_Data_Store::load( 'admin-note' );
		$notes      = $data_store->get_notes_with_name( $note_id );
		$this->assertCount( 1, $notes );
		$note      = Notes::get_note( $notes[0] );
		$note_data = (array) $note->get_content_data();
		$this->assertEquals( 'Your capital loan has been approved!', $note->get_title() );
		$this->assertEquals( $advance_amount, $note_data['advance_amount'] );
		$this->assertEquals( $time, $note_data['advance_paid_out_at'] );
		$this->assertStringContainsString( $formatted_advance_amount, $note->get_content() );
	}

	public function test_handle_loan_approved_inbox_note_created_when_loan_summary_returns_valid_data_with_different_currency() {
		if ( ! version_compare( WC_VERSION, '4.4.0', '>=' ) ) {
			$this->markTestSkipped( 'The used WC components are not backward compatible' );
			return;
		}

		$advance_amount           = 1234567;
		$formatted_advance_amount = wp_kses_normalize_entities( wp_strip_all_tags( wc_price( $advance_amount / 100, [ 'currency' => 'CHF' ] ) ) ); // Match it with note content sanitization process.
		$time                     = time();

		$request = $this->mock_wcpay_request( Get_Request::class );
		$request->expects( $this->once() )
			->method( 'format_response' )
			->willReturn(
				[
					'details' => [
						'advance_amount'      => $advance_amount,
						'advance_paid_out_at' => $time,
						'currency'            => 'CHF',
					],
				]
			);

		$this->wcpay_account->handle_loan_approved_inbox_note( $this->get_cached_account_loan_data() );
		$note_id    = WC_Payments_Notes_Loan_Approved::NOTE_NAME;
		$data_store = WC_Data_Store::load( 'admin-note' );
		$notes      = $data_store->get_notes_with_name( $note_id );
		$this->assertCount( 1, $notes );
		$note      = Notes::get_note( $notes[0] );
		$note_data = (array) $note->get_content_data();
		$this->assertEquals( 'Your capital loan has been approved!', $note->get_title() );
		$this->assertEquals( $advance_amount, $note_data['advance_amount'] );
		$this->assertEquals( $time, $note_data['advance_paid_out_at'] );
		$this->assertStringContainsString( $formatted_advance_amount, $note->get_content() );
	}

	public function test_get_tracking_info() {
		// The Jetpack connection is in working order.
		$this->mock_jetpack_connection();

		$expected = [
			'hosting-provider' => 'test',
		];

		$this->mock_database_cache
			->expects( $this->once() )
			->method( 'get_or_add' )
			->willReturn( $expected );

		$this->assertSame( $expected, $this->wcpay_account->get_tracking_info() );
	}

	/**
	 * Sets up the mocked cache to simulate that its empty and call the generator.
	 */
	private function mock_empty_cache() {
		$this->mock_database_cache
			->method( 'get_or_add' )
			->willReturnCallback(
				function ( $key, $generator, $validator ) {
					$res = $generator();
					return $validator( $res ) ? $res : null;
				}
			);
	}

	/**
	 * Sets up the mocked WPCOM/Jetpack connection.
	 *
	 * @param bool $working_connection Whether the connection should be mocked as working or not.
	 */
	private function mock_jetpack_connection( bool $working_connection = true ) {
		$this->mock_api_client
			->method( 'is_server_connected' )
			->willReturn( $working_connection );
		$this->mock_api_client
			->method( 'has_server_connection_owner' )
			->willReturn( $working_connection );
	}

	/**
	 * @param bool $can_manage_woocommerce
	 *
	 * @return Closure
	 */
	private function create_can_manage_woocommerce_cap_override( bool $can_manage_woocommerce ) {
		return function ( $allcaps ) use ( $can_manage_woocommerce ) {
			$allcaps['manage_woocommerce'] = $can_manage_woocommerce;

			return $allcaps;
		};
	}

	/**
	 * Cache account details.
	 *
	 * @param $account
	 */
	private function cache_account_details( $account ) {
		$this->mock_database_cache
			->method( 'get_or_add' )
			->willReturnCallback(
				function ( $key, $generator, $validator ) use ( $account ) {
					return $validator( $account ) ? $account : $generator();
				}
			);
	}

	/**
	 * @param bool $is_admin
	 */
	private function set_is_admin( bool $is_admin ) {
		global $current_screen;

		if ( ! $is_admin ) {
			$current_screen = null; // phpcs:ignore: WordPress.WP.GlobalVariablesOverride.Prohibited

			return;
		}

		// phpcs:ignore: WordPress.WP.GlobalVariablesOverride.Prohibited
		$current_screen = $this->getMockBuilder( \stdClass::class )
			->setMethods( [ 'in_admin' ] )
			->getMock();

		$current_screen->method( 'in_admin' )->willReturn( $is_admin );
	}
}
