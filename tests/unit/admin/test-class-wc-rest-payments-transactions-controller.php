<?php
/**
 * Class WC_REST_Payments_Transactions_Controller_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Core\Exceptions\Server\Request\Invalid_Request_Parameter_Exception;
use WCPay\Core\Server\Request\List_Fraud_Outcome_Transactions;

/**
 * WC_REST_Payments_Transactions_Controller unit tests.
 */
class WC_REST_Payments_Transactions_Controller_Test extends WCPAY_UnitTestCase {

	/**
	 * @var WC_Payments_API_Client|MockObject
	 */
	private $mock_api_client;

	/**
	 * REST route base.
	 *
	 * @var string
	 */
	private $rest_base = '/wc/v3/payments/transactions';

	public function set_up() {
		parent::set_up();

		wp_set_current_user( 1 );

		// Boots the REST server so the plugin's routes are registered.
		rest_get_server();

		$this->mock_api_client = $this->createMock( WC_Payments_API_Client::class );
	}

	/**
	 * Data provider for the fraud outcome routes, which all require a status.
	 *
	 * @return array
	 */
	public function fraud_outcome_routes() {
		return [
			'list'     => [ '/fraud-outcomes' ],
			'summary'  => [ '/fraud-outcomes/summary' ],
			'search'   => [ '/fraud-outcomes/search' ],
			'download' => [ '/fraud-outcomes/download' ],
		];
	}

	/**
	 * A missing status should be a bad request, not an uncaught exception.
	 *
	 * @dataProvider fraud_outcome_routes
	 *
	 * @param string $route The route to request.
	 */
	public function test_fraud_outcomes_without_status_returns_bad_request( string $route ) {
		$response = rest_do_request( new WP_REST_Request( 'GET', $this->rest_base . $route ) );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'rest_missing_callback_param', $response->get_data()['code'] );
	}

	/**
	 * A status outside the allowed values should be a bad request too.
	 *
	 * @dataProvider fraud_outcome_routes
	 *
	 * @param string $route The route to request.
	 */
	public function test_fraud_outcomes_with_invalid_status_returns_bad_request( string $route ) {
		$request = new WP_REST_Request( 'GET', $this->rest_base . $route );
		$request->set_param( 'status', 'not-a-status' );

		$response = rest_do_request( $request );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'rest_invalid_param', $response->get_data()['code'] );
	}

	/**
	 * The routes declare the statuses the API accepts, so valid ones still get through.
	 *
	 * @dataProvider fraud_outcome_routes
	 *
	 * @param string $route The route to check.
	 */
	public function test_fraud_outcome_routes_accept_the_documented_statuses( string $route ) {
		$routes = rest_get_server()->get_routes();
		$key    = $this->rest_base . $route;

		$this->assertArrayHasKey( $key, $routes );

		$args = $routes[ $key ][0]['args'] ?? [];

		$this->assertArrayHasKey( 'status', $args );
		$this->assertTrue( $args['status']['required'] );
		$this->assertSame( [ 'allow', 'block', 'review' ], $args['status']['enum'] );
	}

	/**
	 * A valid status must get past validation and reach the API, carrying that status.
	 *
	 * Uses the summary route because its API client method doesn't paginate, so the assertion
	 * stays on what this PR changes: the status survives validation and is set on the request.
	 */
	public function test_fraud_outcomes_with_valid_status_is_sent_to_the_api() {
		$wcpay_request = $this->mock_wcpay_request( List_Fraud_Outcome_Transactions::class );

		$wcpay_request->expects( $this->once() )
			->method( 'set_status' )
			->with( 'review' );

		$wcpay_request->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( [] );

		$request = new WP_REST_Request( 'GET', $this->rest_base . '/fraud-outcomes/summary' );
		$request->set_param( 'status', 'review' );

		$response = rest_do_request( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame(
			[
				'count'      => 0,
				'total'      => 0,
				'currencies' => [],
			],
			$response->get_data()
		);
	}

	/**
	 * An invalid parameter thrown while sending a request must not escape as a fatal.
	 */
	public function test_forward_request_converts_invalid_parameter_exception_to_error() {
		$this->mock_api_client
			->method( 'list_fraud_outcome_transactions' )
			->willThrowException(
				new Invalid_Request_Parameter_Exception(
					'Invalid fraud outcome status provided: null',
					'invalid_fraud_outcome_status'
				)
			);

		$controller = new WC_REST_Payments_Transactions_Controller( $this->mock_api_client );

		$response = $controller->forward_request(
			'list_fraud_outcome_transactions',
			[ List_Fraud_Outcome_Transactions::create() ]
		);

		$this->assertInstanceOf( WP_Error::class, $response );
		$this->assertSame( 'invalid_fraud_outcome_status', $response->get_error_code() );
		$this->assertSame( 400, $response->get_error_data()['status'] );
	}
}
