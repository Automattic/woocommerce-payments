<?php
/**
 * Class WC_Payments_API_Client_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\Exceptions\API_Exception;

/**
 * WC_Payments_API_Client unit tests.
 */
class WC_Payments_API_Client_Test extends WP_UnitTestCase {

	/**
	 * System under test
	 *
	 * @var WC_Payments_API_Client
	 */
	private $payments_api_client;

	/**
	 * Mock HTTP client.
	 *
	 * @var WC_Payments_Http|PHPUnit_Framework_MockObject_MockObject
	 */
	private $mock_http_client;

	/**
	 * Mock DB wrapper.
	 *
	 * @var WC_Payments_DB|PHPUnit_Framework_MockObject_MockObject
	 */
	private $mock_db_wrapper;

	/**
	 * Pre-test setup
	 */
	public function setUp() {
		parent::setUp();

		$this->mock_http_client = $this->getMockBuilder( 'WC_Payments_Http' )
			->disableOriginalConstructor()
			->setMethods( [ 'remote_request' ] )
			->getMock();

		$this->mock_db_wrapper = $this->getMockBuilder( 'WC_Payments_DB' )
			->disableOriginalConstructor()
			->getMock();

		$this->payments_api_client = new WC_Payments_API_Client(
			'Unit Test Agent/0.1.0',
			$this->mock_http_client,
			$this->mock_db_wrapper
		);
	}

	/**
	 * Test a successful call to create_intention.
	 *
	 * @throws Exception - In the event of test failure.
	 */
	public function test_create_intention_success() {
		$expected_amount = 123;
		$expected_status = 'succeeded';

		$this->set_http_mock_response(
			200,
			[
				'id'            => 'test_intention_id',
				'amount'        => $expected_amount,
				'created'       => 1557224304,
				'status'        => $expected_status,
				'charges'       => [
					'total_count' => 1,
					'data'        => [
						[
							'id'      => 'test_charge_id',
							'amount'  => $expected_amount,
							'created' => 1557224305,
							'status'  => 'succeeded',
						],
					],
				],
				'client_secret' => 'test_client_secret',
				'currency'      => 'usd',
			]
		);

		$result = $this->payments_api_client->create_and_confirm_intention(
			$expected_amount,
			'usd',
			'pm_123456789',
			1
		);

		$this->assertEquals( $expected_amount, $result->get_amount() );
		$this->assertEquals( $expected_status, $result->get_status() );
	}

	/**
	 * Test a successful call to refund_charge.
	 *
	 * @throws Exception - In the event of test failure.
	 */
	public function test_create_refund_success() {
		$expected_amount = 123;

		// Mock up a test response from WP_Http.
		$this->mock_http_client
			->expects( $this->any() )
			->method( 'remote_request' )
			->will(
				$this->returnValue(
					[
						'headers'  => [],
						'body'     => wp_json_encode(
							[
								'id'     => 'test_refund_id',
								'amount' => $expected_amount,
								'status' => 'succeeded',
							]
						),
						'response' => [
							'code' => 200,
						],
						'cookies'  => [],
						'filename' => null,
					]
				)
			);

		// Attempt to create a refund.
		$refund = $this->payments_api_client->refund_charge( 'test_charge_id', $expected_amount );

		// Assert amount returned is correct (ignoring other properties for now since this is a stub implementation).
		$this->assertEquals( $expected_amount, $refund['amount'] );
	}

	/**
	 * Test a successful call to create_intention with manual capture.
	 *
	 * @throws Exception - In the event of test failure.
	 */
	public function test_create_intention_authorization_success() {
		$expected_amount = 123;
		$expected_status = 'requires_capture';

		$this->set_http_mock_response(
			200,
			[
				'id'            => 'test_intention_id',
				'amount'        => $expected_amount,
				'created'       => 1557224304,
				'status'        => $expected_status,
				'charges'       => [
					'total_count' => 1,
					'data'        => [
						[
							'id'      => 'test_charge_id',
							'amount'  => $expected_amount,
							'created' => 1557224305,
							'status'  => 'succeeded',
						],
					],
				],
				'client_secret' => 'test_client_secret',
				'currency'      => 'usd',
			]
		);

		$result = $this->payments_api_client->create_and_confirm_intention( $expected_amount, 'usd', 'pm_123456789', true );
		$this->assertEquals( $expected_amount, $result->get_amount() );
		$this->assertEquals( $expected_status, $result->get_status() );
	}

