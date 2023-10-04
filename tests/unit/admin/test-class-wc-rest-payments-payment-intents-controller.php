<?php
/**
 * Class WC_REST_Payments_Payment_Intents_Controller_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Core\Server\Request\Create_And_Confirm_Intention;
use WCPay\Exceptions\API_Exception;
use WCPay\Constants\Payment_Type;

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


	/**
	 * @var WC_Payment_Gateway_WCPay|MockObject
	 */
	private $mock_gateway;

	public function set_up() {
		parent::set_up();
		$this->mock_api_client = $this->createMock( WC_Payments_API_Client::class );
		$this->mock_gateway    = $this->createMock( WC_Payment_Gateway_WCPay::class );
		$this->controller      = new WC_REST_Payments_Payment_Intents_Controller( $this->mock_api_client, $this->mock_gateway );
	}

	public function test_create_payment_intent_success() {
		$current_order    = WC_Helper_Order::create_order();
		$current_order_id = $current_order->get_id();
		$this->mock_gateway
			->expects( $this->once() )
			->method( 'get_metadata_from_order' )
			->with( $this->isInstanceOf( WC_Order::class ), Payment_Type::SINGLE() )
			->willReturn(
				[
					'order_id' => $current_order->get_id(),
				]
			);

		$request = new WP_REST_Request( 'POST' );
		$request->set_body_params(
			[
				'order_id'             => $current_order_id,
				'customer'             => 'cus_123',
				'payment_method'       => 'pm_123',
				'payment_method_types' => [ 'card' ],
			]
		);

		$mock_intent = WC_Helper_Intention::create_intention();
		$this->mock_wcpay_request( Create_And_Confirm_Intention::class )
			->expects( $this->once() )
			->method( 'format_response' )
			->with()
			->willReturn(
				$mock_intent
			);

		$response = $this->controller->create_payment_intent( $request );
		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( $mock_intent, $response->get_data() );
	}

	public function test_create_payment_intent_no_order() {
		$request = new WP_REST_Request( 'POST' );
		$request->set_body_params(
			[
				'order_id'             => 'no_such_order',
				'customer'             => 'cus_123',
				'payment_method'       => 'pm_123',
				'payment_method_types' => [ 'card' ],
			]
		);

		$response = $this->controller->create_payment_intent( $request );
		$this->assertInstanceOf( 'WP_Error', $response );
		$data = $response->get_error_data();
		$this->assertArrayHasKey( 'status', $data );
		$this->assertSame( 500, $data['status'] );
	}


	public function test_create_payment_intent_missing_required_params() {
		$current_order    = WC_Helper_Order::create_order();
		$current_order_id = $current_order->get_id();
		$this->mock_gateway
			->expects( $this->once() )
			->method( 'get_metadata_from_order' )
			->with( $this->isInstanceOf( WC_Order::class ), Payment_Type::SINGLE() )
			->willReturn(
				[
					'order_id' => $current_order->get_id(),
				]
			);

		$request = new WP_REST_Request( 'POST' );
		$request->set_body_params(
			[
				'order_id' => $current_order_id,
			]
		);

		$response = $this->controller->create_payment_intent( $request );
		$this->assertInstanceOf( 'WP_Error', $response );
		$data = $response->get_error_data();
		$this->assertArrayHasKey( 'status', $data );
		$this->assertSame( 500, $data['status'] );
	}

	public function test_create_payment_intent_server_error() {
		$current_order    = WC_Helper_Order::create_order();
		$current_order_id = $current_order->get_id();
		$this->mock_gateway
			->expects( $this->once() )
			->method( 'get_metadata_from_order' )
			->with( $this->isInstanceOf( WC_Order::class ), Payment_Type::SINGLE() )
			->willReturn(
				[
					'order_id' => $current_order->get_id(),
				]
			);

		$request = new WP_REST_Request( 'POST' );
		$request->set_body_params(
			[
				'order_id'             => $current_order_id,
				'customer'             => 'cus_123',
				'payment_method'       => 'pm_123',
				'payment_method_types' => [ 'card' ],
			]
		);

		$wcpay_request = $this->mock_wcpay_request( Create_And_Confirm_Intention::class );
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

		$response = $this->controller->create_payment_intent( $request );
		$this->assertInstanceOf( 'WP_Error', $response );
		$data = $response->get_error_data();
		$this->assertArrayHasKey( 'status', $data );
		$this->assertSame( 500, $data['status'] );
		$this->assertEquals( 'Test error.', $response->get_error_message() );
	}

}
