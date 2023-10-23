<?php
/**
 * Class CapturedStateTest
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Tests\Internal\Payment\State;

use PHPUnit\Framework\MockObject\MockObject;
use WC_Helper_Intention;
use WCPay\Internal\Payment\PaymentContext;
use WCPay\Internal\Payment\State\CompletedState;
use WCPay\Internal\Payment\State\CapturedState;
use WCPay\Internal\Payment\State\StateFactory;
use WCPay\Internal\Service\OrderService;
use WCPAY_UnitTestCase;

/**
 * Tests for the captured state.
 */
class CapturedStateTest extends WCPAY_UnitTestCase {
	/**
	 * State under test.
	 *
	 * @var CapturedState
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

		$this->sut = new CapturedState(
			$this->mock_state_factory,
			$this->mock_order_service
		);
		$this->sut->set_context( $this->mock_context );
	}

	public function test_complete_processing_will_transition_to_completed_state() {
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

		$result = $this->sut->complete_processing();

		$this->assertSame( $mock_completed_state, $result );
	}
}
