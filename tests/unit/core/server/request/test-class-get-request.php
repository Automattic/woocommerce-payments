<?php
/**
 * Class Get_Request_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Core\Exceptions\Server\Request\Invalid_Request_Parameter_Exception;
use WCPay\Core\Server\Request;
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

	public function test_exception_will_throw_if_api_route_is_invalid_when_request_filter_is_applied() {
		$request = new Get_Request( $this->mock_api_client, $this->mock_wc_payments_http_client );
		$request->set_api( WC_Payments_API_Client::DEPOSITS_API );
		add_filter(
			'test_exception_will_throw_if_invalid_api_route_is_set_when_filter_is_applied',
			function() {
				$new_class = new class( $this->mock_api_client, $this->mock_wc_payments_http_client) extends Get_Request {

				};
				$new_class->set_api( 'foo' );
				return $new_class;
			}
		);
		$this->expectException( Invalid_Request_Parameter_Exception::class );
		$request->apply_filters( 'test_exception_will_throw_if_invalid_api_route_is_set_when_filter_is_applied' );
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

	public function test_get_method_from_parent_request_class_will_create_get_request() {
		$request = Request::get( WC_Payments_API_Client::DEPOSITS_API );
		$this->assertInstanceOf( Get_Request::class, $request );
		$this->assertSame( WC_Payments_API_Client::DEPOSITS_API, $request->get_api() );
	}
	public function test_get_method_from_parent_request_class_with_id_argument_will_create_get_request() {
		$request = Request::get( WC_Payments_API_Client::DEPOSITS_API, 'foo' );
		$this->assertInstanceOf( Get_Request::class, $request );
		$this->assertSame( WC_Payments_API_Client::DEPOSITS_API . '/foo', $request->get_api() );
	}
}
