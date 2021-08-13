<?php
/**
 * Class WC_Payments_Fraud_Service_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Exceptions\API_Exception;

/**
 * WC_Payments_Fraud_Service unit tests.
 */
class WC_Payments_Fraud_Service_Test extends WP_UnitTestCase {
	/**
	 * System under test.
	 *
	 * @var WC_Payments_Fraud_Service
	 */
	private $fraud_service;

	/**
	 * Mock WC_Payments_API_Client.
	 *
	 * @var WC_Payments_API_Client|MockObject
	 */
	private $mock_api_client;

	/**
	 * Mock WC_Payments_Customer_Service.
	 *
	 * @var WC_Payments_Customer_Service|MockObject
	 */
	private $mock_customer_service;

	/**
	 * Mock WC_Payments_Account.
	 *
	 * @var WC_Payments_Account|MockObject
	 */
	private $mock_account;

	/**
	 * Pre-test setup
	 */
	public function setUp() {
		parent::setUp();

		$this->mock_api_client       = $this->createMock( WC_Payments_API_Client::class );
		$this->mock_customer_service = $this->createMock( WC_Payments_Customer_Service::class );
		$this->mock_account          = $this->createMock( WC_Payments_Account::class );

		$this->fraud_service = new WC_Payments_Fraud_Service( $this->mock_api_client, $this->mock_customer_service, $this->mock_account );
	}

	public function test_registers_filters_and_actions_properly() {
		$this->assertNotFalse( has_filter( 'wcpay_prepare_fraud_config', [ $this->fraud_service, 'prepare_fraud_config' ] ) );
		$this->assertNotFalse( has_filter( 'wcpay_current_session_id', [ $this->fraud_service, 'get_session_id' ] ) );
		$this->assertNotFalse( has_action( 'init', [ $this->fraud_service, 'link_session_if_user_just_logged_in' ] ) );
		$this->assertNotFalse( has_action( 'admin_init', [ $this->fraud_service, 'send_forter_token' ] ) );
	}

	public function test_prepare_fraud_config_forter_returns_null_when_is_not_admin() {
		$this->assertNull( $this->fraud_service->prepare_fraud_config( [], 'forter' ) );
	}

	public function test_prepare_fraud_config_forter_returns_null_when_token_already_sent() {
		update_option( 'wcpay_forter_token_sent', 'acct_id' );

		$this->mock_account
			->method( 'get_stripe_account_id' )
			->willReturn( 'acct_id' );

		$this->assertNull( $this->fraud_service->prepare_fraud_config( [], 'forter' ) );
	}

	public function test_prepare_fraud_config_forter_returns_config_when_is_admin() {
		$this->set_is_admin();
		$this->assertSame( [], $this->fraud_service->prepare_fraud_config( [], 'forter' ) );
	}

	public function test_send_forter_token_returns_early_without_account_connected() {
		$this->mock_account
			->method( 'is_stripe_connected' )
			->willReturn( false );
		$this->mock_account
			->method( 'get_fraud_services_config' )
			->willReturn( [ 'forter' => [] ] );

		$this->mock_account
			->expects( $this->never() )
			->method( 'get_stripe_account_id' );

		$this->fraud_service->send_forter_token( 'token' );
	}

	public function test_send_forter_token_returns_early_without_cookie_and_token() {
		$this->mock_account
			->method( 'is_stripe_connected' )
			->willReturn( true );
		$this->mock_account
			->method( 'get_fraud_services_config' )
			->willReturn( [ 'forter' => [] ] );

		$this->mock_account
			->expects( $this->never() )
			->method( 'get_stripe_account_id' );

		$this->fraud_service->send_forter_token();
	}

	public function test_send_forter_token_returns_early_without_forter_enabled() {
		$this->mock_account
			->method( 'is_stripe_connected' )
			->willReturn( true );
		$this->mock_account
			->method( 'get_fraud_services_config' )
			->willReturn( [] );

		$this->mock_account
			->expects( $this->never() )
			->method( 'get_stripe_account_id' );

		$this->fraud_service->send_forter_token( 'token' );
	}

	public function test_send_forter_token_proceed_with_cookie_only() {
		$_COOKIE['forterToken'] = 'token';

		$this->mock_account
			->method( 'is_stripe_connected' )
			->willReturn( true );
		$this->mock_account
			->method( 'get_fraud_services_config' )
			->willReturn( [ 'forter' => [] ] );

		$this->mock_account
			->expects( $this->once() )
			->method( 'get_stripe_account_id' );

		$this->fraud_service->send_forter_token();
	}

	public function test_send_forter_token_send_only_once() {
		$this->mock_account
			->method( 'is_stripe_connected' )
			->willReturn( true );
		$this->mock_account
			->method( 'get_fraud_services_config' )
			->willReturn( [ 'forter' => [] ] );
		$this->mock_account
			->method( 'get_stripe_account_id' )
			->willReturn( 'acct_id' );

		update_option( 'wcpay_forter_token_sent', 'acct_id' );

		$this->mock_api_client
			->expects( $this->never() )
			->method( 'send_forter_token' );

		$this->fraud_service->send_forter_token( 'token' );
	}

	public function test_send_forter_token_exception() {
		$this->mock_account
			->method( 'is_stripe_connected' )
			->willReturn( true );
		$this->mock_account
			->method( 'get_fraud_services_config' )
			->willReturn( [ 'forter' => [] ] );
		$this->mock_account
			->method( 'get_stripe_account_id' )
			->willReturn( 'acct_id' );

		$this->mock_api_client
			->method( 'send_forter_token' )
			->with( 'token' )
			->willThrowException( new API_Exception( 'Generic Error Message', 'generic_error', 500 ) );

		$this->fraud_service->send_forter_token( 'token' );

		$this->assertFalse( get_option( 'wcpay_forter_token_sent' ) );

	}


	public function test_send_forter_token_succeed() {
		$this->mock_account
			->method( 'is_stripe_connected' )
			->willReturn( true );
		$this->mock_account
			->method( 'get_fraud_services_config' )
			->willReturn( [ 'forter' => [] ] );
		$this->mock_account
			->method( 'get_stripe_account_id' )
			->willReturn( 'acct_id' );

		$this->mock_api_client
			->method( 'send_forter_token' )
			->with( 'token' )
			->willReturn( [ 'result' => 'success' ] );

		$this->fraud_service->send_forter_token( 'token' );

		$this->assertSame( 'acct_id', get_option( 'wcpay_forter_token_sent' ) );
	}


	private function set_is_admin() {
		global $current_screen;

		$current_screen = $this->getMockBuilder( \stdClass::class )
			->setMethods( [ 'in_admin' ] )
			->getMock();

		$current_screen->method( 'in_admin' )->willReturn( true );
	}
}
