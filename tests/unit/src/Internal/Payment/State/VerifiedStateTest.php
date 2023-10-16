<?php
/**
 * Class VerifiedStateTest
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Tests\Internal\Payment\State;

use PHPUnit\Framework\MockObject\MockObject;
use WC_Helper_Intention;
use WCPay\Constants\Intent_Status;
use WCPay\Core\Exceptions\Server\Request\Invalid_Request_Parameter_Exception;
use WCPay\Internal\Payment\PaymentContext;
use WCPay\Internal\Payment\State\AuthenticationRequiredState;
use WCPay\Internal\Payment\State\CompletedState;
use WCPay\Internal\Payment\State\ProcessedState;
use WCPay\Internal\Payment\State\StateFactory;
use WCPay\Internal\Payment\State\SystemErrorState;
use WCPay\Internal\Payment\State\VerifiedState;
use WCPay\Internal\Service\OrderService;
use WCPay\Internal\Service\PaymentRequestService;
use WCPAY_UnitTestCase;

/**
 * Tests for the initial payment state.
 */
class VerifiedStateTest extends WCPAY_UnitTestCase {
	/**
	 * State under test.
	 *
	 * @var VerifiedState
	 */
	private $sut;

	/**
	 * @var StateFactory|MockObject
	 */
	private $mock_state_factory;

	/**
	 * @var OrderService|MockObject
	 */
	private $mock_order_service;

	/**
	 * @var PaymentRequestService|MockObject
	 */
	private $mock_payment_request_service;


	/**
	 * Set up the test.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->mock_state_factory           = $this->createMock( StateFactory::class );
		$this->mock_order_service           = $this->createMock( OrderService::class );
		$this->mock_context                 = $this->createMock( PaymentContext::class );
		$this->mock_payment_request_service = $this->createMock( PaymentRequestService::class );

		$this->sut = new VerifiedState(
			$this->mock_state_factory,
			$this->mock_order_service,
			$this->mock_payment_request_service
		);
		$this->sut->set_context( $this->mock_context );
	}

	public function test_process_will_transition_to_completed_state() {
		$this->mock_payment_request_service->expects( $this->once() )
			->method( 'create_intent' )
			->with( $this->mock_context )
			->willReturn( WC_Helper_Intention::create_intention() );

		$mock_completed_state = $this->createMock( CompletedState::class );
		$mock_processed_state = $this->createMock( ProcessedState::class );

		// We will mock only transition from verified to processed and mock ProcessedState execution code to avoid nested state change within the test.
		$mock_processed_state->expects( $this->once() )
			->method( 'complete' )
			->willReturn( $mock_completed_state );

		$this->mock_state_factory->expects( $this->once() )
			->method( 'create_state' )
			->with( ProcessedState::class, $this->mock_context )
			->willReturn( $mock_processed_state );

		$result = $this->sut->process();

		$this->assertSame( $mock_completed_state, $result );
	}

	public function test_process_will_transition_to_error_state_when_api_exception_occurs() {
		$this->mock_payment_request_service->expects( $this->once() )
			->method( 'create_intent' )
			->with( $this->mock_context )
			->willThrowException( new Invalid_Request_Parameter_Exception( 'Invalid param', 'invalid_param' ) );

		$mock_error_state     = $this->createMock( SystemErrorState::class );
		$mock_processed_state = $this->createMock( ProcessedState::class );

		$mock_processed_state->expects( $this->never() )
			->method( 'complete' );

		$this->mock_state_factory->expects( $this->once() )
			->method( 'create_state' )
			->with( SystemErrorState::class, $this->mock_context )
			->willReturn( $mock_error_state );

		$result = $this->sut->process();

		$this->assertSame( $mock_error_state, $result );
	}

	public function test_process_will_transition_to_auth_required_state() {

		$this->mock_payment_request_service->expects( $this->once() )
			->method( 'create_intent' )
			->with( $this->mock_context )
			->willReturn( WC_Helper_Intention::create_intention( [ 'status' => Intent_Status::REQUIRES_ACTION ] ) );

		$mock_auth_state      = $this->createMock( AuthenticationRequiredState::class );
		$mock_processed_state = $this->createMock( ProcessedState::class );

		$mock_processed_state->expects( $this->never() )
			->method( 'complete' );

		$this->mock_state_factory->expects( $this->once() )
			->method( 'create_state' )
			->with( AuthenticationRequiredState::class, $this->mock_context )
			->willReturn( $mock_auth_state );

		$result = $this->sut->process();

		$this->assertSame( $mock_auth_state, $result );
	}
}
