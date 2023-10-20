<?php
/**
 * Class PaymentProcessingServiceTest
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Tests\Internal\Service;

use PHPUnit\Framework\MockObject\MockObject;
use WC_Payment_Gateway_WCPay;
use WCPay\Internal\Payment\PaymentContext;
use WCPay\Internal\Payment\PaymentRequest;
use WCPay\Internal\Payment\State\CompletedState;
use WCPay\Internal\Payment\State\InitialState;
use WCPAY_UnitTestCase;
use WCPay\Internal\Proxy\LegacyProxy;
use WCPay\Internal\Payment\State\StateFactory;
use WCPay\Internal\Service\PaymentProcessingService;

/**
 * Payment processing service unit tests.
 */
class PaymentProcessingServiceTest extends WCPAY_UnitTestCase {
	/**
	 * Service under test.
	 *
	 * @var PaymentProcessingService|MockObject
	 */
	private $sut;

	/**
	 * @var StateFactory|MockObject
	 */
	private $mock_state_factory;

	/**
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

		$this->sut = $this->getMockBuilder( PaymentProcessingService::class )
			->setConstructorArgs(
				[
					$this->mock_state_factory,
					$this->mock_legacy_proxy,
				]
			)
			->onlyMethods( [ 'create_payment_context' ] )
			->getMock();
	}

	/**
	 * Test the basic happy path of processing a payment.
	 */
	public function test_process_payment_happy_path() {
		// Prepare all required mocks.
		$mock_context         = $this->createMock( PaymentContext::class );
		$mock_initial_state   = $this->createMock( InitialState::class );
		$mock_completed_state = $this->createMock( CompletedState::class );

		// Set up the mocks to be returned.
		$this->sut->expects( $this->once() )
			->method( 'create_payment_context' )
			->with( 1 )
			->willReturn( $mock_context );

		$this->mock_state_factory->expects( $this->once() )
			->method( 'create_state' )
			->with( InitialState::class, $this->isInstanceOf( PaymentContext::class ) )
			->willReturn( $mock_initial_state );

		$mock_initial_state->expects( $this->once() )
			->method( 'process' )
			->with( $this->isInstanceOf( PaymentRequest::class ) )
			->willReturn( $mock_completed_state );

		$result = $this->sut->process_payment( 1 );
		$this->assertSame( $mock_completed_state, $result );
	}

	/**
	 * Test the basic happy path of processing a payment.
	 */
	public function test_process_payment_happy_path_without_mock_builder() {
		$sut = new PaymentProcessingService( $this->mock_state_factory, $this->mock_legacy_proxy );

		$mock_initial_state   = $this->createMock( InitialState::class );
		$mock_completed_state = $this->createMock( CompletedState::class );

		// Prepare a payment method for the context.
		$_POST['payment_method']       = WC_Payment_Gateway_WCPay::GATEWAY_ID;
		$_POST['wcpay-payment-method'] = 'pi_XYZ';

		$this->mock_state_factory->expects( $this->once() )
			->method( 'create_state' )
			->with( InitialState::class, $this->isInstanceOf( PaymentContext::class ) )
			->willReturn( $mock_initial_state );

		$mock_initial_state->expects( $this->once() )
			->method( 'process' )
			->with( $this->isInstanceOf( PaymentRequest::class ) )
			->willReturn( $mock_completed_state );

		$result = $sut->process_payment( 1 );
		$this->assertSame( $mock_completed_state, $result );
	}
}
