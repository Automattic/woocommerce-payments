<?php
/**
 * Class WC_REST_Payments_Orders_Controller_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Core\Server\Request\Create_Intention;
use WCPay\Core\Server\Request\Get_Intention;
use WCPay\Exceptions\API_Exception;
use WCPay\Constants\Order_Status;
use WCPay\Constants\Intent_Status;
use WCPay\Constants\Payment_Method;

/**
 * WC_REST_Payments_Orders_Controller unit tests.
 */
class WC_REST_Payments_Orders_Controller_Test extends WCPAY_UnitTestCase {
	/**
	 * Controller under test.
	 *
	 * @var WC_REST_Payments_Orders_Controller
	 */
	private $controller;

	/**
	 * @var WC_Payments_API_Client|MockObject
	 */
	private $mock_api_client;

	/**
	 * @var WC_Payment_Gateway_WCPay|MockObject
	 */
	private $mock_gateway;

	/**
	 * @var WC_Payments_Customer_Service|MockObject
	 */
	private $mock_customer_service;

	/**
	 * @var WC_Payments_Order_Service
	 */
	private $order_service;

	/**
	 * @var WC_Payments_Token_Service|MockObject
	 */
	private $mock_token_service;

	/**
	 * @var WC_Payments_Token_Service
	 */
	private $original_token_service;

	/**
	 * @var string
	 */
	private $mock_intent_id = 'pi_mock';

	/**
	 * @var string
	 */
	private $mock_charge_id = 'ch_mock';

	/**
	 * @var integer
	 */
	private $mock_charge_created = 1653076178;

	public function set_up() {
		parent::set_up();

		// Set the user so that we can pass the authentication.
		wp_set_current_user( 1 );

		$this->mock_api_client       = $this->createMock( WC_Payments_API_Client::class );
		$this->mock_gateway          = $this->createMock( WC_Payment_Gateway_WCPay::class );
		$this->mock_customer_service = $this->createMock( WC_Payments_Customer_Service::class );
		$this->mock_token_service    = $this->createMock( WC_Payments_Token_Service::class );
		$this->order_service         = $this->getMockBuilder( 'WC_Payments_Order_Service' )
			->setConstructorArgs( [ $this->mock_api_client ] )
			->setMethods(
				[
					'attach_intent_info_to_order',
					'get_intent_id_for_order',
					'set_intent_id_for_order',
				]
			)
			->getMock();

		$this->original_token_service = WC_Payments::get_token_service();
		WC_Payments::set_token_service( $this->mock_token_service );
		$this->controller = new WC_REST_Payments_Orders_Controller(
			$this->mock_api_client,
			$this->mock_gateway,
			$this->mock_customer_service,
			$this->order_service,
		);
	}

	public function tear_down() {
		WC_Payments::set_token_service( $this->original_token_service );
		parent::tear_down();
	}

	public function test_capture_terminal_payment_success() {
		$order       = $this->create_mock_order();
		$mock_intent = WC_Helper_Intention::create_intention(
			[
				'status'   => Intent_Status::REQUIRES_CAPTURE,
				'metadata' => [
					'order_id' => $order->get_id(),
				],
			]
		);

		$request = $this->mock_wcpay_request( Get_Intention::class, 1, $this->mock_intent_id );

		$request->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( $mock_intent );

		$this->mock_gateway
			->expects( $this->once() )
			->method( 'capture_charge' )
			->with( $this->isInstanceOf( WC_Order::class ) )
			->willReturn(
				[
					'status' => Intent_Status::SUCCEEDED,
					'id'     => $this->mock_intent_id,
				]
			);

		$this->order_service
			->expects( $this->once() )
			->method( 'attach_intent_info_to_order' )
			->with(
				$this->isInstanceOf( WC_Order::class ),
				$mock_intent,
			);

		$request = new WP_REST_Request( 'POST' );
		$request->set_body_params(
			[
				'order_id'          => $order->get_id(),
				'payment_intent_id' => $this->mock_intent_id,
			]
		);

		$response      = $this->controller->capture_terminal_payment( $request );
		$response_data = $response->get_data();

		$this->assertEquals( 200, $response->status );
		$this->assertEquals(
			[
				'status' => Intent_Status::SUCCEEDED,
				'id'     => $this->mock_intent_id,
			],
			$response_data
		);

		$result_order = wc_get_order( $order->get_id() );
		$this->assertEquals( 'woocommerce_payments', $result_order->get_payment_method() );
		$this->assertEquals( 'WooCommerce In-Person Payments', $result_order->get_payment_method_title() );
		$this->assertEquals( Order_Status::COMPLETED, $result_order->get_status() );
		$url = '/wc/v3/' . ( $this->is_wpcom() ? 'sites/3/' : '' ) . 'payments/readers/receipts/' . $this->mock_intent_id;
		$this->assertStringEndsWith( $url, $result_order->get_meta( 'receipt_url' ) );
	}

	public function test_capture_terminal_payment_succeeded_intent() {
		$order       = $this->create_mock_order();
		$mock_intent = WC_Helper_Intention::create_intention(
			[
				'metadata' => [
					'order_id' => $order->get_id(),
				],
			]
		);

		$request = $this->mock_wcpay_request( Get_Intention::class, 1, $this->mock_intent_id );

		$request->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( $mock_intent );

		$this->order_service
			->expects( $this->once() )
			->method( 'attach_intent_info_to_order' )
			->with(
				$this->isInstanceOf( WC_Order::class ),
				$mock_intent,
			);

		$this->mock_gateway
			->expects( $this->never() )
			->method( 'capture_charge' );

		$request = new WP_REST_Request( 'POST' );
		$request->set_body_params(
			[
				'order_id'          => $order->get_id(),
				'payment_intent_id' => $this->mock_intent_id,
			]
		);

		$response      = $this->controller->capture_terminal_payment( $request );
		$response_data = $response->get_data();

		$this->assertSame( 200, $response->status );
		$this->assertEquals(
			[
				'status' => Intent_Status::SUCCEEDED,
				'id'     => $this->mock_intent_id,
			],
			$response_data
		);

		$result_order = wc_get_order( $order->get_id() );
		$this->assertSame( 'woocommerce_payments', $result_order->get_payment_method() );
		$this->assertSame( 'WooCommerce In-Person Payments', $result_order->get_payment_method_title() );
		$this->assertSame( Order_Status::COMPLETED, $result_order->get_status() );
		$url = '/wc/v3/' . ( $this->is_wpcom() ? 'sites/3/' : '' ) . 'payments/readers/receipts/';
		$this->assertStringEndsWith( $url . $this->mock_intent_id, $result_order->get_meta( 'receipt_url' ) );
	}

	public function test_capture_terminal_payment_completed_order() {
		// This scenario may occur when `process_webhook_payment_intent_succeeded`
		// is triggered before the terminal payment is captured in the backend.
		$order = $this->create_mock_order();
		$order->update_status( Order_Status::COMPLETED );

		$mock_intent = WC_Helper_Intention::create_intention(
			[
				'metadata' => [
					'order_id' => $order->get_id(),
				],
			]
		);

		$request = $this->mock_wcpay_request( Get_Intention::class, 1, 'pm_mock' );

		$request->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( $mock_intent );

		$this->order_service
			->expects( $this->once() )
			->method( 'attach_intent_info_to_order' )
			->with(
				$this->isInstanceOf( WC_Order::class ),
				$mock_intent,
			);

		$this->mock_gateway
			->expects( $this->never() )
			->method( 'capture_charge' );

		$request = new WP_REST_Request( 'POST' );
		$request->set_body_params(
			[
				'order_id'          => $order->get_id(),
				'payment_intent_id' => $this->mock_intent_id,
			]
		);

		$response      = $this->controller->capture_terminal_payment( $request );
		$response_data = $response->get_data();

		$this->assertSame( 200, $response->status );
		$this->assertEquals(
			[
				'status' => Intent_Status::SUCCEEDED,
				'id'     => $this->mock_intent_id,
			],
			$response_data
		);

		$result_order = wc_get_order( $order->get_id() );
		$this->assertSame( 'woocommerce_payments', $result_order->get_payment_method() );
		$this->assertSame( 'WooCommerce In-Person Payments', $result_order->get_payment_method_title() );
		$this->assertSame( Order_Status::COMPLETED, $result_order->get_status() );
		$url = '/wc/v3/' . ( $this->is_wpcom() ? 'sites/3/' : '' ) . 'payments/readers/receipts/';
		$this->assertStringEndsWith( $url . $this->mock_intent_id, $result_order->get_meta( 'receipt_url' ) );
	}

