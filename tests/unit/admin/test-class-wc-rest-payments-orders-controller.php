<?php
/**
 * Class WC_REST_Payments_Orders_Controller_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Exceptions\Rest_Request_Exception;

/**
 * WC_REST_Payments_Orders_Controller unit tests.
 */
class WC_REST_Payments_Orders_Controller_Test extends WP_UnitTestCase {
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
	 * @var string
	 */
	private $mock_intent_id = 'pi_xxxxxxxxxxxxx';

	/**
	 * @var string
	 */
	private $mock_charge_id = 'ch_yyyyyyyyyyyyy';

	public function setUp() {
		parent::setUp();

		// Set the user so that we can pass the authentication.
		wp_set_current_user( 1 );

		$this->mock_api_client = $this->createMock( WC_Payments_API_Client::class );
		$this->mock_gateway    = $this->createMock( WC_Payment_Gateway_WCPay::class );

		$this->controller = new WC_REST_Payments_Orders_Controller(
			$this->mock_api_client,
			$this->mock_gateway
		);
	}

	public function test_capture_terminal_payment_success() {
		$order = $this->create_mock_order();

		$mock_intent = $this->createMock( WC_Payments_API_Intention::class );
		$mock_intent
			->expects( $this->any() )
			->method( 'get_status' )
			->willReturn( 'requires_capture' );
		$mock_intent
			->expects( $this->any() )
			->method( 'get_id' )
			->willReturn( 'pi_mock' );
		$mock_intent
			->expects( $this->any() )
			->method( 'get_payment_method_id' )
			->willReturn( 'pm_mock' );
		$mock_intent
			->expects( $this->any() )
			->method( 'get_customer_id' )
			->willReturn( 'cus_mock' );
		$mock_intent
			->expects( $this->any() )
			->method( 'get_charge_id' )
			->willReturn( 'ch_mock' );
		$mock_intent
			->expects( $this->any() )
			->method( 'get_currency' )
			->willReturn( 'mok' );

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
				'pi_mock',
				'requires_capture',
				'pm_mock',
				'cus_mock',
				'ch_mock',
				'mok'
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
		$this->assertEquals( 'WooCommerce Payments', $result_order->get_payment_method_title() );
		$this->assertEquals( 'completed', $result_order->get_status() );
	}

	public function test_capture_terminal_payment_intent_non_capturable() {
		$order = $this->create_mock_order();

		$mock_intent = $this->createMock( WC_Payments_API_Intention::class );
		$mock_intent
			->expects( $this->any() )
			->method( 'get_status' )
			->willReturn( 'succeeded' );

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

	public function test_capture_terminal_payment_error_when_capturing() {
		$order = $this->create_mock_order();

		$mock_intent = $this->createMock( WC_Payments_API_Intention::class );
		$mock_intent
			->expects( $this->any() )
			->method( 'get_status' )
			->willReturn( 'requires_capture' );

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

	private function create_mock_order() {
		$order = WC_Helper_Order::create_order();
		$order->set_transaction_id( $this->mock_intent_id );
		$order->update_meta_data( '_intent_id', $this->mock_intent_id );
		$order->update_meta_data( '_charge_id', $this->mock_charge_id );
		$order->update_meta_data( '_intention_status', 'requires_capture' );
		$order->update_status( 'on-hold' );
		return $order;
	}
}
