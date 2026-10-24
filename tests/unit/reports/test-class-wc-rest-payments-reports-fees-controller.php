<?php
/**
 * Class WC_REST_Payments_Reports_Fees_Controller_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Constants\Country_Code;
use WCPay\Core\Server\Request\Get_Transactions_Summary;
use WCPay\Core\Server\Request\List_Transactions;
use WCPay\Exceptions\API_Exception;

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

		$this->assertSame(
			[
				'/wc/v3/payments/reports/fees',
				'/wc/v3/payments/reports/fees/summary',
				'/wc/v3/payments/reports/fees/download',
				'/wc/v3/payments/reports/fees/download/(?P<export_id>[^/\\\\%]+)',
			],
			array_values(
				array_filter(
					array_keys( $routes ),
					static function ( string $route ): bool {
						return 0 === strpos( $route, '/wc/v3/payments/reports/fees' );
					}
				)
			)
		);
		$this->assertFeesRouteRegistered( $routes, '/wc/v3/payments/reports/fees', 'GET' );
		$this->assertFeesRouteRegistered( $routes, '/wc/v3/payments/reports/fees/summary', 'GET' );
		$this->assertFeesRouteRegistered( $routes, '/wc/v3/payments/reports/fees/download', 'POST' );
		$this->assertFeesRouteRegistered( $routes, '/wc/v3/payments/reports/fees/download/(?P<export_id>[^/\\\\%]+)', 'GET' );
	}

	public function test_register_routes_returns_early_when_reports_area_disabled() {
		add_filter( 'pre_option_' . WC_Payments_Features::REPORTS_AREA_FLAG_NAME, [ $this, 'return_disabled_flag' ] );

		$this->controller->register_routes();

		$routes = rest_get_server()->get_routes();

		foreach ( array_keys( $routes ) as $route ) {
			$this->assertStringNotContainsString( '/wc/v3/payments/reports/fees', $route );
		}
	}

	public function test_get_collection_params_registers_fees_filters() {
		$params = $this->controller->get_collection_params();

		$this->assertSame( 'array', $params['search']['type'] );
		$this->assertSame( 'array', $params['type']['type'] );

		$this->assert_validate_callback_accepts_list_and_rejects_non_list( $params, 'search' );
		$this->assert_validate_callback_accepts_list_and_rejects_non_list( $params, 'type' );
	}

	/**
	 * Asserts the configured validate_callback accepts a list and rejects a non-list
	 * shape for the given collection param — guards the contract without pinning
	 * the implementation to the literal `'rest_validate_request_arg'` function name.
	 *
	 * @param array  $params Collection params from get_collection_params().
	 * @param string $name   Param name to exercise.
	 */
	private function assert_validate_callback_accepts_list_and_rejects_non_list( array $params, string $name ) {
		$request = new WP_REST_Request();
		$request->set_attributes( [ 'args' => [ $name => $params[ $name ] ] ] );

		$validate = $params[ $name ]['validate_callback'];
		$this->assertTrue( is_callable( $validate ) );

		$this->assertTrue( $validate( [ 'txn_123' ], $request, $name ) );
		$this->assertWPError( $validate( [ 'not' => 'a-list' ], $request, $name ) );
	}

	public function test_get_collection_params_does_not_advertise_customer_email_filter() {
		$params = $this->controller->get_collection_params();

		$this->assertArrayNotHasKey( 'customer_email', $params );
	}

	public function test_get_collection_params_normalizes_single_value_list_filters() {
		$params = $this->controller->get_collection_params();

		$this->assertSame( [ 'txn_123' ], $params['search']['sanitize_callback']( 'txn_123' ) );
		$this->assertSame( [ 'txn_123', 'pi_123' ], $params['search']['sanitize_callback']( [ 'txn_123', 'pi_123' ] ) );
		$this->assertSame( [ 'payment' ], $params['type']['sanitize_callback']( 'payment' ) );
		$this->assertSame( [ 'payment', 'refund' ], $params['type']['sanitize_callback']( [ 'payment', 'refund' ] ) );
	}

	public function test_get_fees_transaction_filters_maps_report_params_to_transaction_filters() {
		$request = new WP_REST_Request( 'GET' );
		$request->set_param( 'payment_method_type', 'card' );
		$request->set_param( 'type', 'refund' );
		$request->set_param( 'order_id', 123 );
		$request->set_param( 'deposit_id', 'po_mock' );
		$request->set_param( 'date_after', '2026-04-01 00:00:00' );
		$request->set_param( 'date_before', '2026-04-30 23:59:59' );
		$request->set_param( 'date_between', [ '2026-04-01 00:00:00', '2026-04-30 23:59:59' ] );
		$request->set_param( 'match', 'all' );
		$request->set_param( 'search', [ 'ch_123' ] );
		$request->set_param( 'user_timezone', '+00:00' );

		$this->assertSame(
			[
				'source_is'     => 'card',
				'type_is_in'    => [ 'refund' ],
				'order_id_is'   => 123,
				'deposit_id'    => 'po_mock',
				'date_before'   => '2026-04-30 23:59:59',
				'date_after'    => '2026-04-01 00:00:00',
				'date_between'  => [ '2026-04-01 00:00:00', '2026-04-30 23:59:59' ],
				'match'         => 'all',
				'search'        => [ 'ch_123' ],
				'user_timezone' => '+00:00',
			],
			$this->get_fees_transaction_filters_for_test( $request )
		);
	}

	public function test_get_fees_transaction_filters_maps_payout_id_search_to_deposit_filter() {
		$request = new WP_REST_Request( 'GET' );
		$request->set_param( 'search', [ 'po_1TWRT9JbIuFWiDNFsTVj8XPN' ] );

		$filters = $this->get_fees_transaction_filters_for_test( $request );

		$this->assertArrayHasKey( 'deposit_id', $filters );
		$this->assertSame( 'po_1TWRT9JbIuFWiDNFsTVj8XPN', $filters['deposit_id'] );
		$this->assertArrayNotHasKey( 'search', $filters );
	}

	public function test_get_fees_transaction_filters_maps_transaction_id_search_to_transaction_filter() {
		$request = new WP_REST_Request( 'GET' );
		$request->set_param( 'search', [ 'txn_1TWLynJbIuFWiDNFWgCJ6OWO' ] );

		$filters = $this->get_fees_transaction_filters_for_test( $request );

		$this->assertArrayHasKey( 'transaction_id_is', $filters );
		$this->assertSame( 'txn_1TWLynJbIuFWiDNFWgCJ6OWO', $filters['transaction_id_is'] );
		$this->assertArrayNotHasKey( 'search', $filters );
	}

	public function test_get_fees_transaction_filters_drops_customer_email_even_when_request_carries_it() {
		$request = new WP_REST_Request( 'GET' );
		$request->set_param( 'customer_email', 'leak@example.com' );

		$filters = $this->get_fees_transaction_filters_for_test( $request );

		$this->assertArrayNotHasKey( 'customer_email_is', $filters );
		$this->assertArrayNotHasKey( 'customer_email', $filters );
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

	public function test_get_fees_transaction_filters_maps_single_type_to_type_is_in() {
		$request = new WP_REST_Request( 'GET' );
		$request->set_param( 'type', 'payment' );

		$this->assertSame(
			[
				'type_is_in' => [ 'payment' ],
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
		$mock_request->expects( $this->once() )
			->method( 'set_filters' )
			->with(
				[
					'source_is'   => 'card',
					'type_is_in'  => [ 'charge' ],
					'order_id_is' => 123,
				]
			);
		$mock_request->expects( $this->once() )
			->method( 'set_page_size' )
			->with( 2 );

		$response = $this->controller->get_transactions( $request );

		$this->assertSame( $this->get_transactions_list(), $response->get_data() );
	}

	public function test_get_item_schema_does_not_advertise_customer_field() {
		$schema = $this->controller->get_item_schema();

		$this->assertIsArray( $schema );
		$this->assertArrayHasKey( 'properties', $schema );
		$this->assertArrayNotHasKey( 'customer', $schema['properties'] );
	}

	public function test_prepare_item_for_response_strips_customer_field() {
		$request = new WP_REST_Request( 'GET', '/wc/v3/payments/reports/fees' );

		$raw_item = [
			'transaction_id'    => 'txn_test',
			'type'              => 'charge',
			'date'              => '2026-05-01T00:00:00Z',
			'source'            => 'visa',
			'customer_name'     => 'Test User',
			'customer_email'    => 'test@example.com',
			'customer_country'  => Country_Code::UNITED_STATES,
			'amount'            => 1000,
			'net'               => 900,
			'fees'              => 100,
			'currency'          => 'usd',
			'risk_level'        => 0,
			'deposit_id'        => 'po_mock',
			'available_on'      => '2026-05-03',
			'exchange_rate'     => 1.0,
			'customer_currency' => 'usd',
			'order_id'          => 123,
			'channel'           => 'online',
			'deposit_status'    => 'paid',
			'payment_intent_id' => 'pi_mock',
		];

		$response = $this->controller->prepare_item_for_response( $raw_item, $request );

		$this->assertArrayNotHasKey( 'customer', $response->get_data() );
	}

	public function test_get_fees_summary_uses_typed_request_with_mapped_filters() {
		$request = new WP_REST_Request( 'GET' );
		$request->set_param( 'payment_method_type', 'card' );
		$request->set_param( 'type', 'charge' );

		$mock_request = $this->mock_wcpay_request( Get_Transactions_Summary::class, 1, null, [ 'count' => 1 ] );
		$mock_request->expects( $this->once() )
			->method( 'set_filters' )
			->with(
				[
					'source_is'  => 'card',
					'type_is_in' => [ 'charge' ],
				]
			);
		$mock_request->expects( $this->never() )
			->method( 'set_deposit_id' );

		$response = $this->controller->get_fees_summary( $request );

		$this->assertSame( [ 'count' => 1 ], $response->get_data() );
	}

	public function test_get_fees_summary_passes_deposit_id_as_positional_argument() {
		$request = new WP_REST_Request( 'GET' );
		$request->set_param( 'payment_method_type', 'card' );
		$request->set_param( 'type', 'charge' );
		$request->set_param( 'deposit_id', 'po_mock' );

		$mock_request = $this->mock_wcpay_request( Get_Transactions_Summary::class, 1, null, [ 'count' => 1 ] );
		$mock_request->expects( $this->once() )
			->method( 'set_filters' )
			->with(
				[
					'source_is'  => 'card',
					'type_is_in' => [ 'charge' ],
				]
			);
		$mock_request->expects( $this->once() )
			->method( 'set_deposit_id' )
			->with( 'po_mock' );

		$response = $this->controller->get_fees_summary( $request );

		$this->assertSame( [ 'count' => 1 ], $response->get_data() );
	}

	public function test_get_fees_export_forwards_to_api_client_with_fee_bearing_types() {
		$request = new WP_REST_Request( 'POST' );
		$request->set_param( 'user_email', 'merchant@example.com' );
		$request->set_param( 'locale', 'en_US' );

		$this->mock_api_client
			->expects( $this->once() )
			->method( 'get_transactions_export' )
			->with(
				$this->equalTo(
					[
						'type_is_in' => WC_REST_Payments_Reports_Fees_Controller::DEFAULT_FEE_BEARING_TYPES,
					]
				),
				'merchant@example.com',
				null,
				'en_US'
			)
			->willReturn( [ 'export_id' => 'exp_123' ] );

		$response = $this->controller->get_fees_export( $request );

		$this->assertSame( [ 'export_id' => 'exp_123' ], $response->get_data() );
	}

	public function test_get_fees_export_narrows_type_filter_when_param_supplied() {
		$request = new WP_REST_Request( 'POST' );
		$request->set_param( 'type', [ 'dispute' ] );
		$request->set_param( 'user_email', '' );

		$this->mock_api_client
			->expects( $this->once() )
			->method( 'get_transactions_export' )
			->with(
				$this->equalTo( [ 'type_is_in' => [ 'dispute' ] ] ),
				'',
				null,
				null
			)
			->willReturn( [ 'export_id' => 'exp_456' ] );

		$response = $this->controller->get_fees_export( $request );

		$this->assertSame( [ 'export_id' => 'exp_456' ], $response->get_data() );
	}

	public function test_get_fees_export_passes_deposit_id_through() {
		$request = new WP_REST_Request( 'POST' );
		$request->set_param( 'deposit_id', 'po_abc' );

		// Assert the exact filter shape (not anything()) so the test catches
		// the double-send regression class — i.e. deposit_id leaking back into
		// the filters array while also being passed positionally.
		$this->mock_api_client
			->expects( $this->once() )
			->method( 'get_transactions_export' )
			->with(
				$this->equalTo(
					[
						'type_is_in' => WC_REST_Payments_Reports_Fees_Controller::DEFAULT_FEE_BEARING_TYPES,
					]
				),
				'',
				'po_abc',
				null
			)
			->willReturn( [ 'export_id' => 'exp_789' ] );

		$response = $this->controller->get_fees_export( $request );

		$this->assertSame( [ 'export_id' => 'exp_789' ], $response->get_data() );
	}

	public function test_get_export_url_forwards_export_id_to_api_client() {
		$request = new WP_REST_Request( 'GET' );
		$request->set_param( 'export_id', 'exp_xyz' );

		$this->mock_api_client
			->expects( $this->once() )
			->method( 'get_transactions_export_url' )
			->with( 'exp_xyz' )
			->willReturn(
				[
					'status'       => 'success',
					'download_url' => 'https://example.test/file.csv',
				]
			);

		$response = $this->controller->get_export_url( $request );

		$this->assertSame(
			[
				'status'       => 'success',
				'download_url' => 'https://example.test/file.csv',
			],
			$response->get_data()
		);
	}

	public function test_get_export_url_forwards_empty_string_when_export_id_missing() {
		// The route regex (`(?P<export_id>[\w-]+)`) rejects empty IDs at the
		// router layer today, but if that pattern ever loosens we want the
		// method-level behavior pinned: missing param coerces to '' and the
		// backend, not the controller, is responsible for the rejection.
		$request = new WP_REST_Request( 'GET' );

		$this->mock_api_client
			->expects( $this->once() )
			->method( 'get_transactions_export_url' )
			->with( '' )
			->willReturn( [ 'status' => 'pending' ] );

		$response = $this->controller->get_export_url( $request );

		$this->assertSame( [ 'status' => 'pending' ], $response->get_data() );
	}

	public function test_get_fees_export_returns_wp_error_when_api_throws() {
		$request = new WP_REST_Request( 'POST' );

		// Pin the argument shape on the failure path too so a regression that
		// builds wrong filters but happens to throw downstream is still caught.
		$this->mock_api_client
			->expects( $this->once() )
			->method( 'get_transactions_export' )
			->with(
				$this->equalTo(
					[
						'type_is_in' => WC_REST_Payments_Reports_Fees_Controller::DEFAULT_FEE_BEARING_TYPES,
					]
				),
				'',
				null,
				null
			)
			->willThrowException(
				new API_Exception(
					'Backend exploded.',
					'wcpay_export_failed',
					500
				)
			);

		$response = $this->controller->get_fees_export( $request );

		$this->assertInstanceOf( WP_Error::class, $response );
		$this->assertSame( 'wcpay_export_failed', $response->get_error_code() );
	}

	public function test_get_export_url_returns_wp_error_when_api_throws() {
		$request = new WP_REST_Request( 'GET' );
		$request->set_param( 'export_id', 'exp_xyz' );

		$this->mock_api_client
			->expects( $this->once() )
			->method( 'get_transactions_export_url' )
			->with( 'exp_xyz' )
			->willThrowException(
				new API_Exception(
					'Signed URL unavailable.',
					'wcpay_export_url_failed',
					500
				)
			);

		$response = $this->controller->get_export_url( $request );

		$this->assertInstanceOf( WP_Error::class, $response );
		$this->assertSame( 'wcpay_export_url_failed', $response->get_error_code() );
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