	public function test_capture_terminal_payment_intent_non_capturable() {
		$order = $this->create_mock_order();

		$mock_intent = WC_Helper_Intention::create_intention( [ 'status' => Intent_Status::REQUIRES_PAYMENT_METHOD ] );

		$request = $this->mock_wcpay_request( Get_Intention::class, 1, $mock_intent->get_id() );

		$request->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( $mock_intent );

		$this->mock_gateway
			->expects( $this->never() )
			->method( 'capture_charge' );

		$this->order_service
			->expects( $this->never() )
			->method( 'attach_intent_info_to_order' );

		$request = new WP_REST_Request( 'POST' );
		$request->set_body_params(
			[
				'order_id'          => $order->get_id(),
				'payment_intent_id' => $this->mock_intent_id,
			]
		);

		$response = $this->controller->capture_terminal_payment( $request );

		$this->assertInstanceOf( 'WP_Error', $response );
		$data = $response->get_error_data();
		$this->assertArrayHasKey( 'status', $data );
		$this->assertEquals( 409, $data['status'] );
	}

	public function test_capture_terminal_succeeded_payment_intent_missing_order_id() {
		$order = $this->create_mock_order();

		$mock_intent = WC_Helper_Intention::create_intention( [ 'status' => Intent_Status::SUCCEEDED ] );

		$request = $this->mock_wcpay_request( Get_Intention::class, 1, $this->mock_intent_id );

		$request->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( $mock_intent );

		$this->mock_gateway
			->expects( $this->never() )
			->method( 'capture_charge' );

		$this->order_service
			->expects( $this->never() )
			->method( 'attach_intent_info_to_order' );

		$request = new WP_REST_Request( 'POST' );
		$request->set_body_params(
			[
				'order_id'          => $order->get_id(),
				'payment_intent_id' => $this->mock_intent_id,
			]
		);

		$response = $this->controller->capture_terminal_payment( $request );

		$this->assertInstanceOf( 'WP_Error', $response );
		$data = $response->get_error_data();
		$this->assertArrayHasKey( 'status', $data );
		$this->assertSame( 409, $data['status'] );
		$this->assertFalse( $order->has_status( Order_Status::COMPLETED ) );
	}

	public function test_capture_terminal_payment_refunded_order() {
		$order = $this->create_mock_order();

		wc_create_refund(
			[
				'order_id'   => $order->get_id(),
				'amount'     => 10.0,
				'line_items' => [],
			]
		);

		$request = $this->mock_wcpay_request( Get_Intention::class, 0, $this->mock_intent_id );

		$this->mock_gateway
			->expects( $this->never() )
			->method( 'capture_charge' );

		$this->order_service
			->expects( $this->never() )
			->method( 'attach_intent_info_to_order' );

		$request = new WP_REST_Request( 'POST' );
		$request->set_body_params(
			[
				'order_id'          => $order->get_id(),
				'payment_intent_id' => $this->mock_intent_id,
			]
		);

		$response = $this->controller->capture_terminal_payment( $request );

		$this->assertInstanceOf( 'WP_Error', $response );
		$data = $response->get_error_data();
		$this->assertArrayHasKey( 'status', $data );
		$this->assertEquals( 400, $data['status'] );
	}

	public function test_capture_terminal_payment_error_when_capturing() {
		$order = $this->create_mock_order();

		$mock_intent = WC_Helper_Intention::create_intention(
			[
				'status'   => Intent_Status::REQUIRES_CAPTURE,
				'metadata' => [
					'order_id' => $order->get_id(),
				],
			]
		);

		$get_intent_request = $this->mock_wcpay_request( Get_Intention::class, 1, $this->mock_intent_id );

		$get_intent_request->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( $mock_intent );

		$this->order_service
			->expects( $this->once() )
			->method( 'attach_intent_info_to_order' );

		$this->mock_gateway
			->expects( $this->once() )
			->method( 'capture_charge' )
			->willReturn(
				[
					'status'  => 'failed',
					'message' => 'Test error',
				]
			);

		$request = new WP_REST_Request( 'POST' );
		$request->set_body_params(
			[
				'order_id'          => $order->get_id(),
				'payment_intent_id' => $this->mock_intent_id,
			]
		);

		$response = $this->controller->capture_terminal_payment( $request );

		$this->assertInstanceOf( 'WP_Error', $response );
		$data = $response->get_error_data();
		$this->assertArrayHasKey( 'status', $data );
		$this->assertEquals( 502, $data['status'] );
		$this->assertEquals( 'Payment capture failed to complete with the following message: Test error', $response->get_error_message() );
	}

	public function test_capture_terminal_payment_error_invalid_arguments() {
		$order = $this->create_mock_order();

		$mock_intent = WC_Helper_Intention::create_intention(
			[
				'status'   => Intent_Status::REQUIRES_CAPTURE,
				'metadata' => [
					'order_id' => $order->get_id(),
				],
			]
		);

		$request = $this->mock_wcpay_request( Get_Intention::class, 1, $this->mock_intent_id );

		$request->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( $mock_intent );

		$this->order_service
			->expects( $this->once() )
			->method( 'attach_intent_info_to_order' );

		$this->mock_gateway
			->expects( $this->once() )
			->method( 'capture_charge' )
			->willReturn(
				// See https://stripe.com/docs/error-codes#amount-too-large.
				[
					'status'    => 'failed',
					'message'   => 'Error: The payment could not be captured because the requested capture amount is greater than the authorized amount.',
					'http_code' => 400,
				]
			);

		$request = new WP_REST_Request( 'POST' );
		$request->set_body_params(
			[
				'order_id'          => $order->get_id(),
				'payment_intent_id' => $this->mock_intent_id,
			]
		);

		$response = $this->controller->capture_terminal_payment( $request );

		$this->assertInstanceOf( 'WP_Error', $response );
		$data = $response->get_error_data();
		$this->assertArrayHasKey( 'status', $data );
		$this->assertSame( 400, $data['status'] );
		$this->assertSame( 'wcpay_capture_error', $response->get_error_code() );
		$this->assertEquals(
			'Payment capture failed to complete with the following message: ' .
			'Error: The payment could not be captured because the requested capture amount is greater than the authorized amount.',
			$response->get_error_message()
		);
	}

	public function test_capture_terminal_payment_handles_exceptions() {
		$order = $this->create_mock_order();

		$request = $this->mock_wcpay_request( Get_Intention::class, 1, $this->mock_intent_id );
		$request->expects( $this->once() )
			->method( 'format_response' )
			->willThrowException( new Exception( 'test error' ) );

		$request = new WP_REST_Request( 'POST' );
		$request->set_body_params(
			[
				'order_id'          => $order->get_id(),
				'payment_intent_id' => $this->mock_intent_id,
			]
		);

		$response = $this->controller->capture_terminal_payment( $request );

		$this->assertInstanceOf( 'WP_Error', $response );
		$data = $response->get_error_data();
		$this->assertArrayHasKey( 'status', $data );
		$this->assertEquals( 500, $data['status'] );
	}

