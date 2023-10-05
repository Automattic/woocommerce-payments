<?php
/**
 * Class InitialStateTest
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Tests\Internal\Payment\State;

use Exception;
use WCPAY_UnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;
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
	 * @var PaymentRequestService|MockObject
	 */
	private $mock_payment_request_service;

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
			$this->mock_payment_request_service,
		);
		$this->sut->set_context( $this->mock_context );
	}

	/**
	 * Different `process` scenarios.
	 *
	 * There will be a single parameter, representing an exception, if any.
	 *
	 * @return array
	 */
	public function provider_process() {
		return [
			[ null ],
			[ new Invalid_Request_Parameter_Exception( 'A parameter cannot be set.', 'invalid_parameter' ) ],
			[ new Extend_Request_Exception( 'A parameter cannot be set.', 'cannot_extend' ) ],
		];
	}

	/**
	 * Ensures that the `process` method creates a new completed state.
	 *
	 * @param \Exception|null $exception Exception that would be thrown by intent creation.
	 * @dataProvider provider_process
	 */
	public function test_process( Exception $exception = null ) {
		$order_id         = 123;
		$user_id          = 456;
		$customer_id      = 'cus_123';
		$metadata         = [ 'gateway_type' => 'reengineered' ];
		$level3_data      = [ 'items' => [] ];
		$payment_method   = new NewPaymentMethod( 'pm_123' );
		$fingerprint      = 'fingerprint';
		$cvc_confirmation = 'CVCConfirmation';

		$mock_order  = $this->createMock( WC_Order::class );
		$mock_intent = $this->createMock( WC_Payments_API_Payment_Intention::class );

		// There's a single call to get the order ID.
		$this->mock_context->expects( $this->once() )
			->method( 'get_order_id' )
			->willReturn( $order_id );

		// Setup the mock request.
		$mock_request = $this->createMock( PaymentRequest::class );
		$mock_request->expects( $this->once() )->method( 'get_payment_method' )->willReturn( $payment_method );
		$mock_request->expects( $this->once() )->method( 'get_cvc_confirmation' )->willReturn( $cvc_confirmation );
		$mock_request->expects( $this->once() )->method( 'get_fingerprint' )->willReturn( $fingerprint );

		// Assume that everything from the request would be imported into the context.
		$this->mock_context->expects( $this->once() )->method( 'set_payment_method' )->with( $payment_method );
		$this->mock_context->expects( $this->once() )->method( 'set_cvc_confirmation' )->with( $cvc_confirmation );
		$this->mock_context->expects( $this->once() )->method( 'set_fingerprint' )->with( $fingerprint );

		// Arrange the import of order data to the payment.
		$this->mock_order_service->expects( $this->once() )
			->method( 'populate_context_from_order' )
			->with( $order_id, $this->mock_context );

		// Arrange metadata import.
		$this->mock_order_service->expects( $this->once() )
			->method( 'get_payment_metadata' )
			->with( $order_id )
			->willReturn( $metadata );
		$this->mock_context->expects( $this->once() )
			->method( 'set_metadata' )
			->with( $metadata );

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

		// Arrange intent creation.
		if ( $exception ) {
			$this->mock_payment_request_service->expects( $this->once() )
				->method( 'create_intent' )
				->with( $this->mock_context )
				->willThrowException( $exception );
		} else {
			$this->mock_payment_request_service->expects( $this->once() )
				->method( 'create_intent' )
				->with( $this->mock_context )
				->willReturn( $mock_intent );

			// Assert order update.
			$this->mock_order_service->expects( $this->once() )
				->method( 'update_order_from_successful_intent' )
				->with( $order_id, $mock_intent, $this->mock_context );
		}

		// Arrange the final state.
		$state_class      = $exception ? SystemErrorState::class : CompletedState::class;
		$mock_final_state = $this->createMock( $state_class );
		$this->mock_state_factory->expects( $this->once() )
			->method( 'create_state' )
			->with( $state_class, $this->mock_context )
			->willReturn( $mock_final_state );

		// Act: Process.
		$result = $this->sut->process( $mock_request );

		// Assert: Successful transition.
		$this->assertSame( $mock_final_state, $result );
	}
}
