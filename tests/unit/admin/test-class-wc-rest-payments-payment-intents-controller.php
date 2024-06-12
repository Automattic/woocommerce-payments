<?php
/**
 * Class WC_REST_Payments_Payment_Intents_Create_Controller_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Core\Server\Request\Create_And_Confirm_Intention;
use WCPay\Exceptions\API_Exception;
use WCPay\Constants\Payment_Type;
use WCPay\Internal\Service\Level3Service;
use WCPay\Internal\Service\OrderService;

/**
 * WC_REST_Payments_Payment_Intents_Create_Controller unit tests.
 */
class WC_REST_Payments_Payment_Intents_Create_Controller_Test extends WCPAY_UnitTestCase {

	/**
	 * Controller under test.
	 *
	 * @var WC_REST_Payments_Payment_Intents_Create_Controller
	 */
	private $controller;

	/**
	 * @var WC_Payments_API_Client|MockObject
	 */
	private $mock_api_client;

	/**
	 * @var OrderService|MockObject
	 */
	private $mock_order_service;

	/**
	 * @var Level3Service|MockObject
	 */
	private $mock_level3_service;

	/**
	 * @var WC_Payment_Gateway_WCPay|MockObject
	 */
	private $mock_gateway;

	public function set_up() {
		parent::set_up();

		$this->mock_api_client     = $this->createMock( WC_Payments_API_Client::class );
		$this->mock_gateway        = $this->createMock( WC_Payment_Gateway_WCPay::class );
		$this->mock_order_service  = $this->createMock( OrderService::class );
		$this->mock_level3_service = $this->createMock( Level3Service::class );

		$this->controller = new WC_REST_Payments_Payment_Intents_Create_Controller(
			$this->mock_api_client,
			$this->mock_gateway,
			$this->mock_order_service,
			$this->mock_level3_service
		);
	}

	public function test_create_payment_intent_success() {
		$current_order    = WC_Helper_Order::create_order();
		$current_order_id = $current_order->get_id();
		$this->mock_order_service
			->expects( $this->once() )
			->method( 'get_payment_metadata' )
			->with( $current_order_id, Payment_Type::SINGLE() )
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
		$data = $response->get_data();
		$this->assertSame( $mock_intent->get_id(), $data['id'] );
		$this->assertSame( $mock_intent->get_currency(), $data['currency'] );
		$this->assertSame( $mock_intent->get_amount(), $data['amount'] );
		$this->assertSame( $mock_intent->get_charge()->get_id(), $data['charge']['id'] );
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
		$this->mock_order_service
			->expects( $this->once() )
			->method( 'get_payment_metadata' )
			->with( $current_order_id, Payment_Type::SINGLE() )
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
		$this->mock_order_service
			->expects( $this->once() )
			->method( 'get_payment_metadata' )
			->with( $current_order_id, Payment_Type::SINGLE() )
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
