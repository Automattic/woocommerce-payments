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
							'id'                     => 'test_charge_id',
							'amount'                 => $expected_amount,
							'created'                => 1557224305,
							'status'                 => 'succeeded',
							'payment_method_details' => [],
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
							'id'                     => 'test_charge_id',
							'amount'                 => $expected_amount,
							'created'                => 1557224305,
							'status'                 => 'succeeded',
							'payment_method_details' => [],
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
	 * Test a successful call to create_setup_intent.
	 *
	 * @throws Exception - In the event of test failure.
	 */
	public function test_create_setup_intent() {
		$customer_id          = 'cus_test12345';
		$payment_method_types = [ 'card' ];
		$setup_intent_id      = 'seti_mock';

		// Mock the HTTP client manually to assert we are sending the correct args.
		$this->mock_http_client
		->expects( $this->once() )
		->method( 'remote_request' )
		->with(
			[
				'url'             => 'https://public-api.wordpress.com/wpcom/v2/sites/%s/wcpay/setup_intents',
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
					'test_mode'            => false,
					'customer'             => $customer_id,
					'confirm'              => 'false',
					'payment_method_types' => $payment_method_types,
				]
			),
			true,
			false
		)
		->will(
			$this->returnValue(
				[
					'body'     => wp_json_encode(
						[
							'id'     => 'seti_mock',
							'object' => 'setup_intent',
						]
					),
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
				]
			)
		);

		$result = $this->payments_api_client->create_setup_intention( $customer_id, $payment_method_types );

		$this->assertEquals( $setup_intent_id, $result['id'] );
	}
	/**
	 * Test a successful call to create_and_confirm_setup_intent when SEPA is enabled.
	 *
	 * @throws Exception - In the event of test failure.
	 */
	public function test_create_and_confirm_setup_intent_with_SEPA() {
		// Enable SEPA.
		update_option( '_wcpay_feature_sepa', '1' );

		$payment_method_id    = 'pm_mock';
		$customer_id          = 'cus_test12345';
		$payment_method_types = [ 'card', 'sepa_debit' ];
		$mandate_data         = [
			'customer_acceptance' => [
				'type'   => 'online',
				'online' => [
					'ip_address' => '127.0.0.1',
					'user_agent' => 'Unit Test Agent/0.1.0',
				],
			],
		];

		// Mock the HTTP client manually to assert we are adding mandate data.
		$this->mock_http_client
		->expects( $this->once() )
		->method( 'remote_request' )
		->with(
			[
				'url'             => 'https://public-api.wordpress.com/wpcom/v2/sites/%s/wcpay/setup_intents',
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
					'test_mode'            => false,
					'payment_method'       => $payment_method_id,
					'customer'             => $customer_id,
					'confirm'              => 'true',
					'payment_method_types' => $payment_method_types,
					'mandate_data'         => $mandate_data,
				]
			),
			true,
			false
		)
		->will(
			$this->returnValue(
				[
					'body'     => wp_json_encode(
						[
							'id'             => 'seti_mock',
							'object'         => 'setup_intent',
							'payment_method' => $payment_method_id,
						]
					),
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
				]
			)
		);

		$result = $this->payments_api_client->create_and_confirm_setup_intent( $payment_method_id, $customer_id );

		$this->assertEquals( $payment_method_id, $result['payment_method'] );

		// Disable SEPA.
		update_option( '_wcpay_feature_sepa', '0' );
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
							'id'                     => 'test_charge_id',
							'amount'                 => $expected_amount,
							'created'                => 1557224305,
							'status'                 => 'succeeded',
							'payment_method_details' => [],
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
							'id'                     => 'test_charge_id',
							'amount'                 => 123,
							'created'                => 1557224305,
							'status'                 => 'succeeded',
							'payment_method_details' => [],
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
		$customer_data = [
			'name'        => 'Test Customer',
			'email'       => 'test.customer@example.com',
			'description' => 'Test Customer Description',
		];

		$this->set_http_mock_response(
			200,
			[
				'id'   => 'cus_test12345',
				'type' => 'customer',
			]
		);

		$customer_id = $this->payments_api_client->create_customer( $customer_data );

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
				true,
				false
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
				),
				true,
				true // get_oauth_data should use user token auth.
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

	public function test_update_account() {
		$test_data = [ 'mock' => true ];

		$this->mock_http_client
			->expects( $this->once() )
			->method( 'remote_request' )
			->with(
				[
					'url'             => 'https://public-api.wordpress.com/wpcom/v2/sites/%s/wcpay/accounts',
					'method'          => 'POST',
					'headers'         => [
						'Content-Type' => 'application/json; charset=utf-8',
						'User-Agent'   => 'Unit Test Agent/0.1.0',
					],
					'timeout'         => 70,
					'connect_timeout' => 70,
				],
				wp_json_encode(
					array_merge(
						[ 'test_mode' => false ],
						[ 'mock' => true ]
					)
				),
				true,
				true // update_account should use user token auth.
			)
			->willReturn(
				[
					'body'     => wp_json_encode( [ 'mock_account' => true ] ),
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
				]
			);

		$result = $this->payments_api_client->update_account( $test_data );

		$this->assertEquals( [ 'mock_account' => true ], $result );
	}

	public function test_get_login_data() {
		$this->mock_http_client
			->expects( $this->once() )
			->method( 'remote_request' )
			->with(
				[
					'url'             => 'https://public-api.wordpress.com/wpcom/v2/sites/%s/wcpay/accounts/login_links',
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
						'test_mode'    => false,
						'redirect_url' => 'mock_url',
					]
				),
				true,
				true // get_login_data should use user token auth.
			)
			->willReturn(
				[
					'body'     => wp_json_encode( [ 'url' => 'mock' ] ),
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
				]
			);

		$result = $this->payments_api_client->get_login_data( 'mock_url' );

		$this->assertEquals( [ 'url' => 'mock' ], $result );
	}

	public function test_add_tos_agreement() {
		$this->mock_http_client
			->expects( $this->once() )
			->method( 'remote_request' )
			->with(
				[
					'url'             => 'https://public-api.wordpress.com/wpcom/v2/sites/%s/wcpay/accounts/tos_agreements',
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
						'test_mode' => false,
						'source'    => 'mock_source',
						'user_name' => 'mock_name',
					]
				),
				true,
				true // add_tos_agreement should use user token auth.
			)
			->willReturn(
				[
					'body'     => wp_json_encode( [ 'result' => 'success' ] ),
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
				]
			);

		$result = $this->payments_api_client->add_tos_agreement( 'mock_source', 'mock_name' );

		$this->assertEquals( [ 'result' => 'success' ], $result );
	}

	/**
	 * @dataProvider data_request_with_level3_data
	 */
	public function test_request_with_level3_data( $intention_args, $expected_remote_request_args ) {
		$this->mock_http_client
			->expects( $this->once() )
			->method( 'remote_request' )
			->with(
				[
					'url'             => 'https://public-api.wordpress.com/wpcom/v2/sites/%s/wcpay/intentions',
					'method'          => 'POST',
					'headers'         => [
						'Content-Type' => 'application/json; charset=utf-8',
						'User-Agent'   => 'Unit Test Agent/0.1.0',
					],
					'timeout'         => 70,
					'connect_timeout' => 70,
				],
				wp_json_encode( $expected_remote_request_args )
			)
			->willReturn(
				[
					'body'     => wp_json_encode(
						[
							'id'                          => 'pi_1J2qyzR42SiAd3w0B1xdk14f',
							'object'                      => 'payment_intent',
							'amount'                      => 10000,
							'amount_capturable'           => 0,
							'amount_received'             => 10000,
							'application'                 => 'ca_HrWs5SEMnkgOWmbL7lL6OmWI048fypU2',
							'application_fee_amount'      => 320,
							'canceled_at'                 => null,
							'cancellation_reason'         => null,
							'capture_method'              => 'automatic',
							'charges'                     => [
								'object'      => 'list',
								'data'        => [
									[
										'id'              => 'ch_1J2qyzR42SiAd3w0FFFOESpA',
										'object'          => 'charge',
										'amount'          => 10000,
										'amount_captured' => 10000,
										'amount_refunded' => 0,
										'application'     => 'ca_HrWs5SEMnkgOWmbL7lL6OmWI048fypU2',
										'application_fee' => 'fee_1J2qz0R42SiAd3w0yxdF1lx8',
										'application_fee_amount' => 320,
										'balance_transaction' => [
											'id'           => 'txn_1J2qz0R42SiAd3w0QDtOA7EB',
											'object'       => 'balance_transaction',
											'amount'       => 10000,
											'available_on' => 1623974400,
											'created'      => 1623820089,
											'currency'     => 'aud',
											'description'  => null,
											'exchange_rate' => null,
											'fee'          => 320,
											'fee_details'  => [
												[
													'amount' => 320,
													'application' => 'ca_HrWs5SEMnkgOWmbL7lL6OmWI048fypU2',
													'currency' => 'aud',
													'description' => 'Dev Shared Account WCPay application fee',
													'type' => 'application_fee',
												],
											],
											'net'          => 9680,
											'reporting_category' => 'charge',
											'source'       => 'ch_1J2qyzR42SiAd3w0FFFOESpA',
											'status'       => 'pending',
											'type'         => 'charge',
										],
										'billing_details' => [
											'address' => [
												'city'    => 'Sydney',
												'country' => 'AU',
												'line1'   => '100 Pitt Street',
												'line2'   => null,
												'postal_code' => '2000',
												'state'   => 'NSW',
											],
											'email'   => 'chris@example.com',
											'name'    => 'Chris',
											'phone'   => '0411111111',
										],
										'calculated_statement_descriptor' => 'DBA444A20B82.NGROK.IO',
										'captured'        => true,
										'created'         => 1623820089,
										'currency'        => 'aud',
										'customer'        => 'cus_JeKgKIOAFflZqL',
										'description'     => null,
										'destination'     => null,
										'dispute'         => null,
										'disputed'        => false,
										'failure_code'    => null,
										'failure_message' => null,
										'fraud_details'   => [],
										'invoice'         => null,
										'livemode'        => false,
										'metadata'        => [
											'customer_name' => 'Chris',
											'customer_email' => 'chris@example.com',
											'site_url'     => 'http://dba444a20b82.ngrok.io',
											'order_id'     => '79',
											'order_key'    => 'wc_order_jIRf6RgP0xPfU',
											'payment_type' => 'single',
										],
										'on_behalf_of'    => null,
										'order'           => null,
										'outcome'         => [
											'network_status' => 'approved_by_network',
											'reason'     => null,
											'risk_level' => 'normal',
											'risk_score' => 32,
											'seller_message' => 'Payment complete.',
											'type'       => 'authorized',
										],
										'paid'            => true,
										'payment_intent'  => 'pi_1J2qyzR42SiAd3w0B1xdk14f',
										'payment_method'  => 'pm_1J2qyuR42SiAd3w0bQOQ4yvn',
										'payment_method_details' => [
											'card' => [
												'brand'    => 'visa',
												'checks'   => [
													'address_line1_check' => 'pass',
													'address_postal_code_check' => 'pass',
													'cvc_check' => 'pass',
												],
												'country'  => 'US',
												'exp_month' => 1,
												'exp_year' => 2022,
												'fingerprint' => 'GPNBuIQZKl9E7Sku',
												'funding'  => 'credit',
												'installments' => null,
												'last4'    => '4242',
												'network'  => 'visa',
												'three_d_secure' => null,
												'wallet'   => null,
											],
											'type' => 'card',
										],
										'receipt_email'   => null,
										'receipt_number'  => null,
										'receipt_url'     => 'https://pay.stripe.com/receipts/acct_1J0xrrR42SiAd3w0/ch_1J2qyzR42SiAd3w0FFFOESpA/rcpt_JgDP3NtQmHZHclL4LuBVazLribbvZYD',
										'refunded'        => false,
										'refunds'         => [
											'object'      => 'list',
											'data'        => [],
											'has_more'    => false,
											'total_count' => 0,
											'url'         => '/v1/charges/ch_1J2qyzR42SiAd3w0FFFOESpA/refunds',
										],
										'review'          => null,
										'shipping'        => null,
										'source'          => null,
										'source_transfer' => null,
										'statement_descriptor' => null,
										'statement_descriptor_suffix' => null,
										'status'          => 'succeeded',
										'transfer_data'   => null,
										'transfer_group'  => null,
									],
								],
								'has_more'    => false,
								'total_count' => 1,
								'url'         => '/v1/charges?payment_intent=pi_1J2qyzR42SiAd3w0B1xdk14f',
							],
							'client_secret'               => 'pi_1J2qyzR42SiAd3w0B1xdk14f_secret_AIEf79Ba7lv35W1QBRdjLfzTi',
							'confirmation_method'         => 'automatic',
							'created'                     => 1623820089,
							'currency'                    => 'aud',
							'customer'                    => 'cus_JeKgKIOAFflZqL',
							'description'                 => null,
							'invoice'                     => null,
							'last_payment_error'          => null,
							'level3'                      => null,
							'livemode'                    => false,
							'metadata'                    => [
								'customer_name'  => 'Chris',
								'customer_email' => 'chris@example.com',
								'site_url'       => 'http://dba444a20b82.ngrok.io',
								'order_id'       => '79',
								'order_key'      => 'wc_order_jIRf6RgP0xPfU',
								'payment_type'   => 'single',
							],
							'next_action'                 => null,
							'on_behalf_of'                => null,
							'payment_method'              => 'pm_1J2qyuR42SiAd3w0bQOQ4yvn',
							'payment_method_options'      => [
								'card' => [
									'installments' => null,
									'network'      => null,
									'request_three_d_secure' => 'automatic',
								],
							],
							'payment_method_types'        => [
								'card',
							],
							'receipt_email'               => null,
							'review'                      => null,
							'setup_future_usage'          => null,
							'shipping'                    => null,
							'source'                      => null,
							'statement_descriptor'        => null,
							'statement_descriptor_suffix' => null,
							'status'                      => 'succeeded',
							'transfer_data'               => null,
							'transfer_group'              => null,
						]
					),
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
				]
			);

		call_user_func_array( [ $this->payments_api_client, 'create_and_confirm_intention' ], $intention_args );
	}

	/**
	 * Data provider for test_request_with_level3_data
	 */
	public function data_request_with_level3_data() {
		return [
			// Australian merchant.
			[
				[
					10000,
					'aud',
					'pm_1J2pJuR42SiAd3w0Z5UHDK3V',
					'cus_JeKgKIOAFflZqL',
					false,
					false,
					[
						'customer_name'  => 'Chris',
						'customer_email' => 'chris@example.com',
						'site_url'       => 'https://example.com',
						'order_id'       => 22,
						'order_key'      => 'wc_order_XEZjVLa8oimvY',
					],
					[],
				],
				[
					'test_mode'      => false,
					'amount'         => 10000,
					'currency'       => 'aud',
					'confirm'        => 'true',
					'payment_method' => 'pm_1J2pJuR42SiAd3w0Z5UHDK3V',
					'customer'       => 'cus_JeKgKIOAFflZqL',
					'capture_method' => 'automatic',
					'metadata'       => [
						'customer_name'  => 'Chris',
						'customer_email' => 'chris@example.com',
						'site_url'       => 'https://example.com',
						'order_id'       => 22,
						'order_key'      => 'wc_order_XEZjVLa8oimvY',
					],
					'level3'         => [],
				],
			],
			// American merchant (no line items).
			[
				[
					10000,
					'usd',
					'pm_1J2pJuR42SiAd3w0Z5UHDK3V',
					'cus_JeKgKIOAFflZqL',
					false,
					false,
					[
						'customer_name'  => 'Chris',
						'customer_email' => 'chris@example.com',
						'site_url'       => 'https://example.com',
						'order_id'       => 22,
						'order_key'      => 'wc_order_XEZjVLa8oimvY',
					],
					[
						'merchant_reference' => 'abc123',
					],
				],
				[
					'test_mode'      => false,
					'amount'         => 10000,
					'currency'       => 'usd',
					'confirm'        => 'true',
					'payment_method' => 'pm_1J2pJuR42SiAd3w0Z5UHDK3V',
					'customer'       => 'cus_JeKgKIOAFflZqL',
					'capture_method' => 'automatic',
					'metadata'       => [
						'customer_name'  => 'Chris',
						'customer_email' => 'chris@example.com',
						'site_url'       => 'https://example.com',
						'order_id'       => 22,
						'order_key'      => 'wc_order_XEZjVLa8oimvY',
					],
					'level3'         => [
						'merchant_reference' => 'abc123',
						'line_items'         => [
							[
								'discount_amount'     => 0,
								'product_code'        => 'zero-cost-fee',
								'product_description' => 'Zero cost fee',
								'quantity'            => 1,
								'tax_amount'          => 0,
								'unit_cost'           => 0,
							],
						],
					],
				],
			],
			// American merchant (with line items).
			[
				[
					10000,
					'usd',
					'pm_1J2pJuR42SiAd3w0Z5UHDK3V',
					'cus_JeKgKIOAFflZqL',
					false,
					false,
					[
						'customer_name'  => 'Chris',
						'customer_email' => 'chris@example.com',
						'site_url'       => 'https://example.com',
						'order_id'       => 22,
						'order_key'      => 'wc_order_XEZjVLa8oimvY',
					],
					[
						'merchant_reference' => 'abc123',
						'line_items'         => [
							[
								'discount_amount'     => 0,
								'product_code'        => 'free-hug',
								'product_description' => 'Free hug',
								'quantity'            => 1,
								'tax_amount'          => 0,
								'unit_cost'           => 0,
							],
						],
					],
				],
				[
					'test_mode'      => false,
					'amount'         => 10000,
					'currency'       => 'usd',
					'confirm'        => 'true',
					'payment_method' => 'pm_1J2pJuR42SiAd3w0Z5UHDK3V',
					'customer'       => 'cus_JeKgKIOAFflZqL',
					'capture_method' => 'automatic',
					'metadata'       => [
						'customer_name'  => 'Chris',
						'customer_email' => 'chris@example.com',
						'site_url'       => 'https://example.com',
						'order_id'       => 22,
						'order_key'      => 'wc_order_XEZjVLa8oimvY',
					],
					'level3'         => [
						'merchant_reference' => 'abc123',
						'line_items'         => [
							[
								'discount_amount'     => 0,
								'product_code'        => 'free-hug',
								'product_description' => 'Free hug',
								'quantity'            => 1,
								'tax_amount'          => 0,
								'unit_cost'           => 0,
							],
						],
					],
				],
			],
		];
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
