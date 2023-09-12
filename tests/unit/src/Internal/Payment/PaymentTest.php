<?php
/**
 * Class PaymentTest
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Tests\Internal\Payment;

use PHPUnit\Framework\MockObject\MockObject;
use WC_Order;
use WCPAY_UnitTestCase;
use WCPay\Internal\Payment\Payment;
use WCPay\Internal\Payment\State\InitialState;
use WCPay\Internal\Payment\StateFactory;

/**
 * Payment unit tests.
 */
class PaymentTest extends WCPAY_UnitTestCase {
	/**
	 * Service under test.
	 *
	 * @var Payment
	 */
	private $sut;

	/**
	 * StateFactory mock.
	 *
	 * @var StateFactory|MockObject
	 */
	private $mock_state_factory;

	/**
	 * Mock WC_Order.
	 *
	 * @var WC_Order|MockObject
	 */
	private $mock_order;

	/**
	 * Mock InitialState.
	 *
	 * @var InitialState|MockObject
	 */
	private $mock_state;

	/**
	 * Set up the test.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->mock_state_factory = $this->createMock( StateFactory::class );
		$this->mock_order         = $this->createMock( WC_Order::class );
		$this->mock_state         = $this->createMock( InitialState::class );

		$this->setup_get_state();

		$this->sut = new Payment( $this->mock_order, $this->mock_state_factory );
	}

	/**
	 * A test, called in the set up, testing `set_state` and the factory.
	 */
	private function setup_get_state() {
		$this->mock_state_factory->expects( $this->once() )
			->method( 'create_state' )
			->with( InitialState::class )
			->willreturn( $this->mock_state );
	}

	/**
	 * Tests the `get_order()` method.
	 */
	public function test_get_order_returns_order() {
		$this->assertSame( $this->mock_order, $this->sut->get_order() );
	}

	/**
	 * Confirms that `prepare` delegates to the current state.
	 */
	public function test_prepare_delegates_to_state() {
		$pm = 'pm_123';

		$this->mock_state->expects( $this->once() )
			->method( 'prepare' )
			->with( $pm );

		$this->sut->prepare( $pm );
	}

	/**
	 * Confirms that `get_gateway_response` delegates to the current state.
	 */
	public function test_get_gateway_response_delegates_to_state() {
		$mock_response = [
			'success' => true,
		];

		$this->mock_state->expects( $this->once() )
			->method( 'get_gateway_response' )
			->willReturn( $mock_response );

		$result = $this->sut->get_gateway_response();
		$this->assertSame( $mock_response, $result );
	}
}
