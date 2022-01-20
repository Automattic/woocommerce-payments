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
	}

	private function set_is_admin() {
		global $current_screen;

		$current_screen = $this->getMockBuilder( \stdClass::class )
			->setMethods( [ 'in_admin' ] )
			->getMock();

		$current_screen->method( 'in_admin' )->willReturn( true );
	}
}