	public function test_capture_terminal_payment_not_found() {
		$request = new WP_REST_Request( 'POST' );
		$request->set_body_params(
			[
				'order_id'          => 'not_an_id',
				'payment_intent_id' => $this->mock_intent_id,
			]
		);

		$response = $this->controller->capture_terminal_payment( $request );

		$this->assertInstanceOf( 'WP_Error', $response );
		$data = $response->get_error_data();
		$this->assertArrayHasKey( 'status', $data );
		$this->assertEquals( 404, $data['status'] );
	}

	public function test_capture_authorization_success() {
		// Arrange: Create a mock order.
		$order = $this->create_mock_order();

		// Arrange: Create a mock intent to work with.
		$mock_intent = WC_Helper_Intention::create_intention(
			[
				'status'   => Intent_Status::REQUIRES_CAPTURE,
				'metadata' => [
					'order_id' => $order->get_id(),
				],
			]
		);

		// Arrange: Create a mock request for an intent.
		$request = $this->mock_wcpay_request( Get_Intention::class, 1, $this->mock_intent_id );
		$request->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( $mock_intent );

		// Assert: We assert that capture_charge is called.
		$this->mock_gateway
			->expects( $this->once() )
			->method( 'capture_charge' )
			->with( $this->isInstanceOf( WC_Order::class ) )
			->willReturn(
				[
					'status' => Intent_Status::SUCCEEDED,
					'id'     => $this->mock_intent_id,
				]
			);

		// Arrange: Create the request to capture the authorization.
		$request = new WP_REST_Request( 'POST' );
		$request->set_body_params(
			[
				'order_id'          => $order->get_id(),
				'payment_intent_id' => $this->mock_intent_id,
			]
		);

		// Act: Send the request to capture the authorization.
		$response      = $this->controller->capture_authorization( $request );
		$response_data = $response->get_data();

		// Assert: Confirm we have a 200 response and our expected status info.
		$this->assertSame( 200, $response->status );
		$this->assertSame(
			[
				'status' => Intent_Status::SUCCEEDED,
				'id'     => $this->mock_intent_id,
			],
			$response_data
		);

		/**
		 * This is commented out due to we are not able to accurately get the order status from this process.
		 * This is due to the order status is updated in the capture_charge method, which is mocked. The capture_charge
		 * method calls order_service->update_order_status_from_intent, which updates the status.
		 * $result_order = wc_get_order( $order->get_id() );
		 * $this->assertSame( 'processing', $result_order->get_status() );
		*/
	}

	public function test_capture_authorization_succeeded_intent_throws_error() {
		$order = $this->create_mock_order();
		$order->set_payment_method( WC_Payment_Gateway_WCPay::GATEWAY_ID );
		$order->set_payment_method_title( 'WooPayments' );
		$order->save();

		$mock_intent = WC_Helper_Intention::create_intention(
			[
				'metadata' => [
					'order_id' => $order->get_id(),
				],
			]
		);

		$request = $this->mock_wcpay_request( Get_Intention::class, 1, $this->mock_intent_id );
		$request->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( $mock_intent );

		$this->mock_gateway
			->expects( $this->once() )
			->method( 'capture_charge' )
			->with( $this->isInstanceOf( WC_Order::class ) )
			->willReturn(
				[
					'status'    => 'failed',
					'message'   => 'Test error',
					'http_code' => 502,
				]
			);

		$request = new WP_REST_Request( 'POST' );
		$request->set_body_params(
			[
				'order_id'          => $order->get_id(),
				'payment_intent_id' => $this->mock_intent_id,
			]
		);

		$response = $this->controller->capture_authorization( $request );

		$this->assertInstanceOf( 'WP_Error', $response );
		$data = $response->get_error_data();
		$this->assertArrayHasKey( 'status', $data );
		$this->assertSame( 502, $data['status'] );
	}

	public function test_capture_authorization_intent_non_capturable() {
		$order = $this->create_mock_order();
		$order->set_payment_method( WC_Payment_Gateway_WCPay::GATEWAY_ID );
		$order->set_payment_method_title( 'WooPayments' );
		$order->save();

		$mock_intent = WC_Helper_Intention::create_intention( [ 'status' => Intent_Status::REQUIRES_PAYMENT_METHOD ] );

		$request = $this->mock_wcpay_request( Get_Intention::class, 1, $this->mock_intent_id );
		$request->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( $mock_intent );

		$this->mock_gateway
			->expects( $this->never() )
			->method( 'capture_charge' );

		$this->order_service
			->expects( $this->never() )
			->method( 'attach_intent_info_to_order' );

		$request = new WP_REST_Request( 'POST' );
		$request->set_body_params(
			[
				'order_id'          => $order->get_id(),
				'payment_intent_id' => $this->mock_intent_id,
			]
		);

		$response = $this->controller->capture_authorization( $request );

		$this->assertInstanceOf( 'WP_Error', $response );
		$data = $response->get_error_data();
		$this->assertArrayHasKey( 'status', $data );
		$this->assertSame( 409, $data['status'] );
	}

	public function test_capture_authorization_with_succeeded_payment_intent_and_missing_order_id_throws_error() {
		$order = $this->create_mock_order();

		$mock_intent = WC_Helper_Intention::create_intention( [ 'status' => Intent_Status::SUCCEEDED ] );

		$request = $this->mock_wcpay_request( Get_Intention::class, 1, $this->mock_intent_id );
		$request->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( $mock_intent );

		$this->mock_gateway
			->expects( $this->never() )
			->method( 'capture_charge' );

		$this->order_service
			->expects( $this->never() )
			->method( 'attach_intent_info_to_order' );

		$request = new WP_REST_Request( 'POST' );
		$request->set_body_params(
			[
				'order_id'          => $order->get_id(),
				'payment_intent_id' => $this->mock_intent_id,
			]
		);

		$response = $this->controller->capture_authorization( $request );

		$this->assertInstanceOf( 'WP_Error', $response );
		$data = $response->get_error_data();
		$this->assertArrayHasKey( 'status', $data );
		$this->assertSame( 409, $data['status'] );
		$this->assertFalse( $order->has_status( Order_Status::COMPLETED ) );
	}

	public function test_capture_authorization_refunded_order() {
		$order       = $this->create_mock_order();
		$mock_intent = WC_Helper_Intention::create_intention( [ 'status' => Intent_Status::SUCCEEDED ] );

		wc_create_refund(
			[
				'order_id'   => $order->get_id(),
				'amount'     => 10.0,
				'line_items' => [],
			]
		);

		$request = $this->mock_wcpay_request( Get_Intention::class, 0, $this->mock_intent_id );

		$this->mock_gateway
			->expects( $this->never() )
			->method( 'capture_charge' );

		$request = new WP_REST_Request( 'POST' );
		$request->set_body_params(
			[
				'order_id'          => $order->get_id(),
				'payment_intent_id' => $this->mock_intent_id,
			]
		);

		$response = $this->controller->capture_authorization( $request );

		$this->assertInstanceOf( 'WP_Error', $response );
		$data = $response->get_error_data();
		$this->assertArrayHasKey( 'status', $data );
		$this->assertSame( 400, $data['status'] );
	}

