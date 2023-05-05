<?php
/**
 * Class Get_Request_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Core\Exceptions\Server\Request\Invalid_Request_Parameter_Exception;
use WCPay\Core\Server\Request\Get_Request;

/**
 * WCPay\Core\Server\Get_Request_Test unit tests.
 */
class Get_Request_Test extends WCPAY_UnitTestCase {

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

	public function test_exception_will_throw_if_api_route_not_set() {
		$request = new Get_Request( $this->mock_api_client, $this->mock_wc_payments_http_client );
		$this->expectException( Invalid_Request_Parameter_Exception::class );
		$request->send( '' );
	}
	public function test_exception_will_throw_if_api_route_is_invalid() {
		$request = new Get_Request( $this->mock_api_client, $this->mock_wc_payments_http_client );
		$this->expectException( Invalid_Request_Parameter_Exception::class );
		$request->set_api( 'foo' );
	}
	public function test_exception_will_throw_if_api_route_is_invalid_when_id_is_passed() {
		$request = new Get_Request( $this->mock_api_client, $this->mock_wc_payments_http_client, '1' );
		$this->expectException( Invalid_Request_Parameter_Exception::class );
		$request->set_api( 'foo' );
	}
	public function test_request_will_be_created_correctly_when_id_is_not_passed() {
		$request = new Get_Request( $this->mock_api_client, $this->mock_wc_payments_http_client );
		$request->set_api( WC_Payments_API_Client::DEPOSITS_API );
		$this->assertSame( 'GET', $request->get_method() );
		$this->assertSame( WC_Payments_API_Client::DEPOSITS_API, $request->get_api() );
	}
	public function test_request_will_be_created_correctly_when_id_is_passed() {
		$request = new Get_Request( $this->mock_api_client, $this->mock_wc_payments_http_client, '1' );
		$request->set_api( WC_Payments_API_Client::DEPOSITS_API );
		$this->assertSame( 'GET', $request->get_method() );
		$this->assertSame( WC_Payments_API_Client::DEPOSITS_API . '/1', $request->get_api() );
	}
	public function test_request_will_be_created_correctly_passed_api_endpoint_has_additional_route_arguments() {
		$request = new Get_Request( $this->mock_api_client, $this->mock_wc_payments_http_client );
		$request->set_api( WC_Payments_API_Client::DEPOSITS_API . '/foo' );
		$this->assertSame( 'GET', $request->get_method() );
		$this->assertSame( WC_Payments_API_Client::DEPOSITS_API . '/foo', $request->get_api() );
	}
}
