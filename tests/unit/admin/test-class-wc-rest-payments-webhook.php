<?php
/**
 * Class WC_REST_Payments_Webhook_Controller_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Exceptions\Rest_Request_Exception;

/**
 * WC_REST_Payments_Webhook_Controller unit tests.
 */
class WC_REST_Payments_Webhook_Controller_Test extends WP_UnitTestCase {

	/**
	 * The system under test.
	 *
	 * @var WC_REST_Payments_Webhook_Controller
	 */
	private $controller;

	/**
	 * @var WC_Payments_DB|MockObject
	 */
	private $mock_db_wrapper;

	/**
	 * @var WC_Payments_Remote_Note_Service|MockObject
	 */
	private $mock_remote_note_service;

	/**
	 * @var WP_REST_Request
	 */
	private $request;

	/**
	 * @var array
	 */
	private $request_body;

	/**
	 * Pre-test setup
	 */
	public function setUp() {
		parent::setUp();

		// Set the user so that we can pass the authentication.
		wp_set_current_user( 1 );

		/** @var WC_Payments_API_Client|MockObject $mock_api_client */
		$mock_api_client = $this->getMockBuilder( WC_Payments_API_Client::class )
			->disableOriginalConstructor()
			->getMock();

		$account = new WC_Payments_Account( $mock_api_client );

		$this->mock_db_wrapper = $this->getMockBuilder( WC_Payments_DB::class )
			->disableOriginalConstructor()
			->setMethods( [ 'order_from_charge_id' ] )
			->getMock();

		$this->mock_remote_note_service = $this->createMock( WC_Payments_Remote_Note_Service::class );

		$this->controller = new WC_REST_Payments_Webhook_Controller( $mock_api_client, $this->mock_db_wrapper, $account, $this->mock_remote_note_service );

		// Setup a test request.
		$this->request = new WP_REST_Request(
			'POST',
			'/wc/v3/payments/webhook'
		);

		$this->request->set_header( 'Content-Type', 'application/json' );

		// Build the test request data.
		$event_object = [];

		$event_data           = [];
		$event_data['object'] = $event_object;

		$this->request_body         = [];
		$this->request_body['data'] = $event_data;
	}

	/**
	 * Test processing a webhook that requires no action.
	 */
	public function test_noop_webhook() {
		// Setup test request data.
		$this->request_body['type'] = 'unknown.webhook.event';
		$this->request->set_body( wp_json_encode( $this->request_body ) );

		// Run the test.
		$response = $this->controller->handle_webhook( $this->request );

		// Check the response.
		$response_data = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( [ 'result' => 'success' ], $response_data );
	}

	/**
	 * Test a webhook with no type property.
	 */
	public function test_webhook_with_no_type_property() {
		// Setup test request data.
		$this->request->set_body( wp_json_encode( $this->request_body ) );

		// Run the test.
		$response = $this->controller->handle_webhook( $this->request );

		// Check the response.
		$response_data = $response->get_data();

		$this->assertEquals( 400, $response->get_status() );
		$this->assertEquals( [ 'result' => 'bad_request' ], $response_data );
	}

	/**
	 * Test a webhook with no object property.
	 */
	public function test_webhook_with_no_object_property() {
		// Setup test request data.
		$this->request_body['type'] = 'charge.refund.updated';
		unset( $this->request_body['data']['object'] );
		$this->request->set_body( wp_json_encode( $this->request_body ) );

		// Run the test.
		$response = $this->controller->handle_webhook( $this->request );

		// Check the response.
		$response_data = $response->get_data();

		$this->assertEquals( 400, $response->get_status() );
		$this->assertEquals( [ 'result' => 'bad_request' ], $response_data );
	}

	/**
	 * Test a webhook with no data property.
	 */
	public function test_webhook_with_no_data_property() {
		// Setup test request data.
		$this->request_body['type'] = 'charge.refund.updated';
		unset( $this->request_body['data'] );
		$this->request->set_body( wp_json_encode( $this->request_body ) );

		// Run the test.
		$response = $this->controller->handle_webhook( $this->request );

		// Check the response.
		$response_data = $response->get_data();

		$this->assertEquals( 400, $response->get_status() );
		$this->assertEquals( [ 'result' => 'bad_request' ], $response_data );
	}

