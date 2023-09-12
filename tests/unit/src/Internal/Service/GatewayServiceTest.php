<?php
/**
 * Class GatewayServiceTest
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Tests\Internal\Service;

use PHPUnit\Framework\MockObject\MockObject;
use WC_Helper_Order;
use WC_Payment_Gateway_WCPay as Gateway;
use WCPay\Container;
use WCPay\Internal\Service\GatewayService;
use WCPAY_UnitTestCase;

/**
 * Gateway service unit tests.
 */
class GatewayServiceTest extends WCPAY_UnitTestCase {
	/**
	 * Service under test.
	 *
	 * @var GatewayService
	 */
	private $sut;

	/**
	 * Container mock.
	 *
	 * @var Container|MockObject
	 */
	private $mock_container;

	/**
	 * Mock gateway instance.
	 *
	 * @var Gateway|MockObject
	 */
	private $mock_gateway;

	/**
	 * Set up the test.
	 */
	protected function setUp(): void {
		parent::setUp();

		/**
		 * Some editors use the resulting type of `createMock` instead of the property docblock.
		 * @var Container|MockObject
		 */
		$this->mock_container = $this->createMock( Container::class );
		$this->mock_gateway   = $this->createMock( Gateway::class );

		// All tests will perform mocks on the gateway.
		$this->mock_container->expects( $this->once() )
			->method( 'get' )
			->with( Gateway::class )
			->willReturn( $this->mock_gateway );

		$this->sut = new GatewayService( $this->mock_container );
	}

	/**
	 * Test for the `get_return_url()` method.
	 */
	public function test_get_return_url_works() {
		$order      = WC_Helper_Order::create_order();
		$return_url = 'https://example.com/thank-you';

		$this->mock_gateway->expects( $this->once() )
			->method( 'get_return_url' )
			->with( $order )
			->willReturn( $return_url );

		$result = $this->sut->get_return_url( $order );
		$this->assertSame( $return_url, $result );
	}
}
