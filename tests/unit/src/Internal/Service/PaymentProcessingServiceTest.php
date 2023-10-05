<?php
/**
 * Class PaymentProcessingServiceTest
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Tests\Internal\Service;

use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Internal\Payment\PaymentContext;
use WCPay\Internal\Payment\State\CompletedState;
use WCPay\Internal\Payment\State\InitialState;
use WCPAY_UnitTestCase;
use WCPay\Internal\Service\PaymentProcessingService;
use WCPay\Internal\Payment\State\StateFactory;

/**
 * Payment processing service unit tests.
 */
class PaymentProcessingServiceTest extends WCPAY_UnitTestCase {
	/**
	 * Service under test.
	 *
	 * @var PaymentProcessingService
	 */
	private $sut;

	/**
	 * @var StateFactory|MockObject
	 */
	private $mock_state_factory;

	/**
	 * Set up the test.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->mock_state_factory = $this->createMock( StateFactory::class );

		$this->sut = new PaymentProcessingService( $this->mock_state_factory );
	}

	/**
	 * Test the basic happy path of processing a payment.
	 */
	public function test_process_payment_happy_path() {
		$mock_initial_state   = $this->createMock( InitialState::class );
		$mock_completed_state = $this->createMock( CompletedState::class );

		$this->mock_state_factory->expects( $this->once() )
			->method( 'create_state' )
			->with( InitialState::class, $this->isInstanceOf( PaymentContext::class ) )
			->willReturn( $mock_initial_state );

		$mock_initial_state->expects( $this->once() )
			->method( 'process' )
			->willReturn( $mock_completed_state );

		$result = $this->sut->process_payment( 1 );
		$this->assertSame( $mock_completed_state, $result );
	}
}