	public function test_capture_authorization_error_when_capturing() {
		$order = $this->create_mock_order();

		$mock_intent = WC_Helper_Intention::create_intention(
			[
				'status'   => Intent_Status::REQUIRES_CAPTURE,
				'metadata' => [
					'order_id' => $order->get_id(),
				],
			]
		);

		$request = $this->mock_wcpay_request( Get_Intention::class, 1, $this->mock_intent_id );
		$request->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( $mock_intent );

		$this->mock_gateway
			->expects( $this->once() )
			->method( 'capture_charge' )
			->willReturn(
				[
					'status'  => 'failed',
					'message' => 'Test error',
				]
			);

		$request = new WP_REST_Request( 'POST' );
		$request->set_body_params(
			[
				'order_id'          => $order->get_id(),
				'payment_intent_id' => $this->mock_intent_id,
			]
		);

		$response = $this->controller->capture_authorization( $request );

		$this->assertInstanceOf( 'WP_Error', $response );
		$data = $response->get_error_data();
		$this->assertArrayHasKey( 'status', $data );
		$this->assertSame( 502, $data['status'] );
		$this->assertSame( 'Payment capture failed to complete with the following message: Test error', $response->get_error_message() );
	}

	public function test_capture_authorization_error_invalid_arguments() {
		$order = $this->create_mock_order();

		$mock_intent = WC_Helper_Intention::create_intention(
			[
				'status'   => Intent_Status::REQUIRES_CAPTURE,
				'metadata' => [
					'order_id' => $order->get_id(),
				],
			]
		);

		$request = $this->mock_wcpay_request( Get_Intention::class, 1, $this->mock_intent_id );
		$request->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( $mock_intent );

		$this->mock_gateway
			->expects( $this->once() )
			->method( 'capture_charge' )
			->willReturn(
				// See https://stripe.com/docs/error-codes#amount-too-large.
				[
					'status'    => 'failed',
					'message'   => 'Error: The payment could not be captured because the requested capture amount is greater than the authorized amount.',
					'http_code' => 400,
				]
			);

		$request = new WP_REST_Request( 'POST' );
		$request->set_body_params(
			[
				'order_id'          => $order->get_id(),
				'payment_intent_id' => $this->mock_intent_id,
			]
		);

		$response = $this->controller->capture_authorization( $request );

		$this->assertInstanceOf( 'WP_Error', $response );
		$data = $response->get_error_data();
		$this->assertArrayHasKey( 'status', $data );
		$this->assertSame( 400, $data['status'] );
		$this->assertSame( 'wcpay_capture_error', $response->get_error_code() );
		$this->assertSame(
			'Payment capture failed to complete with the following message: ' .
			'Error: The payment could not be captured because the requested capture amount is greater than the authorized amount.',
			$response->get_error_message()
		);
	}

	public function test_capture_authorization_handles_exceptions() {
		$order = $this->create_mock_order();

		$request = $this->mock_wcpay_request( Get_Intention::class, 1, $this->mock_intent_id );
		$request->expects( $this->once() )
			->method( 'format_response' )
			->willThrowException( new Exception( 'test error' ) );

		$request = new WP_REST_Request( 'POST' );
		$request->set_body_params(
			[
				'order_id'          => $order->get_id(),
				'payment_intent_id' => $this->mock_intent_id,
			]
		);

		$response = $this->controller->capture_authorization( $request );

		$this->assertInstanceOf( 'WP_Error', $response );
		$data = $response->get_error_data();
		$this->assertArrayHasKey( 'status', $data );
		$this->assertSame( 500, $data['status'] );
	}

	public function test_capture_authorization_not_found() {
		$request = new WP_REST_Request( 'POST' );
		$request->set_body_params(
			[
				'order_id'          => 'not_an_id',
				'payment_intent_id' => $this->mock_intent_id,
			]
		);

		$response = $this->controller->capture_authorization( $request );

		$this->assertInstanceOf( 'WP_Error', $response );
		$data = $response->get_error_data();
		$this->assertArrayHasKey( 'status', $data );
		$this->assertSame( 404, $data['status'] );
	}

	public function test_create_customer_invalid_order_id() {
		$request = new WP_REST_Request( 'POST' );
		$request->set_body_params(
			[
				'order_id' => 'not_an_id',
			]
		);

		$response = $this->controller->create_customer( $request );

		$this->assertInstanceOf( 'WP_Error', $response );
		$data = $response->get_error_data();
		$this->assertArrayHasKey( 'status', $data );
		$this->assertEquals( 404, $data['status'] );
	}

	public function test_create_customer_from_order_guest_without_customer_id() {
		$order         = WC_Helper_Order::create_order( 0 );
		$customer_data = WC_Payments_Customer_Service::map_customer_data( $order );

		$this->mock_customer_service
			->expects( $this->once() )
			->method( 'create_customer_for_user' )
			->with(
				$this->callback(
					function ( $argument ) {
						return ( $argument instanceof WP_User ) && ! $argument->ID;
					}
				),
				$this->equalTo( $customer_data )
			)
			->willReturn( 'cus_new' );

		$this->mock_customer_service
			->expects( $this->never() )
			->method( 'get_customer_id_by_user_id' );

		$this->mock_customer_service
			->expects( $this->once() )
			->method( 'update_customer_for_user' )
			->with(
				$this->equalTo( 'cus_new' ),
				$this->callback(
					function ( $argument ) {
						return ( $argument instanceof WP_User ) && ! $argument->ID;
					}
				),
				$this->equalTo( $customer_data )
			)
			->willReturn( 'cus_new' );

		$request = new WP_REST_Request( 'POST' );
		$request->set_body_params(
			[
				'order_id' => $order->get_id(),
			]
		);

		$response      = $this->controller->create_customer( $request );
		$response_data = $response->get_data();
		$this->assertSame( 200, $response->status );
		$this->assertSame(
			[
				'id' => 'cus_new',
			],
			$response_data
		);

		$result_order = wc_get_order( $order->get_id() );
		$this->assertSame( 'cus_new', $result_order->get_meta( '_stripe_customer_id' ) );

		$response      = $this->controller->create_customer( $request );
		$response_data = $response->get_data();
		$this->assertSame( 200, $response->status );
		$this->assertSame(
			[
				'id' => 'cus_new',
			],
			$response_data
		);
	}

	public function test_create_customer_from_order_guest_with_customer_id() {
		$order         = WC_Helper_Order::create_order( 0 );
		$customer_data = WC_Payments_Customer_Service::map_customer_data( $order );

		$order->update_meta_data( '_stripe_customer_id', 'cus_guest' );
		$order->save();

		$this->mock_customer_service
			->expects( $this->never() )
			->method( 'create_customer_for_user' );

		$this->mock_customer_service
			->expects( $this->never() )
			->method( 'get_customer_id_by_user_id' );

		$this->mock_customer_service
			->expects( $this->once() )
			->method( 'update_customer_for_user' )
			->with(
				$this->equalTo( 'cus_guest' ),
				$this->callback(
					function ( $argument ) {
						return ( $argument instanceof WP_User ) && ! $argument->ID;
					}
				),
				$this->equalTo( $customer_data )
			)
			->willReturn( 'cus_guest' );

		$request = new WP_REST_Request( 'POST' );
		$request->set_body_params(
			[
				'order_id' => $order->get_id(),
			]
		);

		$response      = $this->controller->create_customer( $request );
		$response_data = $response->get_data();
		$this->assertSame( 200, $response->status );
		$this->assertSame(
			[
				'id' => 'cus_guest',
			],
			$response_data
		);

		$result_order = wc_get_order( $order->get_id() );
		$this->assertSame( 'cus_guest', $result_order->get_meta( '_stripe_customer_id' ) );
	}

