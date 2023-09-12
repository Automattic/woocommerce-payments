<?php
/**
 * Class InitialStateTest
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Tests\Internal\Payment\State;

use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Internal\Payment\Payment;
use WCPay\Internal\Payment\State\CompletedState;
use WCPAY_UnitTestCase;
use WCPay\Internal\Payment\State\InitialState;

/**
 * State factory unit tests.
 */
class InitialStateTest extends WCPAY_UnitTestCase {
	/**
	 * Service under test.
	 *
	 * @var InitialState
	 */
	private $sut;

	/**
	 * Mock payment object.
	 *
	 * @var Payment|MockObject
	 */
	private $mock_payment;

	/**
	 * Set up the test.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->mock_payment = $this->createMock( Payment::class );

		$this->sut = new InitialState();
		$this->sut->set_context( $this->mock_payment );
	}

	/**
	 * Confirms that the `prepare` method transitions the payment to the completed state.
	 */
	public function test_prepare_transitions_to_completed_state() {
		$this->mock_payment->expects( $this->once() )
			->method( 'set_state' )
			->with( CompletedState::class );

		$this->sut->prepare( 'pm_123' );
	}
}
