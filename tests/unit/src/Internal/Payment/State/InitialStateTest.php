<?php
/**
 * Class InitialStateTest
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Tests\Internal\Payment\State;

use WC_Helper_Intention;
use WCPay\Constants\Intent_Status;
use WCPay\Exceptions\Amount_Too_Small_Exception;
use WCPay\Exceptions\API_Exception;
use WCPay\Internal\Payment\Exception\StateTransitionException;
use WCPay\Internal\Payment\FailedTransactionRateLimiter;
use WCPay\Internal\Payment\State\AuthenticationRequiredState;
use WCPay\Internal\Payment\State\ProcessedState;
use WCPay\Internal\Payment\State\DuplicateOrderDetectedState;
use WCPay\Internal\Service\DuplicatePaymentPreventionService;
use WCPay\Internal\Service\FraudPreventionService;
use WCPay\Internal\Service\MinimumAmountService;
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
	 * @var DuplicatePaymentPreventionService|MockObject
	 */
	private $mock_dpps;

	/**
	 * @var PaymentContext|MockObject
	 */
	private $mock_context;

	/**
	 * @var MinimumAmountService|MockObject
	 */
	private $mock_minimum_amount_service;

	/**
	 * @var FailedTransactionRateLimiter|MockObject
	 */
	private $mock_failed_transaction_rate_limiter;

	/**
	 * Mocked dependencies.
	 *
	 * @var MockObject[]
	 */
	private $mock_deps;

	/**
	 * Set up the test.
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->mock_context = $this->createMock( PaymentContext::class );
		$this->mock_deps    = [
			$this->mock_state_factory                   = $this->createMock( StateFactory::class ),
			$this->mock_order_service                   = $this->createMock( OrderService::class ),
			$this->mock_customer_service                = $this->createMock( WC_Payments_Customer_Service::class ),
			$this->mock_level3_service                  = $this->createMock( Level3Service::class ),
			$this->mock_payment_request_service         = $this->createMock( PaymentRequestService::class ),
			$this->mock_dpps                            = $this->createMock( DuplicatePaymentPreventionService::class ),
			$this->mock_minimum_amount_service          = $this->createMock( MinimumAmountService::class ),
			$this->mock_fraud_prevention_service        = $this->createMock( FraudPreventionService::class ),
			$this->mock_failed_transaction_rate_limiter = $this->createMock( FailedTransactionRateLimiter::class ),
		];

		$this->sut = new InitialState( ... $this->mock_deps );
		$this->sut->set_context( $this->mock_context );

		/**
		 * This test works with the root `process` method, which calls a few
		 * internal methods. We want to mock them for the purpose of this test.
		 *
		 * @var MockObject|InitialState
		 */
		$this->mocked_sut = $this->getMockBuilder( InitialState::class )
			->onlyMethods(
				[
					'populate_context_from_request',
					'populate_context_from_order',
					'process_order_phone_number',
					'process_duplicate_order',
					'process_duplicate_payment',
				]
			)
			->setConstructorArgs( $this->mock_deps )
			->getMock();
		$this->mocked_sut->set_context( $this->mock_context );
	}

	public function test_start_processing() {
		$order_id             = 123;
		$user_id              = 456;
		$customer_id          = 'cus_123';
		$mock_request         = $this->createMock( PaymentRequest::class );
		$mock_processed_state = $this->createMock( ProcessedState::class );
		$mock_completed_state = $this->createMock( CompletedState::class );
		$mock_order           = $this->createMock( WC_Order::class );

		$mock_processed_state->expects( $this->once() )
			->method( 'complete_processing' )
			->willReturn( $mock_completed_state );

		// Verify that the context is populated.
		$this->mocked_sut->expects( $this->once() )->method( 'populate_context_from_request' )->with( $mock_request );
		$this->mocked_sut->expects( $this->once() )->method( 'populate_context_from_order' );

		// Verify that FraudPreventionService is called.
		$this->mock_fraud_prevention_service->method( 'verify_token' )->willReturn( true );

		// Make sure that the minimum amount verification is properly called.
		$this->mock_context->expects( $this->once() )->method( 'get_amount' )->willReturn( 100 );
		$this->mock_context->expects( $this->once() )->method( 'get_currency' )->willReturn( 'usd' );

		$this->mocked_sut->expects( $this->once() )->method( 'process_duplicate_order' )->willReturn( null );
		$this->mocked_sut->expects( $this->once() )->method( 'process_duplicate_payment' )->willReturn( null );

		$this->mock_customer_data( $user_id, $order_id, $mock_order, $customer_id );

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

	public function test_start_processing_will_throw_exception_when_minimum_amount_occurs() {
		$mock_request           = $this->createMock( PaymentRequest::class );
		$mock_order             = $this->createMock( WC_Order::class );
		$small_amount_exception = new Amount_Too_Small_Exception( 'Amount too small', 50, 'EUR', 400 );

		$this->mock_payment_request_service
			->expects( $this->once() )
			->method( 'create_intent' )
			->with( $this->mock_context )
			->willThrowException( $small_amount_exception );

		// Make sure that the minimum amount verification is properly called.
		$this->mock_context->expects( $this->once() )->method( 'get_amount' )->willReturn( 1 );
		$this->mock_context->expects( $this->once() )->method( 'get_currency' )->willReturn( 'eur' );

		$this->mocked_sut->expects( $this->once() )->method( 'populate_context_from_request' )->with( $mock_request );
		$this->mocked_sut->expects( $this->once() )->method( 'populate_context_from_order' );

		// Verify that FraudPreventionService is called.
		$this->mock_fraud_prevention_service->method( 'verify_token' )->willReturn( true );

		// Mock get customer.
		$this->mock_customer_data( 1, 1, $mock_order, 'cus_mock' );

		$this->mock_failed_transaction_rate_limiter
			->expects( $this->once() )
			->method( 'is_limited' )
			->willReturn( false );
		$this->mock_minimum_amount_service->expects( $this->once() )
			->method( 'store_amount_from_exception' )
			->with( $small_amount_exception );

		$this->expectExceptionObject( $small_amount_exception );

		// Act.
		$this->mocked_sut->start_processing( $mock_request );
	}

	public function test_start_processing_will_transition_to_error_state_when_api_exception_occurs() {
		$order_id         = 123;
		$user_id          = 456;
		$customer_id      = 'cus_123';
		$mock_request     = $this->createMock( PaymentRequest::class );
		$mock_error_state = $this->createMock( SystemErrorState::class );
		$mock_order       = $this->createMock( WC_Order::class );

		$this->mock_customer_data( $user_id, $order_id, $mock_order, $customer_id );

		$this->mock_payment_request_service
			->expects( $this->once() )
			->method( 'create_intent' )
			->with( $this->mock_context )
			->willThrowException( new Invalid_Request_Parameter_Exception( 'Invalid param', 'invalid_param' ) );

		// Let's mock these services in order to prevent real execution of them.
		$this->mocked_sut->expects( $this->once() )->method( 'populate_context_from_request' )->with( $mock_request );
		$this->mocked_sut->expects( $this->once() )->method( 'populate_context_from_order' );

		// Verify that FraudPreventionService is called.
		$this->mock_fraud_prevention_service->method( 'verify_token' )->willReturn( true );

		// Make sure that the minimum amount verification is properly called.
		$this->mock_context->expects( $this->once() )->method( 'get_amount' )->willReturn( 100 );
		$this->mock_context->expects( $this->once() )->method( 'get_currency' )->willReturn( 'usd' );

		$this->mock_state_factory->expects( $this->once() )
			->method( 'create_state' )
			->with( SystemErrorState::class, $this->mock_context )
			->willReturn( $mock_error_state );
		$result = $this->mocked_sut->start_processing( $mock_request );
		$this->assertSame( $mock_error_state, $result );
	}

	public function test_start_processing_will_bump_rate_limiter_when_specific_error_codes_happen() {
		$mock_request = $this->createMock( PaymentRequest::class );
		$mock_order   = $this->createMock( WC_Order::class );
		$error_code   = 'card_declined';
		$exception    = new API_Exception( 'Card decline', $error_code, 400 );

		$this->mock_payment_request_service
			->expects( $this->once() )
			->method( 'create_intent' )
			->with( $this->mock_context )
			->willThrowException( $exception );

		// Make sure that the minimum amount verification is properly called.
		$this->mock_context->expects( $this->once() )->method( 'get_amount' )->willReturn( 1 );
		$this->mock_context->expects( $this->once() )->method( 'get_currency' )->willReturn( 'eur' );

		$this->mocked_sut->expects( $this->once() )->method( 'populate_context_from_request' )->with( $mock_request );
		$this->mocked_sut->expects( $this->once() )->method( 'populate_context_from_order' );

		// Verify that FraudPreventionService is called.
		$this->mock_fraud_prevention_service->method( 'verify_token' )->willReturn( true );

		// Mock get customer.
		$this->mock_customer_data( 1, 1, $mock_order, 'cus_mock' );

		$this->mock_failed_transaction_rate_limiter
			->expects( $this->once() )
			->method( 'is_limited' )
			->willReturn( false );
		$this->mock_failed_transaction_rate_limiter
			->expects( $this->once() )
			->method( 'should_bump_rate_limiter' )
			->with( $error_code )
			->willReturn( true );

		$this->mock_failed_transaction_rate_limiter
			->expects( $this->once() )
			->method( 'bump' );

		$this->expectExceptionObject( $exception );

		// Act.
		$this->mocked_sut->start_processing( $mock_request );
	}

	public function test_processing_will_transition_to_auth_required_state() {
		$order_id        = 123;
		$user_id         = 456;
		$customer_id     = 'cus_123';
		$mock_order      = $this->createMock( WC_Order::class );
		$mock_request    = $this->createMock( PaymentRequest::class );
		$mock_auth_state = $this->createMock( AuthenticationRequiredState::class );

		$this->mock_customer_data( $user_id, $order_id, $mock_order, $customer_id );

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

		// Verify that FraudPreventionService is called.
		$this->mock_fraud_prevention_service->method( 'verify_token' )->willReturn( true );

		// Make sure that the minimum amount verification is properly called.
		$this->mock_context->expects( $this->once() )->method( 'get_amount' )->willReturn( 100 );
		$this->mock_context->expects( $this->once() )->method( 'get_currency' )->willReturn( 'usd' );

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

	public function test_start_processing_throw_exceptions_due_to_rate_limited() {
		$order_id     = 123;
		$mock_request = $this->createMock( PaymentRequest::class );

		// Arrange mocks.
		$this->mocked_sut->expects( $this->once() )->method( 'populate_context_from_request' )->with( $mock_request );
		$this->mocked_sut->expects( $this->once() )->method( 'populate_context_from_order' );

		// Verify that FraudPreventionService is called.
		$this->mock_fraud_prevention_service->method( 'verify_token' )->willReturn( true );

		$this->mock_failed_transaction_rate_limiter->expects( $this->once() )
			->method( 'is_limited' )
			->willReturn( true );

		$this->mock_context->expects( $this->once() )
			->method( 'get_order_id' )
			->willReturn( $order_id );
		$this->mock_order_service->expects( $this->once() )
			->method( 'add_rate_limiter_note' )
			->with( $order_id );

		$this->expectException( StateTransitionException::class );

		// Act.
		$this->mocked_sut->start_processing( $mock_request );
	}

	public function test_start_processing_throw_exceptions_due_to_invalid_phone() {
		$mock_request = $this->createMock( PaymentRequest::class );

		// Arrange mocks.
		$this->mocked_sut->expects( $this->once() )->method( 'populate_context_from_request' )->with( $mock_request );
		$this->mocked_sut->expects( $this->once() )->method( 'populate_context_from_order' );
		$this->mocked_sut->expects( $this->once() )
			->method( 'process_order_phone_number' )
			->willThrowException( new StateTransitionException() );

		$this->expectException( StateTransitionException::class );

		// Act.
		$this->mocked_sut->start_processing( $mock_request );
	}

	public function test_start_processing_throw_exceptions_due_to_invalid_fraud_prevention_token() {
		$mock_request = $this->createMock( PaymentRequest::class );

		// Arrange mocks.
		$this->mocked_sut->expects( $this->once() )->method( 'populate_context_from_request' )->with( $mock_request );
		$this->mocked_sut->expects( $this->once() )->method( 'populate_context_from_order' );

		// Verify that FraudPreventionService is called.
		$this->mock_fraud_prevention_service->method( 'verify_token' )->willReturn( false );

		$this->expectException( StateTransitionException::class );

		// Act.
		$this->mocked_sut->start_processing( $mock_request );
	}

	public function provider_start_processing_then_detect_duplicates() {
		return [
			'Duplicate order is detected'   => [ true ],
			'Duplicate payment is detected' => [ false ],
		];
	}

	/**
	 * @dataProvider provider_start_processing_then_detect_duplicates
	 *
	 * @param  bool  $is_duplicate_order True if the duplicate order is detected. False for the duplicate payment.
	 */
	public function test_start_processing_then_detect_duplicates( bool $is_duplicate_order ) {
		$mock_request        = $this->createMock( PaymentRequest::class );
		$return_state_class  = $is_duplicate_order ? DuplicateOrderDetectedState::class : CompletedState::class;
		$mock_returned_state = $this->createMock( $return_state_class );

		$this->mocked_sut->expects( $this->once() )
			->method( 'process_duplicate_order' )
			->willReturn( $is_duplicate_order ? $mock_returned_state : null );

		$this->mocked_sut->expects( $this->exactly( $is_duplicate_order ? 0 : 1 ) )
			->method( 'process_duplicate_payment' )
			->willReturn( $is_duplicate_order ? null : $mock_returned_state );

		// Verify that FraudPreventionService is called.
		$this->mock_fraud_prevention_service->method( 'verify_token' )->willReturn( true );

		// Act.
		$result = $this->mocked_sut->start_processing( $mock_request );
		$this->assertInstanceOf( $return_state_class, $result );
	}

	public function test_start_processing_throws_exception_due_to_minimum_amount() {
		$mock_request           = $this->createMock( PaymentRequest::class );
		$small_amount_exception = new Amount_Too_Small_Exception( 'Amount too small', 50, 'EUR', 400 );

		// Arrange mocks.
		$this->mocked_sut->expects( $this->once() )->method( 'populate_context_from_request' )->with( $mock_request );
		$this->mocked_sut->expects( $this->once() )->method( 'populate_context_from_order' );

		// Verify that FraudPreventionService is called.
		$this->mock_fraud_prevention_service->method( 'verify_token' )->willReturn( true );

		$this->mock_failed_transaction_rate_limiter
			->expects( $this->once() )
			->method( 'is_limited' )
			->willReturn( false );

		$this->mock_context->expects( $this->once() )
			->method( 'get_currency' )
			->willReturn( 'EUR' );

		$this->mock_context->expects( $this->once() )
			->method( 'get_amount' )
			->willReturn( 50 );

		$this->mock_minimum_amount_service->expects( $this->once() )
			->method( 'verify_amount' )
			->with( 'EUR', 50 )
			->willThrowException( $small_amount_exception );

		$this->expectExceptionObject( $small_amount_exception );

		// Act.
		$this->mocked_sut->start_processing( $mock_request );
	}

	public function test_populate_context_from_request() {
		$payment_method   = new NewPaymentMethod( 'pm_123' );
		$fingerprint      = 'fingerprint';
		$cvc_confirmation = 'CVCConfirmation';
		$fraud_token      = 'fraud_prevention_token';

		// Setup the mock request.
		$mock_request = $this->createMock( PaymentRequest::class );
		$mock_request->expects( $this->once() )->method( 'get_payment_method' )->willReturn( $payment_method );
		$mock_request->expects( $this->once() )->method( 'get_cvc_confirmation' )->willReturn( $cvc_confirmation );
		$mock_request->expects( $this->once() )->method( 'get_fingerprint' )->willReturn( $fingerprint );
		$mock_request->expects( $this->once() )->method( 'get_fraud_prevention_token' )->willReturn( $fraud_token );

		// Assume that everything from the request would be imported into the context.
		$this->mock_context->expects( $this->once() )->method( 'set_payment_method' )->with( $payment_method );
		$this->mock_context->expects( $this->once() )->method( 'set_cvc_confirmation' )->with( $cvc_confirmation );
		$this->mock_context->expects( $this->once() )->method( 'set_fingerprint' )->with( $fingerprint );
		$this->mock_context->expects( $this->once() )->method( 'set_fraud_prevention_token' )->with( $fraud_token );

		PHPUnit_Utils::call_method( $this->sut, 'populate_context_from_request', [ $mock_request ] );
	}

	public function test_populate_context_from_order() {
		$order_id = 123;

		$metadata    = [ 'sample' => 'true' ];
		$level3_data = [ 'items' => [] ];

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

		PHPUnit_Utils::call_method( $this->sut, 'populate_context_from_order', [] );
	}

	public function test_process_order_phone_number_returns_void() {
		$order_id = 123;

		// Arrange mocks.
		$this->mock_context->expects( $this->once() )
			->method( 'get_order_id' )
			->willReturn( $order_id );

		$this->mock_order_service->expects( $this->once() )
			->method( 'is_valid_phone_number' )
			->with( $order_id )
			->willReturn( true );

		// Act and assert.
		$this->assertNull(
			PHPUnit_Utils::call_method( $this->sut, 'process_order_phone_number', [] )
		);
	}

	public function test_process_order_phone_number_throws_exception() {
		$order_id = 123;

		// Arrange mocks.
		$this->mock_context->expects( $this->once() )
			->method( 'get_order_id' )
			->willReturn( $order_id );

		$this->mock_order_service->expects( $this->once() )
			->method( 'is_valid_phone_number' )
			->with( $order_id )
			->willReturn( false );

		$this->expectException( StateTransitionException::class );

		// Act and assert.
		PHPUnit_Utils::call_method( $this->sut, 'process_order_phone_number', [] );
	}

	public function test_process_duplicate_order_returns_null() {
		$current_order_id = 123;

		// Arrange mocks.
		$this->mock_context->expects( $this->once() )
			->method( 'get_order_id' )
			->willReturn( $current_order_id );

		$this->mock_dpps->expects( $this->once() )
			->method( 'get_previous_paid_duplicate_order_id' )
			->with( $current_order_id )
			->willReturn( null );

		$this->mock_dpps->expects( $this->once() )
			->method( 'update_session_processing_order' )
			->with( $current_order_id );

		$this->mock_dpps->expects( $this->never() )
			->method( 'clean_up_when_detecting_duplicate_order' );

		$this->mock_context->expects( $this->never() )
			->method( 'set_duplicate_order_id' );

		$this->mock_state_factory->expects( $this->never() )
			->method( 'create_state' );

		// Act and assert.
		$result = PHPUnit_Utils::call_method( $this->sut, 'process_duplicate_order', [] );
		$this->assertNull( $result );
	}

	public function test_process_duplicate_order_returns_duplicated_order_state() {
		$current_order_id     = 123;
		$session_order_id     = 456;
		$returned_class_state = DuplicateOrderDetectedState::class;

		// Arrange mocks.
		$this->mock_context->expects( $this->once() )
			->method( 'get_order_id' )
			->willReturn( $current_order_id );

		$this->mock_dpps->expects( $this->once() )
			->method( 'get_previous_paid_duplicate_order_id' )
			->with( $current_order_id )
			->willReturn( $session_order_id );

		$this->mock_dpps->expects( $this->never() )
			->method( 'update_session_processing_order' );

		$this->mock_dpps->expects( $this->once() )
			->method( 'clean_up_when_detecting_duplicate_order' )
			->with( $session_order_id, $current_order_id );

		$this->mock_context->expects( $this->once() )
			->method( 'set_duplicate_order_id' )
			->with( $session_order_id );

		$this->mock_state_factory->expects( $this->once() )
			->method( 'create_state' )
			->with( $returned_class_state, $this->mock_context )
			->willReturn( $this->createMock( $returned_class_state ) );

		// Act and assert.
		$result = PHPUnit_Utils::call_method( $this->sut, 'process_duplicate_order', [] );
		$this->assertInstanceOf( $returned_class_state, $result );
	}

	public function test_process_duplicate_payment_returns_null() {
		$order_id = 123;

		// Arrange mocks.
		$this->mock_context->expects( $this->once() )
			->method( 'get_order_id' )
			->willReturn( $order_id );

		$this->mock_dpps->expects( $this->once() )
			->method( 'get_authorized_payment_intent_attached_to_order' )
			->with( $order_id )
			->willReturn( null );

		$this->mock_context->expects( $this->never() )
			->method( 'set_intent' );

		$this->mock_context->expects( $this->never() )
			->method( 'set_detected_authorized_intent' );

		$this->mock_state_factory->expects( $this->never() )
			->method( 'create_state' );

		// Act and assert.
		$result = PHPUnit_Utils::call_method( $this->sut, 'process_duplicate_payment', [] );
		$this->assertNull( $result );
	}

	public function test_process_duplicate_payment_returns_completed_state() {
		$order_id             = 123;
		$mock_intent          = \WC_Helper_Intention::create_intention();
		$mock_completed_state = $this->createMock( CompletedState::class );
		$mock_processed_state = $this->createMock( ProcessedState::class );

		// Arrange mocks.
		$this->mock_context->expects( $this->once() )
			->method( 'get_order_id' )
			->willReturn( $order_id );

		$this->mock_dpps->expects( $this->once() )
			->method( 'get_authorized_payment_intent_attached_to_order' )
			->with( $order_id )
			->willReturn( $mock_intent );

		$this->mock_context->expects( $this->once() )
			->method( 'set_intent' )
			->with( $mock_intent );

		$this->mock_context->expects( $this->once() )
			->method( 'set_detected_authorized_intent' );

		$this->mock_state_factory->expects( $this->once() )
			->method( 'create_state' )
			->with( ProcessedState::class, $this->mock_context )
			->willReturn( $mock_processed_state );

		$mock_processed_state->expects( $this->once() )
			->method( 'complete_processing' )
			->willReturn( $mock_completed_state );

		// Act and assert.
		$result = PHPUnit_Utils::call_method( $this->sut, 'process_duplicate_payment', [] );
		$this->assertInstanceOf( CompletedState::class, $result );
	}

	/**
	 * Mock customer data.
	 * @param int $user_id                    User id.
	 * @param int $order_id                   Order id.
	 * @param MockObject|WC_Order $mock_order Mock order.
	 * @param string $customer_id             Customer id.
	 *
	 * @return void
	 */
	private function mock_customer_data( int $user_id, int $order_id, $mock_order, string $customer_id ) {

		// Arrange customer management.
		$this->mock_context->expects( $this->once() )
			->method( 'get_user_id' )
			->willReturn( $user_id );
		$this->mock_context->expects( $this->once() )
			->method( 'get_order_id' )
			->willReturn( $order_id );
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
	}
}