	public function test_create_customer_from_order_non_guest_with_customer_id() {
		$order         = WC_Helper_Order::create_order();
		$customer_data = WC_Payments_Customer_Service::map_customer_data( $order );

		$this->mock_customer_service
			->expects( $this->once() )
			->method( 'get_customer_id_by_user_id' )
			->with( $order->get_user()->ID )
			->willReturn( 'cus_exist' );

		$this->mock_customer_service
			->expects( $this->never() )
			->method( 'create_customer_for_user' );

		$this->mock_customer_service
			->expects( $this->once() )
			->method( 'update_customer_for_user' )
			->with( 'cus_exist', $order->get_user(), $customer_data )
			->willReturn( 'cus_exist' );

		$request = new WP_REST_Request( 'POST' );
		$request->set_body_params(
			[
				'order_id' => $order->get_id(),
			]
		);

		$response      = $this->controller->create_customer( $request );
		$response_data = $response->get_data();
		$this->assertSame( 200, $response->status );
		$this->assertSame(
			[
				'id' => 'cus_exist',
			],
			$response_data
		);

		$result_order = wc_get_order( $order->get_id() );
		$this->assertSame( 'cus_exist', $result_order->get_meta( '_stripe_customer_id' ) );
	}

	public function test_create_customer_from_order_with_invalid_status() {
		$order = WC_Helper_Order::create_order();
		$order->set_status( Order_Status::COMPLETED );
		$order->save();

		$this->mock_customer_service
			->expects( $this->never() )
			->method( 'get_customer_id_by_user_id' );

		$this->mock_customer_service
			->expects( $this->never() )
			->method( 'create_customer_for_user' );

		$this->mock_customer_service
			->expects( $this->never() )
			->method( 'update_customer_for_user' );

		$request = new WP_REST_Request( 'POST' );
		$request->set_body_params(
			[
				'order_id' => $order->get_id(),
			]
		);

		$response = $this->controller->create_customer( $request );
		$this->assertInstanceOf( WP_Error::class, $response );

		$data = $response->get_error_data();
		$this->assertArrayHasKey( 'status', $data );
		$this->assertEquals( 400, $data['status'] );
	}

	public function test_create_customer_from_order_non_guest_with_customer_id_from_order_meta() {
		$order         = WC_Helper_Order::create_order();
		$customer_data = WC_Payments_Customer_Service::map_customer_data( $order );

		$order->update_meta_data( '_stripe_customer_id', 'cus_exist' );
		$order->save();

		$this->mock_customer_service
			->expects( $this->never() )
			->method( 'get_customer_id_by_user_id' );

		$this->mock_customer_service
			->expects( $this->never() )
			->method( 'create_customer_for_user' );

		$this->mock_customer_service
			->expects( $this->once() )
			->method( 'update_customer_for_user' )
			->with( 'cus_exist', $order->get_user(), $customer_data )
			->willReturn( 'cus_exist' );

		$request = new WP_REST_Request( 'POST' );
		$request->set_body_params(
			[
				'order_id' => $order->get_id(),
			]
		);

		$response      = $this->controller->create_customer( $request );
		$response_data = $response->get_data();
		$this->assertSame( 200, $response->status );
		$this->assertSame(
			[
				'id' => 'cus_exist',
			],
			$response_data
		);

		$result_order = wc_get_order( $order->get_id() );
		$this->assertSame( 'cus_exist', $result_order->get_meta( '_stripe_customer_id' ) );
	}

	public function test_create_customer_from_order_non_guest_without_customer_id() {
		$order         = WC_Helper_Order::create_order();
		$customer_data = WC_Payments_Customer_Service::map_customer_data( $order );

		$this->mock_customer_service
			->expects( $this->once() )
			->method( 'get_customer_id_by_user_id' )
			->with( $order->get_user()->ID )
			->willReturn( null );

		$this->mock_customer_service
			->expects( $this->never() )
			->method( 'update_customer_for_user' );

		$this->mock_customer_service
			->expects( $this->once() )
			->method( 'create_customer_for_user' )
			->with( $order->get_user(), $customer_data )
			->willReturn( 'cus_new' );

		$request = new WP_REST_Request( 'POST' );
		$request->set_body_params(
			[
				'order_id' => $order->get_id(),
			]
		);

		$response      = $this->controller->create_customer( $request );
		$response_data = $response->get_data();
		$this->assertSame( 200, $response->status );
		$this->assertSame(
			[
				'id' => 'cus_new',
			],
			$response_data
		);

		$result_order = wc_get_order( $order->get_id() );
		$this->assertSame( 'cus_new', $result_order->get_meta( '_stripe_customer_id' ) );
	}

	public function test_create_terminal_intent_success() {
		$order  = $this->create_mock_order();
		$intent = WC_Helper_Intention::create_intention(
			[
				'id'       => 'pi_abcxyz',
				'amount'   => 5000,
				'currency' => 'usd',
				'status'   => Intent_Status::REQUIRES_PAYMENT_METHOD,
			]
		);

		$this->order_service
			->expects( $this->once() )
			->method( 'get_intent_id_for_order' )
			->willReturn( '' );

		$this->order_service
			->expects( $this->once() )
			->method( 'set_intent_id_for_order' )
			->with(
				$this->callback(
					function ( $received_order ) use ( $order ) {
						// Order data is updated in the controller, so we can't pass the $order.
						// Instead, we will compare is the order id same.
						$this->assertInstanceOf( WC_Order::class, $received_order );
						$this->assertEquals( $order->get_id(), $received_order->get_id() );
						return true;
					}
				),
				$intent->get_id()
			);

		$request = $this->mock_wcpay_request( Create_Intention::class );

		$request->expects( $this->once() )
			->method( 'set_amount' )
			->with( $intent->get_amount() );

		$request->expects( $this->once() )
			->method( 'set_currency_code' )
			->with( strtolower( $intent->get_currency() ) );

		$request->expects( $this->once() )
			->method( 'set_capture_method' )
			->with( true );

		$request->expects( $this->once() )
			->method( 'set_metadata' )
			->with(
				$this->callback(
					function ( $metadata ) {
						return isset( $metadata['order_number'] );
					}
				)
			);

		$request->expects( $this->once() )
			->method( 'set_payment_method_types' )
			->with(
				$this->callback(
					function ( $argument ) {
						return is_array( $argument ) && ! empty( $argument );
					}
				)
			);

		$request->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( $intent );
		$request = new WP_REST_Request( 'POST' );
		$request->set_body_params(
			[
				'order_id' => $order->get_id(),
			]
		);

		$response      = $this->controller->create_terminal_intent( $request );
		$response_data = $response->get_data();

		$this->assertSame( 200, $response->status );
		$this->assertSame(
			[
				'id' => $intent->get_id(),
			],
			$response_data
		);
	}

	public function test_create_terminal_intent_will_return_error_response_if_server_request_fails() {
		$order = $this->create_mock_order();

		$this->order_service
			->expects( $this->once() )
			->method( 'get_intent_id_for_order' )
			->willReturn( '' );

		$this->order_service
			->expects( $this->never() )
			->method( 'set_intent_id_for_order' );

		$request = $this->mock_wcpay_request( Create_Intention::class );

		$request->expects( $this->once() )
			->method( 'format_response' )
			->will(
				$this->throwException(
					new API_Exception(
						'Something went wrong!',
						'resource_missing',
						400
					)
				)
			);
		$request = new WP_REST_Request( 'POST' );
		$request->set_body_params(
			[
				'order_id' => $order->get_id(),
			]
		);

		$response = $this->controller->create_terminal_intent( $request );

		$this->assertSame( 500, $response->get_error_data()['status'] );
	}

	public function test_create_terminal_intent_order_not_found() {
		$request = new WP_REST_Request( 'POST' );
		$request->set_body_params(
			[
				'order_id' => 'not_an_id',
			]
		);

		$this->order_service
			->expects( $this->never() )
			->method( 'get_intent_id_for_order' );

		$response = $this->controller->create_terminal_intent( $request );

		$this->assertInstanceOf( 'WP_Error', $response );
		$data = $response->get_error_data();
		$this->assertArrayHasKey( 'status', $data );
		$this->assertSame( 404, $data['status'] );
	}

