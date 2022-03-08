<?php
/**
 * Class WC_REST_Payments_Webhook_Controller_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Exceptions\Invalid_Webhook_Data_Exception;

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
	public function set_up() {
		parent::set_up();

		// There are checks for is_admin() along with tests for test requests, this allows them to pass.
		set_current_screen( 'admin' );

		// Set the user so that we can pass the authentication.
		wp_set_current_user( 1 );

		/** @var WC_Payments_API_Client|MockObject $mock_api_client */
		$mock_api_client = $this->getMockBuilder( WC_Payments_API_Client::class )
			->disableOriginalConstructor()
			->getMock();

		$this->mock_webhook_processing_service = $this->createMock( WC_Payments_Webhook_Processing_Service::class );

		$this->controller = new WC_REST_Payments_Webhook_Controller( $mock_api_client, $this->mock_webhook_processing_service );

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

		$this->request->set_body( wp_json_encode( $this->request_body ) );
	}

	public function tear_down() {
		set_current_screen( 'front' );
		parent::tear_down();
	}

	public function test_handle_webhook_returns_success_message() {
		$this->mock_webhook_processing_service
			->expects( $this->once() )
			->method( 'process' );

		// Run the test.
		$response = $this->controller->handle_webhook( $this->request );

		// Check the response.
		$response_data = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( [ 'result' => 'success' ], $response_data );
	}

	public function test_handle_webhook_with_invalid_data_returns_bad_request_message() {
		$this->mock_webhook_processing_service
			->expects( $this->once() )
			->method( 'process' )
			->willThrowException( new Invalid_Webhook_Data_Exception( 'message' ) );

		// Run the test.
		$response = $this->controller->handle_webhook( $this->request );

		// Check the response.
		$response_data = $response->get_data();

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( [ 'result' => 'bad_request' ], $response_data );
	}

	public function test_handle_webhook_returns_error_message() {
		$this->mock_webhook_processing_service
			->expects( $this->once() )
			->method( 'process' )
			->willThrowException( new Exception( 'message' ) );

		// Run the test.
		$response = $this->controller->handle_webhook( $this->request );

		// Check the response.
		$response_data = $response->get_data();

		$this->assertSame( 500, $response->get_status() );
		$this->assertSame( [ 'result' => 'error' ], $response_data );
	}
}
