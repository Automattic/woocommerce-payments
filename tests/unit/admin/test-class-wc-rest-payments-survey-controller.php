<?php
/**
 * Class WC_REST_Payments_Survey_Controller_Test
 *
 * @package WooCommerce\Payments\Tests
 */

/**
 * WC_REST_Payments_Survey_Controller unit tests.
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
	private $http_client_mock;

	/**
	 * Pre-test setup.
	 */
	public function setUp(): void {
		parent::setUp();

		global $wp_rest_server;
		$wp_rest_server = null;

		wp_set_current_user( 1 );

		$this->http_client_mock = $this->getMockBuilder( WC_Payments_Http::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'get_blog_id', 'wpcom_json_api_request_as_user' ] )
			->getMock();
		$this->controller       = new WC_REST_Payments_Survey_Controller( $this->http_client_mock );
	}

	public function tearDown(): void {
		remove_all_filters( 'pre_option_' . WC_Payments_Features::REPORTS_AREA_FLAG_NAME );

		global $wp_rest_server;
		$wp_rest_server = null;

		parent::tearDown();
	}

	public function test_register_routes_adds_reports_feedback_route_when_reports_area_enabled() {
		add_filter( 'pre_option_' . WC_Payments_Features::REPORTS_AREA_FLAG_NAME, [ $this, 'return_enabled_flag' ] );
		$this->setExpectedIncorrectUsage( 'register_rest_route' );

		$this->controller->register_routes();

		$routes = rest_get_server()->get_routes();

		$this->assertArrayHasKey( '/wc/v3/payments/survey/reports-feedback', $routes );
	}

	public function test_controller_extends_wc_payments_rest_controller() {
		$this->assertInstanceOf( WC_Payments_REST_Controller::class, $this->controller );
	}

	public function test_register_routes_returns_early_when_reports_area_disabled() {
		add_filter( 'pre_option_' . WC_Payments_Features::REPORTS_AREA_FLAG_NAME, [ $this, 'return_disabled_flag' ] );

		$this->controller->register_routes();

		$routes = rest_get_server()->get_routes();

		foreach ( array_keys( $routes ) as $route ) {
			$this->assertStringNotContainsString( '/wc/v3/payments/survey/reports-feedback', $route );
		}
	}

	public function test_empty_request_returns_400_status_code() {
		$request = new WP_REST_Request( 'POST', self::ROUTE . '/reports-feedback' );

		$response = $this->controller->submit_reports_feedback_survey( $request );

		$this->assertSame( 400, $response->get_status() );
	}

	public function test_whitespace_only_request_returns_400_status_code() {
		$this->http_client_mock
			->expects( $this->never() )
			->method( 'wpcom_json_api_request_as_user' );

		$request = new WP_REST_Request( 'POST', self::ROUTE . '/reports-feedback' );
		$request->set_body_params(
			[
				'rating'   => '   ',
				'comments' => " \t\n ",
			]
		);

		$response = $this->controller->submit_reports_feedback_survey( $request );

		$this->assertSame( 400, $response->get_status() );
	}

	public function test_request_without_rating_returns_400_status_code() {
		$this->http_client_mock
			->expects( $this->never() )
			->method( 'wpcom_json_api_request_as_user' );

		$request = new WP_REST_Request( 'POST', self::ROUTE . '/reports-feedback' );
		$request->set_body_params(
			[
				'comments' => 'only text',
			]
		);

		$response = $this->controller->submit_reports_feedback_survey( $request );

		$this->assertSame( 400, $response->get_status() );
	}

	public function test_valid_request_forwards_data_to_wpcom() {
		$this->http_client_mock
			->expects( $this->once() )
			->method( 'get_blog_id' )
			->willReturn( 123 );

		$this->http_client_mock
			->expects( $this->once() )
			->method( 'wpcom_json_api_request_as_user' )
			->with(
				$this->stringContains( '/marketing/survey' ),
				$this->anything(),
				$this->anything(),
				$this->callback(
					function ( $argument ) {
						return 123 === $argument['site_id']
							&& 'wcpay-reports-feedback' === $argument['survey_id']
							&& 'thumbs-up' === $argument['survey_responses']['rating']
							&& 'test comment' === $argument['survey_responses']['comments']['text'];
					}
				)
			)
			->willReturn(
				[
					'body'     => '{"err": ""}',
					'response' => [ 'code' => 200 ],
				]
			);

		$request = new WP_REST_Request( 'POST', self::ROUTE . '/reports-feedback' );
		$request->set_body_params(
			[
				'rating'   => 'thumbs-up',
				'comments' => 'test comment',
			]
		);

		$response = $this->controller->submit_reports_feedback_survey( $request );

		$this->assertSame( 200, $response->get_status(), wp_json_encode( $response->get_data() ) );
	}

	public function test_wpcom_error_returns_500_status_code() {
		$this->http_client_mock
			->expects( $this->once() )
			->method( 'get_blog_id' )
			->willReturn( 123 );

		$this->http_client_mock
			->expects( $this->once() )
			->method( 'wpcom_json_api_request_as_user' )
			->willReturn( new WP_Error( 'http_request_failed', 'Connection timeout' ) );

		$request = new WP_REST_Request( 'POST', self::ROUTE . '/reports-feedback' );
		$request->set_body_params(
			[
				'rating'   => 'thumbs-up',
				'comments' => 'test comment',
			]
		);

		$response = $this->controller->submit_reports_feedback_survey( $request );
		$data     = $response->get_data();

		$this->assertSame( 500, $response->get_status() );
		$this->assertNotEmpty( $data['err'] );
	}

	public function test_check_permission_rejects_logged_out_users() {
		wp_set_current_user( 0 );

		$this->assertFalse( $this->controller->check_permission() );
	}

	public function test_rest_dispatch_rejects_invalid_rating() {
		$this->register_reports_feedback_route();

		$request = new WP_REST_Request( 'POST', self::ROUTE . '/reports-feedback' );
		$request->set_body_params(
			[
				'rating'   => 'neutral',
				'comments' => 'test comment',
			]
		);

		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 400, $response->get_status() );
	}

	public function test_rest_dispatch_preserves_apostrophes_in_comments() {
		$this->register_reports_feedback_route();
		$forwarded_body = null;

		$this->http_client_mock
			->expects( $this->once() )
			->method( 'get_blog_id' )
			->willReturn( 123 );

		$this->http_client_mock
			->expects( $this->once() )
			->method( 'wpcom_json_api_request_as_user' )
			->with(
				$this->anything(),
				$this->anything(),
				$this->anything(),
				$this->callback(
					function ( $argument ) use ( &$forwarded_body ) {
						$forwarded_body = $argument;
						return true;
					}
				)
			)
			->willReturn(
				[
					'body'     => '{"err": ""}',
					'response' => [ 'code' => 200 ],
				]
			);

		$request = new WP_REST_Request( 'POST', self::ROUTE . '/reports-feedback' );
		$request->set_body_params(
			[
				'rating'   => 'thumbs-up',
				'comments' => "it's great",
			]
		);

		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status(), wp_json_encode( $response->get_data() ) );
		$this->assertSame( "it's great", $forwarded_body['survey_responses']['comments']['text'] );
	}

	public function return_enabled_flag() {
		return '1';
	}

	public function return_disabled_flag() {
		return '0';
	}

	private function register_reports_feedback_route() {
		rest_get_server();

		add_filter( 'pre_option_' . WC_Payments_Features::REPORTS_AREA_FLAG_NAME, [ $this, 'return_enabled_flag' ] );

		$this->controller->register_routes();
	}
}
