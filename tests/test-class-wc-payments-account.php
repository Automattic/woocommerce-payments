<?php
/**
 * Class WC_Payment_Gateway_WCPay_Test
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

		$this->mock_api_client = $this->getMockBuilder( 'WC_Payments_API_Client' )
			->disableOriginalConstructor()
			->getMock();
		$this->mock_api_client->expects( $this->any() )->method( 'is_server_connected' )->willReturn( true );

		$this->wcpay_account = new WC_Payments_Account( $this->mock_api_client );
	}

	public function tearDown() {
		delete_transient( WC_Payments_Account::ACCOUNT_TRANSIENT );
		delete_transient( WC_Payments_Account::ON_BOARDING_DISABLED_TRANSIENT );
	}

	public function test_try_is_stripe_connected_returns_true_when_connected() {
		$this->mock_api_client->expects( $this->once() )->method( 'get_account_data' )->will(
			$this->returnValue(
				array(
					'account_id'               => 'acc_test',
					'live_publishable_key'     => 'pk_test_',
					'test_publishable_key'     => 'pk_live_',
					'has_pending_requirements' => true,
					'current_deadline'         => 12345,
					'is_live'                  => true,
				)
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
				array(
					'account_id'               => 'acc_test',
					'live_publishable_key'     => 'pk_test_',
					'test_publishable_key'     => 'pk_live_',
					'has_pending_requirements' => true,
					'current_deadline'         => 12345,
					'is_live'                  => true,
				)
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
				array(
					'account_id'               => 'acc_test',
					'live_publishable_key'     => 'pk_live_',
					'test_publishable_key'     => 'pk_test_',
					'has_pending_requirements' => true,
					'current_deadline'         => 12345,
					'is_live'                  => true,
				)
			)
		);

		$this->assertEquals( 'pk_live_', $this->wcpay_account->get_publishable_key( false ) );
	}

	public function test_get_publishable_key_returns_for_test() {
		$this->mock_api_client->expects( $this->once() )->method( 'get_account_data' )->will(
			$this->returnValue(
				array(
					'account_id'               => 'acc_test',
					'live_publishable_key'     => 'pk_live_',
					'test_publishable_key'     => 'pk_test_',
					'has_pending_requirements' => true,
					'current_deadline'         => 12345,
					'is_live'                  => true,
				)
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
				array(
					'account_id'               => 'acc_test',
					'live_publishable_key'     => 'pk_live_',
					'test_publishable_key'     => 'pk_test_',
					'has_pending_requirements' => true,
					'current_deadline'         => 12345,
					'is_live'                  => true,
				)
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
			array(
				'account_id'               => 'acc_test',
				'live_publishable_key'     => 'pk_test_',
				'test_publishable_key'     => 'pk_live_',
				'has_pending_requirements' => true,
				'current_deadline'         => 12345,
				'is_live'                  => false,
			)
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
			array(
				'account_id'               => 'acc_test',
				'live_publishable_key'     => 'pk_test_',
				'test_publishable_key'     => 'pk_live_',
				'has_pending_requirements' => true,
				'current_deadline'         => 12345,
				'is_live'                  => false,
			)
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
			array(
				'account_id'               => 'acc_test',
				'live_publishable_key'     => 'pk_test_',
				'test_publishable_key'     => 'pk_live_',
				'has_pending_requirements' => true,
				'current_deadline'         => 12345,
				'is_live'                  => true,
			)
		);

		// cached live account should be used and the api shouldn't be called.
		$this->mock_api_client->expects( $this->never() )->method( 'get_account_data' );

		$this->assertTrue( $this->wcpay_account->try_is_stripe_connected() );

		remove_filter( 'wcpay_dev_mode', '__return_true' );
	}
}
