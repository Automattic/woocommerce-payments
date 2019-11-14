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
	 * Mock WC_Payment_Gateway_WCPay.
	 *
	 * @var WC_Payment_Gateway_WCPay|PHPUnit_Framework_MockObject_MockObject
	 */
	private $mock_gateway;

	/**
	 * Pre-test setup
	 */
	public function setUp() {
		parent::setUp();

		$this->mock_api_client = $this->getMockBuilder( 'WC_Payments_API_Client' )
			->disableOriginalConstructor()
			->getMock();

		$this->mock_gateway = $this->getMockBuilder( 'WC_Payment_Gateway_WCPay' )
			->disableOriginalConstructor()
			->getMock();

		$this->wcpay_account = new WC_Payments_Account( $this->mock_api_client, $this->mock_gateway );
	}

	public function tearDown() {
		delete_transient( WC_Payments_Account::ACCOUNT_TRANSIENT );
	}

	public function test_check_stripe_account_status_stripe_disconnected() {
		$this->mock_gateway->method( 'is_stripe_connected' )->will( $this->returnValue( false ) );
		$this->mock_api_client->expects( $this->never() )->method( 'get_account_data' );

		$this->assertFalse( $this->wcpay_account->check_stripe_account_status() );
	}

	public function test_check_stripe_account_status_account_error() {
		$this->mock_gateway->method( 'is_stripe_connected' )->will( $this->returnValue( true ) );
		$this->mock_api_client->expects( $this->once() )->method( 'get_account_data' )->will(
			$this->returnValue( new WP_Error( 'test' ) )
		);

		$this->assertFalse( $this->wcpay_account->check_stripe_account_status() );
	}

	public function test_check_stripe_account_status_returns_true() {
		$this->mock_gateway->method( 'is_stripe_connected' )->will( $this->returnValue( true ) );
		$this->mock_api_client->expects( $this->once() )->method( 'get_account_data' )->will(
			$this->returnValue(
				array(
					'account_id'               => 'acc_test',
					'live_publishable_key'     => 'pk_test_',
					'test_publishable_key'     => 'pk_live_',
					'has_pending_requirements' => true,
					'current_deadline'         => 12345,
				)
			)
		);

		$this->assertTrue( $this->wcpay_account->check_stripe_account_status() );
	}

	public function test_check_stripe_account_status_caches_the_account() {
		$this->mock_gateway->method( 'is_stripe_connected' )->will( $this->returnValue( true ) );
		$this->mock_api_client->expects( $this->once() )->method( 'get_account_data' )->will(
			$this->returnValue(
				array(
					'account_id'               => 'acc_test',
					'live_publishable_key'     => 'pk_test_',
					'test_publishable_key'     => 'pk_live_',
					'has_pending_requirements' => true,
					'current_deadline'         => 12345,
				)
			)
		);

		$this->assertTrue( $this->wcpay_account->check_stripe_account_status() );
		// call the method twice but use the mock_api_client to make sure the account has been retrieved only once.
		$this->assertTrue( $this->wcpay_account->check_stripe_account_status() );
	}
}
