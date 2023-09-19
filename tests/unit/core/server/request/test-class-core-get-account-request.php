<?php
/**
 * Class Get_Account_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Core\Exceptions\Server\Request\Invalid_Request_Parameter_Exception;
use WCPay\Core\Server\Request\Get_Account;

/**
 * WCPay\Core\Server\Get_Account_Test unit tests.
 */
class Get_Account_Test extends WCPAY_UnitTestCase {

	/**
	 * Mock WC_Payments_API_Client.
	 *
	 * @var WC_Payments_API_Client|MockObject
	 */
	private $mock_api_client;
	/**
	 * Mock WC_Payments_API_Client.
	 *
	 * @var WC_Payments_Http_Interface|MockObject
	 */
	private $mock_wc_payments_http_client;


	/**
	 * Set up the unit tests objects.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		$this->mock_api_client              = $this->createMock( WC_Payments_API_Client::class );
		$this->mock_wc_payments_http_client = $this->createMock( WC_Payments_Http_Interface::class );
	}

	public function test_get_account_will_be_request_with_correct_method_and_endpoint() {
		$request = new Get_Account( $this->mock_api_client, $this->mock_wc_payments_http_client );
		$this->assertSame( WC_Payments_API_Client::ACCOUNTS_API, $request->get_api() );
		$this->assertSame( 'GET', $request->get_method() );
	}
	public function test_get_account_will_be_requested_as_test_mode_only_in_dev_mode() {
		// enable test mode.
		WC_Payments::mode()->test();
		$request_test = new Get_Account( $this->mock_api_client, $this->mock_wc_payments_http_client );
		$this->assertFalse( $request_test->get_default_params()['test_mode'] );

		// enable live mode.
		WC_Payments::mode()->live();
		$request_live = new Get_Account( $this->mock_api_client, $this->mock_wc_payments_http_client );
		$this->assertFalse( $request_live->get_default_params()['test_mode'] );

		// enable dev mode.
		WC_Payments::mode()->dev();
		$request_dev = new Get_Account( $this->mock_api_client, $this->mock_wc_payments_http_client );
		$this->assertTrue( $request_dev->get_default_params()['test_mode'] );

		// reset the test.
		WC_Payments::mode()->live();
	}
}
