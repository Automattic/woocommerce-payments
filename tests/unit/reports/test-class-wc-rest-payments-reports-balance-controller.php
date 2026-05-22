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
		$this->assertArrayHasKey( 'GET', $routes['/wc/v3/payments/reports/balance'][0]['methods'] );
		$this->assertIsArray( $routes['/wc/v3/payments/reports/balance'][0]['permission_callback'] );
		$this->assertInstanceOf( WC_REST_Payments_Reports_Balance_Controller::class, $routes['/wc/v3/payments/reports/balance'][0]['permission_callback'][0] );
		$this->assertSame( 'check_permission', $routes['/wc/v3/payments/reports/balance'][0]['permission_callback'][1] );
		$this->assertIsArray( $routes['/wc/v3/payments/reports/balance'][0]['callback'] );
		$this->assertInstanceOf( WC_REST_Payments_Reports_Balance_Controller::class, $routes['/wc/v3/payments/reports/balance'][0]['callback'][0] );
		$this->assertSame( 'get_balance_summary', $routes['/wc/v3/payments/reports/balance'][0]['callback'][1] );
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

		$this->assertSame( 'string', $params['date_start']['type'] );
		$this->assertSame( 'date-time', $params['date_start']['format'] );
		$this->assertTrue( $params['date_start']['required'] );
		$this->assertSame( 'sanitize_text_field', $params['date_start']['sanitize_callback'] );

		$this->assertSame( 'string', $params['date_end']['type'] );
		$this->assertSame( 'date-time', $params['date_end']['format'] );
		$this->assertTrue( $params['date_end']['required'] );
		$this->assertSame( 'sanitize_text_field', $params['date_end']['sanitize_callback'] );

		$this->assertSame( 'string', $params['currency']['type'] );
		$this->assertTrue( $params['currency']['required'] );
		$this->assertSame( [ WC_REST_Payments_Reports_Balance_Controller::class, 'validate_currency_code' ], $params['currency']['validate_callback'] );
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

		$request = new WP_REST_Request( 'GET', '/wc/v3/payments/reports/balance' );
		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}

		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 400, $response->get_status() );
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
			'uppercase currency' => [
				[
					'date_start' => '2024-03-01T00:00:00',
					'date_end'   => '2024-03-31T23:59:59',
					'currency'   => 'USD',
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

		$this->assertContains( $response->get_status(), [ 401, 403 ], true );
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
		$request->set_param( 'currency', 'usd' );

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
		$request = new WP_REST_Request( 'GET', '/wc/v3/payments/reports/balance' );
		$request->set_param( 'date_start', '2024-03-01T00:00:00' );
		$request->set_param( 'date_end', '2024-03-31T23:59:59' );
		$request->set_param( 'currency', 'usd' );

		$mock_request = $this->mock_wcpay_request( Get_Reporting_Balance_Summary::class );
		$mock_request
			->expects( $this->once() )
			->method( 'set_date_start' )
			->with( '2024-03-01T00:00:00' );
		$mock_request
			->expects( $this->once() )
			->method( 'set_date_end' )
			->with( '2024-03-31T23:59:59' );
		$mock_request
			->expects( $this->once() )
			->method( 'set_currency' )
			->with( 'usd' );
		$mock_request
			->expects( $this->once() )
			->method( 'format_response' )
			->willThrowException( new API_Exception( 'Balance unavailable.', 'wcpay_error', 400 ) );

		$response = $this->controller->get_balance_summary( $request );

		$this->assertWPError( $response );
		$this->assertSame( 'wcpay_error', $response->get_error_code() );
	}

	public function return_enabled_flag() {
		return '1';
	}

	public function return_disabled_flag() {
		return '0';
	}
}
