<?php
/**
 * Class WC_REST_Payments_Survey_Controller_Test
 *
 * @package WooCommerce\Payments\Tests
 */

/**
 * WC_REST_Payments_Survey_Controller_Test unit tests.
 */
class WC_REST_Payments_Survey_Controller_Test extends WP_UnitTestCase {

	/**
	 * Tested REST route.
	 */
	const ROUTE = '/wc/v3/payments/upe_survey';

	/**
	 * The system under test.
	 *
	 * @var WC_REST_Payments_Survey_Controller
	 */
	private $controller;

	/**
	 * The HTTP client.
	 *
	 * @var WC_Payments_Http
	 */
	private $http_client_stub;

	/**
	 * Pre-test setup
	 */
	public function setUp() {
		parent::setUp();

		// Set the user so that we can pass the authentication.
		wp_set_current_user( 1 );

		$this->http_client_stub = $this->getMockBuilder( WC_Payments_Http::class )->disableOriginalConstructor()->setMethods( [ 'wpcom_json_api_request_as_user' ] )->getMock();
		$this->controller       = new WC_REST_Payments_Survey_Controller( $this->http_client_stub );
	}

	public function test_empty_request_returns_400_status_code() {
		$request  = new WP_REST_Request( 'POST', self::ROUTE );
		$response = rest_do_request( $request );

		$this->assertEquals( 400, $response->get_status() );
	}

	public function test_valid_request_forwards_data_to_jetpack() {
		$request = new WP_REST_Request( 'POST', self::ROUTE );
		$request->set_body_params(
			[
				'reasons'  => [ 'slow-buggy', 'store-sales' ],
				'comments' => 'test comment',
			]
		);

		/**
		 * '/marketing/survey',
		 * '2',
		 * array(
		 * 'method'  => 'POST',
		 * 'headers' => array(
		 * 'Content-Type'    => 'application/json',
		 * 'X-Forwarded-For' => $this->get_current_user_ip(),
		 * ),
		 * ),
		 * [
		 * 'site_id'          => Jetpack_Options::get_option( 'id' ),
		 * 'survey_id'        => 'wcpay-disable-upe-early-access',
		 * 'survey_responses' => $survey_responses,
		 * ]
		 */

		$this->http_client_stub->expects( $this->once() )
							   ->method( 'wpcom_json_api_request_as_user' )
							->with(
								$this->stringContains( '/marketing/survey' ),
								$this->anything(),
								$this->anything(),
								$this->anything()
							)
							->willReturn(
								[
									'body'     => '{"err": ""}',
									'response' => [ 'code' => 200 ],
								]
							);

		$response = rest_do_request( $request );

		$this->assertEquals( 200, $response->get_status() );
	}
}
