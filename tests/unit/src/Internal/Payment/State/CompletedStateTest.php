<?php
/**
 * Class CompletedStateTest
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Tests\Internal\Payment\State;

use WCPAY_UnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use WC_Order;
use WCPay\Internal\Payment\Payment;
use WCPay\Internal\Payment\State\CompletedState;
use WCPay\Internal\Service\GatewayService;

/**
 * State factory unit tests.
 */
class CompletedStateTest extends WCPAY_UnitTestCase {
	/**
	 * Service under test.
	 *
	 * @var CompletedState
	 */
	private $sut;

	/**
	 * Mock payment object.
	 *
	 * @var Payment|MockObject
	 */
	private $mock_payment;

	/**
	 * Gateway service mock.
	 *
	 * @var GatewayService|MockObject
	 */
	private $mock_gateway_service;

	/**
	 * Set up the test.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->mock_payment         = $this->createMock( Payment::class );
		$this->mock_gateway_service = $this->createMock( GatewayService::class );

		$this->sut = new CompletedState( $this->mock_gateway_service );
		$this->sut->set_context( $this->mock_payment );
	}

	/**
	 * Ensures that `get_gateway_response` returns a well-formed array.
	 */
	public function test_get_gateway_response_returns_array() {
		/**
		 * @var WC_Order|MockObject
		 */
		$mock_order = $this->createMock( WC_Order::class );
		$mock_url   = 'https://example.com/thank-you';

		$this->mock_payment->expects( $this->once() )
			->method( 'get_order' )
			->willReturn( $mock_order );

		$this->mock_gateway_service->expects( $this->once() )
			->method( 'get_return_url' )
			->with( $mock_order )
			->willReturn( $mock_url );

		$result = $this->sut->get_gateway_response();
		$this->assertEquals(
			[
				'result'   => 'success',
				'redirect' => $mock_url,
			],
			$result
		);
	}
}
