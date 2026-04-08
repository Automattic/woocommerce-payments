<?php
/**
 * Class LegacyProxyTest
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Tests\Internal\Proxy;

use Exception;
use WCPAY_UnitTestCase;
use WCPay\Internal\Proxy\LegacyProxy;
use PHPUnit\Framework\MockObject\MockObject;

// We'll have a helper here, which is not used outside of this file.
// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound

/**
 * An object, used as a helper for the test.
 */
class ProxyObject {
	/**
	 * A method, which receives a parameter, and returns it.
	 * The same behavior will be mocked.
	 *
	 * @param mixed $param Any value.
	 * @return mixed
	 */
	public function action( $param ) {
		return $param;
	}

	/**
	 * Simple method, which calls the action method of the object
	 * with the provided param, and returns the response.
	 *
	 * @param ProxyObject $obj   Mock object.
	 * @param mixed       $param Parameter that will be returned.
	 * @return mixed
	 */
	public static function static_action( ProxyObject $obj, $param ) {
		return $obj->action( $param );
	}
}

/**
 * Simple function, which calls the action method of the object
 * with the provided param, and returns the response.
 *
 * @param ProxyObject $obj   Mock object.
 * @param mixed       $param Parameter that will be returned.
 * @return mixed
 */
function wcpay_proxy_test( ProxyObject $obj, $param ) {
	return $obj->action( $param );
}

/**
 * Legacy proxy unit tests.
 */
class LegacyProxyTest extends WCPAY_UnitTestCase {
	/**
	 * Service under test.
	 *
	 * @var LegacyProxy
	 */
	private $sut;

	/**
	 * @var ProxyObject|MockObject
	 */
	private $mock_object;

	/**
	 * Instantiate the service under test.
	 */
	protected function setUp(): void {
		$this->sut         = new LegacyProxy();
		$this->mock_object = $this->createMock( ProxyObject::class );
	}

	public function test_call_function() {
		$param         = 'mock123';
		$mock_response = 'response123';

		// The mock object is used for the simple function, which calls `action`.
		$this->mock_object->expects( $this->once() )
			->method( 'action' )
			->with( $param )
			->willReturn( $mock_response );

		// The last paramter should be retuned back as the result.
		$result = $this->sut->call_function( __NAMESPACE__ . '\\wcpay_proxy_test', $this->mock_object, $param );
		$this->assertSame( $mock_response, $result );
	}

	public function test_call_static() {
		$param         = 'mock123';
		$mock_response = 'response123';

		// The mock object is used for the simple function, which calls `action`.
		$this->mock_object->expects( $this->once() )
			->method( 'action' )
			->with( $param )
			->willReturn( $mock_response );

		// The last paramter should be retuned back as the result.
		$result = $this->sut->call_static( ProxyObject::class, 'static_action', $this->mock_object, $param );
		$this->assertSame( $mock_response, $result );
	}

	public function test_has_global_returns_true() {
		$name             = 'proxy_test_123';
		$GLOBALS[ $name ] = 'xyz';
		$this->assertTrue( $this->sut->has_global( $name ) );
	}

	public function test_has_global_returns_false() {
		$name = 'proxy_test_123';
		unset( $GLOBALS[ $name ] );
		$this->assertFalse( $this->sut->has_global( $name ) );
	}

	public function test_get_global() {
		$name  = 'proxy_test_123';
		$value = 'xyz';

		$GLOBALS[ $name ] = $value;
		$this->assertSame( $value, $this->sut->get_global( $name ) );
	}

	public function test_get_global_throws_exception() {
		$name = 'proxy_test_123';
		unset( $GLOBALS[ $name ] );
		$this->expectException( Exception::class );
		$this->sut->get_global( $name );
	}
}
