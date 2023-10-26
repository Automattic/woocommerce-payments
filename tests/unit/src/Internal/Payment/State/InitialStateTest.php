<?php
/**
 * Class InitialStateTest
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Tests\Internal\Payment\State;

use WC_Helper_Intention;
use WCPay\Constants\Intent_Status;
use WCPay\Internal\Payment\State\AuthenticationRequiredState;
use WCPay\Internal\Payment\State\ProcessedState;
use WCPAY_UnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit_Utils;
use WC_Order;
use WC_Payments_API_Payment_Intention;
use WC_Payments_Customer_Service;
use WCPay\Core\Exceptions\Server\Request\Invalid_Request_Parameter_Exception;
use WCPay\Internal\Payment\State\InitialState;
use WCPay\Internal\Payment\State\StateFactory;
use WCPay\Internal\Payment\PaymentContext;
use WCPay\Internal\Payment\PaymentMethod\NewPaymentMethod;
use WCPay\Internal\Payment\PaymentRequest;
use WCPay\Internal\Payment\State\CompletedState;
use WCPay\Internal\Payment\State\SystemErrorState;
use WCPay\Internal\Service\Level3Service;
use WCPay\Internal\Service\OrderService;
use WCPay\Internal\Service\PaymentRequestService;

/**
 * Tests for the initial payment state.
 */
class InitialStateTest extends WCPAY_UnitTestCase {
	/**
	 * Service under test.
	 *
	 * @var InitialState
	 */
	private $sut;

	/**
	 * Service under test.
	 *
	 * @var InitialState|MockObject
	 */
	private $mocked_sut;

	/**
	 * @var StateFactory|MockObject
	 */
	private $mock_state_factory;

	/**
	 * @var OrderService|MockObject
	 */
	private $mock_order_service;

	/**
	 * @var WC_Payments_Customer_Service|MockObject
	 */
	private $mock_customer_service;

	/**
	 * @var PaymentRequestService|MockObject
	 */
	private $mock_payment_request_service;

	/**
	 * @var Level3Service|MockObject
	 */
	private $mock_level3_service;

	/**
	 * @var PaymentContext|MockObject
	 */
	private $mock_context;

