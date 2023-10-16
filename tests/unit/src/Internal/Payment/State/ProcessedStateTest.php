<?php
/**
 * Class ProcessedStateTestTest
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Tests\Internal\Payment\State;

use PHPUnit\Framework\MockObject\MockObject;
use WC_Helper_Intention;
use WCPay\Internal\Payment\PaymentContext;
use WCPay\Internal\Payment\State\CompletedState;
use WCPay\Internal\Payment\State\ProcessedState;
use WCPay\Internal\Payment\State\StateFactory;
use WCPay\Internal\Service\OrderService;
use WCPAY_UnitTestCase;

/**
 * Tests for the initial payment state.
 */
class ProcessedStateTest extends WCPAY_UnitTestCase {
	/**
	 * State under test.
	 *
	 * @var ProcessedState
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
	 * Set up the test.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->mock_state_factory = $this->createMock( StateFactory::class );
		$this->mock_order_service = $this->createMock( OrderService::class );
		$this->mock_context       = $this->createMock( PaymentContext::class );

		$this->sut = new ProcessedState(
			$this->mock_state_factory,
			$this->mock_order_service
		);
		$this->sut->set_context( $this->mock_context );
	}

	public function test_complete_will_transition_to_completed_state() {

		$intent = WC_Helper_Intention::create_intention();

		$this->mock_context->expects( $this->once() )
			->method( 'get_intent' )
			->willReturn( $intent );

		$this->mock_context->expects( $this->once() )
			->method( 'get_order_id' )
			->willReturn( 1 );

		$this->mock_order_service->expects( $this->once() )
			->method( 'update_order_from_successful_intent' )
			->with( 1, $intent );

		$mock_completed_state = $this->createMock( CompletedState::class );

		$this->mock_state_factory->expects( $this->once() )
			->method( 'create_state' )
			->with( CompletedState::class, $this->mock_context )
			->willReturn( $mock_completed_state );

		$result = $this->sut->complete();

		$this->assertSame( $mock_completed_state, $result );
	}
}
