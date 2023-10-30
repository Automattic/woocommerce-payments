<?php
/**
 * Class ContextLoggerServiceTest
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Tests\Internal\Service;

use WCPAY_UnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Internal\Logger;
use WCPay\Internal\Payment\Change;
use WCPay\Internal\Payment\PaymentContext;
use WCPay\Internal\Payment\Transition;
use WCPay\Internal\Service\PaymentContextLoggerService;
use WCPay\Internal\Payment\PaymentMethod\NewPaymentMethod;

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
			->onlyMethods( [ 'get_transitions', 'get_order_id' ] )
			->getMock();
		$this->mock_payment_context
			->expects( $this->once() )
			->method( 'get_order_id' )
			->will( $this->returnValue( 123 ) );
	}

	protected function setup_transitions() {
		return [
			new Transition( strtotime( '2023-10-26 00:00:00' ), null, 'Initial_State', [ new Change( 'key_1', null, 'new_value_1' ) ] ),
			new Transition(
				strtotime( '2023-10-26 00:00:10' ),
				'Initial_State',
				'Final_State',
				[
					new Change( 'key_1', 'old_value_1', 'new_value_1' ),
					new Change( 'key_2', null, 'new_value_2' ),
					new Change( 'key_3', 'old_value_3', 'new_value_3' ),
				]
			),
			new Transition( strtotime( '2023-10-26 00:00:40' ), 'Final_State', null, [ new Change( 'key_1', 'old_value_4', 'new_value_4' ) ] ),
		];
	}

	/**
	 * Test the basic happy path of logging a payment context with changes
	 */
	public function test_log_changes() {
		$this->mock_payment_context
			->expects( $this->once() )
			->method( 'get_transitions' )
			->will( $this->returnValue( $this->setup_transitions() ) );
		$expected_log = 'For order #123 the following changes were made to the payment context: {' . PHP_EOL .
			'	2023-10-26T00:00:00+00:00 Payment initialized in \'Initial_State\' {' . PHP_EOL .
			'		Set key_1 to "new_value_1"' . PHP_EOL .
			'	}' . PHP_EOL .
			'	2023-10-26T00:00:10+00:00 Transition from \'Initial_State\' to \'Final_State\' {' . PHP_EOL .
			'		Changed key_1 from "old_value_1" to "new_value_1"' . PHP_EOL .
			'		Set key_2 to "new_value_2"' . PHP_EOL .
			'		Changed key_3 from "old_value_3" to "new_value_3"' . PHP_EOL .
			'	}' . PHP_EOL .
			'	2023-10-26T00:00:40+00:00 Changes within \'Final_State\' {' . PHP_EOL .
			'		Changed key_1 from "old_value_4" to "new_value_4"' . PHP_EOL .
			'	}' . PHP_EOL .
			'}';
		$this->mock_logger->expects( $this->once() )
			->method( 'debug' )
			->with( $expected_log );
		$this->sut->log_changes( $this->mock_payment_context );
	}

	/**
	 * Test logging a payment context with a PaymentMethod
	 */
	public function test_log_payment_method() {
		$this->mock_payment_context
			->expects( $this->once() )
			->method( 'get_transitions' )
			->will(
				$this->returnValue(
					[
						new Transition( strtotime( '2023-10-27 00:05:00' ), null, 'Initial_State', [ new Change( 'manual_capture', null, false ) ] ),
						new Transition(
							strtotime( '2023-10-27 00:05:40' ),
							'Initial_State',
							'Intermediate_State',
							[ new Change( 'payment_method', null, new NewPaymentMethod( 'pm_123' ) ) ]
						),
					]
				)
			);
		$expected_log = 'For order #123 the following changes were made to the payment context: {' . PHP_EOL .
			'	2023-10-27T00:05:00+00:00 Payment initialized in \'Initial_State\' {' . PHP_EOL .
			'		Set manual_capture to false' . PHP_EOL .
			'	}' . PHP_EOL .
			'	2023-10-27T00:05:40+00:00 Transition from \'Initial_State\' to \'Intermediate_State\' {' . PHP_EOL .
			'		Set payment_method to WCPay\Internal\Payment\PaymentMethod\NewPaymentMethod {' . PHP_EOL .
			'		    "type": "new",' . PHP_EOL .
			'		    "id": "pm_123"' . PHP_EOL .
			'		}' . PHP_EOL .
			'	}' . PHP_EOL .
			'}';
		$this->mock_logger->expects( $this->once() )
			->method( 'debug' )
			->with( $expected_log );
		$this->sut->log_changes( $this->mock_payment_context );
	}

	/**
	 * Test logging a payment context with no changes in final state
	 */
	public function test_do_not_log_empty_final_state() {
		$this->mock_payment_context
			->expects( $this->once() )
			->method( 'get_transitions' )
			->will(
				$this->returnValue(
					[
						new Transition( strtotime( '2023-10-26 00:00:00' ), null, 'Initial_State', [ new Change( 'key_1', null, 'new_value_1' ) ] ),
						new Transition(
							strtotime( '2023-10-26 00:00:10' ),
							'Initial_State',
							'Final_State',
							[
								new Change( 'key_2', 'old_value_1', 'new_value_1' ),
							]
						),
						new Transition( strtotime( '2023-10-26 00:00:40' ), 'Final_State', null, [] ),
					]
				)
			);
		$expected_log = 'For order #123 the following changes were made to the payment context: {' . PHP_EOL .
			'	2023-10-26T00:00:00+00:00 Payment initialized in \'Initial_State\' {' . PHP_EOL .
			'		Set key_1 to "new_value_1"' . PHP_EOL .
			'	}' . PHP_EOL .
			'	2023-10-26T00:00:10+00:00 Transition from \'Initial_State\' to \'Final_State\' {' . PHP_EOL .
			'		Changed key_2 from "old_value_1" to "new_value_1"' . PHP_EOL .
			'	}' . PHP_EOL .
			'}';
		$this->mock_logger->expects( $this->once() )
			->method( 'debug' )
			->with( $expected_log );
		$this->sut->log_changes( $this->mock_payment_context );
	}

}