	/**
	 * Test a valid failed refund update webhook.
	 */
	public function test_valid_failed_refund_update_webhook() {
		// Setup test request data.
		$this->request_body['type']           = 'charge.refund.updated';
		$this->request_body['data']['object'] = [
			'status'   => 'failed',
			'charge'   => 'test_charge_id',
			'id'       => 'test_refund_id',
			'amount'   => 999,
			'currency' => 'gbp',
		];

		$this->request->set_body( wp_json_encode( $this->request_body ) );

		$mock_order = $this->getMockBuilder( WC_Order::class )
			->disableOriginalConstructor()
			->setMethods( [ 'add_order_note' ] )
			->getMock();

		$mock_order
			->expects( $this->once() )
			->method( 'add_order_note' )
			->with(
				$this->matchesRegularExpression(
					'~^A refund of <span class="woocommerce-Price-amount amount">(<bdi>)?<span class="woocommerce-Price-currencySymbol">&pound;</span>9.99(</bdi>)?</span> was <strong>unsuccessful</strong> using WooCommerce Payments \(<code>test_refund_id</code>\).$~'
				)
			);

		$this->mock_db_wrapper
			->expects( $this->once() )
			->method( 'order_from_charge_id' )
			->with( 'test_charge_id' )
			->willReturn( $mock_order );

		// Run the test.
		$response = $this->controller->handle_webhook( $this->request );

		// Check the response.
		$response_data = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( [ 'result' => 'success' ], $response_data );
	}

	/**
	 * Test a valid failed refund update webhook for non-USD.
	 */
	public function test_valid_failed_refund_update_webhook_non_usd() {
		// Setup test request data.
		$this->request_body['type']           = 'charge.refund.updated';
		$this->request_body['data']['object'] = [
			'status'   => 'failed',
			'charge'   => 'test_charge_id',
			'id'       => 'test_refund_id',
			'amount'   => 999,
			'currency' => 'eur',
		];

		$this->request->set_body( wp_json_encode( $this->request_body ) );

		$mock_order = $this->getMockBuilder( WC_Order::class )
			->disableOriginalConstructor()
			->setMethods( [ 'add_order_note' ] )
			->getMock();

		$mock_order
			->expects( $this->once() )
			->method( 'add_order_note' )
			->with(
				$this->matchesRegularExpression(
					'~^A refund of <span class="woocommerce-Price-amount amount">(<bdi>)?<span class="woocommerce-Price-currencySymbol">&euro;</span>9.99(</bdi>)?</span> was <strong>unsuccessful</strong> using WooCommerce Payments \(<code>test_refund_id</code>\).$~'
				)
			);

		$this->mock_db_wrapper
			->expects( $this->once() )
			->method( 'order_from_charge_id' )
			->with( 'test_charge_id' )
			->willReturn( $mock_order );

		// Run the test.
		$response = $this->controller->handle_webhook( $this->request );

		// Check the response.
		$response_data = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( [ 'result' => 'success' ], $response_data );
	}

	/**
	 * Test a valid failed refund update webhook for zero decimal currency.
	 */
	public function test_valid_failed_refund_update_webhook_zero_decimal_currency() {
		// Setup test request data.
		$this->request_body['type']           = 'charge.refund.updated';
		$this->request_body['data']['object'] = [
			'status'   => 'failed',
			'charge'   => 'test_charge_id',
			'id'       => 'test_refund_id',
			'amount'   => 999,
			'currency' => 'jpy',
		];

		$this->request->set_body( wp_json_encode( $this->request_body ) );

		$mock_order = $this->getMockBuilder( WC_Order::class )
			->disableOriginalConstructor()
			->setMethods( [ 'add_order_note' ] )
			->getMock();

		$mock_order
			->expects( $this->once() )
			->method( 'add_order_note' )
			->with(
				$this->matchesRegularExpression(
					'~^A refund of <span class="woocommerce-Price-amount amount">(<bdi>)?<span class="woocommerce-Price-currencySymbol">&yen;</span>999(</bdi>)?</span> was <strong>unsuccessful</strong> using WooCommerce Payments \(<code>test_refund_id</code>\).$~'
				)
			);

		$this->mock_db_wrapper
			->expects( $this->once() )
			->method( 'order_from_charge_id' )
			->with( 'test_charge_id' )
			->willReturn( $mock_order );

		// Run the test.
		$response = $this->controller->handle_webhook( $this->request );

		// Check the response.
		$response_data = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( [ 'result' => 'success' ], $response_data );
	}

