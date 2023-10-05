<?php
/**
 * Class InitialStateTest
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Tests\Internal\Payment\State;

use WCPAY_UnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Internal\Payment\State\InitialState;
use WCPay\Internal\Payment\State\StateFactory;
use WCPay\Internal\Payment\PaymentContext;
use WCPay\Internal\Payment\PaymentMethod\NewPaymentMethod;
use WCPay\Internal\Payment\State\CompletedState;
use WCPay\Internal\Service\OrderService;

/**
 * Tests for the initial payment state.
 */
class InitialStateTest extends WCPAY_UnitTestCase {
	/**
	 * Service under test.
	 *
	 * @var InitialState
	 */
	private $sut;

	/**
	 * @var StateFactory|MockObject
	 */
	private $mock_state_factory;

	/**
	 * @var OrderService|MockObject
	 */
	private $mock_order_service;

	/**
	 * @var PaymentContext|MockObject
	 */
	private $mock_context;

	/**
	 * Set up the test.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->mock_state_factory = $this->createMock( StateFactory::class );
		$this->mock_order_service = $this->createMock( OrderService::class );
		$this->mock_context       = $this->createMock( PaymentContext::class );

		$this->sut = new InitialState( $this->mock_state_factory, $this->mock_order_service );
		$this->sut->set_context( $this->mock_context );
	}

	/**
	 * Ensures that the `process` method creates a new completed state.
	 */
	public function test_process_returns_new_completed_state() {
		$order_id = 123;
		$pm_id    = 'pm_ZYX';

		$this->mock_context->expects( $this->once() )
			->method( 'get_order_id' )
			->willReturn( $order_id );

		$this->mock_context->expects( $this->once() )
			->method( 'get_payment_method' )
			->willReturn( new NewPaymentMethod( $pm_id ) );

		$this->mock_order_service->expects( $this->once() )
			->method( 'set_payment_method_id' )
			->with( $order_id, $pm_id );

		$mock_completed_state = $this->createMock( CompletedState::class );
		$this->mock_state_factory->expects( $this->once() )
			->method( 'create_state' )
			->with( CompletedState::class, $this->mock_context )
			->willReturn( $mock_completed_state );

		$result = $this->sut->process();
		$this->assertSame( $mock_completed_state, $result );
	}
}
