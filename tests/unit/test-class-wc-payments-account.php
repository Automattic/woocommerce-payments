<?php
/**
 * Class WC_Payments_Account_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\Exceptions\API_Exception;

/**
 * WC_Payments_Account unit tests.
 */
class WC_Payments_Account_Test extends WP_UnitTestCase {

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
	 * @var WC_Payments_API_Client|PHPUnit_Framework_MockObject_MockObject
	 */
	private $mock_api_client;

	/**
	 * Pre-test setup
	 */
	public function setUp() {
		parent::setUp();

		// Set the request as if the user was in the Payments onboarding page so the "wp_redirect(); exit();" code doesn't run.
		$_GET = [
			'page' => 'wc-admin',
			'path' => '/payments/connect',
		];

		$this->mock_api_client = $this->getMockBuilder( 'WC_Payments_API_Client' )
			->disableOriginalConstructor()
			->getMock();
		$this->mock_api_client->expects( $this->any() )->method( 'is_server_connected' )->willReturn( true );

		$this->wcpay_account = new WC_Payments_Account( $this->mock_api_client );
	}

	public function tearDown() {
		delete_option( WC_Payments_Account::ACCOUNT_OPTION );
		delete_transient( WC_Payments_Account::ON_BOARDING_DISABLED_TRANSIENT );
		unset( $_GET );
		parent::tearDown();
	}

	public function test_maybe_redirect_to_onboarding_stripe_disconnected_redirects() {
		// Simulate the situation where the redirect has not happened yet.
		update_option( 'wcpay_should_redirect_to_onboarding', true );

		$this->mock_api_client->expects( $this->once() )->method( 'get_account_data' )->will(
			$this->throwException( new API_Exception( 'test', 'wcpay_account_not_found', 401 ) )
		);

		$this->assertTrue( $this->wcpay_account->maybe_redirect_to_onboarding() );
		$this->assertFalse( WC_Payments_Account::is_on_boarding_disabled() );
		// The option should be updated.
		$this->assertFalse( get_option( 'wcpay_should_redirect_to_onboarding', false ) );
	}

	public function test_maybe_redirect_to_onboarding_stripe_disconnected_and_on_boarding_disabled_redirects() {
		// Simulate the situation where the redirect has not happened yet.
		update_option( 'wcpay_should_redirect_to_onboarding', true );

		$this->mock_api_client->expects( $this->once() )->method( 'get_account_data' )->will(
			$this->throwException(
				new API_Exception(
					'test',
					'wcpay_on_boarding_disabled',
					401
				)
			)
		);

		$this->assertTrue( $this->wcpay_account->maybe_redirect_to_onboarding() );
		$this->assertTrue( WC_Payments_Account::is_on_boarding_disabled() );
		// The option should be updated.
		$this->assertFalse( get_option( 'wcpay_should_redirect_to_onboarding', false ) );
	}

	public function test_maybe_redirect_to_onboarding_account_error() {
		// Simulate the situation where the redirect has not happened yet.
		update_option( 'wcpay_should_redirect_to_onboarding', true );

		$this->mock_api_client->expects( $this->once() )->method( 'get_account_data' )->will(
			$this->throwException( new Exception() )
		);

		$this->expectException( Exception::class );

		$this->assertFalse( $this->wcpay_account->maybe_redirect_to_onboarding() );
		// Should not update the option.
		$this->assertTrue( get_option( 'wcpay_should_redirect_to_onboarding', false ) );
	}

