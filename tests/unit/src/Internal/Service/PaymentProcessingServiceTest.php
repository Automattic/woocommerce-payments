<?php
/**
 * Class PaymentProcessingServiceTest
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Tests\Internal\Service;

use PHPUnit\Framework\MockObject\MockObject;
use WC_Helper_Order;
use WCPay\Internal\Payment\Exception\MethodUnavailableException;
use WCPay\Internal\Payment\Exception\StateTransitionException;
use WCPay\Internal\Payment\State\InitialState;
use WCPay\Internal\Payment\State\State;
use WCPay\Internal\Payment\StateFactory;
use WCPay\Internal\Proxy\LegacyProxy;
use WCPAY_UnitTestCase;
use WCPay\Internal\Service\PaymentProcessingService;

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
	 * StateFactory mock.
	 *
	 * @var StateFactory|MockObject
	 */
	private $mock_state_factory;

	/**
	 * LegacyProxy mock.
	 *
	 * @var LegacyProxy|MockObject
	 */
	private $mock_legacy_proxy;

	/**
	 * Set up the test.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->mock_state_factory = $this->createMock( StateFactory::class );
		$this->mock_legacy_proxy  = $this->createMock( LegacyProxy::class );

		$this->sut = new PaymentProcessingService(
			$this->mock_state_factory,
			$this->mock_legacy_proxy
		);
	}

	/**
	 * Used to determine whether autoloading works.
	 */
	public function test_class_is_loaded() {
		$this->assertTrue( class_exists( PaymentProcessingService::class ) );

		$instance = new PaymentProcessingService( $this->mock_state_factory, $this->mock_legacy_proxy );
		$this->assertInstanceOf( PaymentProcessingService::class, $instance );
	}

	/**
	 * Checks if the `process_payment` method throws an exception if the given order cannot be found.
	 */
	public function test_processing_payment_throws_exception_if_order_not_found() {
		$this->expectException( \Exception::class );
		$this->sut->process_payment( 789 );
	}

	/**
	 * Goes through the happy payment processing flow.
	 */
	public function test_processing_payment_works() {
		$order         = WC_Helper_Order::create_order();
		$mock_response = [
			'success' => 123,
		];

		/**
		 * Initial state, which will simulate all state-related actions.
		 * @var State|MockObject
		 */
		$mock_initial_state = $this->createMock( InitialState::class );

		$this->mock_state_factory->expects( $this->once() )
			->method( 'create_state' )
			->with( InitialState::class )
			->willReturn( $mock_initial_state );

		$mock_initial_state->expects( $this->once() )
			->method( 'prepare' )
			->with( 'pm_XYZ' );

		$mock_initial_state->expects( $this->once() )
			->method( 'get_gateway_response' )
			->willReturn( $mock_response );

		$result = $this->sut->process_payment( $order->get_id() );
		$this->assertSame( $mock_response, $result );
	}

	public function test_processing_payment_handles_state_exceptions() {
		$order = WC_Helper_Order::create_order();

		$this->mock_state_factory->expects( $this->once() )
			->method( 'create_state' )
			->willThrowException( new StateTransitionException( 'Message' ) );

		$result = $this->sut->process_payment( $order->get_id() );
		$this->assertFalse( $result );
	}

	public function test_processing_payment_handles_unavailable_method_exceptions() {
		$order = WC_Helper_Order::create_order();

		$this->mock_state_factory->expects( $this->once() )
			->method( 'create_state' )
			->willThrowException( new MethodUnavailableException( 'Message' ) );

		$result = $this->sut->process_payment( $order->get_id() );
		$this->assertFalse( $result );
	}
}
