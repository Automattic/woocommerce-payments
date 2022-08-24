<?php
/**
 * Class WC_REST_Payments_Orders_Controller_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Exceptions\Rest_Request_Exception;
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
		$this->order_service         = new WC_Payments_Order_Service( $this->mock_api_client );

		$this->controller = new WC_REST_Payments_Orders_Controller(
			$this->mock_api_client,
			$this->mock_gateway,
			$this->mock_customer_service,
			$this->order_service
		);
	}

	public function test_capture_terminal_payment_success() {
		$order       = $this->create_mock_order();
		$mock_intent = WC_Helper_Intention::create_intention(
			[
				'status'   => 'requires_capture',
				'metadata' => [
					'order_id' => $order->get_id(),
				],
			]
		);

		$this->mock_api_client
			->expects( $this->once() )
			->method( 'get_intent' )
			->willReturn( $mock_intent );

		$this->mock_gateway
			->expects( $this->once() )
			->method( 'capture_charge' )
			->with( $this->isInstanceOf( WC_Order::class ) )
			->willReturn(
				[
					'status' => 'succeeded',
					'id'     => $this->mock_intent_id,
				]
			);

		$this->mock_gateway
			->expects( $this->once() )
			->method( 'attach_intent_info_to_order' )
			->with(
				$this->isInstanceOf( WC_Order::class ),
				$this->mock_intent_id,
				'requires_capture',
				'pm_mock',
				'cus_mock',
				$this->mock_charge_id,
				'USD'
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
				'status' => 'succeeded',
				'id'     => $this->mock_intent_id,
			],
			$response_data
		);

		$result_order = wc_get_order( $order->get_id() );
		$this->assertEquals( 'woocommerce_payments', $result_order->get_payment_method() );
		$this->assertEquals( 'WooCommerce In-Person Payments', $result_order->get_payment_method_title() );
		$this->assertEquals( 'completed', $result_order->get_status() );
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

		$this->mock_api_client
			->expects( $this->once() )
			->method( 'get_intent' )
			->willReturn( $mock_intent );

		$this->mock_gateway
			->expects( $this->once() )
			->method( 'attach_intent_info_to_order' )
			->with(
				$this->isInstanceOf( WC_Order::class ),
				$this->mock_intent_id,
				'succeeded',
				'pm_mock',
				'cus_mock',
				$this->mock_charge_id,
				'USD'
			);
		$this->mock_gateway
			->expects( $this->once() )
			->method( 'update_order_status_from_intent' )
			->with(
				$this->isInstanceOf( WC_Order::class ),
				$this->mock_intent_id,
				'succeeded',
				'ch_mock'
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
				'status' => 'succeeded',
				'id'     => $this->mock_intent_id,
			],
			$response_data
		);

		$result_order = wc_get_order( $order->get_id() );
		$this->assertSame( 'woocommerce_payments', $result_order->get_payment_method() );
		$this->assertSame( 'WooCommerce In-Person Payments', $result_order->get_payment_method_title() );
		$this->assertSame( 'completed', $result_order->get_status() );
		$url = '/wc/v3/' . ( $this->is_wpcom() ? 'sites/3/' : '' ) . 'payments/readers/receipts/';
		$this->assertStringEndsWith( $url . $this->mock_intent_id, $result_order->get_meta( 'receipt_url' ) );
	}

	public function test_capture_terminal_payment_completed_order() {
		// This scenario may occur when `process_webhook_payment_intent_succeeded`
		// is triggered before the terminal payment is captured in the backend.
		$order = $this->create_mock_order();
		$order->update_status( 'completed' );

		$mock_intent = WC_Helper_Intention::create_intention(
			[
				'metadata' => [
					'order_id' => $order->get_id(),
				],
			]
		);

		$this->mock_api_client
			->expects( $this->once() )
			->method( 'get_intent' )
			->willReturn( $mock_intent );

		$this->mock_gateway
			->expects( $this->once() )
			->method( 'attach_intent_info_to_order' )
			->with(
				$this->isInstanceOf( WC_Order::class ),
				$this->mock_intent_id,
				'succeeded',
				'pm_mock',
				'cus_mock',
				$this->mock_charge_id,
				'USD'
			);
		$this->mock_gateway
			->expects( $this->once() )
			->method( 'update_order_status_from_intent' )
			->with(
				$this->isInstanceOf( WC_Order::class ),
				$this->mock_intent_id,
				'succeeded',
				'ch_mock'
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
				'status' => 'succeeded',
				'id'     => $this->mock_intent_id,
			],
			$response_data
		);

		$result_order = wc_get_order( $order->get_id() );
		$this->assertSame( 'woocommerce_payments', $result_order->get_payment_method() );
		$this->assertSame( 'WooCommerce In-Person Payments', $result_order->get_payment_method_title() );
		$this->assertSame( 'completed', $result_order->get_status() );
		$url = '/wc/v3/' . ( $this->is_wpcom() ? 'sites/3/' : '' ) . 'payments/readers/receipts/';
		$this->assertStringEndsWith( $url . $this->mock_intent_id, $result_order->get_meta( 'receipt_url' ) );
	}

	public function test_capture_terminal_payment_intent_non_capturable() {
		$order = $this->create_mock_order();

		$mock_intent = WC_Helper_Intention::create_intention( [ 'status' => 'requires_payment_method' ] );

		$this->mock_api_client
			->expects( $this->once() )
			->method( 'get_intent' )
			->willReturn( $mock_intent );

		$this->mock_gateway
			->expects( $this->never() )
			->method( 'capture_charge' );

		$this->mock_gateway
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

	public function test_capture_terminal_payment_refunded_order() {
		$order = $this->create_mock_order();

		wc_create_refund(
			[
				'order_id'   => $order->get_id(),
				'amount'     => 10.0,
				'line_items' => [],
			]
		);

		$this->mock_api_client
			->expects( $this->never() )
			->method( 'get_intent' );

		$this->mock_gateway
			->expects( $this->never() )
			->method( 'capture_charge' );

		$this->mock_gateway
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
				'status'   => 'requires_capture',
				'metadata' => [
					'order_id' => $order->get_id(),
				],
			]
		);

		$this->mock_api_client
			->expects( $this->once() )
			->method( 'get_intent' )
			->willReturn( $mock_intent );

		$this->mock_gateway
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
				'status'   => 'requires_capture',
				'metadata' => [
					'order_id' => $order->get_id(),
				],
			]
		);

		$this->mock_api_client
			->expects( $this->once() )
			->method( 'get_intent' )
			->willReturn( $mock_intent );

		$this->mock_gateway
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

		$this->mock_api_client
			->expects( $this->once() )
			->method( 'get_intent' )
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

	/**
	 * @expectedDeprecated create_customer
	 */
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

	/**
	 * @expectedDeprecated create_customer
	 */
	public function test_create_customer_from_order_guest_without_customer_id() {
		$order         = WC_Helper_Order::create_order( 0 );
		$customer_data = WC_Payments_Customer_Service::map_customer_data( $order );

		$this->mock_customer_service
			->expects( $this->once() )
			->method( 'create_customer_for_user' )
			->with(
				$this->callback(
					function( $argument ) {
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
					function( $argument ) {
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

	/**
	 * @expectedDeprecated create_customer
	 */
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
					function( $argument ) {
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

	/**
	 * @expectedDeprecated create_customer
	 */
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

	/**
	 * @expectedDeprecated create_customer
	 */
	public function test_create_customer_from_order_with_invalid_status() {
		$order = WC_Helper_Order::create_order();
		$order->set_status( 'completed' );
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

	/**
	 * @expectedDeprecated create_customer
	 */
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

	/**
	 * @expectedDeprecated create_customer
	 */
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
		$order = $this->create_mock_order();

		$this->mock_gateway
			->expects( $this->once() )
			->method( 'create_intent' )
			->with( $this->isInstanceOf( WC_Order::class ) )
			->willReturn(
				[
					'status' => 'created',
					'id'     => 'pi_abcxyz',
				]
			);

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
				'status' => 'created',
				'id'     => 'pi_abcxyz',
			],
			$response_data
		);
	}

	public function test_create_terminal_intent_order_not_found() {
		$request = new WP_REST_Request( 'POST' );
		$request->set_body_params(
			[
				'order_id' => 'not_an_id',
			]
		);

		$response = $this->controller->create_terminal_intent( $request );

		$this->assertInstanceOf( 'WP_Error', $response );
		$data = $response->get_error_data();
		$this->assertArrayHasKey( 'status', $data );
		$this->assertSame( 404, $data['status'] );
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
		$response = $this->controller->create_terminal_intent( $request );

		$this->assertInstanceOf( 'WP_Error', $response );
		$data = $response->get_error_data();
		$this->assertArrayHasKey( 'status', $data );
		$this->assertSame( 500, $data['status'] );
	}

	private function create_mock_order() {
		$charge = $this->create_charge_object();

		$order = WC_Helper_Order::create_order();
		$order->set_transaction_id( $this->mock_intent_id );
		$order->update_meta_data( '_intent_id', $this->mock_intent_id );
		$order->update_meta_data( '_charge_id', $charge->get_id() );
		$order->update_meta_data( '_intention_status', 'requires_capture' );
		$order->update_status( 'on-hold' );
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
}
