<?php
/**
 * Class WC_Payments_Account_Test
 *
 * @package WooCommerce\Payments\Tests
 */

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
		delete_transient( WC_Payments_Account::ACCOUNT_TRANSIENT );
		delete_transient( WC_Payments_Account::ON_BOARDING_DISABLED_TRANSIENT );
		unset( $_GET );
		parent::tearDown();
	}

	public function test_check_stripe_account_status_stripe_disconnected() {
		$this->mock_api_client->expects( $this->once() )->method( 'get_account_data' )->will(
			$this->throwException( new WC_Payments_API_Exception( 'test', 'wcpay_account_not_found', 401 ) )
		);

		$this->assertFalse( $this->wcpay_account->check_stripe_account_status() );
		$this->assertFalse( WC_Payments_Account::is_on_boarding_disabled() );
	}

	public function test_check_stripe_account_status_stripe_disconnected_and_on_boarding_disabled() {
		$this->mock_api_client->expects( $this->once() )->method( 'get_account_data' )->will(
			$this->throwException(
				new WC_Payments_API_Exception(
					'test',
					'wcpay_on_boarding_disabled',
					401
				)
			)
		);

		$this->assertFalse( $this->wcpay_account->check_stripe_account_status() );
		$this->assertTrue( WC_Payments_Account::is_on_boarding_disabled() );
	}

	public function test_check_stripe_account_status_account_error() {
		$this->mock_api_client->expects( $this->once() )->method( 'get_account_data' )->will(
			$this->throwException( new Exception() )
		);

		$this->assertFalse( $this->wcpay_account->check_stripe_account_status() );
	}

	public function test_check_stripe_account_status_returns_true() {
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

		$this->assertTrue( $this->wcpay_account->check_stripe_account_status() );
	}

	public function test_check_stripe_account_status_caches_the_account() {
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

		$this->assertTrue( $this->wcpay_account->check_stripe_account_status() );
		// call the method twice but use the mock_api_client to make sure the account has been retrieved only once.
		$this->assertTrue( $this->wcpay_account->check_stripe_account_status() );
	}

	public function test_check_stripe_account_status_returns_true_and_on_boarding_re_enabled() {
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
						throw new WC_Payments_API_Exception(
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
					];
				}
			);

		// First call, on-boarding is disabled.
		$this->assertFalse( $this->wcpay_account->check_stripe_account_status() );
		$this->assertTrue( WC_Payments_Account::is_on_boarding_disabled() );

		// Simulate the account details cache timing out.
		delete_transient( WC_Payments_Account::ACCOUNT_TRANSIENT );

		// Second call, on-boarding re-enabled.
		$this->assertTrue( $this->wcpay_account->check_stripe_account_status() );
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
			$this->throwException( new WC_Payments_API_Exception( 'test', 'server_error', 500 ) )
		);

		$this->expectException( WC_Payments_API_Exception::class );

		$this->wcpay_account->try_is_stripe_connected();
	}

	public function test_try_is_stripe_connected_returns_false() {
		$this->mock_api_client->expects( $this->once() )->method( 'get_account_data' )->will(
			$this->throwException( new WC_Payments_API_Exception( 'test', 'wcpay_account_not_found', 401 ) )
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
			$this->throwException( new WC_Payments_API_Exception( 'test', 'server_error', 500 ) )
		);

		$this->assertFalse( $this->wcpay_account->is_stripe_connected( false ) );
	}

	public function test_is_stripe_connected_returns_false_when_not_connected() {
		$this->mock_api_client->expects( $this->once() )->method( 'get_account_data' )->will(
			$this->throwException( new WC_Payments_API_Exception( 'test', 'wcpay_account_not_found', 401 ) )
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
			$this->throwException( new WC_Payments_API_Exception( 'test', 'test', 123 ) )
		);

		$this->expectException( WC_Payments_API_Exception::class );

		$this->wcpay_account->get_publishable_key( true );
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
			$this->throwException( new WC_Payments_API_Exception( 'test', 'test', 123 ) )
		);

		$this->expectException( WC_Payments_API_Exception::class );

		$this->wcpay_account->get_stripe_account_id();
	}

	public function test_try_is_stripe_connected_returns_true_when_connected_with_dev_account_in_dev_mode() {
		// enable dev mode.
		add_filter( 'wcpay_dev_mode', '__return_true' );

		// cache a dev account.
		set_transient(
			WC_Payments_Account::ACCOUNT_TRANSIENT,
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
		set_transient(
			WC_Payments_Account::ACCOUNT_TRANSIENT,
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
			$this->throwException( new WC_Payments_API_Exception( 'test', 'wcpay_account_not_found', 401 ) )
		);

		$this->assertFalse( $this->wcpay_account->try_is_stripe_connected() );

		remove_filter( 'wcpay_dev_mode', '__return_false' );
	}

	public function test_try_is_stripe_connected_returns_true_when_connected_with_live_account_in_dev_mode() {
		// enable dev mode.
		add_filter( 'wcpay_dev_mode', '__return_true' );

		// cache a live account.
		set_transient(
			WC_Payments_Account::ACCOUNT_TRANSIENT,
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
		delete_transient( WC_Payments_Account::ACCOUNT_TRANSIENT );

		$this->mock_api_client->expects( $this->once() )->method( 'get_account_data' )->will( $this->returnValue( $expected_account ) );
		$this->wcpay_account->refresh_account_data();

		$cached_account = get_transient( WC_Payments_Account::ACCOUNT_TRANSIENT );
		$this->assertEquals( $expected_account, $cached_account, 'Account is not cached' );
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
		set_transient( WC_Payments_Account::ACCOUNT_TRANSIENT, $existing_cache );

		$this->mock_api_client->expects( $this->once() )->method( 'get_account_data' )->will( $this->returnValue( $expected_account ) );
		$this->wcpay_account->refresh_account_data();

		$cached_account = get_transient( WC_Payments_Account::ACCOUNT_TRANSIENT );
		$this->assertEquals( $expected_account, $cached_account, 'Cached account is not updated' );
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
		set_transient( WC_Payments_Account::ACCOUNT_TRANSIENT, $account );

		$this->mock_api_client->expects( $this->once() )->method( 'get_account_data' )->will(
			$this->throwException( new WC_Payments_API_Exception( 'test', 'wcpay_account_not_found', 401 ) )
		);
		$this->wcpay_account->refresh_account_data();

		$cached_account = get_transient( WC_Payments_Account::ACCOUNT_TRANSIENT );
		$this->assertEquals( [], $cached_account, 'Cached account is not cleared' );
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
		set_transient( WC_Payments_Account::ACCOUNT_TRANSIENT, $account );

		$new_descriptor   = 'WCPAY_DEV';
		$expected_account = array_merge(
			$account,
			[ 'statement_descriptor' => $new_descriptor ]
		);

		$this->mock_api_client->expects( $this->once() )->method( 'update_account' )->will( $this->returnValue( $expected_account ) );
		$this->wcpay_account->update_stripe_account( [ 'statement_descriptor' => $new_descriptor ] );

		$cached_account = get_transient( WC_Payments_Account::ACCOUNT_TRANSIENT );
		$this->assertEquals( $expected_account, $cached_account, 'Cached account is not updated' );
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
		set_transient( WC_Payments_Account::ACCOUNT_TRANSIENT, $account );

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
		set_transient( WC_Payments_Account::ACCOUNT_TRANSIENT, $account );

		$this->mock_api_client->expects( $this->once() )->method( 'update_account' )->will(
			$this->throwException( new WC_Payments_API_Exception( 'test', 'bad_request', 400 ) )
		);
		$error_msg = $this->wcpay_account->update_stripe_account( [ 'statement_descriptor' => 'WCPAY_DEV' ] );
		$this->assertEquals( 'test', $error_msg, 'Error message expected' );
	}
}
