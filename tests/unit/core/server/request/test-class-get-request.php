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
	/**
	 * @dataProvider invalid_api_routes_provider
	 */
	public function test_exception_will_throw_if_api_route_is_invalid( $route, $id ) {
		$request = new Get_Request( $this->mock_api_client, $this->mock_wc_payments_http_client, $id );
		$this->expectException( Invalid_Request_Parameter_Exception::class );
		$request->set_api( $route );
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

	/**
	 * @dataProvider valid_api_routes_provider
	 */
	public function test_request_will_be_created( $route, $id ) {
		$request = new Get_Request( $this->mock_api_client, $this->mock_wc_payments_http_client, $id );
		$request->set_api( WC_Payments_API_Client::DEPOSITS_API );
		$this->assertSame( 'GET', $request->get_method() );
		$this->assertSame( $id ? $route . '/' . $id : $route, $request->get_api() );
	}

	/**
	 * @dataProvider valid_api_routes_provider
	 */
	public function test_get_method_from_parent_request_class_will_create_get_request( $route, $id ) {
		$request = Request::get( $route, $id );
		$this->assertInstanceOf( Get_Request::class, $request );
		$this->assertSame( $id ? $route . '/' . $id : $route, $request->get_api() );
	}

	public function invalid_api_routes_provider(): array {
		return [
			'invalid_api_route_without_id_param' => [ 'foo', null ],
			'invalid_api_route_with_id_param'    => [ 'foo', '1' ],
		];
	}

	public function valid_api_routes_provider(): array {
		return [
			'valid_api_route_without_id_param' => [ WC_Payments_API_Client::DEPOSITS_API, null ],
			'valid_api_route_with_id_param'    => [ WC_Payments_API_Client::DEPOSITS_API, '1' ],
		];
	}
}