	/**
	 * Test a successful call to capture intention.
	 *
	 * @throws Exception - In the event of test failure.
	 */
	public function test_capture_intention_success() {
		$expected_amount = 103;
		$expected_status = 'succeeded';

		$this->set_http_mock_response(
			200,
			[
				'id'              => 'test_intention_id',
				'amount'          => 123,
				'amount_captured' => $expected_amount,
				'created'         => 1557224304,
				'status'          => $expected_status,
				'charges'         => [
					'total_count' => 1,
					'data'        => [
						[
							'id'      => 'test_charge_id',
							'amount'  => $expected_amount,
							'created' => 1557224305,
							'status'  => 'succeeded',
						],
					],
				],
				'client_secret'   => 'test_client_secret',
				'currency'        => 'usd',
			]
		);

		$result = $this->payments_api_client->capture_intention( 'test_intention_id', $expected_amount );
		$this->assertEquals( $expected_status, $result->get_status() );
	}

	/**
	 * Test a successful call to cancel intention.
	 *
	 * @throws Exception - In the event of test failure.
	 */
	public function test_cancel_intention_success() {
		$expected_status = 'canceled';

		$this->set_http_mock_response(
			200,
			[
				'id'            => 'test_intention_id',
				'amount'        => 123,
				'created'       => 1557224304,
				'status'        => $expected_status,
				'charges'       => [
					'total_count' => 1,
					'data'        => [
						[
							'id'      => 'test_charge_id',
							'amount'  => 123,
							'created' => 1557224305,
							'status'  => 'succeeded',
						],
					],
				],
				'client_secret' => 'test_client_secret',
				'currency'      => 'usd',
			]
		);

		$result = $this->payments_api_client->cancel_intention( 'test_intention_id' );
		$this->assertEquals( $expected_status, $result->get_status() );
	}

	/**
	 * Test a successful fetch of a single transaction.
	 *
	 * @throws Exception In case of test failure.
	 */
	public function test_get_transaction_success() {
		$transaction_id = 'txn_231mdaism';

		$this->set_http_mock_response(
			200,
			[
				'id'        => $transaction_id,
				'type'      => 'charge',
				'charge_id' => 'ch_ji3djhabvh23',
			]
		);

		$transaction = $this->payments_api_client->get_transaction( $transaction_id );
		$this->assertEquals( $transaction_id, $transaction['id'] );
	}

	/**
	 * Test fetching of non existing transaction.
	 *
	 * @throws Exception In case of test failure.
	 */
	public function test_get_transaction_not_found() {
		$transaction_id = 'txn_231mdaism';
		$error_code     = 'resource_missing';
		$error_message  = 'No such balance transaction';

		$this->set_http_mock_response(
			404,
			[
				'error' => [
					'code'    => $error_code,
					'message' => $error_message,
				],
			]
		);
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( "Error: $error_message" );

		$this->payments_api_client->get_transaction( $transaction_id );
	}

	/**
	 * Test creating a customer.
	 *
	 * @throws API_Exception
	 */
	public function test_create_customer_success() {
		$name        = 'Test Customer';
		$email       = 'test.customer@example.com';
		$description = 'Test Customer Description';

		$this->set_http_mock_response(
			200,
			[
				'id'   => 'cus_test12345',
				'type' => 'customer',
			]
		);

		$customer_id = $this->payments_api_client->create_customer( $name, $email, $description );

		$this->assertEquals( 'cus_test12345', $customer_id );
	}

	/**
	 * Test updating a customer.
	 *
	 * @throws API_Exception
	 */
	public function test_update_customer_success() {
		$name        = 'Test Customer';
		$email       = 'test.customer@example.com';
		$description = 'Test Customer Description';

		// Mock the HTTP client manually so that we can assert against the request used.
		$this->mock_http_client
			->expects( $this->once() )
			->method( 'remote_request' )
			->with(
				[
					'url'             => 'https://public-api.wordpress.com/wpcom/v2/sites/%s/wcpay/customers/cus_test12345',
					'method'          => 'POST',
					'headers'         => [
						'Content-Type' => 'application/json; charset=utf-8',
						'User-Agent'   => 'Unit Test Agent/0.1.0',
					],
					'timeout'         => 70,
					'connect_timeout' => 70,
				],
				wp_json_encode(
					[
						'test_mode'   => false,
						'name'        => 'Test Customer',
						'email'       => 'test.customer@example.com',
						'description' => 'Test Customer Description',
					]
				),
				true
			)
			->will(
				$this->returnValue(
					[
						'body'     => wp_json_encode(
							[
								'id'   => 'cus_test12345',
								'type' => 'customer',
							]
						),
						'response' => [
							'code'    => 200,
							'message' => 'OK',
						],
					]
				)
			);

		$this->payments_api_client->update_customer(
			'cus_test12345',
			[
				'name'        => $name,
				'email'       => $email,
				'description' => $description,
			]
		);
	}

