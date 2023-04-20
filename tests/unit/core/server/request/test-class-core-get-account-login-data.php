<?php
/**
 * Class Get_Account_Login_Data_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Core\Exceptions\Server\Request\Invalid_Request_Parameter_Exception;
use WCPay\Core\Server\Request\Get_Account_Login_Data;

/**
 * WCPay\Core\Server\Get_Account_Login_Data_Test unit tests.
 */
class Get_Account_Login_Data_Test extends WCPAY_UnitTestCase {

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

	public function test_get_account_login_data_will_be_sent() {
		$request   = new Get_Account_Login_Data( $this->mock_api_client, $this->mock_wc_payments_http_client );
		$valid_url = home_url() . '/a-valid-url';
		$request->set_redirect_url( $valid_url );
		$this->assertInstanceOf( Get_Account_Login_Data::class, $request );
		$params = $request->get_params();

		$this->assertIsArray( $params );
		$this->assertSame( $valid_url, $params['redirect_url'] );
		$this->assertFalse( $params['test_mode'] );
		$this->assertSame( 'accounts/login_links', $request->get_api() );
		$this->assertSame( 'POST', $request->get_method() );
		$this->assertTrue( $request->should_use_user_token() );
	}
	public function test_get_account_will_be_requested_as_test_mode_only_in_dev_mode() {
		// enable test mode.
		WC_Payments::mode()->test();
		$request_test = new Get_Account_Login_Data( $this->mock_api_client, $this->mock_wc_payments_http_client );
		$this->assertFalse( $request_test->get_default_params()['test_mode'] );

		// enable live mode.
		WC_Payments::mode()->live();
		$request_live = new Get_Account_Login_Data( $this->mock_api_client, $this->mock_wc_payments_http_client );
		$this->assertFalse( $request_live->get_default_params()['test_mode'] );

		// enable dev mode.
		WC_Payments::mode()->dev();
		$request_dev = new Get_Account_Login_Data( $this->mock_api_client, $this->mock_wc_payments_http_client );
		$this->assertTrue( $request_dev->get_default_params()['test_mode'] );

		// reset the test.
		WC_Payments::mode()->live();
	}

	public function test_get_account_login_data_with_invalid_redirect_url_param_will_throw_exception() {
		$request = new Get_Account_Login_Data( $this->mock_api_client, $this->mock_wc_payments_http_client );
		$this->expectException( Invalid_Request_Parameter_Exception::class );
		$request->set_redirect_url( 'http://invalid.url' );
	}
}
