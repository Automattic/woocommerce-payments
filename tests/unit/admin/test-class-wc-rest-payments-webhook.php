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

		// There are checks for is_admin() along with tests for test requests, this allows them to pass.
		set_current_screen( 'admin' );

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

	public function tearDown() {
		set_current_screen( 'front' );
		parent::tearDown();
	}

}
