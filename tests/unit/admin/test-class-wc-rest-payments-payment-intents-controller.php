<?php
/**
 * Class WC_REST_Payments_Payment_Intents_Controller_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Core\Server\Request\Create_Intention;
use WCPay\Exceptions\API_Exception;

/**
 * WC_REST_Payments_Payment_Intents_Controller unit tests.
 */
class WC_REST_Payments_Payment_Intents_Controller_Test extends WCPAY_UnitTestCase {

	/**
	 * Controller under test.
	 *
	 * @var WC_REST_Payments_Payment_Intents_Controller
	 */
	private $controller;

	/**
	 * @var WC_Payments_API_Client|MockObject
	 */
	private $mock_api_client;

	public function set_up() {
		parent::set_up();
		$this->mock_api_client = $this->createMock( WC_Payments_API_Client::class );
		$this->controller      = new WC_REST_Payments_Payment_Intents_Controller( $this->mock_api_client );
	}

	public function test_create_payment_intent_success() {
		$current_order    = WC_Helper_Order::create_order();
		$current_order_id = $current_order->get_id();
		$request          = new WP_REST_Request( 'POST' );
		$request->set_body_params(
			[
				'metadata'             => [
					'order_number' => $current_order_id,
				],
				'customer'             => 'cus_123',
				'payment_method_types' => [ 'card' ],
			]
		);

		$mock_intent = WC_Helper_Intention::create_intention();
		$this->mock_wcpay_request( Create_Intention::class )
			->expects( $this->once() )
			->method( 'format_response' )
			->with()
			->willReturn(
				$mock_intent
			);

		$response = $this->controller->post_payment_intent( $request );
		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( $mock_intent, $response->get_data() );
	}

	public function test_create_payment_intent_no_order() {
		$request = new WP_REST_Request( 'POST' );
		$request->set_body_params(
			[
				'metadata'             => [
					'order_number' => 'no_such_order',
				],
				'customer'             => 'cus_123',
				'payment_method_types' => [ 'card' ],
			]
		);

		$response = $this->controller->post_payment_intent( $request );
		$this->assertInstanceOf( 'WP_Error', $response );
		$data = $response->get_error_data();
		$this->assertArrayHasKey( 'status', $data );
		$this->assertSame( 500, $data['status'] );
	}


	public function test_create_payment_intent_missing_required_params() {
		$current_order    = WC_Helper_Order::create_order();
		$current_order_id = $current_order->get_id();
		$request          = new WP_REST_Request( 'POST' );
		$request->set_body_params(
			[
				'metadata' => [
					'order_number' => $current_order_id,
				],
			]
		);

		$response = $this->controller->post_payment_intent( $request );
		$this->assertInstanceOf( 'WP_Error', $response );
		$data = $response->get_error_data();
		$this->assertArrayHasKey( 'status', $data );
		$this->assertSame( 500, $data['status'] );
	}

	public function test_create_payment_intent_server_error() {
		$current_order    = WC_Helper_Order::create_order();
		$current_order_id = $current_order->get_id();
		$request          = new WP_REST_Request( 'POST' );
		$request->set_body_params(
			[
				'metadata'             => [
					'order_number' => $current_order_id,
				],
				'customer'             => 'cus_123',
				'payment_method_types' => [ 'card' ],
			]
		);

		$wcpay_request = $this->mock_wcpay_request( Create_Intention::class );
		$wcpay_request->expects( $this->once() )
			->method( 'format_response' )
			->will(
				$this->throwException(
					new API_Exception(
						'Test error.',
						'test_error',
						400
					)
				)
			);

		$response = $this->controller->post_payment_intent( $request );
		$this->assertInstanceOf( 'WP_Error', $response );
		$data = $response->get_error_data();
		$this->assertArrayHasKey( 'status', $data );
		$this->assertSame( 500, $data['status'] );
		$this->assertEquals( 'Test error.', $response->get_error_message() );
	}

}
