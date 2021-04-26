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

	public function test_capture_order_success() {
		$order = $this->create_mock_order();

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

		$request = new WP_REST_Request( 'POST' );
		$request->set_body_params( [ 'order_id' => $order->get_id() ] );

		$response      = $this->controller->capture_order( $request );
		$response_data = $response->get_data();

		$this->assertEquals( 200, $response->status );
		$this->assertEquals(
			[
				'status' => 'succeeded',
				'id'     => $this->mock_intent_id,
			],
			$response_data
		);
	}

	public function test_capture_order_failed() {
		$order = $this->create_mock_order();

		$this->mock_gateway
			->expects( $this->once() )
			->method( 'capture_charge' )
			->with( $this->isInstanceOf( WC_Order::class ) )
			->willReturn(
				[
					'status' => 'failed',
					'id'     => $this->mock_intent_id,
				]
			);

		$request = new WP_REST_Request( 'POST' );
		$request->set_body_params( [ 'order_id' => $order->get_id() ] );

		$response      = $this->controller->capture_order( $request );
		$response_data = $response->get_data();

		$this->assertEquals( 200, $response->status );
		$this->assertEquals(
			[
				'status' => 'failed',
				'id'     => $this->mock_intent_id,
			],
			$response_data
		);
	}

	public function test_capture_order_not_found() {
		$request = new WP_REST_Request( 'POST' );
		$request->set_body_params( [ 'order_id' => 'not_an_order_1234' ] );

		$response = $this->controller->capture_order( $request );

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