	/**
	 * Test updating a customer with null customer ID.
	 *
	 * @throws API_Exception
	 */
	public function test_update_customer_with_null_customer_id() {
		// Ensure we don't make a call to the server.
		$this->mock_http_client
			->expects( $this->never() )
			->method( 'remote_request' );

		$this->expectException( API_Exception::class );
		$this->expectExceptionMessage( 'Customer ID is required' );

		$this->payments_api_client->update_customer( null );
	}

	/**
	 * Test updating a customer with an empty string customer ID.
	 *
	 * @throws API_Exception
	 */
	public function test_update_customer_with_empty_string_customer_id() {
		// Ensure we don't make a call to the server.
		$this->mock_http_client
			->expects( $this->never() )
			->method( 'remote_request' );

		$this->expectException( API_Exception::class );
		$this->expectExceptionMessage( 'Customer ID is required' );

		$this->payments_api_client->update_customer( '' );
	}

	/**
	 * Test updating a customer with an empty string customer ID.
	 *
	 * @throws API_Exception
	 */
	public function test_update_customer_with_whitespace_customer_id() {
		// Ensure we don't make a call to the server.
		$this->mock_http_client
			->expects( $this->never() )
			->method( 'remote_request' );

		$this->expectException( API_Exception::class );
		$this->expectExceptionMessage( 'Customer ID is required' );

		$this->payments_api_client->update_customer( ' ' );
	}

	/**
	 * Test getting initial oauth data.
	 *
	 * @throws API_Exception
	 */
	public function test_get_oauth_data() {
		$this->mock_http_client
			->expects( $this->once() )
			->method( 'remote_request' )
			->with(
				[
					'url'             => 'https://public-api.wordpress.com/wpcom/v2/sites/%s/wcpay/oauth/init',
					'method'          => 'POST',
					'headers'         => [
						'Content-Type' => 'application/json; charset=utf-8',
						'User-Agent'   => 'Unit Test Agent/0.1.0',
					],
					'timeout'         => 70,
					'connect_timeout' => 70,
				],
				wp_json_encode(
					[
						'test_mode'           => false,
						'return_url'          => 'http://localhost',
						'business_data'       => [
							'a' => 1,
							'b' => 2,
							'c' => 3,
						],
						'site_data'           => [
							'site_username' => 'admin',
						],
						'create_live_account' => true,
						'actioned_notes'      => [
							'd' => 4,
							'e' => 5,
							'f' => 6,
						],
					]
				)
			)
			->willReturn(
				[
					'body'     => wp_json_encode( [ 'url' => false ] ),
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
				]
			);

		// Call the method under test.
		$result = $this->payments_api_client->get_oauth_data(
			'http://localhost',
			[
				'a' => 1,
				'b' => 2,
				'c' => 3,
			],
			[
				'site_username' => 'admin',
			],
			[
				'd' => 4,
				'e' => 5,
				'f' => 6,
			]
		);

		// Assert the response is correct.
		$this->assertEquals( [ 'url' => false ], $result );
	}

	/**
	 * Set up http mock response.
	 *
	 * @param int   $status_code status code for the mocked response.
	 * @param array $body body for the mocked response.
	 * @param array $headers headers for the mocked response.
	 * @param array $cookies cookies to be used in the mocked response.
	 */
	private function set_http_mock_response( $status_code, $body = [], $headers = [], $cookies = [] ) {
		$this->mock_http_client
			->expects( $this->any() )
			->method( 'remote_request' )
			->will(
				$this->returnValue(
					[
						'headers'  => $headers,
						'body'     => wp_json_encode( $body ),
						'response' => [
							'code'    => $status_code,
							'message' => 'OK',
						],
						'cookies'  => $cookies,
						'filename' => null,
					]
				)
			);
	}
}
