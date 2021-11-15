<?php
/**
 * Class WC_REST_Payments_Webhook_Controller_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Exceptions\Rest_Request_Exception;

// Need to use WC_Mock_Data_Store.
require_once dirname( __FILE__, 2 ) . '/helpers/class-wc-mock-wc-data-store.php';

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
			->setMethods( [ 'order_from_charge_id', 'order_from_intent_id', 'order_from_order_id' ] )
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
	 * Test a valid refund sets failed meta.
	 */
	public function test_valid_failed_refund_webhook_sets_failed_meta() {
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

		$mock_order = $this->createMock( WC_Order::class );

		$mock_order->method( 'get_currency' )->willReturn( 'GBP' );

		$mock_order
			->expects( $this->once() )
			->method( 'add_order_note' )
			->with(
				$this->matchesRegularExpression(
					'~^A refund of <span class="woocommerce-Price-amount amount">(<bdi>)?<span class="woocommerce-Price-currencySymbol">&pound;</span>9.99(</bdi>)?</span> GBP was <strong>unsuccessful</strong> using WooCommerce Payments \(<code>test_refund_id</code>\).$~'
				)
			);

		// The expects condition here is the real test; we expect that the 'update_meta_data' function
		// is called with the appropriate values.
		$mock_order
			->expects( $this->once() )
			->method( 'update_meta_data' )
			->with( '_wcpay_refund_status', 'failed' );

		$this->mock_db_wrapper
			->expects( $this->once() )
			->method( 'order_from_charge_id' )
			->with( 'test_charge_id' )
			->willReturn( $mock_order );

		// Run the test.
		$this->controller->handle_webhook( $this->request );
	}

	/**
	 * Test a valid refund does not set failed meta.
	 */
	public function test_non_failed_refund_update_webhook_does_not_set_failed_meta() {
		// Setup test request data.
		$this->request_body['type']           = 'charge.refund.updated';
		$this->request_body['data']['object'] = [
			'status' => 'success',
		];

		$this->request->set_body( wp_json_encode( $this->request_body ) );

		$mock_order = $this->createMock( WC_Order::class );

		$this->mock_db_wrapper
			->expects( $this->never() )
			->method( 'order_from_charge_id' );

		// The expects condition here is the real test; we expect that the 'update_meta_data' function
		// is never called to update the meta data.
		$mock_order
			->expects( $this->never() )
			->method( 'update_meta_data' );

		// Run the test.
		$this->controller->handle_webhook( $this->request );
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

		$mock_order = $this->createMock( WC_Order::class );

		$mock_order->method( 'get_currency' )->willReturn( 'GBP' );

		$mock_order
			->expects( $this->once() )
			->method( 'add_order_note' )
			->with(
				$this->matchesRegularExpression(
					'~^A refund of <span class="woocommerce-Price-amount amount">(<bdi>)?<span class="woocommerce-Price-currencySymbol">&pound;</span>9.99(</bdi>)?</span> GBP was <strong>unsuccessful</strong> using WooCommerce Payments \(<code>test_refund_id</code>\).$~'
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

		$mock_order = $this->createMock( WC_Order::class );

		$mock_order->method( 'get_currency' )->willReturn( 'GBP' );

		$mock_order
			->expects( $this->once() )
			->method( 'add_order_note' )
			->with(
				$this->matchesRegularExpression(
					'~^A refund of <span class="woocommerce-Price-amount amount">(<bdi>)?<span class="woocommerce-Price-currencySymbol">&euro;</span>9.99(</bdi>)?</span> GBP was <strong>unsuccessful</strong> using WooCommerce Payments \(<code>test_refund_id</code>\).$~'
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

		$mock_order = $this->createMock( WC_Order::class );

		$mock_order->method( 'get_currency' )->willReturn( 'GBP' );

		$mock_order
			->expects( $this->once() )
			->method( 'add_order_note' )
			->with(
				$this->matchesRegularExpression(
					'~^A refund of <span class="woocommerce-Price-amount amount">(<bdi>)?<span class="woocommerce-Price-currencySymbol">&yen;</span>999.00(</bdi>)?</span> GBP was <strong>unsuccessful</strong> using WooCommerce Payments \(<code>test_refund_id</code>\).$~'
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

	/**
	 * Tests that a payment_intent.succeeded event will complete the order.
	 */
	public function test_payment_intent_successful_and_completes_order() {
		$this->request_body['type']           = 'payment_intent.succeeded';
		$this->request_body['data']['object'] = [
			'id'       => 'pi_123123123123123', // payment_intent's ID.
			'object'   => 'payment_intent',
			'amount'   => 1500,
			'charges'  => [],
			'currency' => 'eur',
		];

		$this->request->set_body( wp_json_encode( $this->request_body ) );

		$mock_order = $this->createMock( WC_Order::class );

		$mock_order
			->expects( $this->once() )
			->method( 'has_status' )
			->with( [ 'processing', 'completed' ] )
			->willReturn( false );

		$mock_order
			->expects( $this->once() )
			->method( 'payment_complete' );

		$this->mock_db_wrapper
			->expects( $this->once() )
			->method( 'order_from_intent_id' )
			->with( 'pi_123123123123123' )
			->willReturn( $mock_order );

		$mock_order
			->method( 'get_data_store' )
			->willReturn( new \WC_Mock_WC_Data_Store() );

		// Run the test.
		$response = $this->controller->handle_webhook( $this->request );

		// Check the response.
		$response_data = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( [ 'result' => 'success' ], $response_data );
	}

	/**
	 * Tests that a payment_intent.succeeded event will complete the order even if the intent was not properly attached into the order.
	 */
	public function test_payment_intent_successful_and_completes_order_without_intent_id() {
		$this->request_body['type']           = 'payment_intent.succeeded';
		$this->request_body['data']['object'] = [
			'id'       => 'pi_123123123123123', // payment_intent's ID.
			'object'   => 'payment_intent',
			'amount'   => 1500,
			'charges'  => [],
			'currency' => 'eur',
			'metadata' => [ 'order_id' => 'id_1323' ], // Using order_id inside of the intent metadata to find the order.
		];

		$this->request->set_body( wp_json_encode( $this->request_body ) );

		$mock_order = $this->createMock( WC_Order::class );

		$mock_order
			->expects( $this->once() )
			->method( 'has_status' )
			->with( [ 'processing', 'completed' ] )
			->willReturn( false );

		$mock_order
			->expects( $this->once() )
			->method( 'payment_complete' );

		$this->mock_db_wrapper
			->expects( $this->once() )
			->method( 'order_from_intent_id' )
			->with( 'pi_123123123123123' )
			->willReturn( null );

		$this->mock_db_wrapper
			->expects( $this->once() )
			->method( 'order_from_order_id' )
			->with( 'id_1323' )
			->willReturn( $mock_order );

		$mock_order
			->method( 'get_data_store' )
			->willReturn( new \WC_Mock_WC_Data_Store() );

		// Run the test.
		$response = $this->controller->handle_webhook( $this->request );

		// Check the response.
		$response_data = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( [ 'result' => 'success' ], $response_data );
	}

	/**
	 * Tests that a payment_intent.succeeded event will not complete the order
	 * if it is already completed/processed.
	 */
	public function test_payment_intent_successful_when_retrying() {
		$this->request_body['type']           = 'payment_intent.succeeded';
		$this->request_body['data']['object'] = [
			'id'       => 'pi_123123123123123', // payment_intent's ID.
			'object'   => 'payment_intent',
			'amount'   => 1500,
			'charges'  => [],
			'currency' => 'eur',
		];

		$this->request->set_body( wp_json_encode( $this->request_body ) );

		$mock_order = $this->createMock( WC_Order::class );

		$mock_order
			->expects( $this->once() )
			->method( 'has_status' )
			->with( [ 'processing', 'completed' ] )
			->willReturn( true );

		$mock_order
			->expects( $this->never() )
			->method( 'payment_complete' );

		$this->mock_db_wrapper
			->expects( $this->once() )
			->method( 'order_from_intent_id' )
			->with( 'pi_123123123123123' )
			->willReturn( $mock_order );

		$mock_order
			->method( 'get_data_store' )
			->willReturn( new \WC_Mock_WC_Data_Store() );

		// Run the test.
		$response = $this->controller->handle_webhook( $this->request );

		// Check the response.
		$response_data = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( [ 'result' => 'success' ], $response_data );
	}

	/**
	 * Tests that an invoice upoming event creates invoice items for subscription.
	 */
	public function test_invoice_upcoming_webhook() {
		// Stub.
		$this->assertTrue( true );
	}

	/**
	 * Tests that an invoice paid event renews a subscription.
	 */
	public function test_invoice_paid_webhook() {
		// Stub.
		$this->assertTrue( true );
	}

	/**
	 * Tests that an invoice payment failed event places a subscription on-hold.
	 */
	public function test_invoice_payment_failed_webhook() {
		// Stub.
		$this->assertTrue( true );
	}

	/**
	 * Tests that a dispute created event adds a respective order note.
	 */
	public function test_dispute_created_order_note() {
		// Setup test request data.
		$this->request_body['type']           = 'charge.dispute.created';
		$this->request_body['data']['object'] = [
			'id'     => 'test_dispute_id',
			'charge' => 'test_charge_id',
			'reason' => 'test_reason',
		];

		$this->request->set_body( wp_json_encode( $this->request_body ) );

		$mock_order = $this->createMock( WC_Order::class );
		$mock_order
			->expects( $this->once() )
			->method( 'add_order_note' )
			->with(
				$this->matchesRegularExpression(
					'/Payment has been disputed as test_reason/'
				)
			);

		$mock_order
			->expects( $this->once() )
			->method( 'update_status' )
			->with( 'on-hold' );

		$this->mock_db_wrapper
			->expects( $this->once() )
			->method( 'order_from_charge_id' )
			->with( 'test_charge_id' )
			->willReturn( $mock_order );

		// Run the test.
		$this->controller->handle_webhook( $this->request );
	}

	/**
	 * Tests that a dispute closed event adds a respective order note.
	 */
	public function test_dispute_closed_order_note() {
		// Setup test request data.
		$this->request_body['type']           = 'charge.dispute.closed';
		$this->request_body['data']['object'] = [
			'id'     => 'test_dispute_id',
			'charge' => 'test_charge_id',
			'status' => 'test_status',
		];

		$this->request->set_body( wp_json_encode( $this->request_body ) );

		$mock_order = $this->createMock( WC_Order::class );
		$mock_order
			->expects( $this->once() )
			->method( 'add_order_note' )
			->with(
				$this->matchesRegularExpression(
					'/Payment dispute has been closed with status test_status/'
				)
			);

		$mock_order
			->expects( $this->once() )
			->method( 'update_status' )
			->with( 'completed' );

		$this->mock_db_wrapper
			->expects( $this->once() )
			->method( 'order_from_charge_id' )
			->with( 'test_charge_id' )
			->willReturn( $mock_order );

		// Run the test.
		$this->controller->handle_webhook( $this->request );
	}

	/**
	 * Tests that a dispute updated event adds a respective order note.
	 */
	public function test_dispute_updated_order_note() {
		// Setup test request data.
		$this->request_body['type']           = 'charge.dispute.updated';
		$this->request_body['data']['object'] = [
			'id'     => 'test_dispute_id',
			'charge' => 'test_charge_id',
		];

		$this->request->set_body( wp_json_encode( $this->request_body ) );

		$mock_order = $this->createMock( WC_Order::class );
		$mock_order
			->expects( $this->once() )
			->method( 'add_order_note' )
			->with(
				$this->matchesRegularExpression(
					'/Payment dispute has been updated/'
				)
			);

		$this->mock_db_wrapper
			->expects( $this->once() )
			->method( 'order_from_charge_id' )
			->with( 'test_charge_id' )
			->willReturn( $mock_order );

		// Run the test.
		$this->controller->handle_webhook( $this->request );
	}

	/**
	 * Tests that a dispute funds withdrawn event adds a respective order note.
	 */
	public function test_dispute_funds_withdrawn_order_note() {
		// Setup test request data.
		$this->request_body['type']           = 'charge.dispute.funds_withdrawn';
		$this->request_body['data']['object'] = [
			'id'     => 'test_dispute_id',
			'charge' => 'test_charge_id',
		];

		$this->request->set_body( wp_json_encode( $this->request_body ) );

		$mock_order = $this->createMock( WC_Order::class );
		$mock_order
			->expects( $this->once() )
			->method( 'add_order_note' )
			->with(
				$this->matchesRegularExpression(
					'/Payment dispute funds have been withdrawn/'
				)
			);

		$this->mock_db_wrapper
			->expects( $this->once() )
			->method( 'order_from_charge_id' )
			->with( 'test_charge_id' )
			->willReturn( $mock_order );

		// Run the test.
		$this->controller->handle_webhook( $this->request );
	}

	/**
	 * Tests that a dispute funds reinstated event adds a respective order note.
	 */
	public function test_dispute_funds_reinstated_order_note() {
		// Setup test request data.
		$this->request_body['type']           = 'charge.dispute.funds_reinstated';
		$this->request_body['data']['object'] = [
			'id'     => 'test_dispute_id',
			'charge' => 'test_charge_id',
		];

		$this->request->set_body( wp_json_encode( $this->request_body ) );

		$mock_order = $this->createMock( WC_Order::class );
		$mock_order
			->expects( $this->once() )
			->method( 'add_order_note' )
			->with(
				$this->matchesRegularExpression(
					'/Payment dispute funds have been reinstated/'
				)
			);

		$this->mock_db_wrapper
			->expects( $this->once() )
			->method( 'order_from_charge_id' )
			->with( 'test_charge_id' )
			->willReturn( $mock_order );

		// Run the test.
		$this->controller->handle_webhook( $this->request );
	}
}
