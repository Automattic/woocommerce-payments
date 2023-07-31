<?php
/**
 * Class Core_Request_Generic_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\Core\Exceptions\Server\Request\Invalid_Request_Parameter_Exception;
use WCPay\Core\Exceptions\Server\Request\Server_Request_Exception;
use WCPay\Core\Exceptions\Server\Response\Server_Response_Exception;
use WCPay\Core\Server\Request\Generic;
use WCPay\Core\Server\Response;

/**
 * WCPay\Core\Server\Core_Request_Generic_Test unit tests.
 */
class Core_Request_Generic_Test extends WCPAY_UnitTestCase {


	public function test_constructor_will_throw_exception_if_method_is_not_defined() {
		$this->expectException( Invalid_Request_Parameter_Exception::class );
		new Generic( '', 'foo' );
	}

	public function test_static_create_function_will_throw_exception_if_its_called_directly() {
		$this->expectException( Server_Request_Exception::class );
		Generic::create();
	}

	public function test_class_creation_using_constructor_will_create_instance_of_class() {
		$api     = 'API';
		$params  = [
			$key = 'foo' => $value = 'bar',
		];
		$request = new Generic( $api, 'POST', $params );
		$this->assertSame( $api, $request->get_api() );
		$this->assertSame( 'POST', $request->get_method() );
		$this->assertArrayHasKey( $key, $params );
		$this->assertSame( $value, $request->get_param( $key ) );
		$this->assertFalse( $request->should_use_user_token() );
	}

	public function test_set_function_will_set_parameter() {
		$key     = 'foo';
		$value   = 'bar';
		$request = new Generic( 'api', 'POST' );
		$request->set( $key, $value );
		$this->assertSame( $value, $request->get_param( $key ) );
	}

	public function test_should_use_user_token_function_will_return_wanted_result() {
		$request = new Generic( 'api', 'POST' );
		$this->assertFalse( $request->should_use_user_token() );
		$request->use_user_token();

		$this->assertTrue( $request->should_use_user_token() );
	}

	public function test_get_params_function_will_return_all_params_with_correct_formatting() {
		$params  = [
			$key      = 'foo'      => $value = 'bar',
			$bool_key = 'bool_key' => true,
		];
		$request = new Generic( 'API', 'POST', $params );
		$params  = $request->get_params();

		$this->assertIsArray( $params );
		$this->assertArrayHasKey( $key, $params );
		$this->assertSame( $value, $params[ $key ] );
		$this->assertArrayHasKey( $bool_key, $params );
		$this->assertSame( 'true', $params[ $bool_key ] );
	}

	public function test_format_response_function_will_return_response_class() {
		$data     = [
			$key = 'foo' => $value = 'bar',
		];
		$request  = new Generic( 'API', 'POST' );
		$response = $request->format_response( $data );
		$this->assertInstanceOf( Response::class, $response );
		$this->assertTrue( $response->offsetExists( $key ) );
		$this->assertFalse( $response->offsetExists( 'random_key' ) );
		$this->assertSame( $value, $response->offsetGet( $key ) );
		$this->expectException( Server_Response_Exception::class );
		$response->offsetSet( 'key', 'value' );
		$this->expectException( Server_Response_Exception::class );
		$response->offsetUnset( $key );
	}

}