	/**
	 * Set up the test.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->mock_state_factory           = $this->createMock( StateFactory::class );
		$this->mock_order_service           = $this->createMock( OrderService::class );
		$this->mock_context                 = $this->createMock( PaymentContext::class );
		$this->mock_customer_service        = $this->createMock( WC_Payments_Customer_Service::class );
		$this->mock_level3_service          = $this->createMock( Level3Service::class );
		$this->mock_payment_request_service = $this->createMock( PaymentRequestService::class );

		$this->sut = new InitialState(
			$this->mock_state_factory,
			$this->mock_order_service,
			$this->mock_customer_service,
			$this->mock_level3_service,
			$this->mock_payment_request_service
		);
		$this->sut->set_context( $this->mock_context );

		/**
		 * This test works with the root `process` method, which calls a few
		 * internal methods. We want to mock them for the purpose of this test.
		 *
		 * @var MockObject|InitialState
		 */
		$this->mocked_sut = $this->getMockBuilder( InitialState::class )
			->onlyMethods( [ 'populate_context_from_request', 'populate_context_from_order' ] )
			->setConstructorArgs(
				[
					$this->mock_state_factory,
					$this->mock_order_service,
					$this->mock_customer_service,
					$this->mock_level3_service,
					$this->mock_payment_request_service,
				]
			)
			->getMock();
		$this->mocked_sut->set_context( $this->mock_context );
	}

	public function test_start_processing() {
		$mock_request         = $this->createMock( PaymentRequest::class );
		$mock_processed_state = $this->createMock( ProcessedState::class );
		$mock_completed_state = $this->createMock( CompletedState::class );

		$mock_processed_state->expects( $this->once() )
			->method( 'complete_processing' )
			->willReturn( $mock_completed_state );

		// Verify that the context is populated.
		$this->mocked_sut->expects( $this->once() )->method( 'populate_context_from_request' )->with( $mock_request );
		$this->mocked_sut->expects( $this->once() )->method( 'populate_context_from_order' );

		$intent = WC_Helper_Intention::create_intention();

		$this->mock_payment_request_service
			->expects( $this->once() )
			->method( 'create_intent' )
			->with( $this->mock_context )
			->willReturn( $intent );

		// Since the original create_state method is mocked, we have to manually set context.
		$this->mock_state_factory->expects( $this->once() )
			->method( 'create_state' )
			->with( ProcessedState::class, $this->mock_context )
			->willReturn( $mock_processed_state );

		// Act: start processing.
		$result = $this->mocked_sut->start_processing( $mock_request );
		// Assert: Successful transition.
		$this->assertSame( $mock_completed_state, $result );
	}

	public function test_start_processing_will_transition_to_error_state_when_api_exception_occurs() {
		$mock_request     = $this->createMock( PaymentRequest::class );
		$mock_error_state = $this->createMock( SystemErrorState::class );

		$this->mock_payment_request_service
			->expects( $this->once() )
			->method( 'create_intent' )
			->with( $this->mock_context )
			->willThrowException( new Invalid_Request_Parameter_Exception( 'Invalid param', 'invalid_param' ) );

		// Let's mock these services in order to prevent real execution of them.
		$this->mocked_sut->expects( $this->once() )->method( 'populate_context_from_request' )->with( $mock_request );
		$this->mocked_sut->expects( $this->once() )->method( 'populate_context_from_order' );

		$this->mock_state_factory->expects( $this->once() )
			->method( 'create_state' )
			->with( SystemErrorState::class, $this->mock_context )
			->willReturn( $mock_error_state );
		$result = $this->mocked_sut->start_processing( $mock_request );
		$this->assertSame( $mock_error_state, $result );
	}

	public function test_processing_will_transition_to_auth_required_state() {
		$order_id        = 123;
		$mock_request    = $this->createMock( PaymentRequest::class );
		$mock_auth_state = $this->createMock( AuthenticationRequiredState::class );

		// Create an intent, and make sure it will be returned by the service.
		$mock_intent = $this->createMock( WC_Payments_API_Payment_Intention::class );
		$mock_intent->expects( $this->once() )->method( 'get_status' )->willReturn( Intent_Status::REQUIRES_ACTION );
		$this->mock_payment_request_service->expects( $this->once() )
			->method( 'create_intent' )
			->with( $this->mock_context )
			->willReturn( $mock_intent );

		// Let's mock these services in order to prevent real execution of them.
		$this->mocked_sut->expects( $this->once() )->method( 'populate_context_from_request' )->with( $mock_request );
		$this->mocked_sut->expects( $this->once() )->method( 'populate_context_from_order' );

		// Before the transition, the order service should update the order.
		$this->mock_context->expects( $this->once() )
			->method( 'get_order_id' )
			->willReturn( $order_id );
		$this->mock_order_service->expects( $this->once() )
			->method( 'update_order_from_intent_that_requires_action' )
			->with( $order_id, $mock_intent, $this->mock_context );

		$this->mock_state_factory->expects( $this->once() )
			->method( 'create_state' )
			->with( AuthenticationRequiredState::class, $this->mock_context )
			->willReturn( $mock_auth_state );

		$result = $this->mocked_sut->start_processing( $mock_request );
		$this->assertSame( $mock_auth_state, $result );
	}

	public function test_populate_context_from_request() {
		$payment_method   = new NewPaymentMethod( 'pm_123' );
		$fingerprint      = 'fingerprint';
		$cvc_confirmation = 'CVCConfirmation';

		// Setup the mock request.
		$mock_request = $this->createMock( PaymentRequest::class );
		$mock_request->expects( $this->once() )->method( 'get_payment_method' )->willReturn( $payment_method );
		$mock_request->expects( $this->once() )->method( 'get_cvc_confirmation' )->willReturn( $cvc_confirmation );
		$mock_request->expects( $this->once() )->method( 'get_fingerprint' )->willReturn( $fingerprint );

		// Assume that everything from the request would be imported into the context.
		$this->mock_context->expects( $this->once() )->method( 'set_payment_method' )->with( $payment_method );
		$this->mock_context->expects( $this->once() )->method( 'set_cvc_confirmation' )->with( $cvc_confirmation );
		$this->mock_context->expects( $this->once() )->method( 'set_fingerprint' )->with( $fingerprint );

		// Use reflection to access.
		PHPUnit_Utils::call_method( $this->sut, 'populate_context_from_request', [ $mock_request ] );
	}

	public function test_populate_context_from_order() {
		$order_id    = 123;
		$user_id     = 456;
		$customer_id = 'cus_123';
		$metadata    = [ 'sample' => 'true' ];
		$level3_data = [ 'items' => [] ];
		$mock_order  = $this->createMock( WC_Order::class );

		// Prepare the order ID.
		$this->mock_context->expects( $this->once() )
			->method( 'get_order_id' )
			->willReturn( $order_id );

		// Arrange the import of order data to the payment.
		$this->mock_order_service->expects( $this->once() )
			->method( 'import_order_data_to_payment_context' )
			->with( $order_id, $this->mock_context );

		// Arrange metadata import.
		$this->mock_order_service->expects( $this->once() )
			->method( 'get_payment_metadata' )
			->with( $order_id )
			->willReturn( $metadata );
		$this->mock_context->expects( $this->once() )
			->method( 'set_metadata' )
			->with( array_merge( $metadata, [ 'gateway_type' => 'src' ] ) );

		// Arrange level 3 data import.
		$this->mock_level3_service->expects( $this->once() )
			->method( 'get_data_from_order' )
			->with( $order_id )
			->willReturn( $level3_data );
		$this->mock_context->expects( $this->once() )
			->method( 'set_level3_data' )
			->with( $level3_data );

		// Arrange customer management.
		$this->mock_context->expects( $this->once() )
			->method( 'get_user_id' )
			->willReturn( $user_id );
		$this->mock_order_service->expects( $this->once() )
			->method( '_deprecated_get_order' )
			->with( $order_id )
			->willReturn( $mock_order );
		$this->mock_customer_service->expects( $this->once() )
			->method( 'get_or_create_customer_id_from_order' )
			->with( $user_id, $mock_order )
			->willReturn( $customer_id );
		$this->mock_context->expects( $this->once() )
			->method( 'set_customer_id' )
			->with( $customer_id );

		PHPUnit_Utils::call_method( $this->sut, 'populate_context_from_order', [] );
	}
}
