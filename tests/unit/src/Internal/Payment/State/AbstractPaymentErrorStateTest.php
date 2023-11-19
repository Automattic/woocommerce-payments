<?php
/**
 * Class AbstractPaymentErrorStateTest
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Tests\Internal\Payment\State;

use Exception;
use WCPay\Internal\Logger;
use WCPay\Internal\Payment\PaymentContext;
use WCPay\Internal\Payment\State\AbstractPaymentErrorState;
use WCPay\Internal\Service\OrderService;
use WCPAY_UnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Internal\Payment\State\StateFactory;


/**
 * Tests for the base payment state class.
 */
class AbstractPaymentErrorStateTest extends WCPAY_UnitTestCase {
	/**
	 * @var AbstractPaymentErrorState|MockObject
	 */
	private $mock_sut;

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
	 * @var PaymentContext|MockObject
	 */
	private $mock_context;

	/**
	 * Set up the test.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->mock_state_factory = $this->createMock( StateFactory::class );
		$this->mock_logger        = $this->createMock( Logger::class );
		$this->mock_order_service = $this->createMock( OrderService::class );
		$this->mock_context       = $this->createMock( PaymentContext::class );
		$this->mock_sut           = $this->getMockBuilder( AbstractPaymentErrorState::class )
			->setConstructorArgs( [ $this->mock_state_factory, $this->mock_logger, $this->mock_order_service ] )
			->onlyMethods( [ 'get_context', 'should_mark_order_as_failed', 'should_log_error' ] )
			->getMock();

	}

	public function test_error_state_will_not_log_exception_and_mark_order_as_failed_on_default_values() {

		$error_msg = 'foo';
		$exception = new Exception( $error_msg );
		$this->mock_error_state( 1, $exception );
		$this->mock_logger
			->expects( $this->never() )
			->method( 'error' );
		$this->mock_order_service
			->expects( $this->never() )
			->method( 'mark_order_as_failed' );

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( $error_msg );
		$this->mock_sut->handle_error_state();
	}
	public function test_error_state_will_log_and_cancel_order() {
		$error_msg = 'foo';
		$order_id  = 1;
		$exception = new Exception( $error_msg );
		$this->mock_error_state( $order_id, $exception, true, true );
		$this->mock_logger
			->expects( $this->once() )
			->method( 'error' )
			->with( "Failed to process order with ID:  $order_id . Reason: " . $exception );
		$this->mock_order_service
			->expects( $this->once() )
			->method( 'mark_order_as_failed' )
			->with( $order_id, $error_msg );

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( $error_msg );
		$this->mock_sut->handle_error_state();
	}
	public function test_error_state_will_throw_default_exception() {
		$this->mock_error_state( 1, null );
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'The payment process could not be completed.' );
		$this->mock_sut->handle_error_state();
	}

	/**
	 * Mock error state.
	 *
	 * @param int $order_id                     Order id.
	 * @param Exception|null $exception         Exception occurred that resulted in state change.
	 * @param bool $should_log_error            Should log error.
	 * @param bool $should_mark_order_as_failed Should mark order as failed.
	 *
	 */
	private function mock_error_state( $order_id, $exception, $should_log_error = false, $should_mark_order_as_failed = false ) {
		$this->mock_context
			->expects( $this->once() )
			->method( 'get_order_id' )
			->willReturn( $order_id );
		$this->mock_context
			->expects( $this->once() )
			->method( 'get_exception' )
			->willReturn( $exception );
		$this->mock_sut
			->expects( $this->once() )
			->method( 'get_context' )
			->willReturn( $this->mock_context );
		$this->mock_sut
			->expects( $this->once() )
			->method( 'should_log_error' )
			->willReturn( $should_log_error );
		$this->mock_sut
			->expects( $this->once() )
			->method( 'should_mark_order_as_failed' )
			->willReturn( $should_mark_order_as_failed );
	}
}
