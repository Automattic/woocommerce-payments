<?php
/**
 * Class ContextLoggerServiceTest
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Tests\Internal\Service;

use WCPAY_UnitTestCase;
use WCPay\Internal\Logger;
use WCPay\Internal\Payment\Change;
use WCPay\Internal\Payment\PaymentContext;
use WCPay\Internal\Payment\Transition;
use WCPay\Internal\Service\PaymentContextLoggerService;

/**
 * Level3 data service unit tests.
 */
class PaymentContextLoggerServiceTest extends WCPAY_UnitTestCase {
	/**
	 * Service under test.
	 *
	 * @var PaymentContextLoggerService
	 */
	private $sut;

	/**
	 * @var Logger|MockObject
	 */
	private $mock_logger;

	/**
	 * @var PaymentContext|MockObject
	 */
	private $mock_payment_context;

	/**
	 * Set up the tests.
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->mock_logger = $this->createMock( Logger::class );
		$this->sut         = new PaymentContextLoggerService( $this->mock_logger );

		$this->mock_payment_context = $this->getMockBuilder( PaymentContext::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'get_transitions' ] )
			->getMock();
		$this->mock_payment_context
			->method( 'get_transitions' )
			->will( $this->returnValue( $this->setup_transitions() ) );
	}

	protected function setup_transitions() {
		return [
			new Transition( 123, null, 'Initial_State', [ new Change( 'key_1', null, 'new_value_1' ) ], time() ),
			new Transition(
				123,
				'Initial_State',
				'Final_State',
				[
					new Change( 'key_1', 'old_value_1', 'new_value_1' ),
					new Change( 'key_2', null, 'new_value_2' ),
					new Change( 'key_3', 'old_value_3', 'new_value_3' ),
				],
				time()
			),
			new Transition( 123, 'Final_State', null, [ new Change( 'key_1', 'old_value_4', 'new_value_4' ) ], time() ),
		];
	}

	public function test_log_changes() {
		$expected_log = 'Payment for order #123 initialized in "Initial_State" [' . PHP_EOL .
						'Set key_1 to "new_value_1"' . PHP_EOL .
						']' . PHP_EOL .
						'Transition from "Initial_State" to "Final_State" [' . PHP_EOL .
						'Changed key_1 from "old_value_1" to "new_value_1"' . PHP_EOL .
						'Set key_2 to "new_value_2"' . PHP_EOL .
						'Changed key_3 from "old_value_3" to "new_value_3"' . PHP_EOL .
						']' . PHP_EOL .
						'Changes within "Final_State" [' . PHP_EOL .
						'Changed key_1 from "old_value_4" to "new_value_4"' . PHP_EOL .
						']' . PHP_EOL;
		$this->mock_logger->expects( $this->once() )
			->method( 'debug' )
			->with( $expected_log );
		$this->sut->log_changes( $this->mock_payment_context );
	}


}
