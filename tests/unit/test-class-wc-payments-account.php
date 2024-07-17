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
	 * Mock WC_Payments_Session_Service.
	 *
	 * @var WC_Payments_Session_Service|MockObject
	 */
	private $mock_session_service;

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

		$this->mock_api_client = $this->getMockBuilder( 'WC_Payments_API_Client' )
			->disableOriginalConstructor()
			->getMock();
		$this->mock_api_client->expects( $this->any() )->method( 'is_server_connected' )->willReturn( true );

		$this->mock_database_cache           = $this->createMock( Database_Cache::class );
		$this->mock_action_scheduler_service = $this->createMock( WC_Payments_Action_Scheduler_Service::class );
		$this->mock_session_service          = $this->createMock( WC_Payments_Session_Service::class );
		$this->mock_redirect_service         = $this->createMock( WC_Payments_Redirect_Service::class );

		$this->wcpay_account = new WC_Payments_Account( $this->mock_api_client, $this->mock_database_cache, $this->mock_action_scheduler_service, $this->mock_session_service, $this->mock_redirect_service );
		$this->wcpay_account->init_hooks();
	}

	public function tear_down() {
		delete_transient( WC_Payments_Account::ON_BOARDING_DISABLED_TRANSIENT );
		unset( $_GET );
		unset( $_REQUEST );
		parent::tear_down();
	}

	public function test_filters_registered_properly() {
		$this->assertNotFalse( has_action( 'admin_init', [ $this->wcpay_account, 'maybe_handle_onboarding' ] ), 'maybe_handle_onboarding action does not exist.' );
		$this->assertNotFalse( has_action( 'admin_init', [ $this->wcpay_account, 'maybe_redirect_after_plugin_activation' ] ), 'maybe_redirect_after_plugin_activation action does not exist.' );
		$this->assertNotFalse( has_action( 'admin_init', [ $this->wcpay_account, 'maybe_redirect_by_get_param' ] ), 'maybe_redirect_by_get_param action does not exist.' );
		$this->assertNotFalse( has_action( 'admin_init', [ $this->wcpay_account, 'maybe_redirect_from_settings_page' ] ), 'maybe_redirect_from_settings_page action does not exist.' );
		$this->assertNotFalse( has_action( 'admin_init', [ $this->wcpay_account, 'maybe_redirect_from_onboarding_page' ] ), 'maybe_redirect_from_onboarding_page action does not exist.' );
		$this->assertNotFalse( has_action( 'admin_init', [ $this->wcpay_account, 'maybe_redirect_from_connect_page' ] ), 'maybe_redirect_from_connect_page action does not exist.' );
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

		$this->mock_api_client
			->method( 'is_server_connected' )
			->willReturn( false );
		$this->mock_api_client
			->method( 'has_server_connection_owner' )
			->willReturn( false );

		// Assert.
		$this->mock_redirect_service
			->expects( $this->once() )
			->method( 'redirect_to_connect_page' )
			->with(
				'Connection to WordPress.com failed. Please connect to WordPress.com to start using WooPayments.',
				'WPCOM_CONNECTION',
				[ 'source' => WC_Payments_Onboarding_Service::SOURCE_WCADMIN_INCENTIVE_PAGE ]
			);

		// Act.
		$this->wcpay_account->maybe_handle_onboarding();
	}

	/**
	 * @dataProvider provider_onboarding_source
	 */
	public function test_maybe_handle_onboarding_connect_by_known_onboarding_source( $onboarding_source, $has_working_jetpack_connection, $is_stripe_connected, $expected_redirect ) {
		// Arrange.
		// We need to be in the WP admin dashboard.
		$this->set_is_admin( true );
		// Test as an admin user.
		wp_set_current_user( 1 );

		$_GET['wcpay-connect'] = 'connect-from';
		$_REQUEST['_wpnonce']  = wp_create_nonce( 'wcpay-connect' );
		// Set the request as if the user was in some bogus page.
		$_GET['page']                   = 'wc-admin';
		$_GET['path']                   = '/payments/some-bogus-page';
		$_GET['source']                 = $onboarding_source;
		$_GET['create_builder_account'] = '0';

		$this->mock_api_client
			->method( 'is_server_connected' )
			->willReturn( $has_working_jetpack_connection );
		$this->mock_api_client
			->method( 'has_server_connection_owner' )
			->willReturn( $has_working_jetpack_connection );

		if ( $is_stripe_connected ) {
			$this->cache_account_details(
				[
					'account_id'        => 'acc_test',
					'is_live'           => true,
					'details_submitted' => true, // Has finished initial KYC.
				]
			);

			$this->mock_api_client
				->method( 'get_onboarding_data' )
				->willReturn( [ 'url' => false ] ); // This means that an account already exits on the platform.
		}

		// Assert.
		switch ( $expected_redirect ) {
			case 'start_jetpack_connection':
				$this->mock_api_client
					->expects( $this->once() )
					->method( 'start_server_connection' )
					->with(
						$this->logicalAnd(
							$this->logicalOr(
								$this->stringContains( 'page=wc-admin&path=/payments/connect' ),
								$this->stringContains( 'page=wc-admin&path=%2Fpayments%2Fconnect' )
							),
							$this->stringContains( 'source=' . $onboarding_source )
						)
					);
				break;
			case 'onboarding_wizard':
				$this->mock_redirect_service
					->expects( $this->once() )
					->method( 'redirect_to_onboarding_wizard' )
					->with( 'connect-from', [ 'source' => $onboarding_source ] );
				break;
			case 'overview_page':
				$this->mock_redirect_service
					->expects( $this->once() )
					->method( 'redirect_to' )
					->with(
						$this->logicalOr(
							$this->stringContains( 'page=wc-admin&path=/payments/overview' ),
							$this->stringContains( 'page=wc-admin&path=%2Fpayments%2Foverview' )
						)
					);
				break;
			default:
				$this->fail( 'Unexpected redirect type: ' . $expected_redirect );
				break;
		}

		// Act.
		$this->wcpay_account->maybe_handle_onboarding();
	}

	/**
	 * Data provider for test_maybe_handle_onboarding_connect_by_known_onboarding_source
	 */
	public function provider_onboarding_source() {
		return [
			'Woo Payments task onboarding source - no Jetpack connection, Stripe connected' => [
				WC_Payments_Onboarding_Service::SOURCE_WCADMIN_PAYMENT_TASK,
				false,
				true,
				'start_jetpack_connection',
			],
			'Woo Payments task onboarding source - Jetpack connection, Stripe not connected' => [
				WC_Payments_Onboarding_Service::SOURCE_WCADMIN_PAYMENT_TASK,
				true,
				false,
				'onboarding_wizard',
			],
			'Woo Payments task onboarding source - Jetpack connection, Stripe connected' => [
				WC_Payments_Onboarding_Service::SOURCE_WCADMIN_PAYMENT_TASK,
				true,
				true,
				'overview_page',
			],
			'Connect page onboarding source - no Jetpack connection, Stripe connected' => [
				WC_Payments_Onboarding_Service::SOURCE_WCPAY_CONNECT_PAGE,
				false,
				true,
				'start_jetpack_connection',
			],
			'Connect page onboarding source - Jetpack connection, Stripe not connected' => [
				WC_Payments_Onboarding_Service::SOURCE_WCPAY_CONNECT_PAGE,
				true,
				false,
				'onboarding_wizard',
			],
			'Connect onboarding source - Jetpack connection, Stripe connected' => [
				WC_Payments_Onboarding_Service::SOURCE_WCPAY_CONNECT_PAGE,
				true,
				true,
				'overview_page',
			],
			'Settings page onboarding source - no Jetpack connection, Stripe connected' => [
				WC_Payments_Onboarding_Service::SOURCE_WCADMIN_SETTINGS_PAGE,
				false,
				true,
				'start_jetpack_connection',
			],
			'Settings page onboarding source - Jetpack connection, Stripe not connected' => [
				WC_Payments_Onboarding_Service::SOURCE_WCADMIN_SETTINGS_PAGE,
				true,
				false,
				'onboarding_wizard',
			],
			'Settings page onboarding source - Jetpack connection, Stripe connected' => [
				WC_Payments_Onboarding_Service::SOURCE_WCADMIN_SETTINGS_PAGE,
				true,
				true,
				'overview_page',
			],
			'Incentive page onboarding source - no Jetpack connection, Stripe connected' => [
				WC_Payments_Onboarding_Service::SOURCE_WCADMIN_INCENTIVE_PAGE,
				false,
				true,
				'start_jetpack_connection',
			],
			'Incentive page onboarding source - Jetpack connection, Stripe not connected' => [
				WC_Payments_Onboarding_Service::SOURCE_WCADMIN_INCENTIVE_PAGE,
				true,
				false,
				'onboarding_wizard',
			],
			'Incentive page onboarding source - Jetpack connection, Stripe connected' => [
				WC_Payments_Onboarding_Service::SOURCE_WCADMIN_INCENTIVE_PAGE,
				true,
				true,
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
		// Set the request as if the user was in some bogus page.
		$_GET['page'] = 'wc-admin';
		$_GET['path'] = '/payments/some-bogus-page';

		$_GET['wcpay-disable-onboarding-test-mode'] = 'true';

		$this->cache_account_details(
			[
				'account_id'        => 'acc_test',
				'is_live'           => true,
				'details_submitted' => true, // Has finished initial KYC.
			]
		);

		// Assert.
		$this->mock_redirect_service
			->expects( $this->once() )
			->method( 'redirect_to_onboarding_wizard' )
			->with( 'connect-from', [ 'source' => WC_Payments_Onboarding_Service::SOURCE_WCPAY_SETUP_LIVE_PAYMENTS ] );

		// Act.
		$this->wcpay_account->maybe_handle_onboarding();
	}

	public function test_maybe_handle_onboarding_reset_account() {
		// Arrange.
		// We need to be in the WP admin dashboard.
		$this->set_is_admin( true );
		// Test as an admin user.
		wp_set_current_user( 1 );

		$_GET['wcpay-connect'] = 'connect-from';
		$_REQUEST['_wpnonce']  = wp_create_nonce( 'wcpay-connect' );
		// Set the request as if the user was in some bogus page.
		$_GET['page'] = 'wc-admin';
		$_GET['path'] = '/payments/some-bogus-page';

		$_GET['wcpay-reset-account'] = 'true';

		$this->cache_account_details(
			[
				'account_id'        => 'acc_test',
				'is_live'           => true,
				'details_submitted' => true, // Has finished initial KYC.
			]
		);

		// Assert.
		$this->mock_redirect_service
			->expects( $this->once() )
			->method( 'redirect_to_onboarding_wizard' )
			->with( 'connect-from', [ 'source' => WC_Payments_Onboarding_Service::SOURCE_WCPAY_RESET_ACCOUNT ] );

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
		// Set the request as if the user was in some bogus page.
		$_GET['page']   = 'wc-admin';
		$_GET['path']   = '/payments/some-bogus-page';
		$_GET['source'] = WC_Payments_Onboarding_Service::SOURCE_WCADMIN_INCENTIVE_PAGE; // This should not matter.
		// Make sure important flags are carried over.
		$_GET['promo']                  = 'incentive_id';
		$_GET['progressive']            = 'true';
		$_GET['create_builder_account'] = 'true';
		$_GET['test_mode']              = '1'; // Some truthy value that will be carried over as `true`.

		// Even if we have connected account data, the Jetpack connection takes precedence.
		$this->cache_account_details(
			[
				'account_id'        => 'acc_test',
				'is_live'           => true,
				'details_submitted' => true, // Has finished initial KYC.
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
					$this->logicalOr(
						$this->stringContains( 'page=wc-admin&path=/payments/connect' ),
						$this->stringContains( 'page=wc-admin&path=%2Fpayments%2Fconnect' )
					),
					$this->stringContains( 'promo=incentive_id' ),
					$this->stringContains( 'progressive=true' ),
					$this->stringContains( 'create_builder_account=true' ),
					$this->stringContains( 'test_mode=true' ),
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
		// Set the request as if the user was in some bogus page.
		$_GET['page']   = 'wc-admin';
		$_GET['path']   = '/payments/some-bogus-page';
		$_GET['source'] = WC_Payments_Onboarding_Service::SOURCE_WCADMIN_INCENTIVE_PAGE; // This should not matter.
		// Make sure important flags are carried over.
		$_GET['promo']       = 'incentive_id';
		$_GET['progressive'] = 'true';

		// There isn't another onboarding started.
		delete_transient( WC_Payments_Account::ON_BOARDING_STARTED_TRANSIENT );

		// The Jetpack connection is in working order.
		$this->mock_api_client
			->method( 'is_server_connected' )
			->willReturn( true );
		$this->mock_api_client
			->method( 'has_server_connection_owner' )
			->willReturn( true );

		// Assert.
		$this->mock_api_client
			->expects( $this->once() )
			->method( 'get_onboarding_data' )
			->with(
				$this->logicalAnd(
					$this->logicalOr(
						$this->stringContains( 'page=wc-admin&path=/payments/overview' ),
						$this->stringContains( 'page=wc-admin&path=%2Fpayments%2Foverview' )
					),
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
			->willReturn(
				[
					'url' => admin_url( 'admin.php' ), // This will pass the redirect validation.
				]
			);

		$this->mock_redirect_service
			->expects( $this->once() )
			->method( 'redirect_to' )
			->with( admin_url( 'admin.php' ) );

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
		// Set the request as if the user was in some bogus page.
		$_GET['page']   = 'wc-admin';
		$_GET['path']   = '/payments/some-bogus-page';
		$_GET['source'] = WC_Payments_Onboarding_Service::SOURCE_WCADMIN_INCENTIVE_PAGE; // This should not matter.
		// Make sure important flags are carried over.
		$_GET['promo']       = 'incentive_id';
		$_GET['progressive'] = 'true';

		// There isn't another onboarding started.
		delete_transient( WC_Payments_Account::ON_BOARDING_STARTED_TRANSIENT );

		// The Jetpack connection is in working order.
		$this->mock_api_client
			->method( 'is_server_connected' )
			->willReturn( true );
		$this->mock_api_client
			->method( 'has_server_connection_owner' )
			->willReturn( true );

		// Assert.
		$this->mock_api_client
			->expects( $this->once() )
			->method( 'get_onboarding_data' )
			->with(
				$this->logicalAnd(
					$this->logicalOr(
						$this->stringContains( 'page=wc-admin&path=/payments/overview' ),
						$this->stringContains( 'page=wc-admin&path=%2Fpayments%2Foverview' )
					),
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
					$this->logicalOr(
						$this->stringContains( 'page=wc-admin&path=/payments/overview' ),
						$this->stringContains( 'page=wc-admin&path=%2Fpayments%2Foverview' )
					),
					$this->stringContains( 'promo=incentive_id' ),
					$this->stringContains( 'progressive=true' ),
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
		// Set the request as if the user was in some bogus page.
		$_GET['page']   = 'wc-admin';
		$_GET['path']   = '/payments/some-bogus-page';
		$_GET['source'] = WC_Payments_Onboarding_Service::SOURCE_WCADMIN_INCENTIVE_PAGE; // This should not matter.
		// Make sure important flags are carried over.
		$_GET['promo']       = 'incentive_id';
		$_GET['progressive'] = 'true';

		// There isn't another onboarding started.
		set_transient( WC_Payments_Account::ON_BOARDING_STARTED_TRANSIENT, true, 10 );

		// The Jetpack connection is in working order.
		$this->mock_api_client
			->method( 'is_server_connected' )
			->willReturn( true );
		$this->mock_api_client
			->method( 'has_server_connection_owner' )
			->willReturn( true );

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

	public function test_maybe_redirect_to_onboarding_stripe_disconnected_redirects() {
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
	}

	public function test_maybe_redirect_to_onboarding_stripe_disconnected_and_on_boarding_disabled_redirects() {
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
	}

	public function test_maybe_redirect_to_onboarding_account_error() {
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

		$this->assertFalse( $this->wcpay_account->maybe_redirect_after_plugin_activation() );
		// Should not update the option.
		$this->assertTrue( (bool) get_option( 'wcpay_should_redirect_to_onboarding', false ) );
	}

	public function test_maybe_redirect_to_onboarding_account_connected() {
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
					]
				)
			);

		$this->assertFalse( $this->wcpay_account->maybe_redirect_after_plugin_activation() );
		// The option should be updated.
		$this->assertFalse( (bool) get_option( 'wcpay_should_redirect_to_onboarding', false ) );
	}

	public function test_maybe_redirect_to_onboarding_with_non_admin_user() {
		// Simulate the situation where the redirect has not happened yet.
		update_option( 'wcpay_should_redirect_to_onboarding', true );

		$this->mock_empty_cache();

		$cb = $this->create_can_manage_woocommerce_cap_override( false );
		add_filter( 'user_has_cap', $cb );

		$this->mock_wcpay_request( Get_Account::class, 0 );

		$this->assertFalse( $this->wcpay_account->maybe_redirect_after_plugin_activation() );
		// The option should be updated.
		$this->assertTrue( (bool) get_option( 'wcpay_should_redirect_to_onboarding', false ) );
	}

	public function test_maybe_redirect_to_onboarding_checks_the_account_once() {
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
					]
				)
			);

		$this->assertFalse( $this->wcpay_account->maybe_redirect_after_plugin_activation() );
		// call the method twice but use the mock_api_client to make sure the account has been retrieved only once.
		$this->assertFalse( $this->wcpay_account->maybe_redirect_after_plugin_activation() );
		// The option should be updated.
		$this->assertFalse( (bool) get_option( 'wcpay_should_redirect_to_onboarding', false ) );
	}

	public function test_maybe_redirect_to_onboarding_returns_true_and_on_boarding_re_enabled() {
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
	public function test_maybe_redirect_from_onboarding_page( $expected_redirect_to_count, $expected_method, $stripe_account_connected, $is_server_connected, $get_params ) {
		wp_set_current_user( 1 );
		$_GET = $get_params;

		if ( $stripe_account_connected ) {
			$this->cache_account_details(
				[
					'account_id' => 'acc_test',
					'is_live'    => true,
				]
			);
		}

		$this->mock_api_client = $this->getMockBuilder( 'WC_Payments_API_Client' )
			->disableOriginalConstructor()
			->getMock();

		$this->mock_api_client
			->method( 'is_server_connected' )
			->willReturn( $is_server_connected );

		$this->wcpay_account = new WC_Payments_Account( $this->mock_api_client, $this->mock_database_cache, $this->mock_action_scheduler_service, $this->mock_session_service, $this->mock_redirect_service );

		$this->mock_redirect_service->expects( $this->exactly( $expected_redirect_to_count ) )->method( $expected_method );

		$this->wcpay_account->maybe_redirect_from_onboarding_page();
	}

	/**
	 * Data provider for test_maybe_redirect_from_onboarding_page
	 */
	public function data_maybe_redirect_from_onboarding_page() {
		return [
			'no_get_params'        => [
				0,
				'redirect_to_connect_page',
				false,
				true,
				[],
			],
			'missing_param'        => [
				0,
				'redirect_to_connect_page',
				false,
				true,
				[
					'page' => 'wc-admin',
				],
			],
			'incorrect_param'      => [
				0,
				'redirect_to_connect_page',
				false,
				true,
				[
					'page' => 'wc-settings',
					'path' => '/payments/onboarding',
				],
			],
			'empty_path_param'     => [
				0,
				'redirect_to_connect_page',
				false,
				true,
				[
					'page' => 'wc-admin',
				],
			],
			'incorrect_path_param' => [
				0,
				'redirect_to_connect_page',
				false,
				true,
				[
					'page' => 'wc-admin',
					'path' => '/payments/does-not-exist',
				],
			],
			'server_not_connected' => [
				1,
				'redirect_to_connect_page',
				false,
				false,
				[
					'page' => 'wc-admin',
					'path' => '/payments/onboarding',
				],
			],
			'stripe not connected' => [
				0,
				'redirect_to_connect_page',
				false,
				true,
				[
					'page' => 'wc-admin',
					'path' => '/payments/onboarding',
				],
			],
			'happy_path'           => [
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
	public function test_maybe_redirect_from_settings_page( $expected_redirect_to_count, $expected_method, $details_submitted, $get_params, $no_account = false ) {
		wp_set_current_user( 1 );
		$_GET = $get_params;

		if ( ! $no_account ) {
			$this->cache_account_details(
				[
					'account_id'        => 'acc_test',
					'is_live'           => true,
					'details_submitted' => $details_submitted,
				]
			);
		}
		$this->mock_redirect_service->expects( $this->exactly( $expected_redirect_to_count ) )
			->method( $expected_method );

		$this->wcpay_account->maybe_redirect_from_settings_page();
	}

	/**
	 * Data provider for test_maybe_redirect_from_settings_page
	 */
	public function data_maybe_redirect_from_settings_page() {
		return [
			'no_get_params'               => [
				0,
				'redirect_to_connect_page',
				false,
				[],
			],
			'missing_param'               => [
				0,
				'redirect_to_connect_page',
				false,
				[
					'page' => 'wc-settings',
					'tab'  => 'checkout',
				],
			],
			'incorrect_param'             => [
				0,
				'redirect_to_connect_page',
				false,
				[
					'page'    => 'wc-admin',
					'tab'     => 'checkout',
					'section' => 'woocommerce_payments',
				],
			],
			'no_account'                  => [
				1,
				'redirect_to_connect_page',
				false,
				[
					'page'    => 'wc-settings',
					'tab'     => 'checkout',
					'section' => 'woocommerce_payments',
				],
				true,
			],
			'account_partially_onboarded' => [
				1,
				'redirect_to_overview_page',
				false,
				[
					'page'    => 'wc-settings',
					'tab'     => 'checkout',
					'section' => 'woocommerce_payments',
				],
				false,
			],
			'account_fully_onboarded'     => [
				0,
				'redirect_to_connect_page',
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
	public function test_maybe_redirect_from_connect_page( $expected_redirect_to_count, $expected_method, $stripe_account_connected, $has_working_jetpack_connection, $get_params ) {
		wp_set_current_user( 1 );
		$_GET = $get_params;

		if ( $stripe_account_connected ) {
			$this->cache_account_details(
				[
					'account_id' => 'acc_test',
					'is_live'    => true,
				]
			);
		}

		$this->mock_api_client = $this->getMockBuilder( 'WC_Payments_API_Client' )
			->disableOriginalConstructor()
			->getMock();

		$this->mock_api_client
			->method( 'is_server_connected' )
			->willReturn( $has_working_jetpack_connection );
		$this->mock_api_client
			->method( 'has_server_connection_owner' )
			->willReturn( $has_working_jetpack_connection );

		$this->wcpay_account = new WC_Payments_Account( $this->mock_api_client, $this->mock_database_cache, $this->mock_action_scheduler_service, $this->mock_session_service, $this->mock_redirect_service );

		$this->mock_redirect_service->expects( $this->exactly( $expected_redirect_to_count ) )->method( $expected_method );

		$this->wcpay_account->maybe_redirect_from_connect_page();
	}

	/**
	 * Data provider for test_maybe_redirect_from_connect_page
	 */
	public function data_maybe_redirect_from_connect_page() {
		return [
			'no_get_params'        => [
				0,
				'redirect_to_overview_page',
				true,
				true,
				[],
			],
			'missing_param'        => [
				0,
				'redirect_to_overview_page',
				true,
				true,
				[
					'page' => 'wc-admin',
				],
			],
			'incorrect_param'      => [
				0,
				'redirect_to_overview_page',
				true,
				true,
				[
					'page' => 'wc-settings',
					'path' => '/payments/connect',
				],
			],
			'empty_path_param'     => [
				0,
				'redirect_to_overview_page',
				true,
				true,
				[
					'page' => 'wc-admin',
				],
			],
			'incorrect_path_param' => [
				0,
				'redirect_to_overview_page',
				true,
				true,
				[
					'page' => 'wc-admin',
					'path' => '/payments/does-not-exist',
				],
			],
			'server_not_connected' => [
				0,
				'redirect_to_overview_page',
				true,
				false,
				[
					'page' => 'wc-admin',
					'path' => '/payments/connect',
				],
			],
			'stripe_not_connected' => [
				0,
				'redirect_to_overview_page',
				false,
				true,
				[
					'page' => 'wc-admin',
					'path' => '/payments/connect',
				],
			],
			'happy_path'           => [
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

	public function test_try_is_stripe_connected_returns_true_when_connected() {
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

	public function test_try_is_stripe_connected_throws() {
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
		$this->mock_empty_cache();

		$this->mock_wcpay_request( Get_Account::class )
			->expects( $this->once() )
			->method( 'format_response' )
			->willThrowException( new API_Exception( 'test', 'wcpay_mock', 500 ) );

		$this->assertFalse( $this->wcpay_account->is_stripe_account_valid() );
	}

	public function test_is_stripe_account_valid_when_empty_account_data() {
		$this->mock_empty_cache();

		$this->mock_wcpay_request( Get_Account::class )
			->expects( $this->once() )
			->method( 'format_response' )
			->willReturn(
				new Response( [] )
			);

		$this->assertFalse( $this->wcpay_account->is_stripe_account_valid() );
	}

	public function test_is_stripe_account_valid_when_capability_unrequested() {
		$this->mock_database_cache->expects( $this->exactly( 2 ) )->method( 'get_or_add' )->willReturn(
			[
				'account_id'               => 'acc_test',
				'live_publishable_key'     => 'pk_live_',
				'test_publishable_key'     => 'pk_test_',
				'has_pending_requirements' => true,
				'current_deadline'         => 12345,
				'is_live'                  => true,
				'capabilities'             => [
					'card_payments' => 'unrequested',
				],
			]
		);

		$this->assertFalse( $this->wcpay_account->is_stripe_account_valid() );
	}

	public function test_is_stripe_account_valid_when_capability_requested() {
		$this->mock_database_cache->expects( $this->exactly( 2 ) )->method( 'get_or_add' )->willReturn(
			[
				'account_id'               => 'acc_test',
				'live_publishable_key'     => 'pk_live_',
				'test_publishable_key'     => 'pk_test_',
				'has_pending_requirements' => true,
				'current_deadline'         => 12345,
				'is_live'                  => true,
				'capabilities'             => [
					'card_payments' => 'requested',
				],
			]
		);

		$this->assertTrue( $this->wcpay_account->is_stripe_account_valid() );
	}

	public function test_is_stripe_account_valid_when_capability_active() {
		$this->mock_database_cache->expects( $this->exactly( 2 ) )->method( 'get_or_add' )->willReturn(
			[
				'account_id'               => 'acc_test',
				'live_publishable_key'     => 'pk_live_',
				'test_publishable_key'     => 'pk_test_',
				'has_pending_requirements' => true,
				'current_deadline'         => 12345,
				'is_live'                  => true,
				'capabilities'             => [
					'card_payments' => 'active',
				],
			]
		);

		$this->assertTrue( $this->wcpay_account->is_stripe_account_valid() );
	}

	public function test_is_stripe_account_valid_when_capability_pending_verification() {
		$this->mock_database_cache->expects( $this->exactly( 2 ) )->method( 'get_or_add' )->willReturn(
			[
				'account_id'               => 'acc_test',
				'live_publishable_key'     => 'pk_live_',
				'test_publishable_key'     => 'pk_test_',
				'has_pending_requirements' => true,
				'current_deadline'         => 12345,
				'is_live'                  => true,
				'capabilities'             => [
					'card_payments' => 'pending_verification',
				],
			]
		);

		$this->assertTrue( $this->wcpay_account->is_stripe_account_valid() );
	}

	public function test_get_publishable_key_returns_for_live() {
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
		$this->mock_empty_cache();

		$this->mock_wcpay_request( Get_Account::class )
			->expects( $this->once() )
			->method( 'format_response' )
			->willThrowException(
				new API_Exception( 'test', 'test', 123 )
			);

		$this->assertNull( $this->wcpay_account->get_stripe_account_id() );
	}

	public function test_try_is_stripe_connected_returns_true_when_connected_with_dev_account_in_dev_mode() {
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

		WC_Payments::mode()->live();
	}

	public function test_try_is_stripe_connected_returns_false_when_connected_with_dev_account_in_live_mode() {
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

		WC_Payments::mode()->live();
	}

	public function test_try_is_stripe_connected_returns_true_when_connected_with_live_account_in_dev_mode() {
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

		WC_Payments::mode()->live();
	}

	public function test_is_account_rejected_returns_true() {
		$this->mock_database_cache->expects( $this->exactly( 2 ) )->method( 'get_or_add' )->willReturn(
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
		$this->mock_database_cache->expects( $this->exactly( 2 ) )->method( 'get_or_add' )->willReturn(
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
		$this->mock_database_cache->expects( $this->once() )
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
		$expected_account = [
			'account_id'               => 'acc_test',
			'live_publishable_key'     => 'pk_test_',
			'test_publishable_key'     => 'pk_live_',
			'has_pending_requirements' => true,
			'current_deadline'         => 12345,
			'is_live'                  => true,
		];

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
