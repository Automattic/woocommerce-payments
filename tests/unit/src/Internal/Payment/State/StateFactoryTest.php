<?php
/**
 * Class StateFactoryTest
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Tests\Internal\Payment\State;

use WCPAY_UnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Container;
use WCPay\Vendor\League\Container\Exception\ContainerException;
use WCPay\Internal\Payment\Exception\StateTransitionException;
use WCPay\Internal\Payment\PaymentContext;
use WCPay\Internal\Payment\State\InitialState;
use WCPay\Internal\Payment\State\AbstractPaymentState;
use WCPay\Internal\Payment\State\StateFactory;
use WCPay\Internal\Service\PaymentProcessingService;

// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound


/**
 * A class, which exists, but is not registered with the container.
 */
class UnmanagedState extends AbstractPaymentState {
}

/**
 * Payment state factory tests.
 */
class StateFactoryTest extends WCPAY_UnitTestCase {
	/**
	 * Service under test.
	 *
	 * @var StateFactory
	 */
	private $sut;

	/**
	 * Mock container.
	 *
	 * @var Container|MockObject
	 */
	private $mock_container;

	/**
	 * @var PaymentContext|MockObject
	 */
	private $mock_context;

	/**
	 * Set up the test.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->mock_container = $this->createMock( Container::class );
		$this->mock_context   = $this->createMock( PaymentContext::class );

		$this->sut = new StateFactory( $this->mock_container );
	}

	public function test_create_state_creates_state() {
		$mock_state = $this->createMock( InitialState::class );
		$mock_state->expects( $this->once() )
			->method( 'set_context' )
			->with( $this->mock_context );

		$this->mock_container->expects( $this->once() )
			->method( 'get' )
			->with( InitialState::class )
			->willReturn( $mock_state );

		$result = $this->sut->create_state( InitialState::class, $this->mock_context );
		$this->assertSame( $mock_state, $result );
	}

	public function test_create_state_rejects_invalid_classes() {
		$this->expectException( StateTransitionException::class );
		$this->sut->create_state( PaymentProcessingService::class, $this->mock_context );
	}
}
