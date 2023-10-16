<?php
/**
 * Class InitialStateTest
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Tests\Internal\Payment\State;

use Exception;
use WC_Helper_Intention;
use WCPay\Internal\Payment\State\ProcessedState;
use WCPay\Internal\Payment\State\VerifiedState;
use WCPAY_UnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit_Utils;
use ReflectionClass;
use WC_Order;
use WC_Payments_API_Payment_Intention;
use WC_Payments_Customer_Service;
use WCPay\Core\Exceptions\Server\Request\Extend_Request_Exception;
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

		$this->mock_state_factory    = $this->createMock( StateFactory::class );
		$this->mock_order_service    = $this->createMock( OrderService::class );
		$this->mock_context          = $this->createMock( PaymentContext::class );
		$this->mock_customer_service = $this->createMock( WC_Payments_Customer_Service::class );
		$this->mock_level3_service   = $this->createMock( Level3Service::class );

		$this->sut = new InitialState(
			$this->mock_state_factory,
			$this->mock_order_service,
			$this->mock_customer_service,
			$this->mock_level3_service
		);
		$this->sut->set_context( $this->mock_context );
	}

	public function test_start_processing() {
		$mock_request = $this->createMock( PaymentRequest::class );

		/**
		 * This test works with the root `process` method, which calls a few
		 * internal methods. We want to mock them for the purpose of this test.
		 *
		 * @var MockObject|InitialState
		 */
		$this->sut = $this->getMockBuilder( InitialState::class )
			->onlyMethods( [ 'populate_context_from_request', 'populate_context_from_order' ] )
			->setConstructorArgs(
				[
					$this->mock_state_factory,
					$this->mock_order_service,
					$this->mock_customer_service,
					$this->mock_level3_service,
				]
			)
			->getMock();
		$this->sut->set_context( $this->mock_context );

		// Mock get order id calls.
		$this->mock_context->expects( $this->exactly( 2 ) )
			->method( 'get_order_id' )
			->willReturn( 1 );

		// Verify that the context is populated.
		$this->sut->expects( $this->once() )->method( 'populate_context_from_request' )->with( $mock_request );
		$this->sut->expects( $this->once() )->method( 'populate_context_from_order' );

		// Since the original create_state method is mocked, we have to manually set context.
		$this->mock_state_factory->expects( $this->exactly( 3 ) )
			->method( 'create_state' )
			->withConsecutive(
				[ VerifiedState::class, $this->mock_context ],
				[ ProcessedState::class, $this->mock_context ],
				[ CompletedState::class, $this->mock_context ]
			)
			->willReturnOnConsecutiveCalls(
				( function () {
					$intent = WC_Helper_Intention::create_intention();

					$mock_payment_request_service = $this->createMock( PaymentRequestService::class );
					$mock_payment_request_service->expects( $this->once() )
						->method( 'create_intent' )
						->with( $this->mock_context )
						->willReturn( $intent );

					$this->mock_context->expects( $this->exactly( 2 ) )
						->method( 'get_intent' )
						->willReturn( $intent );

					$verified_state = new VerifiedState( $this->mock_state_factory, $mock_payment_request_service );
					$verified_state->set_context( $this->mock_context );

					return $verified_state;
				} )(),
				( function () {
					$this->mock_order_service->expects( $this->once() )
						->method( 'update_order_from_successful_intent' )
						->with( $this->mock_context->get_order_id(), $this->mock_context->get_intent(), $this->mock_context );
					$processed_state = new ProcessedState( $this->mock_state_factory, $this->mock_order_service );
					$processed_state->set_context( $this->mock_context );

					return $processed_state;
				} )(),
				( function () {
					$completed_state = new CompletedState( $this->mock_state_factory );
					$completed_state->set_context( $this->mock_context );

					return $completed_state;
				} )()
			);

		// Act: start processing.
		$result = $this->sut->start_processing( $mock_request );
		// Assert: Successful transition.
		$this->assertInstanceOf( CompletedState::class, $result );
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
