<?php
/**
 * Class StateFactoryTest
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Tests\Internal\Payment;

use PHPUnit\Framework\MockObject\MockObject;
use WCPAY_UnitTestCase;
use WCPay\Container;
use WCPay\Internal\Payment\Exception\StateTransitionException;
use WCPay\Internal\Payment\State\InitialState;
use WCPay\Internal\Payment\State\State;
use WCPay\Internal\Payment\StateFactory;

// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound

/**
 * An object that represents a state, but is not managed in DI.
 */
class RandomState extends State {}

/**
 * State factory unit tests.
 */
class StateFactoryTest extends WCPAY_UnitTestCase {
	/**
	 * Service under test.
	 *
	 * @var StateFactory
	 */
	private $sut;

	/**
	 * Container mock.
	 *
	 * @var Container|MockObject
	 */
	private $mock_container;

	/**
	 * Set up the test.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->mock_container = $this->createMock( Container::class );
		$this->sut            = new StateFactory( $this->mock_container );
	}

	/**
	 * Tests the happy path of creating a state.
	 */
	public function test_create_state_works() {
		$this->mock_container->expects( $this->once() )
			->method( 'get' )
			->with( InitialState::class )
			->willReturn( new InitialState() );

		$result = $this->sut->create_state( InitialState::class );
		$this->assertInstanceOf( InitialState::class, $result );
	}

	/**
	 * Tests that classes, which are not states, are rejected.
	 */
	public function test_non_state_class_gets_rejected() {
		$this->expectException( StateTransitionException::class );
		$this->sut->create_state( StateFactory::class );
	}

	/**
	 * Tests that container exceptions are converted to state transition exceptions.
	 */
	public function test_container_exception_handling() {
		$this->mock_container->expects( $this->once() )
			->method( 'get' )
			->with( RandomState::class )
			->willThrowException( new StateTransitionException( 'Cannot resolve class.' ) );

		$this->expectException( StateTransitionException::class );
		$this->sut->create_state( RandomState::class );
	}
}
