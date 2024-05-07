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
	const ROUTE = '/wc/v3/payments/survey';

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
	public function setUp(): void {
		parent::setUp();

		// Set the user so that we can pass the authentication.
		wp_set_current_user( 1 );

		$this->http_client_stub = $this->getMockBuilder( WC_Payments_Http::class )->disableOriginalConstructor()->setMethods( [ 'remote_request' ] )->getMock();
		$this->controller       = new WC_REST_Payments_Survey_Controller( $this->http_client_stub );
	}

	public function test_empty_request_returns_400_status_code() {
		$request = new WP_REST_Request( 'POST', self::ROUTE . '/payments-overview' );

		$response = $this->controller->submit_payments_overview_survey( $request );

		$this->assertEquals( 400, $response->get_status() );
	}

	public function test_empty_rating_returns_400_status_code() {
		$request = new WP_REST_Request( 'POST', self::ROUTE . '/payments-overview' );
		$request->set_body_params(
			[
				'comments' => 'test comment',
			]
		);

		$response = $this->controller->submit_payments_overview_survey( $request );

		$this->assertEquals( 400, $response->get_status() );
	}


	public function test_valid_request_forwards_data_to_jetpack() {
		$request_url = WC_Payments_API_Client::ENDPOINT_BASE . '/marketing/survey';

		$this->http_client_stub
			->expects( $this->any() )
			->method( 'remote_request' )
			->with(
				// Check the request argument URL is the same.
				$this->callback(
					function ( $argument ) use ( $request_url ) {
						return $request_url === $argument['url'];
					}
				),
				$this->logicalAnd(
					$this->callback(
						function ( $argument ) {
							$json_body = json_decode( $argument, true );
							return 'wcpay-payment-activity' === $json_body['survey_id'];
						}
					),
					$this->callback(
						function ( $argument ) {
							$json_body = json_decode( $argument, true );
							return 'happy' === $json_body['survey_responses']['rating'];
						}
					),
					$this->callback(
						function ( $argument ) {
							$json_body = json_decode( $argument, true );
							return 'test comment' === $json_body['survey_responses']['comments']['text'];
						}
					),
				),
				$this->isTrue(),
				$this->isTrue(),
			)
			->willReturn(
				[
					'body'     => '{"err": ""}',
					'response' => [ 'code' => 200 ],
				]
			);

		$request = new WP_REST_Request( 'POST', self::ROUTE . '/payments-overview' );
		$request->set_body_params(
			[
				'rating'   => 'happy',
				'comments' => 'test comment',
			]
		);

		$response = $this->controller->submit_payments_overview_survey( $request );

		$this->assertEquals( 200, $response->get_status() );
	}
}
