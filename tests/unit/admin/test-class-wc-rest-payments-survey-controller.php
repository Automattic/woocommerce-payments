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
		$request = new WP_REST_Request( 'POST', self::ROUTE );

		$response = $this->controller->submit_survey( $request );

		$this->assertEquals( 400, $response->get_status() );
	}

	public function test_valid_request_forwards_data_to_jetpack() {
		$this->http_client_stub
			->expects( $this->any() )
			->method( 'wpcom_json_api_request_as_user' )
			->with(
				$this->stringContains( '/marketing/survey' ),
				$this->anything(),
				$this->anything(),
				$this->logicalAnd(
					$this->arrayHasKey( 'survey_id' ),
					$this->arrayHasKey( 'survey_responses' ),
					$this->callback(
						function ( $argument ) {
							return 'wcpay-upe-disable-early-access' === $argument['survey_id'];
						}
					),
					$this->callback(
						function ( $argument ) {
							return 'slow-buggy' === $argument['survey_responses']['why-disable'];
						}
					),
					$this->callback(
						function ( $argument ) {
							return 'test comment' === $argument['survey_responses']['comments']['text'];
						}
					)
				)
			)
			->willReturn(
				[
					'body'     => '{"err": ""}',
					'response' => [ 'code' => 200 ],
				]
			);

		$request = new WP_REST_Request( 'POST', self::ROUTE );
		$request->set_body_params(
			[
				'why-disable' => 'slow-buggy',
				'comments'    => 'test comment',
			]
		);

		$response = $this->controller->submit_survey( $request );

		$this->assertEquals( 200, $response->get_status() );
	}
}
