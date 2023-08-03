<?php
/**
 * Class HooksProxyTest
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Tests\Internal\Proxy;

use WCPAY_UnitTestCase;
use WCPay\Internal\Proxy\HooksProxy;
use PHPUnit\Framework\MockObject\MockObject;

// We'll have a helper here, which is not used outside of this file.
// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound

/**
 * An object, used as a helper for the test.
 */
class HooksHelper {
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
}

/**
 * Hooks proxy unit tests.
 */
class HooksProxyTest extends WCPAY_UnitTestCase {
	/**
	 * Service under test.
	 *
	 * @var HooksProxy
	 */
	private $sut;

	/**
	 * A helper for callbacks.
	 *
	 * @var HooksHelper|MockObject
	 */
	private $helper;

	/**
	 * Test setup.
	 */
	protected function setUp(): void {
		$this->sut    = new HooksProxy();
		$this->helper = $this->createMock( HooksHelper::class );
	}

	public function test_add_filter() {
		$hook_name = 'proxy_test_filter';

		$this->helper->expects( $this->once() )
			->method( 'action' )
			->with( 1, 2, 3 )
			->willReturn( 4 );

		$this->sut->add_filter( $hook_name, [ $this->helper, 'action' ], 11, 3 );

		$result = apply_filters( $hook_name, 1, 2, 3 );
		$this->assertSame( 4, $result );
	}

	public function test_add_action() {
		$hook_name = 'proxy_test_action';

		$this->helper->expects( $this->once() )
			->method( 'action' )
			->with( 1, 2, 3 )
			->willReturn( 4 );

		$this->sut->add_action( $hook_name, [ $this->helper, 'action' ], 11, 3 );

		$result = do_action( $hook_name, 1, 2, 3 );
		$this->assertNull( $result ); // Non-null would be a filter.
	}
}
