<?php
/**
 * Class Get_Account_Capital_Link_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Core\Exceptions\Server\Request\Invalid_Request_Parameter_Exception;
use WCPay\Core\Server\Request\Get_Account_Capital_Link;

/**
 * WCPay\Core\Server\Get_Account_Capital_Link_Test unit tests.
 */
class Get_Account_Capital_Link_Test extends WCPAY_UnitTestCase {

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

	public function test_get_account_capital_link_will_be_sent() {
		$request           = new Get_Account_Capital_Link( $this->mock_api_client, $this->mock_wc_payments_http_client );
		$valid_return_url  = home_url() . '/return-url';
		$valid_refresh_url = home_url() . '/refresh-url';

		$request->set_type( 'some_type' );
		$request->set_return_url( $valid_return_url );
		$request->set_refresh_url( $valid_refresh_url );
		$this->assertInstanceOf( Get_Account_Capital_Link::class, $request );
		$params = $request->get_params();

		$this->assertIsArray( $params );
		$this->assertSame( $valid_return_url, $params['return_url'] );
		$this->assertSame( $valid_refresh_url, $params['refresh_url'] );
		$this->assertSame( 'accounts/capital_links', $request->get_api() );
		$this->assertSame( 'POST', $request->get_method() );
		$this->assertTrue( $request->should_use_user_token() );
	}

	public function test_get_account_login_data_with_invalid_return_url_param_will_throw_exception() {
		$request = new Get_Account_Capital_Link( $this->mock_api_client, $this->mock_wc_payments_http_client );
		$this->expectException( Invalid_Request_Parameter_Exception::class );
		$request->set_return_url( 'http://invalid-redirect.url' );
	}

	public function test_get_account_login_data_with_invalid_refresh_url_param_will_throw_exception() {
		$request = new Get_Account_Capital_Link( $this->mock_api_client, $this->mock_wc_payments_http_client );
		$this->expectException( Invalid_Request_Parameter_Exception::class );
		$request->set_refresh_url( 'http://invalid-refresh.url' );
	}
}
