<?php
/**
 * Class PaymentStateTest
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Tests\Internal\Payment\State;

use WCPAY_UnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Internal\Payment\Exception\MethodUnavailableException;
use WCPay\Internal\Payment\PaymentContext;
use WCPay\Internal\Payment\State\CompletedState;
use WCPay\Internal\Payment\State\PaymentState;
use WCPay\Internal\Payment\State\StateFactory;

// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound

/**
 * Pure state implementation, used to test the base state.
 */
class PureState extends PaymentState {
	public function create_test_state( string $state_class ) {
		return $this->create_state( $state_class );
	}
}

/**
 * Tests for the base payment state class.
 */
class PaymentStateTest extends WCPAY_UnitTestCase {
	/**
	 * Service under test.
	 *
	 * @var PureState
	 */
	private $sut;

	/**
	 * @var StateFactory|MockObject
	 */
	private $mock_state_factory;

	/**
	 * @var PaymentContext|MockObject
	 */
	private $mock_context;

	/**
	 * Set up the test.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->mock_context       = $this->createMock( PaymentContext::class );
		$this->mock_state_factory = $this->createMock( StateFactory::class );

		$this->sut = new PureState( $this->mock_state_factory );
		$this->sut->set_context( $this->mock_context );
	}

	public function test_set_context_and_get_context_work() {
		$mock_context = $this->createMock( PaymentContext::class );

		$this->sut->set_context( $mock_context );
		$this->assertSame( $mock_context, $this->sut->get_context() );
	}

	public function test_create_state_uses_the_factory() {
		$mock_completed_state = $this->createMock( CompletedState::class );

		$this->mock_state_factory->expects( $this->once() )
			->method( 'create_state' )
			->with( CompletedState::class, $this->mock_context )
			->willReturn( $mock_completed_state );

		$result = $this->sut->create_test_state( CompletedState::class );
		$this->assertSame( $mock_completed_state, $result );
	}

	public function test_process_throws_exception() {
		$this->expectException( MethodUnavailableException::class );
		$this->expectExceptionMessage( 'The WCPay\Internal\Payment\State\PaymentState::process method is not available in the current payment state (' . PureState::class . ').' );
		$this->sut->process();
	}
}
