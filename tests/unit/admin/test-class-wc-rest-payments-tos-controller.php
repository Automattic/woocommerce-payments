<?php
/**
 * Class WC_REST_Payments_Tos_Controller_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;

/**
 * WC_REST_Payments_Tos_Controller unit tests.
 */
class WC_REST_Payments_Tos_Controller_Test extends WP_UnitTestCase {

	/**
	 * The system under test.
	 *
	 * @var WC_REST_Payments_Tos_Controller
	 */
	private $controller;

	/**
	 * Gateway.
	 *
	 * @var WC_Payment_Gateway_WCPay
	 */
	private $gateway;


	/**
	 * @var WP_REST_Request
	 */
	private $request;

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

		$mock_rate_limiter = $this->getMockBuilder( Session_Rate_Limiter::class )
			->disableOriginalConstructor()
			->getMock();

		$account                  = new WC_Payments_Account( $mock_api_client );
		$customer_service         = new WC_Payments_Customer_Service( $mock_api_client, $account );
		$token_service            = new WC_Payments_Token_Service( $mock_api_client, $customer_service );
		$action_scheduler_service = new WC_Payments_Action_Scheduler_Service( $mock_api_client );
		$order_service            = new WC_Payments_Order_Service();

		$this->gateway    = new WC_Payment_Gateway_WCPay(
			$mock_api_client,
			$account,
			$customer_service,
			$token_service,
			$action_scheduler_service,
			$mock_rate_limiter,
			$order_service
		);
		$this->controller = new WC_REST_Payments_Tos_Controller( $mock_api_client, $this->gateway, $account );

		// Setup a test request.
		$this->request = new WP_REST_Request(
			'POST',
			'/wc/v3/payments/tos'
		);

		$this->request->set_header( 'Content-Type', 'application/json' );
	}

	public function test_gateway_disabled_on_tos_declined() {
		$this->gateway->enable();
		$this->request->set_body( wp_json_encode( [ 'accept' => false ] ) );

		// Run the test.
		$response = $this->controller->handle_tos( $this->request );

		// Check the response.
		$response_data = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( [ 'result' => 'success' ], $response_data );
		$this->assertEquals( 'no', $this->gateway->get_option( 'enabled' ) );
	}

	public function test_gateway_enabled_on_tos_accepted() {
		$this->gateway->disable();
		$this->request->set_body( wp_json_encode( [ 'accept' => true ] ) );

		// Run the test.
		$response = $this->controller->handle_tos( $this->request );

		// Check the response.
		$response_data = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( [ 'result' => 'success' ], $response_data );
		$this->assertEquals( 'yes', $this->gateway->get_option( 'enabled' ) );
	}

	public function test_accept_argument_is_required() {
		$this->request->set_body( wp_json_encode( [ 'key' => 'value' ] ) );

		// Run the test.
		$response = $this->controller->handle_tos( $this->request );

		// Check the response.
		$response_data = $response->get_data();

		$this->assertEquals( 400, $response->get_status() );
		$this->assertEquals( [ 'result' => 'bad_request' ], $response_data );
	}

	public function test_remove_stripe_connect_track_should_delete_option() {
		add_option( '_wcpay_onboarding_stripe_connected', [ 'props' => true ] );

		// Run the test.
		$this->controller->remove_stripe_connect_track( $this->request );

		$this->assertFalse( get_option( '_wcpay_onboarding_stripe_connected', false ) );
	}
}
