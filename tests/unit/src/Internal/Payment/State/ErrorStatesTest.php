<?php
/**
 * Class ErrorStatesTest
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Tests\Internal\Payment\State;

use PHPUnit\Framework\MockObject\MockObject;
use ReflectionClass;
use WCPay\Internal\Logger;
use WCPay\Internal\Payment\State\PaymentErrorState;
use WCPay\Internal\Payment\State\PaymentRequestErrorState;
use WCPay\Internal\Payment\State\StateFactory;
use WCPay\Internal\Payment\State\SystemErrorState;
use WCPay\Internal\Payment\State\WooPaymentsApiServerErrorState;
use WCPay\Internal\Service\OrderService;
use WCPAY_UnitTestCase;

/**
 * Tests for the processed payment state.
 */
class ErrorStatesTest extends WCPAY_UnitTestCase {
	/**
	 * @var StateFactory|MockObject
	 */
	private $mock_state_factory;

	/**
	 * @var Logger|MockObject
	 */
	private $mock_logger;

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
		$this->mock_logger        = $this->createMock( Logger::class );
	}

	/**
	 * @dataProvider provider_error_states_should_methods
	 */
	public function test_states_should_methods( $state_class, $should_log_error, $should_mark_order_as_failed ) {
		$sut                      = new $state_class(
			$this->mock_state_factory,
			$this->mock_logger,
			$this->mock_order_service
		);
		$reflection               = new ReflectionClass( $sut );
		$method_log_error         = $reflection->getMethod( 'should_log_error' );
		$method_mark_order_failed = $reflection->getMethod( 'should_mark_order_as_failed' );

		$method_log_error->setAccessible( true );
		$method_mark_order_failed->setAccessible( true );

		$this->assertSame( $method_log_error->invoke( $sut ), $should_log_error );
		$this->assertSame( $method_mark_order_failed->invoke( $sut ), $should_mark_order_as_failed );
	}
	public function provider_error_states_should_methods() {
		return [
			[ WooPaymentsApiServerErrorState::class, true, false ],
			[ PaymentErrorState::class, true, false ],
			[ PaymentRequestErrorState::class, true, false ],
			[ SystemErrorState::class, false, false ],
		];
	}
}
