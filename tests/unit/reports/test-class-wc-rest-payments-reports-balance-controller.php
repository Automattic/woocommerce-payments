<?php
/**
 * Class WC_REST_Payments_Reports_Balance_Controller_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Core\Server\Request\Get_Reporting_Balance_Summary;
use WCPay\Exceptions\API_Exception;

/**
 * WC_REST_Payments_Reports_Balance_Controller unit tests.
 */
class WC_REST_Payments_Reports_Balance_Controller_Test extends WCPAY_UnitTestCase {
	/**
	 * Controller under test.
	 *
	 * @var WC_REST_Payments_Reports_Balance_Controller
	 */
	private $controller;

	/**
	 * @var WC_Payments_API_Client|MockObject
	 */
	private $mock_api_client;

	/**
	 * Filter callback registered by a test and removed in tear down.
	 *
	 * @var callable|null
	 */
	private $create_request_filter;

	public function set_up() {
		parent::set_up();

		global $wp_rest_server;
		$wp_rest_server = null;

		require_once WCPAY_ABSPATH . 'tests/unit/fixtures/reports-balance-summary.php';

		$this->mock_api_client = $this->createMock( WC_Payments_API_Client::class );
		$this->controller      = new WC_REST_Payments_Reports_Balance_Controller( $this->mock_api_client );
	}

	public function tear_down() {
		remove_all_filters( 'pre_option_' . WC_Payments_Features::REPORTS_AREA_FLAG_NAME );
		if ( null !== $this->create_request_filter ) {
			remove_filter( 'wcpay_create_request', $this->create_request_filter );
			$this->create_request_filter = null;
		}

		global $wp_rest_server;
		$wp_rest_server = null;

		parent::tear_down();
	}

	public function test_register_routes_adds_balance_route_when_reports_area_enabled() {
		add_filter( 'pre_option_' . WC_Payments_Features::REPORTS_AREA_FLAG_NAME, [ $this, 'return_enabled_flag' ] );
		$this->setExpectedIncorrectUsage( 'register_rest_route' );

		$this->controller->register_routes();

		$routes = rest_get_server()->get_routes();

		$this->assertArrayHasKey( '/wc/v3/payments/reports/balance', $routes );
	}

	public function test_register_routes_returns_early_when_reports_area_disabled() {
		add_filter( 'pre_option_' . WC_Payments_Features::REPORTS_AREA_FLAG_NAME, [ $this, 'return_disabled_flag' ] );

		$this->controller->register_routes();

		$routes = rest_get_server()->get_routes();

		foreach ( array_keys( $routes ) as $route ) {
			$this->assertStringNotContainsString( '/wc/v3/payments/reports/balance', $route );
		}
	}

	public function test_get_collection_params_requires_balance_query_args() {
		$params = $this->controller->get_collection_params();

		// Only assert behaviors that aren't already covered by the
		// full-stack REST dispatch tests below: the `required` booleans
		// (which determine 400 vs. null for missing params) and the
		// endpoint-specific currency callbacks.
		$this->assertTrue( $params['date_start']['required'] );
		$this->assertIsCallable( $params['date_start']['sanitize_callback'] );

		$this->assertTrue( $params['date_end']['required'] );
		$this->assertIsCallable( $params['date_end']['sanitize_callback'] );

		$this->assertTrue( $params['currency']['required'] );
		$this->assertSame( 'usd', $params['currency']['sanitize_callback']( ' USD ' ) );
		$this->assertTrue( $params['currency']['validate_callback']( 'usd' ) );
		$this->assertTrue( $params['currency']['validate_callback']( 'USD' ) );
		$this->assertWPError( $params['currency']['validate_callback']( 'usd1' ) );
	}