	public function test_create_terminal_intent_error_when_intent_id_exists() {
		$order = $this->create_mock_order();

		$request = new WP_REST_Request( 'POST' );
		$request->set_body_params(
			[
				'order_id' => $order->get_id(),
			]
		);

		$this->order_service
			->expects( $this->once() )
			->method( 'get_intent_id_for_order' )
			->willReturn( 'pi_123' );

		$this->order_service
			->expects( $this->never() )
			->method( 'set_intent_id_for_order' );

		$response = $this->controller->create_terminal_intent( $request );

		$this->assertInstanceOf( 'WP_Error', $response );
		$data = $response->get_error_data();
		$this->assertArrayHasKey( 'status', $data );
		$this->assertSame( 400, $data['status'] );
	}

	public function test_create_terminal_intent_invalid_payment_method_format() {
		$order = $this->create_mock_order();

		$request = new WP_REST_Request( 'POST' );
		$request->set_body_params(
			[
				'order_id' => $order->get_id(),
			]
		);
		$request->set_param( 'payment_methods', 'not_an_array' );

		$this->order_service
			->expects( $this->once() )
			->method( 'get_intent_id_for_order' )
			->willReturn( '' );

		$this->order_service
			->expects( $this->never() )
			->method( 'set_intent_id_for_order' );

		$response = $this->controller->create_terminal_intent( $request );

		$this->assertInstanceOf( 'WP_Error', $response );
		$data = $response->get_error_data();
		$this->assertArrayHasKey( 'status', $data );
		$this->assertSame( 500, $data['status'] );
	}

	public function test_create_terminal_intent_invalid_payment_method() {
		$order = $this->create_mock_order();

		$request = new WP_REST_Request( 'POST' );
		$request->set_body_params(
			[
				'order_id' => $order->get_id(),
			]
		);
		$request->set_param( 'payment_methods', [ 'invalid_payment_method' ] );
		$response = $this->controller->create_terminal_intent( $request );

		$this->order_service
			->expects( $this->never() )
			->method( 'get_intent_id_for_order' );

		$this->assertInstanceOf( 'WP_Error', $response );
		$data = $response->get_error_data();
		$this->assertArrayHasKey( 'status', $data );
		$this->assertSame( 500, $data['status'] );
	}

	public function test_create_terminal_intent_invalid_capture_method() {
		$order = $this->create_mock_order();

		$request = new WP_REST_Request( 'POST' );
		$request->set_body_params(
			[
				'order_id' => $order->get_id(),
			]
		);
		$request->set_param( 'capture_method', 'invalid_payment_method' );
		$this->order_service
			->expects( $this->once() )
			->method( 'get_intent_id_for_order' )
			->willReturn( '' );

		$this->order_service
			->expects( $this->never() )
			->method( 'set_intent_id_for_order' );

		$response = $this->controller->create_terminal_intent( $request );

		$this->assertInstanceOf( 'WP_Error', $response );
		$data = $response->get_error_data();
		$this->assertArrayHasKey( 'status', $data );
		$this->assertSame( 500, $data['status'] );
	}

	public function test_cancel_authorization_success() {
		$order   = $this->create_mock_order();
		$request = new WP_REST_Request( 'POST' );
		$request->set_url_params(
			[
				'order_id' => $order->get_id(),
			]
		);
		$request->set_body_params(
			[
				'payment_intent_id' => $this->mock_intent_id,
			]
		);

		$this->mock_gateway
			->expects( $this->once() )
			->method( 'cancel_authorization' )
			->with( $this->isInstanceOf( WC_Order::class ) )
			->willReturn(
				[
					'status' => Intent_Status::CANCELED,
					'id'     => $this->mock_intent_id,
				]
			);

		$mock_intent   = WC_Helper_Intention::create_intention(
			[
				'id'       => $this->mock_intent_id,
				'status'   => Intent_Status::REQUIRES_CAPTURE,
				'metadata' => [
					'order_id' => $order->get_id(),
				],
			]
		);
		$wcpay_request = $this->mock_wcpay_request( Get_Intention::class, 1, $this->mock_intent_id );

		$wcpay_request->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( $mock_intent );
		$response = $this->controller->cancel_authorization( $request );

		$response_data = $response->get_data();

		$this->assertEquals( 200, $response->status );
		$this->assertEquals(
			[
				'status' => Intent_Status::CANCELED,
				'id'     => $this->mock_intent_id,
			],
			$response_data
		);
	}
	public function test_cancel_authorization_will_fail_if_order_is_incorrect() {
		$order   = $this->create_mock_order();
		$request = new WP_REST_Request( 'POST' );
		$request->set_url_params(
			[
				'order_id' => $order->get_id() + 1,
			]
		);
		$request->set_body_params(
			[
				'payment_intent_id' => $this->mock_intent_id,
			]
		);

		$this->mock_gateway
			->expects( $this->never() )
			->method( 'cancel_authorization' );

		$this->mock_wcpay_request( Get_Intention::class, 0 );

		$response = $this->controller->cancel_authorization( $request );

		$this->assertInstanceOf( 'WP_Error', $response );
		$data = $response->get_error_data();
		$this->assertArrayHasKey( 'status', $data );
		$this->assertSame( 404, $data['status'] );
	}
	public function test_cancel_authorization_will_fail_if_order_is_refunded() {
		$order = $this->create_mock_order();
		wc_create_refund(
			[
				'order_id'   => $order->get_id(),
				'amount'     => 10.0,
				'line_items' => [],
			]
		);
		$request = new WP_REST_Request( 'POST' );
		$request->set_url_params(
			[
				'order_id' => $order->get_id(),
			]
		);
		$request->set_body_params(
			[
				'payment_intent_id' => $this->mock_intent_id,
			]
		);

		$this->mock_gateway
			->expects( $this->never() )
			->method( 'cancel_authorization' );

		$this->mock_wcpay_request( Get_Intention::class, 0 );

		$response = $this->controller->cancel_authorization( $request );

		$this->assertInstanceOf( 'WP_Error', $response );
		$data = $response->get_error_data();
		$this->assertArrayHasKey( 'status', $data );
		$this->assertSame( 400, $data['status'] );
	}
	public function test_cancel_authorization_will_fail_if_order_does_not_match_with_payment_intent() {
		$order   = $this->create_mock_order();
		$request = new WP_REST_Request( 'POST' );
		$request->set_url_params(
			[
				'order_id' => $order->get_id(),
			]
		);
		$request->set_body_params(
			[
				'payment_intent_id' => $this->mock_intent_id,
			]
		);

		$mock_intent   = WC_Helper_Intention::create_intention(
			[
				'id'       => $this->mock_intent_id,
				'status'   => Intent_Status::REQUIRES_CAPTURE,
				'metadata' => [
					'order_id' => $order->get_id() + 1,
				],
			]
		);
		$wcpay_request = $this->mock_wcpay_request( Get_Intention::class, 1, $this->mock_intent_id );

		$wcpay_request->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( $mock_intent );

		$this->mock_gateway
			->expects( $this->never() )
			->method( 'cancel_authorization' );

		$response = $this->controller->cancel_authorization( $request );

		$this->assertInstanceOf( 'WP_Error', $response );
		$data = $response->get_error_data();
		$this->assertArrayHasKey( 'status', $data );
		$this->assertSame( 409, $data['status'] );
	}

