<?php
/**
 * Class WC_REST_Payments_Reports_Fees_Controller_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Constants\Country_Code;
use WCPay\Core\Server\Request\List_Transactions;

/**
 * WC_REST_Payments_Reports_Fees_Controller unit tests.
 */
class WC_REST_Payments_Reports_Fees_Controller_Test extends WCPAY_UnitTestCase {
	/**
	 * Controller under test.
	 *
	 * @var WC_REST_Payments_Reports_Fees_Controller
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

		$this->mock_api_client = $this->createMock( WC_Payments_API_Client::class );
		$this->controller      = new WC_REST_Payments_Reports_Fees_Controller( $this->mock_api_client );
	}

	public function tear_down() {
		remove_all_filters( 'pre_option_' . WC_Payments_Features::REPORTS_AREA_FLAG_NAME );

		global $wp_rest_server;
		$wp_rest_server = null;

		parent::tear_down();
	}

	public function test_register_routes_adds_fees_routes_when_reports_area_enabled() {
		add_filter( 'pre_option_' . WC_Payments_Features::REPORTS_AREA_FLAG_NAME, [ $this, 'return_enabled_flag' ] );
		$this->setExpectedIncorrectUsage( 'register_rest_route' );

		$this->controller->register_routes();

		$routes = rest_get_server()->get_routes();

		$this->assertFeesRouteRegistered( $routes, '/wc/v3/payments/reports/fees', 'GET' );
		$this->assertFeesRouteRegistered( $routes, '/wc/v3/payments/reports/fees/(?P<id>\w+)', 'GET' );
		$this->assertFeesRouteRegistered( $routes, '/wc/v3/payments/reports/fees/summary', 'GET' );
		$this->assertFeesRouteRegistered( $routes, '/wc/v3/payments/reports/fees/download', 'POST' );
		$this->assertFeesRouteRegistered( $routes, '/wc/v3/payments/reports/fees/download/(?P<export_id>[^/\\%]+)', 'GET' );
	}

	public function test_register_routes_returns_early_when_reports_area_disabled() {
		add_filter( 'pre_option_' . WC_Payments_Features::REPORTS_AREA_FLAG_NAME, [ $this, 'return_disabled_flag' ] );

		$this->controller->register_routes();

		$routes = rest_get_server()->get_routes();

		foreach ( array_keys( $routes ) as $route ) {
			$this->assertStringNotContainsString( '/wc/v3/payments/reports/fees', $route );
		}
	}

	public function test_get_fees_transaction_filters_maps_report_params_to_transaction_filters() {
		$request = new WP_REST_Request( 'GET' );
		$request->set_param( 'payment_method_type', 'card' );
		$request->set_param( 'type', 'refund' );
		$request->set_param( 'order_id', 123 );
		$request->set_param( 'customer_email', 'customer@example.com' );
		$request->set_param( 'deposit_id', 'po_mock' );
		$request->set_param( 'date_after', '2026-04-01 00:00:00' );
		$request->set_param( 'date_before', '2026-04-30 23:59:59' );
		$request->set_param( 'date_between', [ '2026-04-01 00:00:00', '2026-04-30 23:59:59' ] );
		$request->set_param( 'available_on_after', '2026-04-02 00:00:00' );
		$request->set_param( 'available_on_before', '2026-05-01 00:00:00' );
		$request->set_param( 'match', 'all' );
		$request->set_param( 'user_timezone', '+00:00' );

		$this->assertSame(
			[
				'source_is'           => 'card',
				'type_is'             => 'refund',
				'order_id_is'         => 123,
				'customer_email_is'   => 'customer@example.com',
				'deposit_id'          => 'po_mock',
				'date_before'         => '2026-04-30 23:59:59',
				'date_after'          => '2026-04-01 00:00:00',
				'date_between'        => [ '2026-04-01 00:00:00', '2026-04-30 23:59:59' ],
				'available_on_before' => '2026-05-01 00:00:00',
				'available_on_after'  => '2026-04-02 00:00:00',
				'match'               => 'all',
				'user_timezone'       => '+00:00',
			],
			$this->get_fees_transaction_filters_for_test( $request )
		);
	}

	public function test_get_fees_transaction_filters_maps_type_list_to_type_is_in() {
		$request = new WP_REST_Request( 'GET' );
		$request->set_param( 'type', [ 'payment', 'dispute' ] );

		$this->assertSame(
			[
				'type_is_in' => [ 'payment', 'dispute' ],
			],
			$this->get_fees_transaction_filters_for_test( $request )
		);
	}

	public function test_get_fees_transaction_filters_adds_default_fee_bearing_types_when_type_is_absent() {
		$request = new WP_REST_Request( 'GET' );

		$this->assertSame(
			[
				'type_is_in' => [
					'charge',
					'payment',
					'payment_failure_refund',
					'payment_refund',
					'refund',
					'refund_failure',
					'dispute',
					'dispute_reversal',
					'fee_refund',
					'network_costs',
				],
			],
			$this->get_fees_transaction_filters_for_test( $request )
		);
	}

	public function test_get_transactions_uses_mapped_filters_and_returns_report_shaped_rows() {
		$request = new WP_REST_Request( 'GET' );
		$request->set_param( 'per_page', 2 );
		$request->set_param( 'payment_method_type', 'card' );
		$request->set_param( 'type', 'charge' );
		$request->set_param( 'order_id', 123 );

		$mock_request = $this->mock_wcpay_request( List_Transactions::class );
		$mock_request->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( $this->get_transactions_list_from_server() );
		$mock_request->expects( $this->any() )
			->method( 'set_filters' )
			->withConsecutive(
				[ $this->anything() ],
				[
					[
						'source_is'   => 'card',
						'type_is'     => 'charge',
						'order_id_is' => 123,
					],
				]
			);
		$mock_request->expects( $this->any() )
			->method( 'set_page_size' )
			->withConsecutive(
				[ $this->anything() ],
				[ 2 ]
			);

		$response = $this->controller->get_transactions( $request );

		$this->assertSame( $this->get_transactions_list(), $response->get_data() );
	}

	public function test_get_fees_summary_forwards_mapped_filters() {
		$request = new WP_REST_Request( 'GET' );
		$request->set_param( 'payment_method_type', 'card' );
		$request->set_param( 'type', 'charge' );

		$this->mock_api_client->expects( $this->once() )
			->method( 'get_reports_fees_summary' )
			->with(
				[
					'source_is' => 'card',
					'type_is'   => 'charge',
				]
			)
			->willReturn( [ 'count' => 1 ] );

		$response = $this->controller->get_fees_summary( $request );

		$this->assertSame( [ 'count' => 1 ], $response->get_data() );
	}

	public function test_get_fees_export_forwards_mapped_filters_email_and_locale() {
		$request = new WP_REST_Request( 'POST' );
		$request->set_param( 'payment_method_type', 'card' );
		$request->set_param( 'type', 'charge' );
		$request->set_param( 'user_email', 'merchant@example.com' );
		$request->set_param( 'locale', 'en_US' );

		$this->mock_api_client->expects( $this->once() )
			->method( 'get_reports_fees_export' )
			->with(
				[
					'source_is' => 'card',
					'type_is'   => 'charge',
				],
				'merchant@example.com',
				'en_US'
			)
			->willReturn( [ 'export_id' => 'export_mock' ] );

		$response = $this->controller->get_fees_export( $request );

		$this->assertSame( [ 'export_id' => 'export_mock' ], $response->get_data() );
	}

	public function test_get_fees_export_url_forwards_export_id() {
		$request = new WP_REST_Request( 'GET' );
		$request->set_param( 'export_id', 'export_mock' );

		$this->mock_api_client->expects( $this->once() )
			->method( 'get_reports_fees_export_url' )
			->with( 'export_mock' )
			->willReturn( [ 'url' => 'https://example.com/export.csv' ] );

		$response = $this->controller->get_fees_export_url( $request );

		$this->assertSame( [ 'url' => 'https://example.com/export.csv' ], $response->get_data() );
	}

	public function return_enabled_flag() {
		return '1';
	}

	public function return_disabled_flag() {
		return '0';
	}

	private function assertFeesRouteRegistered( array $routes, string $route, string $method ) {
		$this->assertArrayHasKey( $route, $routes );
		$this->assertArrayHasKey( $method, $routes[ $route ][0]['methods'] );
		$this->assertIsArray( $routes[ $route ][0]['permission_callback'] );
		$this->assertInstanceOf( WC_REST_Payments_Reports_Fees_Controller::class, $routes[ $route ][0]['permission_callback'][0] );
		$this->assertSame( 'check_permission', $routes[ $route ][0]['permission_callback'][1] );
	}

	private function get_fees_transaction_filters_for_test( WP_REST_Request $request ): array {
		$method = new ReflectionMethod( $this->controller, 'get_fees_transaction_filters' );
		$method->setAccessible( true );

		return $method->invoke( $this->controller, $request );
	}

	private function get_transactions_list_from_server() {
		return [
			'data' => [
				[
					'transaction_id'    => 'txn_123',
					'type'              => 'charge',
					'date'              => '2026-04-19 10:27:55',
					'source'            => 'visa',
					'customer_name'     => 'Test Customer',
					'customer_email'    => 'customer@example.com',
					'customer_country'  => Country_Code::UNITED_STATES,
					'amount'            => 2583,
					'net'               => 2426,
					'fees'              => 157,
					'currency'          => 'usd',
					'risk_level'        => 0,
					'deposit_id'        => 'po_mock',
					'available_on'      => '2026-04-21',
					'exchange_rate'     => 1.12284,
					'customer_currency' => 'eur',
					'order_id'          => 123,
					'channel'           => 'online',
					'deposit_status'    => 'paid',
					'payment_intent_id' => 'pi_mock',
				],
			],
		];
	}

	private function get_transactions_list() {
		return [
			[
				'transaction_id'       => 'txn_123',
				'date'                 => '2026-04-19 10:27:55',
				'payment_id'           => 'pi_mock',
				'channel'              => 'online',
				'payment_method'       => [
					'type' => 'visa',
				],
				'type'                 => 'charge',
				'transaction_currency' => 'eur',
				'amount'               => 2583,
				'exchange_rate'        => 1.12284,
				'deposit_currency'     => 'usd',
				'fees'                 => 157,
				'customer'             => [
					'name'    => 'Test Customer',
					'email'   => 'customer@example.com',
					'country' => Country_Code::UNITED_STATES,
				],
				'net_amount'           => 2426,
				'order_id'             => 123,
				'risk_level'           => 0,
				'deposit_date'         => '2026-04-21',
				'deposit_id'           => 'po_mock',
				'deposit_status'       => 'paid',
			],
		];
	}
}