	/**
	 * @dataProvider invalid_balance_request_provider
	 *
	 * @param array $params Request params.
	 */
	public function test_balance_route_rejects_invalid_args( array $params ) {
		add_filter( 'pre_option_' . WC_Payments_Features::REPORTS_AREA_FLAG_NAME, [ $this, 'return_enabled_flag' ] );
		$this->setExpectedIncorrectUsage( 'register_rest_route' );
		$this->controller->register_routes();
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		$request = new WP_REST_Request( 'GET', '/wc/v3/payments/reports/balance' );
		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}

		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 400, $response->get_status() );
	}

	public function test_balance_route_returns_descriptive_error_for_invalid_currency() {
		add_filter( 'pre_option_' . WC_Payments_Features::REPORTS_AREA_FLAG_NAME, [ $this, 'return_enabled_flag' ] );
		$this->setExpectedIncorrectUsage( 'register_rest_route' );
		$this->controller->register_routes();
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		$request = new WP_REST_Request( 'GET', '/wc/v3/payments/reports/balance' );
		$request->set_param( 'date_start', '2024-03-01T00:00:00.000Z' );
		$request->set_param( 'date_end', '2024-03-31T23:59:59.999Z' );
		$request->set_param( 'currency', 'usd1' );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 400, $response->get_status() );
		$this->assertStringContainsString( 'ISO 4217 three-letter code', $data['data']['params']['currency'] );
	}

	public function invalid_balance_request_provider(): array {
		return [
			'missing date_start' => [
				[
					'date_end' => '2024-03-31T23:59:59',
					'currency' => 'usd',
				],
			],
			'missing date_end'   => [
				[
					'date_start' => '2024-03-01T00:00:00',
					'currency'   => 'usd',
				],
			],
			'missing currency'   => [
				[
					'date_start' => '2024-03-01T00:00:00',
					'date_end'   => '2024-03-31T23:59:59',
				],
			],
		];
	}

	public function test_balance_route_rejects_unauthenticated_requests() {
		add_filter( 'pre_option_' . WC_Payments_Features::REPORTS_AREA_FLAG_NAME, [ $this, 'return_enabled_flag' ] );
		$this->setExpectedIncorrectUsage( 'register_rest_route' );
		$this->controller->register_routes();
		wp_set_current_user( 0 );

		$request = new WP_REST_Request( 'GET', '/wc/v3/payments/reports/balance' );
		$request->set_param( 'date_start', '2024-03-01T00:00:00.000Z' );
		$request->set_param( 'date_end', '2024-03-31T23:59:59.999Z' );
		$request->set_param( 'currency', 'usd' );

		$response = rest_get_server()->dispatch( $request );

		$this->assertContains( $response->get_status(), [ 401, 403 ] );
	}

	public function test_balance_route_rejects_unprivileged_users() {
		add_filter( 'pre_option_' . WC_Payments_Features::REPORTS_AREA_FLAG_NAME, [ $this, 'return_enabled_flag' ] );
		$this->setExpectedIncorrectUsage( 'register_rest_route' );
		$this->controller->register_routes();
		wp_set_current_user( $this->factory->user->create( [ 'role' => 'subscriber' ] ) );

		$request = new WP_REST_Request( 'GET', '/wc/v3/payments/reports/balance' );
		$request->set_param( 'date_start', '2024-03-01T00:00:00.000Z' );
		$request->set_param( 'date_end', '2024-03-31T23:59:59.999Z' );
		$request->set_param( 'currency', 'usd' );

		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 403, $response->get_status() );
	}

	public function test_get_balance_summary_uses_typed_request_and_dispatches_verbatim_response() {
		$fixture = wcpay_test_balance_summary_fixture();
		add_filter( 'pre_option_' . WC_Payments_Features::REPORTS_AREA_FLAG_NAME, [ $this, 'return_enabled_flag' ] );
		$this->setExpectedIncorrectUsage( 'register_rest_route' );
		$this->controller->register_routes();
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		$request = new WP_REST_Request( 'GET', '/wc/v3/payments/reports/balance' );
		$request->set_param( 'date_start', '2024-03-01T00:00:00.000Z' );
		$request->set_param( 'date_end', '2024-03-31T23:59:59.999Z' );
		$request->set_param( 'currency', 'USD' );

		$mock_request = $this->mock_wcpay_request( Get_Reporting_Balance_Summary::class, 1, null, $fixture );
		$mock_request
			->expects( $this->once() )
			->method( 'set_date_start' )
			->with( '2024-03-01T00:00:00.000Z' );
		$mock_request
			->expects( $this->once() )
			->method( 'set_date_end' )
			->with( '2024-03-31T23:59:59.999Z' );
		$mock_request
			->expects( $this->once() )
			->method( 'set_currency' )
			->with( 'usd' );

		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( $fixture, $response->get_data() );
	}

	public function test_get_balance_summary_converts_api_exception_to_wp_error() {
		add_filter( 'pre_option_' . WC_Payments_Features::REPORTS_AREA_FLAG_NAME, [ $this, 'return_enabled_flag' ] );
		$this->setExpectedIncorrectUsage( 'register_rest_route' );
		$this->controller->register_routes();
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		$request = new WP_REST_Request( 'GET', '/wc/v3/payments/reports/balance' );
		$request->set_param( 'date_start', '2024-03-01T00:00:00.000Z' );
		$request->set_param( 'date_end', '2024-03-31T23:59:59.999Z' );
		$request->set_param( 'currency', 'usd' );

		$api_client = $this->createMock( WC_Payments_API_Client::class );
		$api_client
			->expects( $this->once() )
			->method( 'send_request' )
			->willThrowException( new API_Exception( 'Balance unavailable.', 'wcpay_error', 400 ) );
		$http_client   = $this->createMock( WC_Payments_Http::class );
		$wcpay_request = new Get_Reporting_Balance_Summary( $api_client, $http_client );

		$create_request_filter       = function ( $existing_request, $class_name ) use ( $wcpay_request, &$create_request_filter ) {
			if ( Get_Reporting_Balance_Summary::class !== $class_name ) {
				return $existing_request;
			}

			remove_filter( 'wcpay_create_request', $create_request_filter );
			return $wcpay_request;
		};
		$this->create_request_filter = $create_request_filter;
		add_filter( 'wcpay_create_request', $this->create_request_filter, 10, 2 );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		// API_Exception carried HTTP 400, but Request::handle_rest_request wraps
		// it as a WP_Error without a `status` data field. WP_REST_Server then
		// defaults the response status to 500. If the controller is ever
		// changed to forward the upstream HTTP status, update this assertion
		// to match.
		$this->assertSame( 500, $response->get_status() );
		$this->assertSame( 'wcpay_error', $data['code'] );
	}

	public function return_enabled_flag() {
		return '1';
	}

	public function return_disabled_flag() {
		return '0';
	}
}