	public function test_cancel_authorization_will_fail_if_gateway_fails_to_cancel_authorization() {
		$order   = $this->create_mock_order();
		$request = new WP_REST_Request( 'POST' );
		$request->set_url_params(
			[
				'order_id' => $order->get_id(),
			]
		);
		$request->set_body_params(
			[
				'payment_intent_id' => $this->mock_intent_id,
			]
		);

		$mock_intent   = WC_Helper_Intention::create_intention(
			[
				'id'       => $this->mock_intent_id,
				'status'   => Intent_Status::REQUIRES_CAPTURE,
				'metadata' => [
					'order_id' => $order->get_id(),
				],
			]
		);
		$wcpay_request = $this->mock_wcpay_request( Get_Intention::class, 1, $this->mock_intent_id );

		$wcpay_request->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( $mock_intent );

		$this->mock_gateway
			->method( 'cancel_authorization' )
			->with( $this->isInstanceOf( WC_Order::class ) )
			->willReturn(
				[
					'status' => Intent_Status::REQUIRES_CAPTURE,
					'id'     => $this->mock_intent_id,
				]
			);

		$response = $this->controller->cancel_authorization( $request );

		$this->assertInstanceOf( 'WP_Error', $response );
		$data = $response->get_error_data();
		$this->assertArrayHasKey( 'status', $data );
		$this->assertSame( 502, $data['status'] );
	}

	private function create_mock_order() {
		$charge = $this->create_charge_object();

		$order = WC_Helper_Order::create_order();
		$order->set_transaction_id( $this->mock_intent_id );
		$order->update_meta_data( '_intent_id', $this->mock_intent_id );
		$order->update_meta_data( '_charge_id', $charge->get_id() );
		$order->update_meta_data( '_intention_status', Intent_Status::REQUIRES_CAPTURE );
		$order->update_status( Order_Status::ON_HOLD );
		return $order;
	}

	/**
	 * @dataProvider provider_get_terminal_intent_payment_method
	 */
	public function test_get_terminal_intent_payment_method( $payment_methods, $expected ) {
		$request = new WP_REST_Request( 'POST' );
		$request->set_param( 'payment_methods', $payment_methods );

		$this->assertSame( $this->controller->get_terminal_intent_payment_method( $request ), $expected );
	}

	public function provider_get_terminal_intent_payment_method(): array {
		return [
			[ null, [ Payment_Method::CARD_PRESENT ] ],
			[ [ Payment_Method::CARD_PRESENT, Payment_Method::INTERAC_PRESENT ], [ Payment_Method::CARD_PRESENT, Payment_Method::INTERAC_PRESENT ] ],
		];
	}

	public function test_get_terminal_intent_payment_method_not_an_array() {
		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Invalid param \'payment_methods\'!' );
		$request = new WP_REST_Request( 'POST' );
		$request->set_param( 'payment_methods', 'not_an_array' );