	public function test_maybe_redirect_to_onboarding_account_connected() {
		// Simulate the situation where the redirect has not happened yet.
		update_option( 'wcpay_should_redirect_to_onboarding', true );

		$this->mock_api_client->expects( $this->once() )->method( 'get_account_data' )->will(
			$this->returnValue(
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

		$this->assertFalse( $this->wcpay_account->maybe_redirect_to_onboarding() );
		// The option should be updated.
		$this->assertFalse( get_option( 'wcpay_should_redirect_to_onboarding', false ) );
	}

	public function test_maybe_redirect_to_onboarding_checks_the_account_once() {
		// Simulate the situation where the redirect has not happened yet.
		update_option( 'wcpay_should_redirect_to_onboarding', true );

		$this->mock_api_client->expects( $this->once() )->method( 'get_account_data' )->will(
			$this->returnValue(
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

		$this->assertFalse( $this->wcpay_account->maybe_redirect_to_onboarding() );
		// call the method twice but use the mock_api_client to make sure the account has been retrieved only once.
		$this->assertFalse( $this->wcpay_account->maybe_redirect_to_onboarding() );
		// The option should be updated.
		$this->assertFalse( get_option( 'wcpay_should_redirect_to_onboarding', false ) );
	}

	public function test_maybe_redirect_to_onboarding_returns_true_and_on_boarding_re_enabled() {
		// We will call get_account_data twice. The first call will tell us no account is connected and that on-boarding
		// is disabled. The second call will just tell us that no account is connected (i.e. on-boarding was
		// re-enabled). willReturnCallback is being used because PHPUnit doesn't have any helper methods for returning a
		// mix of values and exceptions.
		$expected_call_count = $this->exactly( 2 );
		$this->mock_api_client
			->expects( $expected_call_count )
			->method( 'get_account_data' )
			->willReturnCallback(
				function () use ( $expected_call_count ) {
					if ( $expected_call_count->getInvocationCount() === 1 ) {
						throw new API_Exception(
							'test',
							'wcpay_on_boarding_disabled',
							401
						);
					}

					return [
						'account_id'               => 'acc_test',
						'live_publishable_key'     => 'pk_test_',
						'test_publishable_key'     => 'pk_live_',
						'has_pending_requirements' => true,
						'current_deadline'         => 12345,
						'is_live'                  => true,
					];
				}
			);

		// Simulate the situation where the redirect has not happened yet.
		update_option( 'wcpay_should_redirect_to_onboarding', true );

		// First call, on-boarding is disabled.
		$this->wcpay_account->maybe_redirect_to_onboarding();
		$this->assertTrue( WC_Payments_Account::is_on_boarding_disabled() );

		// Simulate the account details cache timing out.
		delete_option( WC_Payments_Account::ACCOUNT_OPTION );

		// Simulate the situation where the redirect has not happened yet.
		update_option( 'wcpay_should_redirect_to_onboarding', true );

		// Second call, on-boarding re-enabled.
		$this->wcpay_account->maybe_redirect_to_onboarding();
		$this->assertFalse( WC_Payments_Account::is_on_boarding_disabled() );
	}

	public function test_try_is_stripe_connected_returns_true_when_connected() {
		$this->mock_api_client->expects( $this->once() )->method( 'get_account_data' )->will(
			$this->returnValue(
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
		$this->mock_api_client->expects( $this->once() )->method( 'get_account_data' )->will(
			$this->throwException( new API_Exception( 'test', 'server_error', 500 ) )
		);

		// Server exception is masked by generic exception.
		$this->expectException( Exception::class );

		$this->wcpay_account->try_is_stripe_connected();
	}

	public function test_try_is_stripe_connected_returns_false() {
		$this->mock_api_client->expects( $this->once() )->method( 'get_account_data' )->will(
			$this->throwException( new API_Exception( 'test', 'wcpay_account_not_found', 401 ) )
		);

		$this->assertFalse( $this->wcpay_account->try_is_stripe_connected() );
	}

	public function test_is_stripe_connected_returns_true_when_connected() {
		$this->mock_api_client->expects( $this->once() )->method( 'get_account_data' )->will(
			$this->returnValue(
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
		$this->mock_api_client->expects( $this->once() )->method( 'get_account_data' )->will(
			$this->throwException( new API_Exception( 'test', 'server_error', 500 ) )
		);

		$this->assertFalse( $this->wcpay_account->is_stripe_connected( false ) );
	}

	public function test_is_stripe_connected_returns_false_when_not_connected() {
		$this->mock_api_client->expects( $this->once() )->method( 'get_account_data' )->will(
			$this->throwException( new API_Exception( 'test', 'wcpay_account_not_found', 401 ) )
		);

		$this->assertFalse( $this->wcpay_account->is_stripe_connected( false ) );
	}

	public function test_get_publishable_key_returns_for_live() {
		$this->mock_api_client->expects( $this->once() )->method( 'get_account_data' )->will(
			$this->returnValue(
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
		$this->mock_api_client->expects( $this->once() )->method( 'get_account_data' )->will(
			$this->returnValue(
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
		$this->mock_api_client->expects( $this->once() )->method( 'get_account_data' )->will(
			$this->throwException( new API_Exception( 'test', 'test', 123 ) )
		);

		$this->assertNull( $this->wcpay_account->get_publishable_key( true ) );
	}

	public function test_get_stripe_account_id() {
		$this->mock_api_client->expects( $this->once() )->method( 'get_account_data' )->will(
			$this->returnValue(
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
		$this->mock_api_client->expects( $this->once() )->method( 'get_account_data' )->will(
			$this->throwException( new API_Exception( 'test', 'test', 123 ) )
		);

		$this->assertNull( $this->wcpay_account->get_stripe_account_id() );
	}

	public function test_try_is_stripe_connected_returns_true_when_connected_with_dev_account_in_dev_mode() {
		// enable dev mode.
		add_filter( 'wcpay_dev_mode', '__return_true' );

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
		$this->mock_api_client->expects( $this->never() )->method( 'get_account_data' );

		$this->assertTrue( $this->wcpay_account->try_is_stripe_connected() );

		remove_filter( 'wcpay_dev_mode', '__return_true' );
	}

	public function test_try_is_stripe_connected_returns_false_when_connected_with_dev_account_in_live_mode() {
		// disable dev mode.
		add_filter( 'wcpay_dev_mode', '__return_false' );

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
		$this->mock_api_client->expects( $this->once() )->method( 'get_account_data' )->will(
			$this->throwException( new API_Exception( 'test', 'wcpay_account_not_found', 401 ) )
		);

		$this->assertFalse( $this->wcpay_account->try_is_stripe_connected() );

		remove_filter( 'wcpay_dev_mode', '__return_false' );
	}

	public function test_try_is_stripe_connected_returns_true_when_connected_with_live_account_in_dev_mode() {
		// enable dev mode.
		add_filter( 'wcpay_dev_mode', '__return_true' );

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
		$this->mock_api_client->expects( $this->never() )->method( 'get_account_data' );

		$this->assertTrue( $this->wcpay_account->try_is_stripe_connected() );

		remove_filter( 'wcpay_dev_mode', '__return_true' );
	}

	public function test_refresh_account_data_with_empty_cache() {
		$expected_account = [
			'account_id'               => 'acc_test',
			'live_publishable_key'     => 'pk_test_',
			'test_publishable_key'     => 'pk_live_',
			'has_pending_requirements' => true,
			'current_deadline'         => 12345,
			'is_live'                  => true,
		];

		// Make sure cache is clear.
		delete_option( WC_Payments_Account::ACCOUNT_OPTION );

		$this->mock_api_client->expects( $this->once() )->method( 'get_account_data' )->will( $this->returnValue( $expected_account ) );
		$this->wcpay_account->refresh_account_data();

		$cached_account = get_option( WC_Payments_Account::ACCOUNT_OPTION );
		$this->assertEquals( $expected_account, $cached_account['account'], 'Account is not cached' );
	}

	public function test_refresh_account_data_with_existing_cache() {
		$expected_account = [
			'account_id'               => 'acc_test',
			'live_publishable_key'     => 'pk_test_',
			'test_publishable_key'     => 'pk_live_',
			'has_pending_requirements' => true,
			'current_deadline'         => 12345,
			'is_live'                  => true,
		];

		$existing_cache                     = $expected_account;
		$existing_cache['current_deadline'] = 11111;
		$this->cache_account_details( $existing_cache );

		$this->mock_api_client->expects( $this->once() )->method( 'get_account_data' )->will( $this->returnValue( $expected_account ) );
		$this->wcpay_account->refresh_account_data();

		$cached_account = get_option( WC_Payments_Account::ACCOUNT_OPTION );
		$this->assertEquals( $expected_account, $cached_account['account'], 'Cached account is not updated' );
	}

	public function test_refresh_account_data_clears_cache_on_failure() {
		$account = [
			'account_id'               => 'acc_test',
			'live_publishable_key'     => 'pk_test_',
			'test_publishable_key'     => 'pk_live_',
			'has_pending_requirements' => true,
			'current_deadline'         => 12345,
			'is_live'                  => true,
		];
		$this->cache_account_details( $account );

		$this->mock_api_client->expects( $this->once() )->method( 'get_account_data' )->will(
			$this->throwException( new API_Exception( 'test', 'wcpay_account_not_found', 401 ) )
		);
		$this->wcpay_account->refresh_account_data();

		$cached_account = get_option( WC_Payments_Account::ACCOUNT_OPTION );
		$this->assertEquals( [], $cached_account['account'], 'Cached account is not cleared' );
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
		$this->cache_account_details( $account );

		$new_descriptor   = 'WCPAY_DEV';
		$expected_account = array_merge(
			$account,
			[ 'statement_descriptor' => $new_descriptor ]
		);

		$this->mock_api_client->expects( $this->once() )->method( 'update_account' )->will( $this->returnValue( $expected_account ) );
		$this->wcpay_account->update_stripe_account( [ 'statement_descriptor' => $new_descriptor ] );

		$cached_account = get_option( WC_Payments_Account::ACCOUNT_OPTION );
		$this->assertEquals( $expected_account, $cached_account['account'], 'Cached account is not updated' );
	}

	public function test_update_stripe_account_when_cached_account_invalid() {
		$this->cache_account_details( 'ERROR' );

		$new_descriptor   = 'WCPAY_DEV';
		$expected_account = [
			'account_id'               => 'acc_test',
			'live_publishable_key'     => 'pk_test_',
			'test_publishable_key'     => 'pk_live_',
			'has_pending_requirements' => true,
			'current_deadline'         => 12345,
			'is_live'                  => true,
			'statement_descriptor'     => 'WCPAY_DEV',
		];

		$this->mock_api_client
			->expects( $this->once() )
			->method( 'update_account' )
			->will( $this->returnValue( $expected_account ) );

		$this->wcpay_account->update_stripe_account( [ 'statement_descriptor' => $new_descriptor ] );

		$cached_account = get_option( WC_Payments_Account::ACCOUNT_OPTION );
		$this->assertEquals( $expected_account, $cached_account['account'], 'Cached account is not updated' );
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
		$this->cache_account_details( $account );

		$this->mock_api_client->expects( $this->never() )->method( 'update_account' );
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
		$this->cache_account_details( $account );

		$this->mock_api_client->expects( $this->once() )->method( 'update_account' )->will(
			$this->throwException( new API_Exception( 'test', 'bad_request', 400 ) )
		);
		$error_msg = $this->wcpay_account->update_stripe_account( [ 'statement_descriptor' => 'WCPAY_DEV' ] );
		$this->assertEquals( 'test', $error_msg, 'Error message expected' );
	}

	/**
	 * Test get_cached_account_data when we have already cached the "no account connected" response.
	 */
	public function test_get_cached_account_data_when_no_account_connected_and_result_cached() {
		// Setup the cache with expired account information.
		$this->cache_account_details( [] );

		// Wire up the API client mock to return updated account data.
		$this->mock_api_client->expects( $this->never() )->method( 'get_account_data' );

		// Fetch the account.
		$account = $this->wcpay_account->get_cached_account_data();

		// Assert that the "no account connected" special case is returned as expected.
		$this->assertSame( [], $account );

		// Test to check that scheduled action was not created.
		$action_scheduler_service = new WC_Payments_Action_Scheduler_Service( $this->mock_api_client );
		$action_hook              = WC_Payments_Account::ACCOUNT_CACHE_REFRESH_ACTION;
		$this->assertNotTrue( $action_scheduler_service->pending_action_exists( $action_hook ) );
	}

	public function test_get_cached_account_data_handle_previous_account_retrieval_error() {
		// Setup the cache as if there had been a connection error.
		$this->cache_account_details( 'ERROR' );

		// Fetch the account.
		$account = $this->wcpay_account->get_cached_account_data();

		$this->assertFalse( $account );

		// Test to check that scheduled action was not created.
		$action_scheduler_service = new WC_Payments_Action_Scheduler_Service( $this->mock_api_client );
		$action_hook              = WC_Payments_Account::ACCOUNT_CACHE_REFRESH_ACTION;
		$this->assertNotTrue( $action_scheduler_service->pending_action_exists( $action_hook ) );
	}

	public function test_get_cached_account_data_refetches_when_cache_has_expired() {
		// Setup the cache with expired account information.
		$cached_account = [
			'account_id'               => 'acc_test',
			'live_publishable_key'     => 'pk_test_',
			'test_publishable_key'     => 'pk_live_',
			'has_pending_requirements' => true,
			'current_deadline'         => 12345,
			'is_live'                  => true,
			'statement_descriptor'     => 'WCPAY',
		];

		add_option(
			WC_Payments_Account::ACCOUNT_OPTION,
			[
				'account' => $cached_account,
				'expires' => time() - HOUR_IN_SECONDS,
			]
		);

		// Wire up the API client mock to return updated account data.
		$updated_account                         = $cached_account;
		$updated_account['statement_descriptor'] = 'NEW_DESCRIPTOR';
		$this->mock_api_client
			->expects( $this->once() )
			->method( 'get_account_data' )
			->will( $this->returnValue( $updated_account ) );

		// Fetch the account.
		$account = $this->wcpay_account->get_cached_account_data();

		// Assert that we see the new account information instead of the cached information.
		$this->assertSame( $updated_account, $account );

		// Test to see if scheduled action was created.
		$action_scheduler_service = new WC_Payments_Action_Scheduler_Service( $this->mock_api_client );
		$action_hook              = WC_Payments_Account::ACCOUNT_CACHE_REFRESH_ACTION;
		$this->assertTrue( $action_scheduler_service->pending_action_exists( $action_hook ) );
	}

	public function test_get_cached_account_data_handles_missing_account_key() {
		// Setup the cache with a value missing the account key.
		add_option(
			WC_Payments_Account::ACCOUNT_OPTION,
			[
				'expires' => time() + 2 * HOUR_IN_SECONDS,
			]
		);

		// Wire up the API client mock to return account data.
		$updated_account = [
			'account_id'               => 'acc_test',
			'live_publishable_key'     => 'pk_test_',
			'test_publishable_key'     => 'pk_live_',
			'has_pending_requirements' => true,
			'current_deadline'         => 12345,
			'is_live'                  => true,
			'statement_descriptor'     => 'WCPAY',
		];

		$this->mock_api_client
			->expects( $this->once() )
			->method( 'get_account_data' )
			->will( $this->returnValue( $updated_account ) );

		// Fetch the account.
		$account = $this->wcpay_account->get_cached_account_data();

		// Assert that we see the new account information instead of the cached information.
		$this->assertSame( $updated_account, $account );

		// Test to see if scheduled action was created.
		$action_scheduler_service = new WC_Payments_Action_Scheduler_Service( $this->mock_api_client );
		$action_hook              = WC_Payments_Account::ACCOUNT_CACHE_REFRESH_ACTION;
		$this->assertTrue( $action_scheduler_service->pending_action_exists( $action_hook ) );
	}

	public function test_get_cached_account_data_handles_missing_expires_key() {
		// Setup the cache with a value missing the account key.
		$cached_account = [
			'account_id'               => 'acc_test',
			'live_publishable_key'     => 'pk_test_',
			'test_publishable_key'     => 'pk_live_',
			'has_pending_requirements' => true,
			'current_deadline'         => 12345,
			'is_live'                  => true,
			'statement_descriptor'     => 'WCPAY',
		];

		add_option(
			WC_Payments_Account::ACCOUNT_OPTION,
			[
				'account' => $cached_account,
			]
		);

		// Wire up the API client mock to return updated account data.
		$updated_account                         = $cached_account;
		$updated_account['statement_descriptor'] = 'NEW_DESCRIPTOR';

		$this->mock_api_client
			->expects( $this->once() )
			->method( 'get_account_data' )
			->will( $this->returnValue( $updated_account ) );

		// Fetch the account.
		$account = $this->wcpay_account->get_cached_account_data();

		// Assert that we see the new account information instead of the cached information.
		$this->assertSame( $updated_account, $account );

		// Test to see if scheduled action was created.
		$action_scheduler_service = new WC_Payments_Action_Scheduler_Service( $this->mock_api_client );
		$action_hook              = WC_Payments_Account::ACCOUNT_CACHE_REFRESH_ACTION;
		$this->assertTrue( $action_scheduler_service->pending_action_exists( $action_hook ) );
	}

	public function test_get_cached_account_data_with_force_refresh() {
		$cached_account = [
			'account_id'               => 'acc_test',
			'live_publishable_key'     => 'pk_test_',
			'test_publishable_key'     => 'pk_live_',
			'has_pending_requirements' => true,
			'current_deadline'         => 12345,
			'is_live'                  => true,
			'statement_descriptor'     => 'WCPAY',
		];

		add_option(
			WC_Payments_Account::ACCOUNT_OPTION,
			[
				'account' => $cached_account,
				'expires' => time() + 2 * HOUR_IN_SECONDS,
			]
		);

		// Wire up the API client mock to return updated account data.
		$updated_account                         = $cached_account;
		$updated_account['statement_descriptor'] = 'NEW_DESCRIPTOR';

		$this->mock_api_client
			->expects( $this->once() )
			->method( 'get_account_data' )
			->will( $this->returnValue( $updated_account ) );

		// Fetch the account. Because force refresh is true, we expect a request to the server.
		$account = $this->wcpay_account->get_cached_account_data( true );

		// Assert that we see the new account information instead of the cached information.
		$this->assertSame( $updated_account, $account );

		// Test to see if scheduled action was created.
		$action_scheduler_service = new WC_Payments_Action_Scheduler_Service( $this->mock_api_client );
		$action_hook              = WC_Payments_Account::ACCOUNT_CACHE_REFRESH_ACTION;
		$this->assertTrue( $action_scheduler_service->pending_action_exists( $action_hook ) );
	}

	public function test_handle_instant_deposits_inbox_note() {

		if ( ! version_compare( WC_VERSION, '4.4.0', '>=' ) ) {
			$this->markTestSkipped( 'The used WC components are not backward compatible' );
			return;
		}

		$account = [
			'is_live'                   => true,
			'instant_deposits_eligible' => true,
		];

		$this->wcpay_account->handle_instant_deposits_inbox_note( $account );

		$note_id = WC_Payments_Notes_Instant_Deposits_Eligible::NOTE_NAME;
		$this->assertNotSame( [], ( WC_Data_Store::load( 'admin-note' ) )->get_notes_with_name( $note_id ) );

		// Test to see if scheduled action was created.
		$action_scheduler_service = new WC_Payments_Action_Scheduler_Service( $this->mock_api_client );
		$action_hook              = 'wcpay_instant_deposit_reminder';
		$this->assertTrue( $action_scheduler_service->pending_action_exists( $action_hook ) );
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

	public function test_handle_instant_deposits_inbox_reminder() {

		if ( ! version_compare( WC_VERSION, '4.4.0', '>=' ) ) {
			$this->markTestSkipped( 'The used WC components are not backward compatible' );
			return;
		}

		$account = [
			'is_live'                   => true,
			'instant_deposits_eligible' => true,
		];
		// Handle_instant_deposits_inbox_reminder will retrieve the account from cache, so set it there.
		$this->cache_account_details( $account );

		// This will create and log the first note, like what we would see in the wild.
		$this->test_handle_instant_deposits_inbox_note( $account );
		$note_id    = WC_Payments_Notes_Instant_Deposits_Eligible::NOTE_NAME;
		$first_note = ( WC_Data_Store::load( 'admin-note' ) )->get_notes_with_name( $note_id );

		// This will delete the first note and create a new note since it calls test_handle_instant_deposits_inbox_note again.
		$this->wcpay_account->handle_instant_deposits_inbox_reminder();
		$second_note = ( WC_Data_Store::load( 'admin-note' ) )->get_notes_with_name( $note_id );

		// So we make sure the two are different.
		$this->assertNotSame( $first_note, $second_note );
	}

	/**
	 * Cache account details.
	 *
	 * @param $account
	 */
	private function cache_account_details( $account ) {
		add_option(
			WC_Payments_Account::ACCOUNT_OPTION,
			[
				'account' => $account,
				'expires' => time() + 2 * HOUR_IN_SECONDS,
			]
		);
	}
}