	/**
	 * Test a valid failed refund update webhook with an unknown charge ID.
	 */
	public function test_valid_failed_refund_update_webhook_with_unknown_charge_id() {
		// Setup test request data.
		$this->request_body['type']           = 'charge.refund.updated';
		$this->request_body['data']['object'] = [
			'status'   => 'failed',
			'charge'   => 'unknown_charge_id',
			'id'       => 'test_refund_id',
			'amount'   => 999,
			'currency' => 'gbp',
		];

		$this->request->set_body( wp_json_encode( $this->request_body ) );

		$this->mock_db_wrapper
			->expects( $this->once() )
			->method( 'order_from_charge_id' )
			->with( 'unknown_charge_id' )
			->willReturn( false );

		// Run the test.
		$response = $this->controller->handle_webhook( $this->request );

		// Check the response.
		$response_data = $response->get_data();

		$this->assertEquals( 500, $response->get_status() );
		$this->assertEquals( [ 'result' => 'error' ], $response_data );
	}

	/**
	 * Test a valid non-failed refund update webhook
	 */
	public function test_non_failed_refund_update_webhook() {
		// Setup test request data.
		$this->request_body['type']           = 'charge.refund.updated';
		$this->request_body['data']['object'] = [
			'status' => 'updated',
			'charge' => 'test_charge_id',
			'id'     => 'test_refund_id',
			'amount' => 999,
		];

		$this->request->set_body( wp_json_encode( $this->request_body ) );

		$this->mock_db_wrapper
			->expects( $this->never() )
			->method( 'order_from_charge_id' );

		// Run the test.
		$response = $this->controller->handle_webhook( $this->request );

		// Check the response.
		$response_data = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( [ 'result' => 'success' ], $response_data );
	}

	/**
	 * Tests that a remote note webhook puts the note in the inbox.
	 */
	public function test_remote_note_puts_note() {
		// Setup test request data.
		$this->request_body['type'] = 'wcpay.notification';
		$this->request_body['data'] = [
			'title'   => 'test',
			'content' => 'hello',
		];
		$this->request->set_body( wp_json_encode( $this->request_body ) );

		$this->mock_remote_note_service
			->expects( $this->once() )
			->method( 'put_note' )
			->with(
				[
					'title'   => 'test',
					'content' => 'hello',
				]
			);

		$response = $this->controller->handle_webhook( $this->request );

		$response_data = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( [ 'result' => 'success' ], $response_data );
	}

	/**
	 * Tests that a remote note webhook handles service exceptions.
	 */
	public function test_remote_note_fails_returns_response() {
		// Setup test request data.
		$this->request_body['type'] = 'wcpay.notification';
		$this->request_body['data'] = [
			'foo' => 'bar',
		];
		$this->request->set_body( wp_json_encode( $this->request_body ) );

		$this->mock_remote_note_service
			->expects( $this->once() )
			->method( 'put_note' )
			->willThrowException( new Rest_Request_Exception( 'Invalid note.' ) );

		$response = $this->controller->handle_webhook( $this->request );

		$response_data = $response->get_data();

		$this->assertEquals( 400, $response->get_status() );
		$this->assertEquals( [ 'result' => 'bad_request' ], $response_data );
	}

	/**
	 * Tests that an exception thrown in an action will be caught but webhook will still be handled successfully
	 */
	public function test_action_hook_exception_returns_response() {
		add_action(
			'woocommerce_payments_before_webhook_delivery',
			function() {
				throw new Exception( 'Crash before' );
			}
		);

		add_action(
			'woocommerce_payments_after_webhook_delivery',
			function() {
				throw new Exception( 'Crash after' );
			}
		);

		// Setup test request data.
		$this->request_body['type'] = 'wcpay.notification';
		$this->request_body['data'] = [
			'title'   => 'test',
			'content' => 'hello',
		];
		$this->request->set_body( wp_json_encode( $this->request_body ) );

		$this->mock_remote_note_service
			->expects( $this->once() )
			->method( 'put_note' )
			->with(
				[
					'title'   => 'test',
					'content' => 'hello',
				]
			);

		$response = $this->controller->handle_webhook( $this->request );

		$response_data = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( [ 'result' => 'success' ], $response_data );
	}
}