		$this->controller->get_terminal_intent_payment_method( $request );
	}

	public function test_get_terminal_intent_payment_method_invalid_value() {
		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'One or more payment methods are not supported!' );
		$request = new WP_REST_Request( 'POST' );
		$request->set_param( 'payment_methods', [ 'invalid_val' ] );

		$this->controller->get_terminal_intent_payment_method( $request );
	}

	/**
	 * @dataProvider provider_capture_terminal_payment_allows_charging_order_with_intent_meta
	 */
	public function test_capture_terminal_payment_allows_charging_order_with_intent_meta( string $order_meta_intent_status ) {
		$order = $this->create_mock_order();
		$order->update_meta_data( WC_Payments_Order_Service::INTENT_ID_META_KEY, $this->mock_intent_id );
		$order->update_meta_data( WC_Payments_Order_Service::INTENTION_STATUS_META_KEY, $order_meta_intent_status );
		$order->save_meta_data();

		$mock_intent = WC_Helper_Intention::create_intention(
			[
				'status'   => Intent_Status::REQUIRES_CAPTURE,
				'metadata' => [
					'order_id' => $order->get_id(),
				],
			]
		);

		$request = $this->mock_wcpay_request( Get_Intention::class, 1, $this->mock_intent_id );

		$request->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( $mock_intent );

		$this->mock_gateway
			->expects( $this->once() )
			->method( 'capture_charge' )
			->with( $this->isInstanceOf( WC_Order::class ) )
			->willReturn(
				[
					'status' => Intent_Status::SUCCEEDED,
					'id'     => $this->mock_intent_id,
				]
			);

		$this->order_service
			->expects( $this->once() )
			->method( 'attach_intent_info_to_order' )
			->with( $this->anything() );

		$request = new WP_REST_Request( 'POST' );
		$request->set_body_params(
			[
				'order_id'          => $order->get_id(),
				'payment_intent_id' => $this->mock_intent_id,
			]
		);

		$response      = $this->controller->capture_terminal_payment( $request );
		$response_data = $response->get_data();

		$this->assertSame( 200, $response->status );
		$this->assertSame(
			[
				'status' => Intent_Status::SUCCEEDED,
				'id'     => $this->mock_intent_id,
			],
			$response_data
		);
	}

	public function provider_capture_terminal_payment_allows_charging_order_with_intent_meta(): array {
		return [
			[ '' ],
			[ Intent_Status::REQUIRES_CAPTURE ],
		];
	}

	/**
	 * @dataProvider provider_capture_terminal_payment_prevents_double_charging_order_with_intent_meta
	 */
	public function test_capture_terminal_payment_prevents_double_charging_order_with_intent_meta( string $order_meta_intent_id, string $order_meta_intent_status, string $request_intent_id ) {
		$order = $this->create_mock_order();
		$order->update_meta_data( WC_Payments_Order_Service::INTENT_ID_META_KEY, $order_meta_intent_id );
		$order->update_meta_data( WC_Payments_Order_Service::INTENTION_STATUS_META_KEY, $order_meta_intent_status );
		$order->save_meta_data();

		$request = $this->mock_wcpay_request( Get_Intention::class, 0, $request_intent_id );

		$this->mock_gateway
			->expects( $this->never() )
			->method( 'capture_charge' );

		$this->order_service
			->expects( $this->never() )
			->method( 'attach_intent_info_to_order' );

		$request = new WP_REST_Request( 'POST' );
		$request->set_body_params(
			[
				'order_id'          => $order->get_id(),
				'payment_intent_id' => $request_intent_id,
			]
		);

		$response = $this->controller->capture_terminal_payment( $request );

		$this->assertInstanceOf( 'WP_Error', $response );
		$data = $response->get_error_data();
		$this->assertArrayHasKey( 'status', $data );
		$this->assertSame( 409, $data['status'] );
	}

	public function provider_capture_terminal_payment_prevents_double_charging_order_with_intent_meta(): array {
		return [
			[ 'pi_abc', Intent_Status::REQUIRES_CAPTURE, 'pi_xyz' ],
			[ 'pi_abc', '', 'pi_xyz' ],
			[ 'pi_abc', Intent_Status::SUCCEEDED, 'pi_abc' ],
			[ 'pi_abc', Intent_Status::SUCCEEDED, 'pi_xyz' ],
			[ 'pi_abc', Intent_Status::CANCELED, 'pi_abc' ],
			[ 'pi_abc', Intent_Status::CANCELED, 'pi_xyz' ],
			[ 'pi_abc', Intent_Status::PROCESSING, 'pi_abc' ],
		];
	}

	/**
	 * @dataProvider provider_get_terminal_intent_capture_method
	 */
	public function test_get_terminal_intent_capture_method( $capture_method, $expected ) {
		$request = new WP_REST_Request( 'POST' );
		$request->set_param( 'capture_method', $capture_method );

		$this->assertSame( $expected, $this->controller->get_terminal_intent_capture_method( $request ) );
	}

	public function provider_get_terminal_intent_capture_method(): array {
		return [
			[ null, 'manual' ],
			[ 'automatic', 'automatic' ],
		];
	}

	public function test_get_terminal_intent_capture_method_invalid_value() {
		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Invalid param \'capture_method\'!' );
		$request = new WP_REST_Request( 'POST' );
		$request->set_param( 'capture_method', 'invalid_val' );

		$this->controller->get_terminal_intent_capture_method( $request );
	}

	private function create_charge_object() {
		$created = new DateTime();
		$created->setTimestamp( $this->mock_charge_created );

		return new WC_Payments_API_Charge( $this->mock_charge_id, 1500, $created );
	}

	public function test_capture_terminal_payment_with_subscription_product_sets_generated_card_on_user() {
		$order = $this->create_mock_order();

		$subscription = new WC_Subscription();
		$subscription->set_parent( $order );
		$this->mock_wcs_order_contains_subscription( true );
		$this->mock_wcs_get_subscriptions_for_order( [ $subscription ] );
		$this->mock_wcs_is_manual_renewal_required( false );

		$generated_card_id = 'pm_generatedCardId';

		$mock_intent = WC_Helper_Intention::create_intention(
			[
				'charge'   => [
					'payment_method_details' => [
						'type'         => 'card_present',
						'card_present' => [
							'generated_card' => $generated_card_id,
						],
					],
				],
				'metadata' => [
					'order_id' => $order->get_id(),
				],
				'status'   => Intent_Status::REQUIRES_CAPTURE,
			]
		);

		$request = $this->mock_wcpay_request( Get_Intention::class, 1, $this->mock_intent_id );

		$request->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( $mock_intent );

		$this->mock_gateway
			->expects( $this->once() )
			->method( 'capture_charge' )
			->with( $this->isInstanceOf( WC_Order::class ) )
			->willReturn(
				[
					'status' => Intent_Status::SUCCEEDED,
					'id'     => $this->mock_intent_id,
				]
			);

		$this->order_service
			->expects( $this->once() )
			->method( 'attach_intent_info_to_order' )
			->with(
				$this->isInstanceOf( WC_Order::class ),
				$mock_intent,
			);

		$this->mock_token_service
			->expects( $this->once() )
			->method( 'add_payment_method_to_user' )
			->with(
				$generated_card_id,
				$this->isInstanceOf( WP_User::class )
			);

		$request = new WP_REST_Request( 'POST' );
		$request->set_body_params(
			[
				'order_id'          => $order->get_id(),
				'payment_intent_id' => $this->mock_intent_id,
			]
		);

		$response = $this->controller->capture_terminal_payment( $request );
		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( 'woocommerce_payments', $subscription->get_payment_method() );
	}

	/**
	 * @dataProvider provider_capture_terminal_payment_with_subscription_product_sets_manual_renewal
	 */
	public function test_capture_terminal_payment_with_subscription_product_sets_manual_renewal( bool $manual_renewal_required_setting, bool $initial_subscription_manual_renewal, bool $expected_subscription_manual_renewal ) {
		$order = $this->create_mock_order();

		$subscription = new WC_Subscription();
		$subscription->set_parent( $order );
		$subscription->set_requires_manual_renewal( $initial_subscription_manual_renewal );
		$this->mock_wcs_order_contains_subscription( true );
		$this->mock_wcs_get_subscriptions_for_order( [ $subscription ] );
		$this->mock_wcs_is_manual_renewal_required( $manual_renewal_required_setting );

		$generated_card_id = 'pm_generatedCardId';

		$mock_intent = WC_Helper_Intention::create_intention(
			[
				'charge'   => [
					'payment_method_details' => [
						'type'         => 'card_present',
						'card_present' => [
							'generated_card' => $generated_card_id,
						],
					],
				],
				'metadata' => [
					'order_id' => $order->get_id(),
				],
				'status'   => Intent_Status::REQUIRES_CAPTURE,
			]
		);

		$request = $this->mock_wcpay_request( Get_Intention::class, 1, $this->mock_intent_id );

		$request->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( $mock_intent );

		$this->mock_gateway
			->expects( $this->once() )
			->method( 'capture_charge' )
			->with( $this->isInstanceOf( WC_Order::class ) )
			->willReturn(
				[
					'status' => Intent_Status::SUCCEEDED,
					'id'     => $this->mock_intent_id,
				]
			);

		$this->order_service
			->expects( $this->once() )
			->method( 'attach_intent_info_to_order' )
			->with(
				$this->isInstanceOf( WC_Order::class ),
				$mock_intent,
			);

		$this->mock_token_service
			->expects( $this->once() )
			->method( 'add_payment_method_to_user' )
			->with(
				$generated_card_id,
				$this->isInstanceOf( WP_User::class )
			);

		$request = new WP_REST_Request( 'POST' );
		$request->set_body_params(
			[
				'order_id'          => $order->get_id(),
				'payment_intent_id' => $this->mock_intent_id,
			]
		);

		$response = $this->controller->capture_terminal_payment( $request );
		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( $expected_subscription_manual_renewal, $subscription->is_manual() );
	}

	/**
	 * bool $manual_renewal_required_setting
	 * bool $initial_subscription_manual_renewal
	 * bool $expected_subscription_manual_renewal
	 */
	public function provider_capture_terminal_payment_with_subscription_product_sets_manual_renewal(): array {
		return [
			[ true, true, true ],
			[ false, true, false ],
			[ true, false, false ], // even if manual_renewal_required, we won't set it to manual_renewal if it started as automatic.
			[ false, false, false ],
		];
	}

	/**
	 * Cleanup after all tests.
	 */
	public static function tear_down_after_class() {
		WC_Subscriptions::set_wcs_order_contains_subscription( null );
		WC_Subscriptions::set_wcs_get_subscriptions_for_order( null );
		WC_Subscriptions::set_wcs_is_manual_renewal_required( null );
		parent::tear_down_after_class();
	}

	private function mock_wcs_order_contains_subscription( $value ) {
		WC_Subscriptions::set_wcs_order_contains_subscription(
			function ( $order ) use ( $value ) {
				return $value;
			}
		);
	}

	private function mock_wcs_get_subscriptions_for_order( $value ) {
		WC_Subscriptions::set_wcs_get_subscriptions_for_order(
			function ( $order ) use ( $value ) {
				return $value;
			}
		);
	}

	private function mock_wcs_is_manual_renewal_required( $value ) {
		WC_Subscriptions::set_wcs_is_manual_renewal_required(
			function () use ( $value ) {
				return $value;
			}
		);
	}

	public function test_capture_terminal_payment_with_subscription_product_returns_success_even_if_no_generated_card() {
		$order = $this->create_mock_order();

		$subscription = new WC_Subscription();
		$subscription->set_parent( $order );
		$this->mock_wcs_order_contains_subscription( true );
		$this->mock_wcs_get_subscriptions_for_order( [ $subscription ] );

		$mock_intent = WC_Helper_Intention::create_intention(
			[
				'charge'   => [
					'payment_method_details' => [
						'type'         => 'card_present',
						'card_present' => [],
					],
				],
				'metadata' => [
					'order_id' => $order->get_id(),
				],
				'status'   => Intent_Status::REQUIRES_CAPTURE,
			]
		);

		$request = $this->mock_wcpay_request( Get_Intention::class, 1, $this->mock_intent_id );

		$request->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( $mock_intent );

		$this->mock_gateway
			->expects( $this->once() )
			->method( 'capture_charge' )
			->with( $this->isInstanceOf( WC_Order::class ) )
			->willReturn(
				[
					'status' => Intent_Status::SUCCEEDED,
					'id'     => $this->mock_intent_id,
				]
			);

		$this->order_service
			->expects( $this->once() )
			->method( 'attach_intent_info_to_order' )
			->with(
				$this->isInstanceOf( WC_Order::class ),
				$mock_intent,
			);

		$this->mock_token_service
			->expects( $this->never() )
			->method( 'add_payment_method_to_user' );

		$request = new WP_REST_Request( 'POST' );
		$request->set_body_params(
			[
				'order_id'          => $order->get_id(),
				'payment_intent_id' => $this->mock_intent_id,
			]
		);

		$response = $this->controller->capture_terminal_payment( $request );
		$this->assertSame( 200, $response->status );
	}
}
